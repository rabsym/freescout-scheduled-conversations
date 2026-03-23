<?php

/**
 * Migration: Create Scheduled Conversation Logs Table
 *
 * Creates the scheduled_conversation_logs table which records every execution
 * attempt (success or failure) for each scheduled conversation.
 *
 * Used to power the Execution History view and calculate success rates.
 * On failure, error_message stores the full exception message, file and line number
 * to facilitate debugging without needing to check the Laravel log.
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduledConversationLogsTable extends Migration
{
    public function up()
    {
        Schema::create('scheduled_conversation_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('scheduled_conversation_id')->unsigned();
            $table->integer('conversation_id')->unsigned()->nullable();
            $table->datetime('executed_at');
            $table->string('status', 20);
            $table->string('recipient_email', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('scheduled_conversation_id', 'idx_scheduled_conv');
            $table->index('executed_at', 'idx_executed_at');
            $table->index('status', 'idx_status');

            $table->foreign('scheduled_conversation_id')->references('id')->on('scheduled_conversations')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scheduled_conversation_logs');
    }
}
