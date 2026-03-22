<?php

/**
 * Scheduled Conversation Log Entity
 *
 * Eloquent model for the scheduled_conversation_logs table.
 *
 * Records each execution attempt of a scheduled conversation, whether successful
 * or failed. Used to display the Execution History view and calculate success rates.
 *
 * Each log entry stores:
 * - scheduled_conversation_id: Reference to the parent scheduled conversation
 * - conversation_id: The FreeScout conversation created (null if execution failed)
 * - executed_at: Timestamp of the execution attempt
 * - status: success or failed
 * - recipient_email: Email address used as recipient
 * - error_message: Full error message including file and line number (on failure)
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.5.0
 */

namespace Modules\ScheduledConversations\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Conversation;

class ScheduledConversationLog extends Model
{
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'scheduled_conversation_id',
        'conversation_id',
        'executed_at',
        'status',
        'recipient_email',
        'error_message',
    ];

    protected $dates = [
        'executed_at',
        'created_at',
    ];

    public $timestamps = false;

    /**
     * Relationship with ScheduledConversation
     */
    public function scheduledConversation()
    {
        return $this->belongsTo(ScheduledConversation::class);
    }

    /**
     * Relationship with Conversation
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute()
    {
        switch ($this->status) {
            case self::STATUS_SUCCESS:
                return __('Success');
            case self::STATUS_FAILED:
                return __('Failed');
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
            case self::STATUS_SUCCESS:
                return '✅';
            case self::STATUS_FAILED:
                return '❌';
            default:
                return '';
        }
    }
}
