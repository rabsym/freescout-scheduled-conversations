<?php

/**
 * Migration: Create Scheduled Conversations Table
 *
 * Creates the scheduled_conversations table which stores all scheduled conversation
 * configurations including frequency, destination, body, and execution metadata.
 *
 * Key columns:
 * - frequency_config: JSON blob storing type-specific scheduling parameters
 * - catch_up_mode: Behaviour for missed executions (skip / catch_up_last)
 * - next_run_at: Pre-calculated timestamp of the next scheduled execution
 * - last_run_at: Timestamp of the most recent execution
 * - run_count: Total number of successful executions
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.5.0
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduledConversationsTable extends Migration
{
    public function up()
    {
        Schema::create('scheduled_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('mailbox_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('status', 20)->default('active');
            $table->string('subject', 255);
            $table->text('body');
            $table->string('destination_type', 20);
            $table->string('destination_value', 255)->nullable();
            $table->string('frequency_type', 30);
            $table->text('frequency_config')->nullable();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->datetime('last_run_at')->nullable();
            $table->datetime('next_run_at')->nullable();
            $table->integer('run_count')->unsigned()->default(0);
            $table->string('catch_up_mode', 20)->default('skip');
            $table->timestamps();

            $table->index('mailbox_id', 'idx_mailbox_id');
            $table->index('status', 'idx_status');
            $table->index(['next_run_at', 'status'], 'idx_next_run');

            $table->foreign('mailbox_id')->references('id')->on('mailboxes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scheduled_conversations');
    }
}
