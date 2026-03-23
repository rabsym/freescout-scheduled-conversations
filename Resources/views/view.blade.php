@extends('layouts.app')

@section('title_full', __('View Scheduled Conversation') . ' - ' . $mailbox->name)

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox->id }}"@endsection

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')
<div class="section-heading margin-bottom">
    {{ __('View Scheduled Conversation') }}
</div>

<div class="container">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
                <div class="panel-body">
                    <dl class="dl-horizontal">

                        <dt>{{ __('Status') }}</dt>
                        <dd>{!! $scheduled->status_icon !!} {{ $scheduled->status_display }}</dd>

                        <dt>{{ __('Subject') }}</dt>
                        <dd>{{ $scheduled->subject }}</dd>

                        <dt>{{ __('Destination') }}</dt>
                        <dd>
                            @if ($scheduled->destination_type === 'internal')
                                {{ __('Internal message to this mailbox') }} <small class="text-muted">({{ __('No email sent') }})</small>
                            @elseif ($scheduled->destination_type === 'customer')
                                {{ __('FreeScout customer address') }}: {{ \App\Customer::find($scheduled->destination_value)?->getMainEmail() ?? $scheduled->destination_value }}
                            @else
                                {{ __('Email Address') }}: {{ $scheduled->destination_value }}
                            @endif
                        </dd>

                        <dt>{{ __('Frequency') }}</dt>
                        <dd>{{ $scheduled->frequency_display }}</dd>

                        @if (!empty($config['time']))
                        <dt>{{ __('Time') }}</dt>
                        <dd>{{ $config['time'] }}</dd>
                        @endif

                        @if ($scheduled->frequency_type !== 'once')
                        <dt>{{ __('Missed Executions') }}</dt>
                        <dd>
                            @if ($scheduled->catch_up_mode === 'skip')
                                {{ __('Skip missed executions till next cycle') }}
                            @else
                                {{ __('Execute when possible even if delayed') }}
                            @endif
                        </dd>
                        @endif

                        <dt>{{ __('Start Date') }}</dt>
                        <dd>{{ $scheduled->start_date ? $scheduled->start_date->format('d/m/Y') : '-' }}</dd>

                        <dt>{{ __('End Date') }}</dt>
                        <dd>{{ $scheduled->end_date ? $scheduled->end_date->format('d/m/Y') : '-' }}</dd>

                        <dt>{{ __('Next Run') }}</dt>
                        <dd>{{ $scheduled->next_run_at ? $scheduled->next_run_at->format('d/m/Y H:i') : '-' }}</dd>

                    </dl>
                </div>
            </div>

            {{-- Message body --}}
            <div class="form-group">
                <label class="control-label">{{ __('Message') }}</label>
                <div class="well well-sm" style="min-height: 80px; margin-top: 5px;">
                    {!! $scheduled->body !!}
                </div>
            </div>

            {{-- Back button --}}
            <div class="form-group margin-top">
                <a href="{{ route('scheduledconversations.index', ['mailbox_id' => $scheduled->mailbox_id]) }}" class="btn btn-default">
                    <i class="glyphicon glyphicon-arrow-left"></i> {{ __('Back to List') }}
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
