@extends('layouts.app')

@section('title_full', __('Scheduled Conversations') . ' - ' . $mailbox->name)

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox->id }}"@endsection

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')
<div class="section-heading margin-bottom">
    {{ __('Scheduled Conversations') }}
    @if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canManage(null, $mailbox->id))
        <a href="{{ route('scheduledconversations.create', ['mailbox_id' => $mailbox->id]) }}" class="btn btn-primary btn-sm" style="margin-left: 15px;">
            <i class="glyphicon glyphicon-plus"></i> {{ __('New Scheduled Conversation') }}
        </a>
    @endif
</div>

<div class="container">
    <div class="row">
        <div class="col-xs-12">

            @if (count($scheduled_conversations))
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('To') }}</th>
                            <th>{{ __('Frequency') }}</th>
                            <th>{{ __('Next Run') }}</th>
                            <th>{{ __('Created by') }}</th>
                            <th>{{ __('Created at') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scheduled_conversations as $sc)
                            <tr>
                                <td>{{ $sc->id }}</td>
                                <td>{!! $sc->status_icon !!} {{ $sc->status_display }}</td>
                                <td>{{ $sc->subject }}</td>
                                <td>
                                    @if ($sc->destination_type === 'internal')
                                        <span class="text-muted">{{ __('Internal') }}</span>
                                    @elseif ($sc->destination_type === 'customer')
                                        {{-- Resolve customer email from ID --}}
                                        @php
                                            $destCustomer = \App\Customer::find($sc->destination_value);
                                        @endphp
                                        {{ $destCustomer ? $destCustomer->getMainEmail() : $sc->destination_value }}
                                    @else
                                        {{ $sc->destination_value }}
                                    @endif
                                </td>
                                <td>{{ $sc->frequency_display }}</td>
                                <td>
                                    @if ($sc->next_run_at)
                                        {{ $sc->next_run_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($sc->user)
                                        {{ $sc->user->getFullName() }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $sc->created_at->format('d/m/Y') }}</td>
                                <td>
                                    {{-- View button visible to all who can view --}}
                                    @if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canView(null, $mailbox->id))
                                        <a href="{{ route('scheduledconversations.view', $sc->id) }}" class="btn btn-xs btn-default" title="{{ __('View') }}">
                                            <i class="glyphicon glyphicon-eye-open"></i>
                                        </a>
                                    @endif
                                    {{-- Edit only for managers --}}
                                    @if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canManage(null, $mailbox->id))
                                        <a href="{{ route('scheduledconversations.edit', $sc->id) }}" class="btn btn-xs btn-default" title="{{ __('Edit') }}">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                    @endif
                                    {{-- History visible to all who can view --}}
                                    @if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canView(null, $mailbox->id))
                                        <a href="{{ route('scheduledconversations.history', $sc->id) }}" class="btn btn-xs btn-default" title="{{ __('History') }}">
                                            <i class="glyphicon glyphicon-list"></i>
                                        </a>
                                    @endif
                                    {{-- Delete only for managers --}}
                                    @if (\Modules\ScheduledConversations\Entities\ScheduledConversation::canManage(null, $mailbox->id))
                                        <a href="{{ route('scheduledconversations.destroy', $sc->id) }}"
                                           class="btn btn-xs btn-danger"
                                           data-method="delete"
                                           data-confirm="{{ __('Delete this scheduled conversation?') }}"
                                           title="{{ __('Delete') }}">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="alert alert-info">
                    {{ __('No scheduled conversations yet.') }}
                </div>
            @endif

        </div>
    </div>
</div>
@endsection

@section('javascript')
    @parent
    // Handle delete button with Bootstrap modal confirmation
    $('a[data-method="delete"]').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var confirmMsg = $(this).data('confirm');

        showModalConfirm(confirmMsg, 'confirm-delete', {
            on_show: function(modal) {
                modal.children().find('.confirm-delete:first').click(function(e) {
                    // Plain POST form — route is Route::post, no method spoofing needed
                    var form = $('<form>', { 'method': 'POST', 'action': url });
                    var csrf = $('<input>', { 'type': 'hidden', 'name': '_token', 'value': '{{ csrf_token() }}' });
                    form.append(csrf).appendTo('body').submit();
                });
            }
        });
    });
@endsection
