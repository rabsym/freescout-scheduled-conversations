<?php

/**
 * Scheduled Conversations Module Bootstrap
 *
 * Entry point loaded by the nwidart/laravel-modules package when the module is active.
 * Registers the module's route file with the Laravel router.
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 * @version 1.6.0
 */

/*
|--------------------------------------------------------------------------
| Register Namespaces and Routes
|--------------------------------------------------------------------------
*/

// Load routes
if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
