# Rahdana — MVP 0.1

Scope: secure account bootstrap, personal tasks, daily check-ins and activity summary.
PHP/PayaCore remains unchanged. No AI calls or scheduled jobs are implemented.

## Data ownership

Every task and check-in belongs to a user_id established by Authenticate middleware.
Never trust user_id from client input. The API returns JSON with `data` or `error`.

## Authentication

- Registration POST /api/v1/auth/register is disabled unless ALLOW_REGISTRATION=true.
- Login POST /api/v1/auth/login returns an opaque 256-bit bearer token, stored hashed in MySQL.
- Login throttle locks the supplied email identity after 5 failures for 15 minutes.
- Protected endpoints require Authorization: Bearer <token>.
- Logout POST /api/v1/auth/logout revokes the current token.
- Set HTTPS on production; do not store secrets in Git.
- Public registration is OFF by default. Before public deployment add IP-based limits, security monitoring and policies appropriate to your users.

## Core routes

| Method | Route | Purpose |
|---|---|---|
| GET | /api/v1/health | Health |
| POST | /api/v1/auth/register | Register (opt-in) |
| POST | /api/v1/auth/login | Login |
| POST | /api/v1/auth/logout | Revoke session |
| GET | /api/v1/me | Current user |
| GET | /api/v1/tasks | Filter by plan_date/status |
| POST | /api/v1/tasks | Create |
| GET | /api/v1/tasks/{id} | Read |
| PATCH | /api/v1/tasks/{id} | Update |
| DELETE | /api/v1/tasks/{id} | Delete |
| GET | /api/v1/checkins/latest | Latest daily report |
| POST | /api/v1/checkins | Save report for ISO calendar date |
| GET | /api/v1/stats/summary | Count completed/pending tasks |

## Example check-in

```json
{
  "date": "2026-10-31",
  "energy": 2,
  "availableHours": 2,
  "completion": "partial",
  "note": ""
}
```

Date is Gregorian ISO for storage; display Jalali dates at the UI level.
The server stores available_minutes = 120 and permits one check-in per user/day.
For now, check-in analytics describe data rather than automatically editing the plan.

## Setup

1. Use PHP 8.3+ with pdo_mysql and mbstring. Configure only `public/` as web root.
2. `composer install`
3. Create an empty MySQL DB and provide DB_* environment variables.
4. `php bin/migrate.php` (take a backup before migrations on a populated DB).
5. In a **private local** environment set ALLOW_REGISTRATION=true to create an account; reset to false afterward.
6. Start `php -S 127.0.0.1:8000 -t public public/index.php` and use /api/v1 endpoints.

## Not yet implemented

Projects, study timer/log, detailed planner, AI memory, reminders, fully responsive production UI.
Do not expose MVP publicly until deployment security, rate limiting and integration checks are completed.
