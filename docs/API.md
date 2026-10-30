# Rahdana REST API — v0.1

Base URL: `/api/v1`. JSON UTF-8. No auth required for /health.
Other routes: login + optionally register public; all remaining routes use Bearer token.

## Onboarding (local only)

Enable `ALLOW_REGISTRATION=true` in your local web server environment, then POST:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/register -H 'Content-Type: application/json' \
 -d '{"name":"Demo","email":"demo@example.test","password":"a-long-unique-password","timezone":"Asia/Tehran"}'
```

Turn registration back OFF afterwards; it is not appropriate to leave it open on a public server without additional safeguards.

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login -H 'Content-Type: application/json' \
 -d '{"email":"demo@example.test","password":"a-long-unique-password"}'
```

Use the returned token in an `Authorization: Bearer ...` header. Keep the token secret.

## Personal tasks

```bash
curl -X POST http://127.0.0.1:8000/api/v1/tasks -H "Authorization: Bearer $TOKEN" \
 -H 'Content-Type: application/json' \
 -d '{"title":"۳۰ دقیقه مطالعه","priority":"medium","estimated_minutes":30,"plan_date":"2026-10-31"}'
```

Tasks accept `title, notes, priority(low|medium|high), status(pending|in_progress|done), estimated_minutes(5..1440), due_date, plan_date, start_time(HH:MM)`.
`GET /tasks?plan_date=YYYY-MM-DD&status=pending` lists up to 200 tasks. `PATCH /tasks/{id}` changes named fields. `DELETE /tasks/{id}` removes one task.
Dates in API are always Gregorian ISO dates.

## Daily check-in

```bash
curl -X POST http://127.0.0.1:8000/api/v1/checkins -H "Authorization: Bearer $TOKEN" \
 -H 'Content-Type: application/json' \
 -d '{"date":"2026-10-31","energy":2,"availableHours":2,"completion":"partial","note":""}'
```

`GET /checkins/latest`, `GET /checkins?from=YYYY-MM-DD&to=YYYY-MM-DD` return check-in records.

`GET /stats/summary?date=YYYY-MM-DD` returns task counts and the daily report. `GET /exports/context` returns a portable personal JSON snapshot (last 90 check-ins and 200 tasks). There are no outbound AI calls.

## Response shape

Successful: `{"data":...}`.
Error: `{"error":{"code":"VALIDATION_ERROR","message":"..."}}`.
Common statuses: 200, 201, 204, 400, 401, 403, 404, 405, 409, 413, 422, 429, 500.

## Security limits

- Requests are limited to 1 MiB by PayaCore.
- User IDs come from verified tokens, never request bodies.
- `/api/v1/auth/login` includes a per-identity lockout. Public deployment also requires IP/device limits and monitoring.
- Tokens are stored as SHA-256 hashes; responses should not be cached by clients or intermediaries.
- Browser clients should avoid long-lived token storage. This MVP is for local development, not Internet exposure yet.
