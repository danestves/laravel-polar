# Migrating from v2.13.1 to v2.13.2

`v2.13.2` is a **bug fix release**. No required action.

## What's fixed

`LaravelPolar::ingestEvents()` (and by extension `$user->ingestUsageEvent()` / `$user->ingestUsageEvents()`) used to throw `Failed to ingest events` on every successful call, because the status check looked for HTTP `202` but Polar's `/v1/events/ingest` endpoint actually returns `200`.

The check is now any `2xx` is success:

```php
// Before:
if ($response->statusCode !== 202) { throw ... }

// After:
if ($response->statusCode < 200 || $response->statusCode >= 300) { throw ... }
```

This matches HTTP convention and is robust to Polar tweaking the exact code (e.g. `200` → `202` in the future).

## Required user actions

None. If you were wrapping `ingestUsageEvent` calls in try/catch to suppress the false-positive exception, you can remove the wrapper — the call now succeeds silently on the happy path.

If you weren't catching it, you were previously getting unexplained 500s in your logs. Those should stop.

## Notes

- Behavior on real failure (4xx/5xx from Polar) is unchanged — `Polar\Models\Errors\APIException` is still thrown, same shape as before.
- No public API changed. No signature changed.
