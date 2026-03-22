<?php

/**
 * Scheduled Conversations Controller
 *
 * Handles all HTTP requests for the Scheduled Conversations module.
 *
 * Routes handled:
 * - index()   : List all scheduled conversations for a mailbox
 * - create()  : Show creation form
 * - store()   : Validate and save a new scheduled conversation
 * - edit()    : Show edit form with existing data
 * - update()  : Validate and update an existing scheduled conversation
 * - history() : Show execution log for a scheduled conversation
 * - toggle()  : Pause or resume a scheduled conversation
 * - destroy() : Delete a scheduled conversation
 * - ajax()    : AJAX endpoint for dynamic actions
 *
 * Validation is centralised in validateScheduledConversation() and covers
 * all frequency types, destination types, and date/time coherence rules.
 * next_run_at is recalculated when frequency config changes or when the
 * stored value is in the past (e.g. reactivating a paused conversation).
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.6.0
 */

namespace Modules\ScheduledConversations\Http\Controllers;

use App\Mailbox;
use App\Customer;
use Modules\ScheduledConversations\Entities\ScheduledConversation;
use Modules\ScheduledConversations\Entities\ScheduledConversationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Validator;

class ScheduledConversationsController extends Controller
{
    /**
     * Display a listing of scheduled conversations for a mailbox
     */
    public function index($mailbox_id)
    {
        if (!ScheduledConversation::canView(null, $mailbox_id)) {
            \Helper::denyAccess();
        }

        $mailbox = Mailbox::findOrFail($mailbox_id);
        
        $scheduledConversations = ScheduledConversation::where('mailbox_id', $mailbox_id)
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'paused' THEN 2 WHEN 'expired' THEN 3 ELSE 4 END")
            ->orderBy('next_run_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('scheduledconversations::index', [
            'mailbox' => $mailbox,
            'scheduled_conversations' => $scheduledConversations,
        ]);
    }

    /**
     * Show the form for creating a new scheduled conversation
     */
    public function create($mailbox_id)
    {
        if (!ScheduledConversation::canManage(null, $mailbox_id)) {
            \Helper::denyAccess();
        }
        
        $mailbox = Mailbox::findOrFail($mailbox_id);

        return view('scheduledconversations::create', [
            'mailbox' => $mailbox,
        ]);
    }

    /**
     * Search customers via AJAX
     */
    public function searchCustomers(Request $request)
    {
        $search = $request->input('q', '');
        
        if (strlen($search) < 2) {
            return response()->json([]);
        }
        
        $customers = Customer::select(['customers.id', 'customers.first_name', 'customers.last_name', 'emails.email'])
            ->join('emails', function($join) {
                $join->on('customers.id', '=', 'emails.customer_id');
            })
            ->where(function($query) use ($search) {
                $query->where('customers.first_name', 'LIKE', "%{$search}%")
                      ->orWhere('customers.last_name', 'LIKE', "%{$search}%")
                      ->orWhere('emails.email', 'LIKE', "%{$search}%");
            })
            ->whereNotNull('emails.email')
            ->orderBy('customers.first_name')
            ->limit(50)
            ->get()
            ->unique('id')
            ->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'text' => $customer->first_name . ' ' . $customer->last_name . ' (' . $customer->email . ')',
                ];
            });
        
        return response()->json($customers->values());
    }

    /**
     * Store a newly created scheduled conversation
     */
    public function store($mailbox_id, Request $request)
    {
        if (!ScheduledConversation::canManage(null, $mailbox_id)) {
            \Helper::denyAccess();
        }

        $errors = $this->validateScheduledConversation($request, 'create');

        if (!empty($errors)) {
            return redirect()->route('scheduledconversations.create', ['mailbox_id' => $mailbox_id])
                        ->withErrors($errors)
                        ->withInput();
        }

        $frequencyConfig  = $this->buildFrequencyConfig($request);
        $destinationValue = $this->getDestinationValue($request);
        $nextRunAt        = $this->calculateInitialNextRun($request->frequency_type, $frequencyConfig, $request->start_date);

        ScheduledConversation::create([
            'mailbox_id'       => $mailbox_id,
            'user_id'          => auth()->user()->id,
            'status'           => $request->has('active') ? ScheduledConversation::STATUS_ACTIVE : ScheduledConversation::STATUS_PAUSED,
            'subject'          => $request->subject,
            'body'             => $request->body,
            'destination_type' => $request->destination_type,
            'destination_value'=> $destinationValue,
            'frequency_type'   => $request->frequency_type,
            'frequency_config' => $frequencyConfig,
            'start_date'       => $request->start_date ? new \DateTime($request->start_date . ' 00:00:00') : null,
            'end_date'         => $request->end_date ? new \DateTime($request->end_date . ' 23:59:59') : null,
            'next_run_at'      => $nextRunAt,
            'catch_up_mode'    => $request->input('catch_up_mode', 'skip'),
        ]);

        \Session::flash('flash_success_floating', __('Scheduled conversation created successfully'));

        return redirect()->route('scheduledconversations.index', ['mailbox_id' => $mailbox_id]);
    }

    /**
     * Show the form for editing a scheduled conversation
     */
    public function edit($id)
    {
        $scheduled = ScheduledConversation::findOrFail($id);
        
        if (!ScheduledConversation::canManage(null, $scheduled->mailbox_id)) {
            \Helper::denyAccess();
        }

        $mailbox = $scheduled->mailbox;
        
        $selectedCustomer = null;
        if ($scheduled->destination_type === 'customer' && $scheduled->destination_value) {
            $selectedCustomer = Customer::find($scheduled->destination_value);
            if ($selectedCustomer) {
                $selectedCustomer->email = $selectedCustomer->getMainEmail();
            }
        }

        return view('scheduledconversations::edit', [
            'mailbox'           => $mailbox,
            'scheduled'         => $scheduled,
            'selected_customer' => $selectedCustomer,
        ]);
    }

    /**
     * Update a scheduled conversation
     */
    public function update($id, Request $request)
    {
        $scheduled = ScheduledConversation::findOrFail($id);
        
        if (!ScheduledConversation::canManage(null, $scheduled->mailbox_id)) {
            \Helper::denyAccess();
        }

        $errors = $this->validateScheduledConversation($request, 'edit');

        if (!empty($errors)) {
            return redirect()->route('scheduledconversations.edit', ['id' => $id])
                        ->withErrors($errors)
                        ->withInput();
        }

        $frequencyConfig = $this->buildFrequencyConfig($request);
        $destinationValue = $this->getDestinationValue($request);

        // Recalculate next_run_at if frequency changed or if it is in the past
        $nextRunAt = $scheduled->next_run_at;
        if ($request->frequency_type !== $scheduled->frequency_type ||
            json_encode($frequencyConfig) !== json_encode($scheduled->frequency_config)) {
            $nextRunAt = $this->calculateInitialNextRun($request->frequency_type, $frequencyConfig, $request->start_date);
        }
        // Also recalculate if next_run_at is in the past (e.g. reactivating a paused conversation)
        if ($nextRunAt && $nextRunAt < now()) {
            $nextRunAt = $this->calculateInitialNextRun($request->frequency_type, $frequencyConfig, $request->start_date);
        }

        $scheduled->update([
            'status'           => $request->has('active') ? ScheduledConversation::STATUS_ACTIVE : ScheduledConversation::STATUS_PAUSED,
            'subject'          => $request->subject,
            'body'             => $request->body,
            'destination_type' => $request->destination_type,
            'destination_value'=> $destinationValue,
            'frequency_type'   => $request->frequency_type,
            'frequency_config' => $frequencyConfig,
            'start_date'       => $request->start_date ? new \DateTime($request->start_date . ' 00:00:00') : null,
            'end_date'         => $request->end_date ? new \DateTime($request->end_date . ' 23:59:59') : null,
            'next_run_at'      => $nextRunAt,
            'catch_up_mode'    => $request->input('catch_up_mode', 'skip'),
        ]);

        \Session::flash('flash_success_floating', __('Scheduled conversation updated successfully'));

        return redirect()->route('scheduledconversations.index', ['mailbox_id' => $scheduled->mailbox_id]);
    }

    /**
     * Display execution history
     */
    public function history($id)
    {
        $scheduled = ScheduledConversation::findOrFail($id);
        
        if (!ScheduledConversation::canView(null, $scheduled->mailbox_id)) {
            \Helper::denyAccess();
        }

        $logs = ScheduledConversationLog::where('scheduled_conversation_id', $id)
            ->orderBy('executed_at', 'desc')
            ->paginate(50);

        return view('scheduledconversations::history', [
            'scheduled' => $scheduled,
            'logs'      => $logs,
            'mailbox'   => $scheduled->mailbox,
        ]);
    }

    /**
     * Toggle pause/resume status
     */
    public function toggle($id)
    {
        $scheduled = ScheduledConversation::findOrFail($id);
        
        if (!ScheduledConversation::canManage(null, $scheduled->mailbox_id)) {
            \Helper::denyAccess();
        }

        if ($scheduled->status === ScheduledConversation::STATUS_ACTIVE) {
            $scheduled->status = ScheduledConversation::STATUS_PAUSED;
            $message = __('Scheduled conversation paused');
        } else {
            $scheduled->status = ScheduledConversation::STATUS_ACTIVE;
            $message = __('Scheduled conversation resumed');
        }
        
        $scheduled->save();

        \Session::flash('flash_success_floating', $message);

        return redirect()->route('scheduledconversations.index', ['mailbox_id' => $scheduled->mailbox_id]);
    }

    /**
     * Delete a scheduled conversation
     */
    public function destroy($id)
    {
        $scheduled = ScheduledConversation::findOrFail($id);
        
        if (!ScheduledConversation::canManage(null, $scheduled->mailbox_id)) {
            \Helper::denyAccess();
        }

        $mailbox_id = $scheduled->mailbox_id;
        $scheduled->delete();

        \Session::flash('flash_success_floating', __('Scheduled conversation deleted'));

        return redirect()->route('scheduledconversations.index', ['mailbox_id' => $mailbox_id]);
    }

    /**
     * AJAX handler
     */
    public function ajax(Request $request)
    {
        $response = ['status' => 'error', 'msg' => ''];

        switch ($request->action) {
            case 'delete':
                $scheduled = ScheduledConversation::find($request->scheduled_id);
                if (!$scheduled) {
                    $response['msg'] = __('Scheduled conversation not found');
                    break;
                }
                if (!ScheduledConversation::canManage(null, $scheduled->mailbox_id)) {
                    $response['msg'] = __('Not enough permissions');
                    break;
                }
                $mailbox_id = $scheduled->mailbox_id;
                $scheduled->delete();
                $response['status'] = 'success';
                $response['redirect_url'] = route('scheduledconversations.index', ['mailbox_id' => $mailbox_id]);
                \Session::flash('flash_success_floating', __('Scheduled conversation deleted'));
                break;

            default:
                $response['msg'] = __('Unknown action');
                break;
        }

        return \Response::json($response);
    }

    // =========================================================================
    // PROTECTED HELPERS
    // =========================================================================

    /**
     * Centralized validation for both store and update.
     * Returns array of error messages keyed by field name (empty array = valid).
     *
     * @param Request $request
     * @param string  $mode  'create' | 'edit'
     * @return array
     */
    protected function validateScheduledConversation(Request $request, $mode = 'create')
    {
        $errors = [];

        // --- Subject ---
        if (empty(trim($request->subject ?? ''))) {
            $errors['subject'] = __('The subject field is required.');
        }

        // --- Body (Summernote sends HTML; strip tags to detect empty content) ---
        $bodyText = strip_tags($request->body ?? '');
        if (empty(trim($bodyText))) {
            $errors['body'] = __('The message body cannot be empty.');
        }

        // --- Destination type ---
        $validDestinations = ['internal', 'customer', 'email'];
        if (!in_array($request->destination_type, $validDestinations)) {
            $errors['destination_type'] = __('Please select a valid destination type.');
        } else {
            if ($request->destination_type === 'customer') {
                if (empty($request->destination_customer)) {
                    $errors['destination_customer'] = __('Please select a customer.');
                } elseif (!Customer::find($request->destination_customer)) {
                    $errors['destination_customer'] = __('The selected customer does not exist.');
                }
            } elseif ($request->destination_type === 'email') {
                if (empty($request->destination_email)) {
                    $errors['destination_email'] = __('Please enter an email address.');
                } elseif (!filter_var($request->destination_email, FILTER_VALIDATE_EMAIL)) {
                    $errors['destination_email'] = __('Please enter a valid email address.');
                }
            }
        }

        // --- Frequency type + per-type field validation ---
        $validFrequencies = ['once', 'daily', 'weekly', 'monthly', 'monthly_ordinal', 'yearly'];
        if (!in_array($request->frequency_type, $validFrequencies)) {
            $errors['frequency_type'] = __('Please select a valid frequency.');
        } else {
            switch ($request->frequency_type) {

                case 'once':
                    if (empty($request->once_date)) {
                        $errors['once_date'] = __('The date is required for a one-time scheduled conversation.');
                    }
                    if (empty($request->once_time)) {
                        $errors['once_time'] = __('The time is required for a one-time scheduled conversation.');
                    }
                    if (empty($errors['once_date']) && empty($errors['once_time'])) {
                        $onceDateTime = \DateTime::createFromFormat('Y-m-d H:i', $request->once_date . ' ' . $request->once_time);
                        if (!$onceDateTime) {
                            $errors['once_date'] = __('The date or time format is invalid.');
                        } elseif ($onceDateTime <= new \DateTime()) {
                            $errors['once_date'] = __('The scheduled date and time must be in the future.');
                        }
                    }
                    break;

                case 'daily':
                    if (empty($request->daily_time)) {
                        $errors['daily_time'] = __('The time is required for daily frequency.');
                    }
                    break;

                case 'weekly':
                    // weekly_days is now an array of day integers (0=Sun..6=Sat)
                    $weeklyDays = $request->input('weekly_days', []);
                    if (empty($weeklyDays)) {
                        $errors['weekly_days'] = __('Please select at least one day of the week.');
                    } else {
                        $validDays = ['0','1','2','3','4','5','6'];
                        foreach ($weeklyDays as $d) {
                            if (!in_array((string)$d, $validDays)) {
                                $errors['weekly_days'] = __('Please select a valid day of the week.');
                                break;
                            }
                        }
                    }
                    if (empty($request->weekly_time)) {
                        $errors['weekly_time'] = __('The time is required for weekly frequency.');
                    }
                    break;

                case 'monthly':
                    $d = (int)$request->monthly_day;
                    if ($d < 1 || $d > 31) {
                        $errors['monthly_day'] = __('Please select a valid day of the month (1-31).');
                    }
                    if (empty($request->monthly_time)) {
                        $errors['monthly_time'] = __('The time is required for monthly frequency.');
                    }
                    break;

                case 'monthly_ordinal':
                    if (!in_array($request->monthly_ordinal_position, ['first','second','third','fourth','last'])) {
                        $errors['monthly_ordinal_position'] = __('Please select a valid position.');
                    }
                    if (!in_array((string)$request->monthly_ordinal_day, ['0','1','2','3','4','5','6'])) {
                        $errors['monthly_ordinal_day'] = __('Please select a valid day of the week.');
                    }
                    if (empty($request->monthly_ordinal_time)) {
                        $errors['monthly_ordinal_time'] = __('The time is required.');
                    }
                    break;

                case 'yearly':
                    $ym = (int)$request->yearly_month;
                    $yd = (int)$request->yearly_day;
                    if ($ym < 1 || $ym > 12) {
                        $errors['yearly_month'] = __('Please select a valid month.');
                    }
                    if ($yd < 1 || $yd > 31) {
                        $errors['yearly_day'] = __('Please select a valid day.');
                    }
                    // Check day is valid for the selected month
                    if (empty($errors['yearly_month']) && empty($errors['yearly_day'])) {
                        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $ym, date('Y'));
                        if ($yd > $daysInMonth) {
                            $errors['yearly_day'] = __('The selected day does not exist in the chosen month.');
                        }
                    }
                    if (empty($request->yearly_time)) {
                        $errors['yearly_time'] = __('The time is required for yearly frequency.');
                    }
                    break;
            }
        }

        // --- Start date / End date coherence ---
        if (!empty($request->start_date)) {
            $startDt = new \DateTime($request->start_date . ' 00:00:00');
            // On create, start date must not be in the past
            if ($mode === 'create' && $startDt < new \DateTime()) {
                $errors['start_date'] = __('The start date cannot be in the past.');
            }
            // End date must be after start date
            if (!empty($request->end_date)) {
                $endDt = new \DateTime($request->end_date . ' 23:59:59');
                if ($endDt <= $startDt) {
                    $errors['end_date'] = __('The end date must be after the start date.');
                }
            }
        }

        // End date must be in the future
        if (!empty($request->end_date) && empty($errors['end_date'])) {
            $endDt = new \DateTime($request->end_date . ' 23:59:59');
            if ($endDt <= new \DateTime()) {
                $errors['end_date'] = __('The end date must be in the future.');
            }
        }

        // For 'once': end_date must be after the once datetime (if both present)
        if ($request->frequency_type === 'once'
            && !empty($request->once_date) && !empty($request->once_time)
            && !empty($request->end_date) && empty($errors['end_date'])) {
            $onceDateTime = \DateTime::createFromFormat('Y-m-d H:i', $request->once_date . ' ' . $request->once_time);
            $endDt = new \DateTime($request->end_date . ' 23:59:59');
            if ($onceDateTime && $endDt <= $onceDateTime) {
                $errors['end_date'] = __('The end date must be after the scheduled date and time.');
            }
        }

        return $errors;
    }

    /**
     * Build frequency config array from request.
     * Field names MUST match what the views send.
     * Day of week: integer 0=Sunday ... 6=Saturday (PHP date('w') convention).
     */
    protected function buildFrequencyConfig($request)
    {
        switch ($request->frequency_type) {
            case 'once':
                return [
                    'date' => $request->once_date,
                    'time' => $request->once_time,
                ];

            case 'daily':
                return [
                    'time' => $request->daily_time,
                ];

            case 'weekly':
                // Store as days_of_week array to support multiple days selection
                $days = array_map('intval', $request->input('weekly_days', []));
                sort($days); // normalize order
                return [
                    'days_of_week' => $days,
                    'time'         => $request->weekly_time,
                ];

            case 'monthly':
                return [
                    'day'  => (int)$request->monthly_day,
                    'time' => $request->monthly_time,
                ];

            case 'monthly_ordinal':
                return [
                    'position'    => $request->monthly_ordinal_position,
                    'day_of_week' => (int)$request->monthly_ordinal_day,
                    'time'        => $request->monthly_ordinal_time,
                ];

            case 'yearly':
                return [
                    'day'   => (int)$request->yearly_day,
                    'month' => (int)$request->yearly_month,
                    'time'  => $request->yearly_time,
                ];
        }

        return [];
    }

    /**
     * Extract destination value from request
     */
    protected function getDestinationValue($request)
    {
        if ($request->destination_type === 'customer') {
            return $request->destination_customer;
        }
        if ($request->destination_type === 'email') {
            return $request->destination_email;
        }
        return null;
    }

    /**
     * Calculate initial next_run_at from frequency config.
     * Day of week integer: 0=Sunday, 1=Monday ... 6=Saturday (PHP date('w')).
     *
     * @param string      $frequencyType
     * @param array       $config
     * @param string|null $startDate
     * @return \DateTime
     */
    protected function calculateInitialNextRun($frequencyType, $config, $startDate = null)
    {
        $now      = new \DateTime();
        $baseDate = $startDate ? new \DateTime($startDate) : clone $now;

        switch ($frequencyType) {

            case 'once':
                return \DateTime::createFromFormat('Y-m-d H:i', $config['date'] . ' ' . $config['time']);

            case 'daily':
                list($hour, $minute) = explode(':', $config['time']);
                $next = clone $baseDate;
                $next->setTime((int)$hour, (int)$minute, 0);
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
                return $next;

            case 'weekly':
                list($hour, $minute) = explode(':', $config['time']);
                // Support both legacy day_of_week (int) and new days_of_week (array)
                $days = isset($config['days_of_week']) ? $config['days_of_week'] :
                        (isset($config['day_of_week']) ? [$config['day_of_week']] : [1]);
                $days = array_map('intval', $days);
                sort($days);
                $currentDow = (int)(clone $baseDate)->format('w');
                $bestDiff = null;
                foreach ($days as $targetDow) {
                    $diff = ($targetDow - $currentDow + 7) % 7;
                    $candidate = clone $baseDate;
                    if ($diff === 0) {
                        $candidate->setTime((int)$hour, (int)$minute, 0);
                        if ($candidate <= $now) {
                            $diff = 7;
                        }
                    }
                    if ($diff > 0) {
                        $candidate = clone $baseDate;
                        $candidate->modify("+{$diff} days");
                        $candidate->setTime((int)$hour, (int)$minute, 0);
                    }
                    if ($bestDiff === null || $candidate < $bestDiff) {
                        $bestDiff = $candidate;
                    }
                }
                return $bestDiff ?? $baseDate;

            case 'monthly':
                list($hour, $minute) = explode(':', $config['time']);
                $next = clone $baseDate;
                $next->setDate($next->format('Y'), $next->format('m'), $config['day']);
                $next->setTime((int)$hour, (int)$minute, 0);
                if ($next <= $now) {
                    $next->modify('+1 month');
                    $next->setDate($next->format('Y'), $next->format('m'), $config['day']);
                }
                return $next;

            case 'monthly_ordinal':
                list($hour, $minute) = explode(':', $config['time']);
                $dowNames  = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                $dowName   = $dowNames[(int)$config['day_of_week']];
                $next      = new \DateTime("{$config['position']} {$dowName} of this month");
                $next->setTime((int)$hour, (int)$minute, 0);
                if ($next <= $now) {
                    $next = new \DateTime("{$config['position']} {$dowName} of next month");
                    $next->setTime((int)$hour, (int)$minute, 0);
                }
                return $next;

            case 'yearly':
                list($hour, $minute) = explode(':', $config['time']);
                $next = clone $baseDate;
                $next->setDate($next->format('Y'), $config['month'], $config['day']);
                $next->setTime((int)$hour, (int)$minute, 0);
                if ($next <= $now) {
                    $next->modify('+1 year');
                    $next->setDate($next->format('Y'), $config['month'], $config['day']);
                }
                return $next;
        }

        return $now;
    }
}
