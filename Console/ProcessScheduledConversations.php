<?php

/**
 * Process Scheduled Conversations Command
 *
 * Artisan command: scheduledconversations:process
 *
 * Fetches all pending scheduled conversations (next_run_at <= now, status = active)
 * and creates the corresponding FreeScout conversations/threads.
 *
 * DESTINATION TYPE BEHAVIOUR:
 *
 * - internal: Creates a conversation with Thread::TYPE_CUSTOMER so it appears as an
 *   incoming (white) thread. No SMTP email is sent — FreeScout does not send emails
 *   for customer-originated threads. The mailbox email is used as the customer.
 *   Notifications are fired manually via CustomerCreatedConversation event +
 *   Subscription::processEvents() because TerminateHandler middleware does not run
 *   in console context.
 *
 * - customer: Creates an outgoing (blue) conversation via SMTP to the selected
 *   FreeScout customer. Marked as STATUS_CLOSED since no action is expected.
 *
 * - email: Same as customer but recipient is a free-form email address.
 *   Existing customer is reused if found; otherwise a new one is created.
 *
 * MISSED EXECUTION HANDLING:
 * - If next_run_at is more than 120 minutes in the past, execution is considered missed.
 * - catch_up_mode = skip: skips execution, advances next_run_at to next future cycle.
 * - catch_up_mode = catch_up_last: executes even if delayed.
 * - FREQUENCY_ONCE always executes regardless of delay.
 *
 * PRE-FLIGHT VALIDATION:
 * - previewNextRun() is called before executeScheduledConversation() to validate
 *   frequency_config. If config is invalid, execution is skipped and the error is
 *   logged to scheduled_conversation_logs. This prevents the pattern of a conversation
 *   being created successfully but next_run_at failing to update (infinite loop).
 *
 * MONTHLY DAY HANDLING:
 * - If the configured day (e.g. 31) does not exist in the target month, the last
 *   available day of that month is used. The original configured day is always used
 *   for subsequent calculations, so months with 31 days will execute on the 31st.
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.6.0
 */

namespace Modules\ScheduledConversations\Console;

use Illuminate\Console\Command;
use Modules\ScheduledConversations\Entities\ScheduledConversation;
use Modules\ScheduledConversations\Entities\ScheduledConversationLog;
use App\Conversation;
use App\Thread;
use App\Customer;
use App\Subscription;
use Carbon\Carbon;

class ProcessScheduledConversations extends Command
{
    protected $signature = 'scheduledconversations:process';
    protected $description = 'Process pending scheduled conversations and create FreeScout conversations';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Main handler — fetches pending scheduled conversations and processes each one.
     */
    public function handle()
    {
        $maxPerCycle = config('scheduledconversations.max_per_cycle', 100);

        $pending = ScheduledConversation::pending()
            ->limit($maxPerCycle)
            ->get();

        $processedCount = 0;
        $successCount   = 0;
        $failedCount    = 0;

        foreach ($pending as $scheduled) {
            // If past end_date, mark as expired and skip
            if ($scheduled->end_date && now() > $scheduled->end_date) {
                $scheduled->status      = ScheduledConversation::STATUS_EXPIRED;
                $scheduled->next_run_at = null;
                $scheduled->save();
                continue;
            }

            // Skip if start_date has not been reached yet
            if (!$scheduled->isInValidDateRange()) {
                continue;
            }

            // Check if this execution is considered "missed" (more than 120 minutes late).
            // For FREQUENCY_ONCE: always execute regardless of delay — there is only one chance.
            // For recurring types: apply catch_up_mode logic.
            $minutesLate = now()->diffInMinutes($scheduled->next_run_at);

            if ($minutesLate > 120 && $scheduled->frequency_type !== ScheduledConversation::FREQUENCY_ONCE) {
                if ($scheduled->catch_up_mode === ScheduledConversation::CATCHUP_SKIP) {
                    // Skip this missed execution and advance next_run_at to the next future cycle
                    $this->info("Skipping missed execution for scheduled conversation #{$scheduled->id} ({$minutesLate} minutes late)");
                    $this->calculateNextRun($scheduled);
                    $processedCount++;
                    continue;
                }
                // CATCHUP_LAST: execute even if delayed, then calculate next run normally
                $this->info("Executing delayed scheduled conversation #{$scheduled->id} ({$minutesLate} minutes late)");
            }

            // PRE-FLIGHT CHECK: verify calculateNextRun will succeed before executing.
            // If frequency_config is corrupt (e.g. invalid day_of_week), we must not create
            // the conversation — log the error, advance next_run_at, and skip execution.
            // This prevents the pattern: conversation created OK + calculateNextRun fails.
            try {
                $nextRun = $this->previewNextRun($scheduled);
            } catch (\Throwable $e) {
                $this->error("Invalid frequency_config for #{$scheduled->id}: " . $e->getMessage());

                ScheduledConversationLog::create([
                    'scheduled_conversation_id' => $scheduled->id,
                    'conversation_id'           => null,
                    'executed_at'               => now(),
                    'status'                    => ScheduledConversationLog::STATUS_FAILED,
                    'recipient_email'           => null,
                    'error_message'             => 'Invalid frequency_config — execution skipped: ' . $e->getMessage(),
                    'created_at'                => now(),
                ]);

                // Force advance next_run_at based on frequency to prevent infinite loop
                switch ($scheduled->frequency_type) {
                    case ScheduledConversation::FREQUENCY_DAILY:
                        $scheduled->next_run_at = Carbon::now()->addDay();
                        break;
                    case ScheduledConversation::FREQUENCY_WEEKLY:
                        $scheduled->next_run_at = Carbon::now()->addWeek();
                        break;
                    case ScheduledConversation::FREQUENCY_MONTHLY:
                    case ScheduledConversation::FREQUENCY_MONTHLY_ORDINAL:
                        $scheduled->next_run_at = Carbon::now()->addMonth();
                        break;
                    case ScheduledConversation::FREQUENCY_YEARLY:
                        $scheduled->next_run_at = Carbon::now()->addYear();
                        break;
                    default:
                        $scheduled->next_run_at = Carbon::now()->addDay();
                        break;
                }
                $scheduled->save();
                $failedCount++;
                $processedCount++;
                continue;
            }

            // frequency_config is valid — proceed with execution
            $result = $this->executeScheduledConversation($scheduled);

            if ($result) {
                $successCount++;
            } else {
                $failedCount++;
            }

            // Calculate next run (safe — already validated above)
            $this->calculateNextRun($scheduled);

            // CIRCUIT BREAKER: detect execution loops caused by unhandled errors.
            // If this conversation has been executed 3 or more times in the last 60 minutes,
            // auto-pause it AFTER the current execution completes — the user receives the
            // current message but no further executions will occur until manually reactivated.
            $recentExecutions = ScheduledConversationLog::where('scheduled_conversation_id', $scheduled->id)
                ->where('executed_at', '>=', now()->subMinutes(60))
                ->count();

            if ($recentExecutions >= 3) {
                $scheduled->status = ScheduledConversation::STATUS_PAUSED;
                $scheduled->save();

                ScheduledConversationLog::create([
                    'scheduled_conversation_id' => $scheduled->id,
                    'conversation_id'           => null,
                    'executed_at'               => now(),
                    'status'                    => ScheduledConversationLog::STATUS_FAILED,
                    'recipient_email'           => null,
                    'error_message'             => "Auto-paused: execution loop detected ({$recentExecutions} executions in the last 60 minutes). Please review the configuration and reactivate manually.",
                    'created_at'               => now(),
                ]);

                $this->error("Auto-paused scheduled conversation #{$scheduled->id}: execution loop detected ({$recentExecutions} executions in 60 minutes)");
            }

            $processedCount++;
        }

        if ($processedCount > 0) {
            $this->info("Processed {$processedCount} scheduled conversations: {$successCount} success, {$failedCount} failed");
        }

        return 0;
    }

    /**
     * Execute a single scheduled conversation by creating a FreeScout conversation.
     *
     * DESTINATION TYPES:
     *
     * - internal: Creates a conversation in the mailbox that appears as an incoming message
     *   (white thread, TYPE_CUSTOMER). No SMTP email is sent — FreeScout does not send emails
     *   for TYPE_CUSTOMER threads. The conversation customer is the mailbox email itself.
     *   Notifications are fired manually since we are running in console context (not HTTP).
     *
     * - customer: Creates a conversation sent to a FreeScout customer via SMTP (TYPE_MESSAGE).
     *   Conversation is marked as CLOSED since it was sent automatically with no action needed.
     *
     * - email: Same as customer but the recipient is a free-form email address.
     *   If the email already exists as a FreeScout customer, that customer is reused.
     *   Otherwise a new customer is created automatically.
     */
    protected function executeScheduledConversation($scheduled)
    {
        $recipientEmail = null;

        try {
            $recipientEmail = $this->getRecipientEmail($scheduled);
            $customer       = $this->getCustomer($scheduled);

            $isInternal = ($scheduled->destination_type === ScheduledConversation::DESTINATION_INTERNAL);

            $subject = $this->replaceVariables($scheduled->subject, $scheduled);
            $body    = $this->replaceVariables($scheduled->body, $scheduled);

            // Build conversation data.
            // - internal: STATUS_ACTIVE so agents can see and act on it
            // - email/customer: STATUS_CLOSED since the message was sent automatically
            $conversationData = [
                'type'               => Conversation::TYPE_EMAIL,
                'status'             => $isInternal ? Conversation::STATUS_ACTIVE : Conversation::STATUS_CLOSED,
                'state'              => Conversation::STATE_PUBLISHED,
                'subject'            => $subject,
                'mailbox_id'         => $scheduled->mailbox_id,
                'created_by_user_id' => $scheduled->user_id,
                'source_via'         => $isInternal ? Conversation::PERSON_CUSTOMER : Conversation::PERSON_USER,
                'source_type'        => Conversation::SOURCE_TYPE_API,
                'user_id'            => null, // leave unassigned
                'imported'           => false,
            ];

            // Build thread data.
            //
            // For INTERNAL destinations:
            //   - Thread type is TYPE_CUSTOMER (value: 1) — this makes FreeScout render the thread
            //     as an incoming message (white background) instead of an outgoing one (blue).
            //   - source_via is PERSON_CUSTOMER — FreeScout does NOT send SMTP emails for
            //     customer-originated threads, so no email leaves the server.
            //   - created_by_customer_id is set to the mailbox customer — since no real user
            //     "caused" this action, FreeScout will not exclude anyone from notifications.
            //     All mailbox agents subscribed to new conversation events will be notified.
            //
            // For EMAIL/CUSTOMER destinations:
            //   - Thread type is TYPE_MESSAGE (value: 2) — outgoing message sent via SMTP.
            //   - created_by_user_id is the user who created the scheduled conversation.
            if ($isInternal) {
                $threadData = [
                    'type'                   => Thread::TYPE_CUSTOMER,
                    'body'                   => $body,
                    'source_via'             => Conversation::PERSON_CUSTOMER,
                    'source_type'            => Conversation::SOURCE_TYPE_API,
                    'created_by_customer_id' => $customer->id,
                ];
            } else {
                $threadData = [
                    'type'               => Thread::TYPE_MESSAGE,
                    'body'               => $body,
                    'user_id'            => $scheduled->user_id,
                    'created_by_user_id' => $scheduled->user_id,
                    'source_via'         => Conversation::PERSON_USER,
                    'source_type'        => Conversation::SOURCE_TYPE_API,
                ];
                if ($recipientEmail) {
                    $threadData['to'] = json_encode([['email' => $recipientEmail]]);
                }
            }

            // FreeScout's Conversation::create() returns an array:
            // ['conversation' => Conversation, 'thread' => Thread]
            // It does NOT return the Eloquent model directly.
            $result = Conversation::create($conversationData, [$threadData], $customer);

            if (!$result || empty($result['conversation'])) {
                throw new \Exception('Conversation::create() returned no conversation.');
            }

            $conversation = $result['conversation'];
            $thread       = $result['thread'];

            // Fire notifications for internal conversations.
            //
            // Normally FreeScout fires the CustomerCreatedConversation event inside Thread::createExtended()
            // which is called by Conversation::create(). However, that event is only fired under certain
            // conditions. To guarantee notifications are sent, we fire it manually here.
            //
            // IMPORTANT: In console context (Artisan commands), the TerminateHandler middleware does NOT run,
            // so Subscription::processEvents() is never called automatically. We must call it explicitly,
            // just like FetchEmails.php does, to actually dispatch the notification jobs.
            if ($isInternal) {
                event(new \App\Events\CustomerCreatedConversation($conversation, $thread));
                Subscription::processEvents();
            }

            // Log successful execution
            ScheduledConversationLog::create([
                'scheduled_conversation_id' => $scheduled->id,
                'conversation_id'           => $conversation->id,
                'executed_at'               => now(),
                'status'                    => ScheduledConversationLog::STATUS_SUCCESS,
                'recipient_email'           => $recipientEmail,
                'created_at'               => now(),
            ]);

            $scheduled->last_run_at = now();
            $scheduled->run_count++;
            $scheduled->save();

            return true;

        } catch (\Throwable $e) {
            // Log failure with file and line for easier debugging
            ScheduledConversationLog::create([
                'scheduled_conversation_id' => $scheduled->id,
                'executed_at'               => now(),
                'status'                    => ScheduledConversationLog::STATUS_FAILED,
                'recipient_email'           => $recipientEmail,
                'error_message'             => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                'created_at'               => now(),
            ]);

            $this->error("Failed to execute scheduled conversation #{$scheduled->id}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get the recipient email address based on destination type.
     *
     * For internal: returns the mailbox email (used as the customer identity,
     * not as an SMTP recipient — no email is actually sent).
     * For customer: returns the customer's main email.
     * For email: returns the raw email address stored in destination_value.
     */
    protected function getRecipientEmail($scheduled)
    {
        switch ($scheduled->destination_type) {
            case ScheduledConversation::DESTINATION_INTERNAL:
                return $scheduled->mailbox->email;

            case ScheduledConversation::DESTINATION_CUSTOMER:
                $customer = Customer::find($scheduled->destination_value);
                return $customer ? $customer->getMainEmail() : null;

            case ScheduledConversation::DESTINATION_EMAIL:
                return $scheduled->destination_value;

            default:
                return null;
        }
    }

    /**
     * Get or create the FreeScout customer for the conversation.
     *
     * - internal:  The mailbox email is used as the customer. This is required because
     *              FreeScout's Conversation::create() always needs a customer object.
     *              Using the mailbox email makes the conversation appear self-contained.
     *
     * - customer:  The FreeScout customer selected by the user when creating the schedule.
     *
     * - email:     First tries to find an existing customer with that email. If multiple
     *              customers share the same email, the first one found is used. If none
     *              exists, a new customer is created automatically.
     */
    protected function getCustomer($scheduled)
    {
        switch ($scheduled->destination_type) {
            case ScheduledConversation::DESTINATION_INTERNAL:
                return $this->findOrCreateCustomerByEmail($scheduled->mailbox->email);

            case ScheduledConversation::DESTINATION_CUSTOMER:
                return Customer::find($scheduled->destination_value);

            case ScheduledConversation::DESTINATION_EMAIL:
                return $this->findOrCreateCustomerByEmail($scheduled->destination_value);

            default:
                return null;
        }
    }

    /**
     * Find an existing FreeScout customer by email address, or create a new one.
     * If multiple customers share the same email, returns the first one found.
     */
    protected function findOrCreateCustomerByEmail($email)
    {
        $customer = Customer::join('emails', 'customers.id', '=', 'emails.customer_id')
            ->where('emails.email', $email)
            ->select('customers.*')
            ->first();

        if ($customer) {
            return $customer;
        }

        // No customer found — create a minimal one with just the email
        $customer = Customer::create([
            'first_name' => '',
            'last_name'  => '',
        ]);
        $customer->emails()->create([
            'email' => $email,
            'type'  => 'work',
        ]);

        return $customer;
    }

    /**
     * Replace template variables in subject or body text.
     *
     * Available variables:
     * {customer_name} - Full name of the destination customer (empty for internal/email types)
     * {date}          - Current date in Y-m-d format
     * {time}          - Current time in H:i format
     * {mailbox_name}  - Name of the mailbox this scheduled conversation belongs to
     * {user_name}     - Full name of the user who created the scheduled conversation
     */
    protected function replaceVariables($text, $scheduled)
    {
        $customer = null;
        if ($scheduled->destination_type === ScheduledConversation::DESTINATION_CUSTOMER) {
            $customer = Customer::find($scheduled->destination_value);
        }

        $variables = [
            '{customer_name}' => $customer ? $customer->getFullName() : '[not available]',
            '{date}'          => now()->format('Y-m-d'),
            '{time}'          => now()->format('H:i'),
            '{mailbox_name}'  => $scheduled->mailbox->name,
            '{user_name}'     => $scheduled->user->getFullName(),
        ];

        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    /**
     * Preview the next run date without saving — used as a pre-flight validation check.
     * Throws an exception if frequency_config contains invalid values.
     * This allows us to detect corrupt config BEFORE executing the conversation,
     * so we never end up with a conversation created but next_run_at not updated.
     *
     * @throws \Exception if frequency_config is invalid
     */
    protected function previewNextRun($scheduled)
    {
        $config = $scheduled->frequency_config;
        $dowNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        switch ($scheduled->frequency_type) {
            case ScheduledConversation::FREQUENCY_ONCE:
                return null; // Once — no next run needed

            case ScheduledConversation::FREQUENCY_DAILY:
                return Carbon::now()->addDay()->setTimeFromTimeString($config['time']);

            case ScheduledConversation::FREQUENCY_WEEKLY:
                // Support both legacy day_of_week (int/string) and new days_of_week (array)
                if (isset($config['days_of_week']) && is_array($config['days_of_week'])) {
                    $days = array_map('intval', $config['days_of_week']);
                    if (empty($days)) {
                        throw new \Exception("days_of_week array is empty");
                    }
                    foreach ($days as $d) {
                        if (!isset($dowNames[$d])) {
                            throw new \Exception("Invalid day_of_week integer: {$d}");
                        }
                    }
                } else {
                    $rawDow = $config['day_of_week'];
                    if (is_numeric($rawDow)) {
                        if (!isset($dowNames[(int)$rawDow])) {
                            throw new \Exception("Invalid day_of_week integer: {$rawDow}");
                        }
                        $days = [(int)$rawDow];
                    } else {
                        $dowName = strtolower($rawDow);
                        if (!in_array($dowName, $dowNames)) {
                            throw new \Exception("Invalid day_of_week string: {$rawDow}");
                        }
                        $days = [array_search($dowName, $dowNames)];
                    }
                }
                sort($days);
                $now = Carbon::now();
                $currentDow = (int)$now->format('w');
                $best = null;
                foreach ($days as $targetDow) {
                    $diff = ($targetDow - $currentDow + 7) % 7;
                    $candidate = Carbon::now()->addDays($diff === 0 ? 7 : $diff)->setTimeFromTimeString($config['time']);
                    if ($best === null || $candidate < $best) {
                        $best = $candidate;
                    }
                }
                return $best;

            case ScheduledConversation::FREQUENCY_MONTHLY:
                $next = Carbon::now()->addMonth();
                $day  = min((int)$config['day'], $next->daysInMonth);
                return $next->day($day)->setTimeFromTimeString($config['time']);

            case ScheduledConversation::FREQUENCY_MONTHLY_ORDINAL:
                $rawDow = $config['day_of_week'];
                if (is_numeric($rawDow)) {
                    if (!isset($dowNames[(int)$rawDow])) {
                        throw new \Exception("Invalid day_of_week integer: {$rawDow}");
                    }
                    $dowName = $dowNames[(int)$rawDow];
                } else {
                    $dowName = strtolower($rawDow);
                    if (!in_array($dowName, $dowNames)) {
                        throw new \Exception("Invalid day_of_week string: {$rawDow}");
                    }
                }
                $next = new \DateTime("{$config['position']} {$dowName} of next month");
                return Carbon::instance($next)->setTimeFromTimeString($config['time']);

            case ScheduledConversation::FREQUENCY_YEARLY:
                return Carbon::now()->addYear()->month($config['month'])->day($config['day'])->setTimeFromTimeString($config['time']);
        }

        return null;
    }

    /**
     * Calculate and set the next_run_at timestamp after a successful execution.
     *
     * Day-of-week convention: integer 0=Sunday, 1=Monday ... 6=Saturday (PHP date('w')).
     * This matches the values stored in frequency_config['day_of_week'].
     *
     * For FREQUENCY_ONCE: marks the scheduled conversation as expired (one-time only).
     * For all recurring types: advances to the next occurrence based on the frequency config.
     * If the next occurrence exceeds end_date, also marks as expired.
     */
    protected function calculateNextRun($scheduled)
    {
        $config = $scheduled->frequency_config;

        switch ($scheduled->frequency_type) {

            case ScheduledConversation::FREQUENCY_ONCE:
                // One-time execution — mark as expired after running
                $scheduled->status      = ScheduledConversation::STATUS_EXPIRED;
                $scheduled->next_run_at = null;
                break;

            case ScheduledConversation::FREQUENCY_DAILY:
                $scheduled->next_run_at = Carbon::now()
                    ->addDay()
                    ->setTimeFromTimeString($config['time']);
                break;

            case ScheduledConversation::FREQUENCY_WEEKLY:
                // Support both legacy day_of_week (int/string) and new days_of_week (array).
                // Find the nearest next day from the selected days array.
                $dowNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                if (isset($config['days_of_week']) && is_array($config['days_of_week'])) {
                    $days = array_map('intval', $config['days_of_week']);
                } else {
                    $rawDow = $config['day_of_week'];
                    $days = [is_numeric($rawDow) ? (int)$rawDow : array_search(strtolower($rawDow), $dowNames)];
                }
                sort($days);
                $nowDow = (int)Carbon::now()->format('w');
                $best   = null;
                foreach ($days as $targetDow) {
                    $diff      = ($targetDow - $nowDow + 7) % 7;
                    $candidate = Carbon::now()->addDays($diff === 0 ? 7 : $diff)->setTimeFromTimeString($config['time']);
                    if ($best === null || $candidate < $best) {
                        $best = $candidate;
                    }
                }
                $scheduled->next_run_at = $best;
                break;

            case ScheduledConversation::FREQUENCY_MONTHLY:
                // Use min() to handle months shorter than the configured day.
                // e.g. day=31 in February -> executes on day 28/29.
                // Next month's calculation always uses the original configured day,
                // so if March has 31 days it will execute on the 31st as expected.
                $next = Carbon::now()->addMonth();
                $day  = min((int)$config['day'], $next->daysInMonth);
                $scheduled->next_run_at = $next->day($day)->setTimeFromTimeString($config['time']);
                break;

            case ScheduledConversation::FREQUENCY_MONTHLY_ORDINAL:
                // day_of_week: same defensive handling as weekly
                $dowNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $rawDow   = $config['day_of_week'];
                if (is_numeric($rawDow)) {
                    $dowName = $dowNames[(int)$rawDow];
                } else {
                    $dowName = strtolower($rawDow);
                }
                $next = new \DateTime("{$config['position']} {$dowName} of next month");
                $next = Carbon::instance($next)->setTimeFromTimeString($config['time']);
                $scheduled->next_run_at = $next;
                break;

            case ScheduledConversation::FREQUENCY_YEARLY:
                $scheduled->next_run_at = Carbon::now()
                    ->addYear()
                    ->month($config['month'])
                    ->day($config['day'])
                    ->setTimeFromTimeString($config['time']);
                break;
        }

        // If next run exceeds end_date, expire the scheduled conversation
        if ($scheduled->end_date && $scheduled->next_run_at && $scheduled->next_run_at > $scheduled->end_date) {
            $scheduled->status      = ScheduledConversation::STATUS_EXPIRED;
            $scheduled->next_run_at = null;
        }

        $scheduled->save();
    }
}
