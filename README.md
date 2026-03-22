# Scheduled Conversations for FreeScout

Schedule and automate recurring messages with flexible frequencies, customizable destinations, and detailed execution tracking.

## Features

- **6 Frequency Types**: Once, Daily, Weekly, Monthly, Monthly (nth weekday) (e.g., "first Monday"), Yearly
- **3 Destination Types**: Internal, Customers, Email addresses
- **Execution History**: Track all scheduled conversation runs with success rates and detailed logs
- **Dynamic Variables**: Personalize messages with `{customer_name}`, `{date}`, `{time}`, etc.
- **Permission System**: Granular control over who can view and manage scheduled conversations
- **Start/End Dates**: Set validity periods for recurring conversations
- **Missed Execution Handling**: Choose between skipping or catching up missed executions
- **Settings Page**: Configure visibility and scheduler frequency from FreeScout's admin panel
- **Extended Editor Support**: Integrates with the Extended Editor module when available
- **Circuit Breaker**: Auto-pauses scheduled conversations that are stuck in execution loops to prevent mailbox flooding
- **Localization**: Available in English and Spanish

## Requirements

- FreeScout >= 1.8.0
- PHP >= 7.4
- Laravel Scheduler configured (cron job)

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/rabsym/freescout-scheduled-conversations/releases)
2. Extract to `Modules/ScheduledConversations/`
3. Activate the module in FreeScout's Modules page — migrations run automatically

## Configuration

### Laravel Scheduler

Ensure your cron is configured to run the Laravel Scheduler every minute:

```bash
* * * * * cd /path-to-freescout && php artisan schedule:run >> /dev/null 2>&1
```

The module processes scheduled conversations every 5 minutes by default. This can be changed in **Manage → Settings → Scheduled Conversations**.

### Permissions

Go to **Manage → Users → [User] → Permissions** and enable:
- **"Users are allowed to manage scheduled conversations"** — allows creating, editing and deleting

Visibility for all users can be toggled from the module's Settings page.

## Usage

1. Navigate to any mailbox
2. Click the ⚙️ settings icon in the sidebar
3. Select **"Scheduled Conversations"**
4. Click **"+ New Scheduled Conversation"** and configure:
   - Subject and body (with optional dynamic variables)
   - Destination type: Internal, Customer, or Email address
   - Frequency and schedule
   - Optional start/end dates
   - Missed execution behaviour

## Supported Frequencies

| Frequency | Description |
|-----------|-------------|
| **Once** | Single execution at a specific date and time |
| **Daily** | Every day at a specified time |
| **Weekly** | Every week on a specified day and time |
| **Monthly** | Every month on a specified day and time |
| **Monthly (nth weekday)** | Every month on the nth weekday (e.g. "first Monday", "last Friday") |
| **Yearly** | Every year on a specified date and time |

## Destination Types

| Type | Description |
|------|-------------|
| **Internal** | Creates a conversation inside the mailbox. No email is sent. All mailbox agents subscribed to new conversation notifications will be notified. |
| **Customer** | Sends an email to a FreeScout customer via SMTP. Conversation is marked as Closed. |
| **Email** | Sends an email to any email address. If the address already exists as a FreeScout customer, that customer is reused. |

## Dynamic Variables

Use these variables in subject and body:

| Variable | Description |
|----------|-------------|
| `{customer_name}` | Customer's full name (destination type: customer only) |
| `{date}` | Current date (Y-m-d) |
| `{time}` | Current time (H:i) |
| `{mailbox_name}` | Name of the source mailbox |
| `{user_name}` | Full name of the user who created the scheduled conversation |

Variables with no value for the current destination type are replaced with `[not available]`.

## License

MIT License — See LICENSE.txt

## Author

**Raimundo Alba**
- GitHub: [@rabsym](https://github.com/rabsym)

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/rabsym/freescout-scheduled-conversations/issues) page.

---

## Changelog

### v1.6.0 — 22 March 2026
Weekly multi-day selection (Mon/Wed/Fri, Tue/Thu, etc.). Circuit breaker to auto-pause conversations stuck in execution loops.

### v1.5.0 — 21 March 2026
Field validation, coherence checks, and Spanish localization. Improved error handling with pre-flight config validation to prevent execution loops.

### v1.4.0 — 20 March 2026
Extended Editor integration for rich message composition. Execution history view with success rates, per-run logs, and error details.

### v1.3.0 — 16 March 2026
Settings page under FreeScout's admin panel. Options to control visibility for all users and configure scheduler frequency (1, 5, or 15 minutes).

### v1.2.0 — 12 March 2026
Internal destination type redesigned to create conversations without sending SMTP email. Full notification support for mailbox agents via FreeScout's event system.

### v1.1.0 — 7 March 2026
Added Monthly (nth weekday) and Yearly frequency types. Introduced missed execution handling with skip and catch-up modes.

### v1.0.0 — 1 March 2026
Initial release. Basic CRUD for scheduled conversations with Once, Daily, Weekly, and Monthly frequencies. Internal, Customer, and Email destination types.
