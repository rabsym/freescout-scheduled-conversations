<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeScheduledConversationsBodyToLongtext extends Migration
{
    public function up()
    {
        $table = DB::getTablePrefix().'scheduled_conversations';
        DB::statement("ALTER TABLE `{$table}` MODIFY `body` LONGTEXT NOT NULL");
    }

    public function down()
    {
        $table = DB::getTablePrefix().'scheduled_conversations';
        DB::statement("ALTER TABLE `{$table}` MODIFY `body` TEXT NOT NULL");
    }
}
