@if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canView(null, $mailbox->id))
<li @if (Route::currentRouteName() == 'scheduledconversations.index')class="active"@endif>
    <a href="{{ route('scheduledconversations.index', ['mailbox_id' => $mailbox->id]) }}">
        <i class="glyphicon glyphicon-calendar"></i> {{ __('Scheduled Conversations') }}
    </a>
</li>
@endif
