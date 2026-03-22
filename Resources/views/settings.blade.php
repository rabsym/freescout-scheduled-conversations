<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    {{-- Required dummy field for FreeScout settings system --}}
    <input type="hidden" name="settings[dummy]" value="1" />

    {{-- All users can view --}}
    <div class="form-group">
        <label class="col-sm-2 control-label">
            {{ __('All users can view Scheduled Conversations') }}
        </label>
        <div class="col-sm-6">
            <div class="controls">
                <div class="onoffswitch-wrap">
                    <div class="onoffswitch">
                        <input type="hidden" name="settings[scheduledconversations.all_users_can_view]" value="0" />
                        <input type="checkbox"
                            name="settings[scheduledconversations.all_users_can_view]"
                            value="1"
                            {{ !empty($settings['scheduledconversations.all_users_can_view']) ? 'checked' : '' }}
                            id="all_users_can_view"
                            class="onoffswitch-checkbox">
                        <label class="onoffswitch-label" for="all_users_can_view"></label>
                    </div>
                </div>
            </div>
            <p class="form-help">
                {{ __('When enabled, all users with mailbox access can view scheduled conversations (read-only). When disabled, only users with the manage permission can access them.') }}
            </p>
        </div>
    </div>

    {{-- Process frequency --}}
    <div class="form-group">
        <label for="process_frequency" class="col-sm-2 control-label">
            {{ __('Scheduler Frequency') }}
        </label>
        <div class="col-sm-6">
            <select name="settings[scheduledconversations.process_frequency]" id="process_frequency" class="form-control input-sized">
                <option value="1"  {{ ($settings['scheduledconversations.process_frequency'] ?? 5) == 1  ? 'selected' : '' }}>{{ __('Every minute') }}</option>
                <option value="5"  {{ ($settings['scheduledconversations.process_frequency'] ?? 5) == 5  ? 'selected' : '' }}>{{ __('Every 5 minutes') }} ({{ __('Recommended') }})</option>
                <option value="15" {{ ($settings['scheduledconversations.process_frequency'] ?? 5) == 15 ? 'selected' : '' }}>{{ __('Every 15 minutes') }}</option>
            </select>
            <p class="form-help">
                {{ __('How often the scheduler checks for pending conversations to process. Changes take effect on the next scheduler run.') }}
            </p>
        </div>
    </div>

    {{-- Save button --}}
    <div class="form-group margin-top margin-bottom-0">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                <i class="glyphicon glyphicon-ok"></i> {{ __('Save Settings') }}
            </button>
        </div>
    </div>

</form>
