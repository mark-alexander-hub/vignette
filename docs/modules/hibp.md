# HIBP Module -- HaveIBeenPwned

Source: `modules/hibp.php`
Class: `HibpModule`

## Overview

Checks email addresses against the HaveIBeenPwned database of known data breaches. Also supports password breach checks using the k-anonymity model (password hashes are never sent over the network).

## API Details

| Property | Value |
|----------|-------|
| API | HaveIBeenPwned v3 |
| Base URL | `https://haveibeenpwned.com/api/v3` |
| Docs | https://haveibeenpwned.com/API/v3 |
| Auth | `hibp-api-key` header |
| Cost | $3.50/month (paid key required) |
| Rate Limit | ~10 requests/minute |
| User-Agent | `Vignette-OSINT-Platform` (required by HIBP) |

## Query Types Handled

| Query Type | Method Used |
|------------|-------------|
| `email` | `checkBreaches(email)` |

## Constructor

```php
$mod = new HibpModule(string $apiKey);
```

The API key is read from `config/api_keys.php` under `$keys['hibp']['api_key']`.

## Methods

### `checkBreaches(string $email): array`

Calls `GET /breachedaccount/{email}?truncateResponse=false` with the API key in the `hibp-api-key` header.

Returns:
- Array of breach objects on success (HTTP 200)
- Empty array if no breaches found (HTTP 404)
- `['error' => 'Rate limited -- retry after delay']` on HTTP 429
- `['error' => 'HIBP API returned status {code}']` on other errors

Timeout: 10 seconds.

### `checkPassword(string $password): int`

Checks if a password has appeared in any known breach using the Pwned Passwords API with k-anonymity. Only the first 5 characters of the SHA-1 hash are sent to the API.

Endpoint: `GET https://api.pwnedpasswords.com/range/{prefix}`

Returns the number of times the password was seen in breaches (0 if not found). No API key required for this endpoint.

Timeout: 5 seconds.

### `normalize(array $breaches): array`

Converts raw HIBP breach data into Vignette's standard format.

**Error case:**

```json
{
  "source": "haveibeenpwned",
  "status": "error",
  "error": "...",
  "data": []
}
```

**Success case:**

```json
{
  "source": "haveibeenpwned",
  "status": "success",
  "breach_count": 3,
  "data": [
    {
      "name": "BreachName",
      "title": "Breach Title",
      "domain": "example.com",
      "breach_date": "2020-01-01",
      "added_date": "2020-03-15T00:00:00Z",
      "pwn_count": 1000000,
      "data_classes": ["Email addresses", "Passwords"],
      "is_sensitive": false,
      "is_verified": true,
      "logo_path": "https://..."
    }
  ]
}
```

## Integration

The orchestrator dispatches this module for `email` query types:

```php
// In core/orchestrator.php, planTasks()
case 'email':
    $tasks['haveibeenpwned'] = function () use ($queryValue) {
        $mod = new HibpModule($this->apiKeys['hibp']['api_key'] ?? '');
        $raw = $mod->checkBreaches($queryValue);
        return $mod->normalize($raw);
    };
```

The aggregator maps `haveibeenpwned` results into the `breaches` and `breach_count` fields of the merged profile.

## Current Key Status

The platform is currently using a **test API key** that only works with `@hibp-integration-tests.com` email addresses. A paid key ($3.50/month) is needed for real email lookups. The module handles missing/invalid keys gracefully by suppressing the warning (yellow warning cards are suppressed for config/billing errors).

## Notes

- The HIBP API **requires a paid API key** for production use. The current test key works only with HIBP's integration test accounts.
- The `checkPassword` method is available but not currently called by the orchestrator pipeline. It can be used for standalone password checks.
- HIBP enforces a User-Agent header -- the module sends `Vignette-OSINT-Platform`.
- Missing API keys are handled gracefully -- modules with missing keys return a `skipped` status rather than a hard error.
