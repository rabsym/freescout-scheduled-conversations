# Changelog — Scheduled Conversations for FreeScout

All notable changes to this module are documented here.

---

## v1.7.0 — 23 March 2026

### Added
- **Read-only view** — new view mode accessible to all users with `canView()` permission. Shows the full scheduled conversation configuration in a disabled form (no editing possible). Accessible via the eye icon (👁) in the index table — visible to all users including those without manage permission
- **Clear History** — administrators can now clear the execution log for a scheduled conversation directly from the History view. A confirmation dialog is shown before deletion
- **New date variables & subject support** — added `{date_eu}` (DD/MM/YYYY) and `{date_us}` (MM/DD/YYYY). All variables including existing ones can now be used in the message subject as well as the body

### Changed
- **Frequency display** enriched with configuration details:
  - Monthly → "Monthly (day 15)"
  - Monthly (nth weekday) → "Monthly (nth weekday) (Last Sunday)"
  - Yearly → "Yearly (21 March)"
- **`@version` tag removed** from all PHP file headers — version is now maintained only in `version.txt` and `module.json`
- **Index action buttons** reordered to: View, Edit, History, Delete
- **Available variables** in create and edit forms now show `{date_eu}` and `{date_us}`



---

## v1.6.0 — 22 March 2026

### Added
- **Weekly multi-day selection** — the weekly frequency type now supports selecting multiple days of the week via checkboxes (e.g. Mon/Wed/Fri, Tue/Thu, weekdays only). Previously only a single day was allowed
- **Circuit breaker** — if a scheduled conversation executes 3 or more times within 60 minutes, it is automatically paused after the current execution completes (the current message is still delivered). A "Auto-paused: execution loop detected" entry is written to the execution log. The conversation must be manually reactivated after reviewing the configuration
- **`days_of_week` array format** in `frequency_config` for weekly type (e.g. `{"days_of_week":[1,3,5],"time":"09:00"}`)

### Changed
- **`calculateNextRun()`** for weekly — now finds the nearest next day from the selected days array
- **`previewNextRun()`** for weekly — same logic with full validation of the days array
- **`calculateInitialNextRun()`** in controller — same logic for initial scheduling
- **Index frequency display** — weekly now shows selected days abbreviated: "Weekly (Mon, Wed, Fri)"
- **`frequency_config` change detection** in `update()` — now uses `json_encode()` comparison to reliably detect changes (e.g. changing only the time without changing the day)

### Compatibility
- Existing weekly scheduled conversations stored with the legacy `day_of_week` (single integer or string) format are **fully compatible** with v1.6.0 without any data migration. The code reads both formats transparently. The format is only updated to `days_of_week` array when a conversation is edited and saved

---

## v1.5.0 — 21 March 2026

### Added
- **Spanish localization** (`es.json`) covering all UI strings, validation messages, and settings labels
- **Pre-flight config validation** via `previewNextRun()` — validates `frequency_config` before executing a scheduled conversation to prevent the pattern of a conversation being created successfully but `next_run_at` failing to update (which caused infinite execution loops)
- **Catch block for `calculateNextRun()`** — if next-run calculation fails after execution, `next_run_at` is advanced by one frequency cycle (day/week/month/year) to prevent infinite loops, and the error is logged to `scheduled_conversation_logs`
- **`\Throwable` catch** instead of `\Exception` to capture PHP type errors (e.g. `TypeError` from invalid `frequency_config` values)
- **Monthly day overflow handling** — if the configured day (e.g. 31) does not exist in the target month, the last available day is used. Subsequent months use the original configured day
- **`[not available]` substitution** for dynamic variables that have no value for the current destination type
- **File headers** with description, author and version on all PHP source files
- **`FUNDING.yml`** for GitHub Sponsors integration

### Changed
- **"Monthly (Ordinal)"** renamed to **"Monthly (nth weekday)"** throughout all views, entity display attributes, and translations
- **`catch_up_mode` default** changed to `skip` (recommended) in the create form
- **`catch_up_mode` field** hidden and forced to `catch_up_last` when frequency is `once` (one-time messages should always execute if possible)
- **`start_date` / `end_date`** inputs changed from `datetime-local` to `date` — start date is stored as `00:00:00`, end date as `23:59:59`
- **`next_run_at` recalculation** in `update()` — now also recalculates if the stored value is in the past (e.g. reactivating a paused conversation)
- **Validation errors** now shown consistently via submit handler only (no blur-based validation) to avoid inconsistent UX when fields lose focus during form submission
- **Module version** reset to `1.5.0` to align with public release versioning

### Fixed
- **`canView()` in controller** — `index()` and `history()` now use `canView()` instead of `canManage()`, allowing read-only users to access the list and history
- **Actions column in index** — History button visible to all `canView()` users; Edit and Delete restricted to `canManage()` users
- **`Module::getOption` / `Module::setOption`** replaced with `Option::get` / `Option::set` throughout — FreeScout stores settings in the `options` table, not a module-specific table
- **`loadTranslationsFrom`** replaced with `loadJsonTranslationsFrom` to use FreeScout's JSON-based translation system
- **`day_of_week` defensive handling** — accepts both integer (new format) and string (legacy format) values in `calculateNextRun()` and `previewNextRun()`

---

## v1.4.0 — 20 March 2026

### Added
- **Extended Editor integration** — auto-detects the Extended Editor module and uses it for message composition when available; falls back to standard Summernote otherwise
- **Execution History view** — per-conversation log showing date, status, recipient, linked conversation, and error message for each execution attempt
- **Success rate display** in Execution History (e.g. "3 / 5 (60%)")
- **Error details in logs** — error message includes file path and line number for easier debugging

### Changed
- **Index table** — added ID, Created by, and Created at columns; destination column now resolves customer ID to email address
- **Index ordering** — Active → Paused → Expired, then by `next_run_at`, then by `created_at`
- **History view** — mailbox name now shown with email in parentheses (e.g. "AA Dpto. Informática (informatica@unielectrica.com)")
- **Sidebar fix** — `mailboxes.menu_current_route` filter ensures mailbox switcher links work correctly from history, edit, and create views

---

## v1.3.0 — 16 March 2026

### Added
- **Settings page** integrated into FreeScout's native admin settings system (Manage → Settings → Scheduled Conversations)
- **"All users can view" toggle** — when enabled, all mailbox users can view scheduled conversations in read-only mode; when disabled, only users with the manage permission can access them
- **Scheduler frequency selector** — configurable from the Settings page: every 1, 5, or 15 minutes (default: 5)

### Changed
- Scheduler frequency now read from `options` table at runtime (configurable without code changes)

---

## v1.2.0 — 12 March 2026

### Added
- **Internal destination redesign** — internal conversations are now created using `Thread::TYPE_CUSTOMER` which does not trigger SMTP sending; the mailbox email is used as the customer identity
- **Notification support for internal conversations** — `CustomerCreatedConversation` event is fired manually after creation, followed by `Subscription::processEvents()` (required in console context since `TerminateHandler` middleware does not run in Artisan commands)
- **Missed execution handling** — `catch_up_mode` field with two options: skip missed executions until the next cycle, or execute when possible even if delayed; threshold is 120 minutes
- **`STATUS_CLOSED`** for email and customer destination types — conversations sent automatically are marked as closed since no agent action is expected


---

## v1.1.0 — 7 March 2026

### Added
- **Monthly (nth weekday)** frequency type — execute on the nth occurrence of a weekday in a month (e.g. "first Monday", "last Friday")
- **Yearly** frequency type — execute once per year on a specific month and day
- **`catch_up_mode`** field introduced (skip / catch_up_last)
- **`start_date` / `end_date`** support — conversations can be restricted to a validity period

### Changed
- RESTful routing conventions adopted
- Conditional JS loading — module JS only loads on module pages

---

## v1.0.0 — 1 March 2026

### Initial Release

- Basic CRUD for scheduled conversations (create, edit, delete, toggle active/paused)
- **Frequency types**: Once, Daily, Weekly, Monthly
- **Destination types**: Internal, Customer (FreeScout customer), Email (free-form address)
- **Dynamic variables**: `{customer_name}`, `{date}`, `{time}`, `{mailbox_name}`, `{user_name}`
- Permission system: `scheduledconversations.manage` permission
- Mailbox settings menu integration
- Background processing via Artisan command: `scheduledconversations:process`
- Execution logs stored in `scheduled_conversation_logs` table
