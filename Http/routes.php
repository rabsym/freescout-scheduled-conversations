<?php

/**
 * Scheduled Conversations Module Routes
 *
 * All routes are grouped under the 'web' middleware and the module's controller namespace.
 * Subdirectory prefix is applied automatically if FreeScout is installed in a subdirectory.
 *
 * Route naming convention: scheduledconversations.{action}
 *
 * Note: Update uses POST to /update (not PUT) to avoid 405 Method Not Allowed errors
 * in some server/proxy configurations that do not support HTTP method spoofing.
 * Delete uses POST to /delete for the same reason.
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 */

$routeOptions = [
    'middleware' => 'web',
    'namespace' => 'Modules\ScheduledConversations\Http\Controllers'
];

$subdirectory = \Helper::getSubdirectory();
if ($subdirectory) {
    $routeOptions['prefix'] = $subdirectory;
}

Route::group($routeOptions, function()
{
    // AJAX search for customers
    Route::get('/scheduled-conversations/search-customers', ['uses' => 'ScheduledConversationsController@searchCustomers'])->name('scheduledconversations.search_customers');
    
    Route::get('/mailbox/{mailbox_id}/scheduled-conversations', ['uses' => 'ScheduledConversationsController@index'])->name('scheduledconversations.index');
    Route::get('/mailbox/{mailbox_id}/scheduled-conversations/create', ['uses' => 'ScheduledConversationsController@create'])->name('scheduledconversations.create');
    Route::post('/mailbox/{mailbox_id}/scheduled-conversations', ['uses' => 'ScheduledConversationsController@store'])->name('scheduledconversations.store');
    
    Route::get('/scheduled-conversations/{id}/view', ['uses' => 'ScheduledConversationsController@showView'])->name('scheduledconversations.view');
    Route::get('/scheduled-conversations/{id}/edit', ['uses' => 'ScheduledConversationsController@edit'])->name('scheduledconversations.edit');
    Route::post('/scheduled-conversations/{id}/update', ['uses' => 'ScheduledConversationsController@update'])->name('scheduledconversations.update');
    Route::get('/scheduled-conversations/{id}/history', ['uses' => 'ScheduledConversationsController@history'])->name('scheduledconversations.history');
    Route::post('/scheduled-conversations/{id}/toggle', ['uses' => 'ScheduledConversationsController@toggle'])->name('scheduledconversations.toggle');
    Route::post('/scheduled-conversations/{id}/clear-history', ['uses' => 'ScheduledConversationsController@clearHistory'])->name('scheduledconversations.clear_history');
    Route::post('/scheduled-conversations/{id}/delete', ['uses' => 'ScheduledConversationsController@destroy'])->name('scheduledconversations.destroy');
    
    Route::post('/mailbox/scheduled-conversations/ajax', ['uses' => 'ScheduledConversationsController@ajax', 'laroute' => true])->name('scheduledconversations.ajax');
});
