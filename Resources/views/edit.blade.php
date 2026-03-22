@extends('layouts.app')

@section('title_full', __('Edit Scheduled Conversation') . ' - ' . $mailbox->name)

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox->id }}"@endsection

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('mailboxes/sidebar_menu')
@endsection

@section('content')
<div class="section-heading margin-bottom">
    {{ __('Edit Scheduled Conversation') }}
</div>

<div class="container">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">

            {{-- Server-side validation error summary --}}
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>{{ __('Please correct the following errors:') }}</strong>
                    <ul class="margin-top-sm margin-bottom-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- POST to /update route — no method spoofing to avoid 405 errors --}}
            <form method="POST" action="{{ route('scheduledconversations.update', ['id' => $scheduled->id]) }}" id="scheduledconv-form">
                {{ csrf_field() }}

                {{-- Status --}}
                <div class="form-group">
                    <label class="control-label">{{ __('Status') }}</label>
                    <div class="controls">
                        <div class="onoffswitch-wrap">
                            <div class="onoffswitch">
                                <input type="checkbox" name="active" value="1" {{ $scheduled->status === 'active' ? 'checked' : '' }} id="active" class="onoffswitch-checkbox">
                                <label class="onoffswitch-label" for="active"></label>
                            </div>
                            <label for="active" class="control-label">{{ __('Active') }}</label>
                        </div>
                    </div>
                </div>

                {{-- Subject --}}
                <div class="form-group{{ $errors->has('subject') ? ' has-error' : '' }}" id="fg-subject">
                    <label for="subject" class="control-label">{{ __('Subject') }} <span class="text-danger">*</span></label>
                    <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject', $scheduled->subject) }}">
                    @if ($errors->has('subject'))
                        <span class="help-block">{{ $errors->first('subject') }}</span>
                    @endif
                </div>

                {{-- Destination type --}}
                <div class="form-group{{ $errors->has('destination_type') ? ' has-error' : '' }}">
                    <label class="control-label">{{ __('Destination') }} <span class="text-danger">*</span></label>
                    <div class="radio">
                        <label>
                            <input type="radio" name="destination_type" value="internal" id="dest_internal" {{ old('destination_type', $scheduled->destination_type) === 'internal' ? 'checked' : '' }}>
                            {{ __('Internal message to this mailbox') }} <small class="text-muted">({{ __('No email sent') }})</small>
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="destination_type" value="customer" id="dest_customer" {{ old('destination_type', $scheduled->destination_type) === 'customer' ? 'checked' : '' }}>
                            {{ __('FreeScout customer address') }}
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="destination_type" value="email" id="dest_email" {{ old('destination_type', $scheduled->destination_type) === 'email' ? 'checked' : '' }}>
                            {{ __('Email Address') }}
                        </label>
                    </div>
                    @if ($errors->has('destination_type'))
                        <span class="help-block">{{ $errors->first('destination_type') }}</span>
                    @endif
                </div>

                {{-- Destination value fields --}}
                <div class="form-group" id="destination_value_group" style="display:none;">

                    {{-- Customer search --}}
                    <div id="dest_customer_field" style="display:none;">
                        <label for="customer_search" class="control-label">{{ __('Search Customer') }} <span class="text-danger">*</span></label>

                        @if($scheduled->destination_type === 'customer' && $selected_customer)
                            <div id="selected_customer" style="margin-top:10px; padding:10px; background:#f5f5f5; border-radius:4px;">
                                <strong>{{ __('Selected:') }}</strong>
                                <span id="selected_customer_text">{{ $selected_customer->getFullName() }} ({{ $selected_customer->email }})</span>
                                <button type="button" class="btn btn-xs btn-link" id="clear_customer" style="margin-left:10px;">{{ __('Change') }}</button>
                            </div>
                            <input type="text" id="customer_search" class="form-control" placeholder="{{ __('Type at least 2 characters to search...') }}" autocomplete="off" data-search-url="{{ route('scheduledconversations.search_customers') }}" style="display:none;">
                            <input type="hidden" name="destination_customer" id="destination_customer" value="{{ $selected_customer->id }}">
                        @else
                            <input type="text" id="customer_search" class="form-control" placeholder="{{ __('Type at least 2 characters to search...') }}" autocomplete="off" data-search-url="{{ route('scheduledconversations.search_customers') }}">
                            <input type="hidden" name="destination_customer" id="destination_customer">
                            <div id="selected_customer" style="display:none; margin-top:10px; padding:10px; background:#f5f5f5; border-radius:4px;">
                                <strong>{{ __('Selected:') }}</strong> <span id="selected_customer_text"></span>
                                <button type="button" class="btn btn-xs btn-link" id="clear_customer" style="margin-left:10px;">{{ __('Clear') }}</button>
                            </div>
                        @endif

                        <div id="customer_results" style="display:none; position:absolute; background:white; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%; z-index:1000; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                        <small class="help-block">{{ __('Type at least 2 characters to search by name or email') }}</small>
                        @if ($errors->has('destination_customer'))
                            <span class="help-block text-danger">{{ $errors->first('destination_customer') }}</span>
                        @endif
                    </div>

                    {{-- Email input --}}
                    <div id="dest_email_field" style="display:none;">
                        <label for="destination_email" class="control-label">{{ __('Email Address') }} <span class="text-danger">*</span></label>
                        <input type="email" name="destination_email" id="destination_email" class="form-control"
                               value="{{ old('destination_email', $scheduled->destination_type === 'email' ? $scheduled->destination_value : '') }}"
                               placeholder="user@example.com">
                        @if ($errors->has('destination_email'))
                            <span class="help-block text-danger">{{ $errors->first('destination_email') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Frequency type --}}
                @php $config = $scheduled->frequency_config; @endphp

                <div class="form-group{{ $errors->has('frequency_type') ? ' has-error' : '' }}">
                    <label for="frequency_type" class="control-label">{{ __('Frequency') }} <span class="text-danger">*</span></label>
                    <select name="frequency_type" id="frequency_type" class="form-control">
                        <option value="once"            {{ old('frequency_type', $scheduled->frequency_type) === 'once'            ? 'selected' : '' }}>{{ __('Once') }}</option>
                        <option value="daily"           {{ old('frequency_type', $scheduled->frequency_type) === 'daily'           ? 'selected' : '' }}>{{ __('Daily') }}</option>
                        <option value="weekly"          {{ old('frequency_type', $scheduled->frequency_type) === 'weekly'          ? 'selected' : '' }}>{{ __('Weekly') }}</option>
                        <option value="monthly"         {{ old('frequency_type', $scheduled->frequency_type) === 'monthly'         ? 'selected' : '' }}>{{ __('Monthly') }}</option>
                        <option value="monthly_ordinal" {{ old('frequency_type', $scheduled->frequency_type) === 'monthly_ordinal' ? 'selected' : '' }}>{{ __('Monthly (nth weekday)') }}</option>
                        <option value="yearly"          {{ old('frequency_type', $scheduled->frequency_type) === 'yearly'          ? 'selected' : '' }}>{{ __('Yearly') }}</option>
                    </select>
                    @if ($errors->has('frequency_type'))
                        <span class="help-block">{{ $errors->first('frequency_type') }}</span>
                    @endif
                </div>

                {{-- Dynamic frequency fields --}}
                <div id="frequency_fields" class="well well-sm margin-top">

                    {{-- Once --}}
                    <div id="freq_once" class="frequency-config" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group{{ $errors->has('once_date') ? ' has-error' : '' }}" id="fg-once-date">
                                    <label>{{ __('Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="once_date" id="once_date" class="form-control" value="{{ old('once_date', $config['date'] ?? '') }}">
                                    @if ($errors->has('once_date'))
                                        <span class="help-block">{{ $errors->first('once_date') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group{{ $errors->has('once_time') ? ' has-error' : '' }}" id="fg-once-time">
                                    <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="once_time" id="once_time" class="form-control" value="{{ old('once_time', $config['time'] ?? '09:00') }}">
                                    @if ($errors->has('once_time'))
                                        <span class="help-block">{{ $errors->first('once_time') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div id="once_datetime_warning" class="alert alert-warning" style="display:none;">
                            <i class="glyphicon glyphicon-warning-sign"></i>
                            {{ __('The selected date and time is in the past. Please choose a future date and time.') }}
                        </div>
                    </div>

                    {{-- Daily --}}
                    <div id="freq_daily" class="frequency-config" style="display:none;">
                        <div class="form-group{{ $errors->has('daily_time') ? ' has-error' : '' }}" id="fg-daily-time">
                            <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                            <input type="time" name="daily_time" id="daily_time" class="form-control" value="{{ old('daily_time', $config['time'] ?? '09:00') }}">
                            @if ($errors->has('daily_time'))
                                <span class="help-block">{{ $errors->first('daily_time') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Weekly --}}
                    <div id="freq_weekly" class="frequency-config" style="display:none;">
                        {{-- Multiple day selection — at least one day must be checked --}}
                        <div class="form-group{{ $errors->has('weekly_days') ? ' has-error' : '' }}">
                            <label>{{ __('Days of Week') }} <span class="text-danger">*</span></label>
                            <div class="weekly-days-checkboxes">
                                @php
                                    // Support both legacy single day_of_week and new days_of_week array
                                    $savedDays = old('weekly_days',
                                        isset($config['days_of_week']) ? $config['days_of_week'] :
                                        (isset($config['day_of_week']) ? [$config['day_of_week']] : [1])
                                    );
                                @endphp
                                @foreach([1=>__('Monday'),2=>__('Tuesday'),3=>__('Wednesday'),4=>__('Thursday'),5=>__('Friday'),6=>__('Saturday'),0=>__('Sunday')] as $val => $label)
                                <div class="checkbox-inline" style="margin-right:15px; display:inline-block;">
                                    <label>
                                        <input type="checkbox" name="weekly_days[]" value="{{ $val }}"
                                            {{ in_array($val, (array)$savedDays) ? 'checked' : '' }}>
                                        {{ $label }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            @if ($errors->has('weekly_days'))
                                <span class="help-block">{{ $errors->first('weekly_days') }}</span>
                            @endif
                        </div>
                        <div class="form-group{{ $errors->has('weekly_time') ? ' has-error' : '' }}">
                            <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                            <input type="time" name="weekly_time" class="form-control" value="{{ old('weekly_time', $config['time'] ?? '09:00') }}">
                        </div>
                    </div>

                    {{-- Monthly --}}
                    <div id="freq_monthly" class="frequency-config" style="display:none;">
                        <div class="form-group{{ $errors->has('monthly_day') ? ' has-error' : '' }}">
                            <label>{{ __('Day of Month') }} <span class="text-danger">*</span></label>
                            <select name="monthly_day" class="form-control">
                                @for ($d = 1; $d <= 31; $d++)
                                    <option value="{{ $d }}" {{ old('monthly_day', $config['day'] ?? 1) == $d ? 'selected' : '' }}>{{ $d }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group{{ $errors->has('monthly_time') ? ' has-error' : '' }}">
                            <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                            <input type="time" name="monthly_time" class="form-control" value="{{ old('monthly_time', $config['time'] ?? '09:00') }}">
                        </div>
                    </div>

                    {{-- Monthly ordinal --}}
                    <div id="freq_monthly_ordinal" class="frequency-config" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group{{ $errors->has('monthly_ordinal_position') ? ' has-error' : '' }}">
                                    <label>{{ __('Position') }} <span class="text-danger">*</span></label>
                                    <select name="monthly_ordinal_position" class="form-control">
                                        <option value="first"  {{ old('monthly_ordinal_position', $config['position'] ?? 'first') == 'first'  ? 'selected' : '' }}>{{ __('First') }}</option>
                                        <option value="second" {{ old('monthly_ordinal_position', $config['position'] ?? 'first') == 'second' ? 'selected' : '' }}>{{ __('Second') }}</option>
                                        <option value="third"  {{ old('monthly_ordinal_position', $config['position'] ?? 'first') == 'third'  ? 'selected' : '' }}>{{ __('Third') }}</option>
                                        <option value="fourth" {{ old('monthly_ordinal_position', $config['position'] ?? 'first') == 'fourth' ? 'selected' : '' }}>{{ __('Fourth') }}</option>
                                        <option value="last"   {{ old('monthly_ordinal_position', $config['position'] ?? 'first') == 'last'   ? 'selected' : '' }}>{{ __('Last') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group{{ $errors->has('monthly_ordinal_day') ? ' has-error' : '' }}">
                                    <label>{{ __('Day of Week') }} <span class="text-danger">*</span></label>
                                    <select name="monthly_ordinal_day" class="form-control">
                                        <option value="1" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 1 ? 'selected' : '' }}>{{ __('Monday') }}</option>
                                        <option value="2" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 2 ? 'selected' : '' }}>{{ __('Tuesday') }}</option>
                                        <option value="3" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 3 ? 'selected' : '' }}>{{ __('Wednesday') }}</option>
                                        <option value="4" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 4 ? 'selected' : '' }}>{{ __('Thursday') }}</option>
                                        <option value="5" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 5 ? 'selected' : '' }}>{{ __('Friday') }}</option>
                                        <option value="6" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 6 ? 'selected' : '' }}>{{ __('Saturday') }}</option>
                                        <option value="0" {{ old('monthly_ordinal_day', $config['day_of_week'] ?? 1) == 0 ? 'selected' : '' }}>{{ __('Sunday') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('monthly_ordinal_time') ? ' has-error' : '' }}">
                            <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                            <input type="time" name="monthly_ordinal_time" class="form-control" value="{{ old('monthly_ordinal_time', $config['time'] ?? '09:00') }}">
                        </div>
                    </div>

                    {{-- Yearly --}}
                    <div id="freq_yearly" class="frequency-config" style="display:none;">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group{{ $errors->has('yearly_month') ? ' has-error' : '' }}">
                                    <label>{{ __('Month') }} <span class="text-danger">*</span></label>
                                    <select name="yearly_month" id="yearly_month" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ old('yearly_month', $config['month'] ?? 1) == $m ? 'selected' : '' }}>
                                                {{ [1=>__('January'),2=>__('February'),3=>__('March'),4=>__('April'),5=>__('May'),6=>__('June'),7=>__('July'),8=>__('August'),9=>__('September'),10=>__('October'),11=>__('November'),12=>__('December')][$m] }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group{{ $errors->has('yearly_day') ? ' has-error' : '' }}">
                                    <label>{{ __('Day') }} <span class="text-danger">*</span></label>
                                    <select name="yearly_day" class="form-control" id="yearly_day">
                                        @for ($d = 1; $d <= 31; $d++)
                                            <option value="{{ $d }}" {{ old('yearly_day', $config['day'] ?? 1) == $d ? 'selected' : '' }}>{{ $d }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group{{ $errors->has('yearly_time') ? ' has-error' : '' }}">
                                    <label>{{ __('Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="yearly_time" class="form-control" value="{{ old('yearly_time', $config['time'] ?? '09:00') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>{{-- end #frequency_fields --}}

                {{-- Catch-up mode — hidden for 'once' frequency (always executes when possible) --}}
                <div class="form-group" id="catch_up_mode_group">
                    <label class="control-label">{{ __('Missed Executions') }}</label>
                    <div class="radio">
                        <label>
                            <input type="radio" name="catch_up_mode" value="skip" {{ old('catch_up_mode', $scheduled->catch_up_mode ?? 'skip') === 'skip' ? 'checked' : '' }}>
                            {{ __('Skip missed executions till next cycle') }} <small class="text-muted">({{ __('Recommended') }})</small>
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="catch_up_mode" value="catch_up_last" {{ old('catch_up_mode', $scheduled->catch_up_mode ?? 'skip') === 'catch_up_last' ? 'checked' : '' }}>
                            {{ __('Execute when possible even if delayed') }}
                        </label>
                    </div>
                    <p class="form-help">{{ __('What to do with missed executions when the server is down or paused.') }}</p>
                </div>

                {{-- Start / End Dates --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group{{ $errors->has('start_date') ? ' has-error' : '' }}" id="fg-start-date">
                            <label for="start_date" class="control-label">{{ __('Start Date') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                            <input type="date" name="start_date" id="start_date" class="form-control"
                                   value="{{ old('start_date', $scheduled->start_date ? $scheduled->start_date->format('Y-m-d') : '') }}">
                            <small class="help-block">{{ __('Leave empty to start immediately') }}</small>
                            @if ($errors->has('start_date'))
                                <span class="help-block text-danger">{{ $errors->first('start_date') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group{{ $errors->has('end_date') ? ' has-error' : '' }}" id="fg-end-date">
                            <label for="end_date" class="control-label">{{ __('End Date') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                            <input type="date" name="end_date" id="end_date" class="form-control"
                                   value="{{ old('end_date', $scheduled->end_date ? $scheduled->end_date->format('Y-m-d') : '') }}">
                            <small class="help-block">{{ __('Leave empty for no expiration') }}</small>
                            @if ($errors->has('end_date'))
                                <span class="help-block text-danger">{{ $errors->first('end_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Message Body --}}
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group{{ $errors->has('body') ? ' has-error' : '' }}" id="fg-body" style="clear: both; display: block;">
                            <label for="body" class="control-label">{{ __('Message') }} <span class="text-danger">*</span></label>
                            <div class="scheduled-editor-wrapper">
                                @php
                                    $proModule   = \Module::find('Extended Editor');
                                    $isProActive = ($proModule && $proModule->enabled());
                                @endphp
                                @if ($isProActive)
                                    <div id="is-pro-active" style="display:none;"></div>
                                    @include('partials/editor')
                                    <textarea name="body" id="body" class="form-control js-pro-editor" rows="12">{{ old('body', $scheduled->body) }}</textarea>
                                @else
                                    <textarea name="body" id="body" class="form-control js-std-editor" rows="12">{{ old('body', $scheduled->body) }}</textarea>
                                @endif
                            </div>
                            @if ($errors->has('body'))
                                <span class="help-block">{{ $errors->first('body') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Available variables --}}
                <div class="alert alert-info">
                    <strong>{{ __('Available Variables') }}:</strong>
                    <code>{customer_name}</code>, <code>{date}</code>, <code>{time}</code>, <code>{mailbox_name}</code>, <code>{user_name}</code>
                </div>

                {{-- Submit --}}
                <div class="form-group margin-top">
                    <button type="submit" class="btn btn-primary" id="scheduledconv-submit">
                        <i class="glyphicon glyphicon-ok"></i> {{ __('Update Scheduled Conversation') }}
                    </button>
                    <a href="{{ route('scheduledconversations.index', ['mailbox_id' => $mailbox->id]) }}" class="btn btn-link">
                        {{ __('Cancel') }}
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection

@section('javascript')
    @parent
    $(document).ready(function() {

        // Show/hide catch_up_mode field based on frequency type.
        // For 'once': hide the field and force catch_up_last (one-time executions should always run).
        // For recurring types: show the field normally.
        function updateCatchUpMode() {
            var freqType = $('#frequency_type').val();

            // Weekly: at least one day must be selected
            if (freqType === 'weekly') {
                if ($('input[name="weekly_days[]"]:checked').length === 0) {
                    errors.push('{{ __('Please select at least one day of the week.') }}');
                }
            }

            if (freqType === 'once') {
                $('#catch_up_mode_group').hide();
                $('input[name="catch_up_mode"]').prop('checked', false);
                $('input[name="catch_up_mode"][value="catch_up_last"]').prop('checked', true);
            } else {
                $('#catch_up_mode_group').show();
            }
        }
        $('#frequency_type').on('change', updateCatchUpMode);
        updateCatchUpMode(); // Run on page load

        // Hide server-side error block as soon as user starts correcting anything
        $('#scheduledconv-form').one('change keyup', 'input, select, textarea', function() {
            $('.alert-danger:not(#client-errors)').fadeOut(300);
        });

        // Initialize Summernote editor
        setTimeout(function() {
            initScheduledConvEditor();
        }, 500);

        // -------------------------------------------------------
        // Helpers
        // -------------------------------------------------------
        var MAX_YEAR = new Date().getFullYear() + 20;
        var MIN_YEAR = new Date().getFullYear();

        function isValidYear(dateStr) {
            if (!dateStr) return true;
            var year = parseInt(dateStr.substring(0, 4));
            return year >= MIN_YEAR && year <= MAX_YEAR;
        }

        function isDatetimeInPast(dateStr, timeStr) {
            if (!dateStr || !timeStr) return false;
            return new Date(dateStr + 'T' + timeStr) <= new Date();
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function getBodyContent() {
            var $editor = $('.js-pro-editor').length ? $('.js-pro-editor') : $('.js-std-editor');
            try {
                return $editor.summernote('isEmpty') ? '' : $editor.summernote('code');
            } catch(ex) {
                return $('#body').val();
            }
        }

        // -------------------------------------------------------
        // Submit validation — validate all fields before sending
        // Errors are shown in order of appearance in the form:
        // subject → destination → frequency fields → start/end date → body
        // -------------------------------------------------------
        $('#scheduledconv-form').on('submit', function(e) {
            var errors = [];

            // 1. Subject
            if ($.trim($('#subject').val()) === '') {
                errors.push('{{ __('The subject field is required.') }}');
                $('#fg-subject').addClass('has-error');
            }

            // 2. Destination
            var destType = $('input[name="destination_type"]:checked').val();
            if (destType === 'customer' && $.trim($('#destination_customer').val()) === '') {
                errors.push('{{ __('Please select a customer.') }}');
            }
            if (destType === 'email') {
                var emailVal = $.trim($('#destination_email').val());
                if (emailVal === '') {
                    errors.push('{{ __('Please enter an email address.') }}');
                } else if (!isValidEmail(emailVal)) {
                    errors.push('{{ __('Please enter a valid email address.') }}');
                }
            }

            // 3. Frequency fields
            var freqType = $('#frequency_type').val();

            // Weekly: at least one day must be selected
            if (freqType === 'weekly') {
                if ($('input[name="weekly_days[]"]:checked').length === 0) {
                    errors.push('{{ __('Please select at least one day of the week.') }}');
                }
            }

            if (freqType === 'once') {
                var onceDate = $('#once_date').val();
                var onceTime = $('#once_time').val();
                if (!onceDate) {
                    errors.push('{{ __('The date is required for a one-time scheduled conversation.') }}');
                    $('#fg-once-date').addClass('has-error');
                } else if (!isValidYear(onceDate)) {
                    errors.push('{{ __('Please enter a valid year for the date.') }}');
                    $('#fg-once-date').addClass('has-error');
                }
                if (!onceTime) {
                    errors.push('{{ __('The time is required for a one-time scheduled conversation.') }}');
                    $('#fg-once-time').addClass('has-error');
                }
                if (onceDate && onceTime && isValidYear(onceDate) && isDatetimeInPast(onceDate, onceTime)) {
                    errors.push('{{ __('The scheduled date and time must be in the future.') }}');
                    $('#fg-once-date').addClass('has-error');
                    $('#fg-once-time').addClass('has-error');
                }
            }

            // 4. Start / End date coherence
            var startDate = $('#start_date').val();
            var endDate   = $('#end_date').val();
            if (startDate && !isValidYear(startDate.substring(0, 10))) {
                errors.push('{{ __('Please enter a valid year for the start date.') }}');
                $('#fg-start-date').addClass('has-error');
            } else if (startDate && new Date(startDate) < new Date()) {
                errors.push('{{ __('The start date cannot be in the past.') }}');
                $('#fg-start-date').addClass('has-error');
            }
            if (endDate && !isValidYear(endDate.substring(0, 10))) {
                errors.push('{{ __('Please enter a valid year for the end date.') }}');
                $('#fg-end-date').addClass('has-error');
            } else if (endDate && new Date(endDate) <= new Date()) {
                errors.push('{{ __('The end date must be in the future.') }}');
                $('#fg-end-date').addClass('has-error');
            } else if (startDate && endDate && new Date(endDate) <= new Date(startDate)) {
                errors.push('{{ __('The end date must be after the start date.') }}');
                $('#fg-end-date').addClass('has-error');
            }

            // 5. Body (last field in the form)
            var bodyContent = getBodyContent();
            if ($.trim(bodyContent) === '' || bodyContent === '<p><br></p>') {
                errors.push('{{ __('The message body cannot be empty.') }}');
                $('#fg-body').addClass('has-error');
            }

            if (errors.length > 0) {
                e.preventDefault();
                var html = '<div class="alert alert-danger" id="client-errors"><strong>{{ __('Please correct the following errors:') }}</strong><ul class="margin-top-sm margin-bottom-0">';
                $.each(errors, function(i, msg) { html += '<li>' + msg + '</li>'; });
                html += '</ul></div>';
                $('#client-errors').remove();
                $('#scheduledconv-form').prepend(html);
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        });

    });
@endsection
