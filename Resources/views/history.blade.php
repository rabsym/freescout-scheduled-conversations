@extends('layouts.app')

@section('title_full', __('Execution History') . ' - ' . $scheduled->subject)

@section('body_attrs')@parent data-mailbox_id="{{ $scheduled->mailbox_id }}"@endsection

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')
<div class="section-heading margin-bottom">
    {{ __('Execution History') }}
</div>

<div class="container">
    <div class="row">
        <div class="col-xs-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>{{ $scheduled->subject }}</h4>
                    <p class="text-muted">
                        <strong>{{ __('Mailbox') }}:</strong> {{ $scheduled->mailbox->name }} ({{ $scheduled->mailbox->email }})<br>
                        <strong>{{ __('Status') }}:</strong> {{ $scheduled->status_icon }} {{ $scheduled->status_display }}<br>
                        <strong>{{ __('Frequency') }}:</strong> {{ $scheduled->frequency_display }}<br>
                        <strong>{{ __('Total Executions') }}:</strong> {{ $scheduled->run_count }}
                    </p>
                </div>
            </div>

            @if (count($logs) > 0)
                <div class="alert alert-info">
                    <strong>{{ __('Success Rate') }}:</strong>
                    {{ $logs->where('status', 'success')->count() }} / {{ $logs->total() }}
                    ({{ $logs->total() > 0 ? round(($logs->where('status', 'success')->count() / $logs->total()) * 100, 1) : 0 }}%)
                </div>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Recipient') }}</th>
                            <th>{{ __('Conversation') }}</th>
                            <th>{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->executed_at->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $log->status_icon }} {{ $log->status_display }}</td>
                                <td>{{ $log->recipient_email ?? '-' }}</td>
                                <td>
                                    @if ($log->conversation_id)
                                        <a href="{{ route('conversations.view', ['id' => $log->conversation_id]) }}" target="_blank">
                                            #{{ $log->conversation_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->error_message)
                                        <span class="text-danger" title="{{ $log->error_message }}">
                                            {{ \Str::limit($log->error_message, 50) }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{ $logs->links() }}
            @else
                <div class="alert alert-info">
                    {{ __('No execution history yet.') }}
                </div>
            @endif

            <div class="margin-top">
                <a href="{{ route('scheduledconversations.index', ['mailbox_id' => $scheduled->mailbox_id]) }}" class="btn btn-default">
                    <i class="glyphicon glyphicon-arrow-left"></i> {{ __('Back to List') }}
                </a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('scheduledconversations.clear_history', $scheduled->id) }}"
                       class="btn btn-danger js-clear-history"
                       data-confirm="{{ __('Are you sure you want to clear the execution history?') }}"
                       style="margin-left: 10px;">
                        <i class="glyphicon glyphicon-trash"></i> {{ __('Clear History') }}
                    </a>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection

@section('javascript')
    @parent
    $('a.js-clear-history').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var confirmMsg = $(this).data('confirm');
        showModalConfirm(confirmMsg, 'confirm-delete', {
            on_show: function(modal) {
                modal.children().find('.confirm-delete:first').click(function(e) {
                    var form = $('<form>', { 'method': 'POST', 'action': url });
                    var csrf = $('<input>', { 'type': 'hidden', 'name': '_token', 'value': '{{ csrf_token() }}' });
                    form.append(csrf).appendTo('body').submit();
                });
            }
        });
    });
@endsection
