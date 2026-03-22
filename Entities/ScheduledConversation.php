<?php

/**
 * Scheduled Conversation Entity
 *
 * Eloquent model for the scheduled_conversations table.
 *
 * Stores the configuration for each scheduled conversation including destination,
 * frequency, body, and execution metadata (last_run_at, next_run_at, run_count).
 *
 * FREQUENCY TYPES:
 * - once            : Single execution at a specific date and time
 * - daily           : Every day at a specified time
 * - weekly          : Every week on a specific day and time (day_of_week: 0=Sun..6=Sat)
 * - monthly         : Every month on a specific day and time
 * - monthly_ordinal : Every month on the nth weekday (e.g. "first monday")
 * - yearly          : Every year on a specific month, day and time
 *
 * DESTINATION TYPES:
 * - internal  : Internal message to the mailbox (no SMTP sent)
 * - customer  : Email sent to a FreeScout customer
 * - email     : Email sent to a free-form email address
 *
 * PERMISSIONS:
 * - canManage(): Admin always, or user with PERM_MANAGE_SCHEDULED_CONVERSATIONS + mailbox access
 * - canView():   Admin always, or any mailbox user if all_users_can_view setting is enabled
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.6.0
 */

namespace Modules\ScheduledConversations\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Mailbox;
use App\User;

class ScheduledConversation extends Model
{
    // Permission
    const PERM_MANAGE_SCHEDULED_CONVERSATIONS = "scheduledconversations.manage";
    
    // Status
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_EXPIRED = 'expired';
    
    // Destination types
    const DESTINATION_INTERNAL = 'internal';
    const DESTINATION_CUSTOMER = 'customer';
    const DESTINATION_EMAIL = 'email';
    
    // Frequency types
    const FREQUENCY_ONCE = 'once';
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_MONTHLY_ORDINAL = 'monthly_ordinal';
    const FREQUENCY_YEARLY = 'yearly';
    
    // Catch-up modes
    const CATCHUP_SKIP = 'skip';
    const CATCHUP_LAST = 'catch_up_last';

    protected $fillable = [
        'mailbox_id',
        'user_id',
        'status',
        'subject',
        'body',
        'destination_type',
        'destination_value',
        'frequency_type',
        'frequency_config',
        'start_date',
        'end_date',
        'last_run_at',
        'next_run_at',
        'run_count',
        'catch_up_mode',
    ];

    protected $casts = [
        'frequency_config' => 'array',
        'run_count' => 'integer',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'last_run_at',
        'next_run_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Check if user can manage scheduled conversations
     *
     * @param User|null $user
     * @param int|null $mailbox_id
     * @return bool
     */
    public static function canManage($user = null, $mailbox_id = null)
    {
        if (!$user) {
            $user = auth()->user();
        }
        if (!$user) {
            return false;
        }
        
        // Admin can always manage
        if ($user->isAdmin()) {
            return true;
        }
        
        // Check permission and mailbox access
        if ($mailbox_id) {
            return $user->hasAccessToMailbox($mailbox_id) 
                && $user->hasPermission(self::PERM_MANAGE_SCHEDULED_CONVERSATIONS);
        }
        
        // Just check permission
        return $user->hasPermission(self::PERM_MANAGE_SCHEDULED_CONVERSATIONS);
    }

    /**
     * Check if user can view scheduled conversations (read-only)
     *
     * @param User|null $user
     * @param int|null $mailbox_id
     * @return bool
     */
    public static function canView($user = null, $mailbox_id = null)
    {
        if (!$user) {
            $user = auth()->user();
        }
        if (!$user) {
            return false;
        }

        // Admin can always view
        if ($user->isAdmin()) {
            return true;
        }

        // If "all users can view" is disabled, only users with manage permission can view
        $allUsersCanView = \Option::get('scheduledconversations.all_users_can_view', true);
        if (!$allUsersCanView) {
            return self::canManage($user, $mailbox_id);
        }

        // User can view if has access to mailbox
        if ($mailbox_id) {
            return $user->hasAccessToMailbox($mailbox_id);
        }

        return false;
    }

    /**
     * Relationship with Mailbox
     */
    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * Relationship with User (creator)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with logs
     */
    public function logs()
    {
        return $this->hasMany(ScheduledConversationLog::class);
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute()
    {
        switch ($this->status) {
            case self::STATUS_ACTIVE:
                return __('Active');
            case self::STATUS_PAUSED:
                return __('Paused');
            case self::STATUS_EXPIRED:
                return __('Expired');
            default:
                return $this->status;
        }
    }

    /**
     * Get status icon
     */
    public function getStatusIconAttribute()
    {
        switch ($this->status) {
            case self::STATUS_ACTIVE:
                return '🟢';
            case self::STATUS_PAUSED:
                return '⏸️';
            case self::STATUS_EXPIRED:
                return '🔴';
            default:
                return '';
        }
    }

    /**
     * Get frequency display name
     */
    public function getFrequencyDisplayAttribute()
    {
        switch ($this->frequency_type) {
            case self::FREQUENCY_ONCE:
                return __('Once');
            case self::FREQUENCY_DAILY:
                return __('Daily');
            case self::FREQUENCY_WEEKLY:
                // Show selected days if available
                if (!empty($this->frequency_config['days_of_week'])) {
                    $dowNames = [0=>__('Sun'),1=>__('Mon'),2=>__('Tue'),3=>__('Wed'),4=>__('Thu'),5=>__('Fri'),6=>__('Sat')];
                    $days = array_map(function($d) use ($dowNames) { return $dowNames[(int)$d] ?? $d; }, $this->frequency_config['days_of_week']);
                    return __('Weekly') . ' (' . implode(', ', $days) . ')';
                }
                return __('Weekly');
            case self::FREQUENCY_MONTHLY:
                return __('Monthly');
            case self::FREQUENCY_MONTHLY_ORDINAL:
                return __('Monthly (nth weekday)');
            case self::FREQUENCY_YEARLY:
                return __('Yearly');
            default:
                return $this->frequency_type;
        }
    }

    /**
     * Scope to get pending scheduled conversations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('next_run_at', '<=', now())
            ->whereNotNull('next_run_at');
    }

    /**
     * Check if scheduled conversation is in valid date range
     */
    public function isInValidDateRange()
    {
        $now = now();
        
        // Check start_date
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }
        
        // Check end_date
        if ($this->end_date && $now > $this->end_date) {
            return false;
        }
        
        return true;
    }
}
