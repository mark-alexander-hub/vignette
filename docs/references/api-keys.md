# API Keys Setup

All API keys are stored in `config/api_keys.php`, which is gitignored. A template with placeholder values is provided at `config/api_keys.example.php`.

## Setup

1. Copy the example file:

```bash
cp config/api_keys.example.php config/api_keys.php
```

2. Edit `config/api_keys.php` and replace the placeholder values with real keys.

3. The file returns a PHP array:

```php
return [
    'hibp' => [
        'api_key' => 'YOUR_HIBP_API_KEY',
    ],
    'github' => [
        'token' => 'YOUR_GITHUB_TOKEN',
    ],
    'ipinfo' => [
        'token' => 'YOUR_IPINFO_TOKEN',
    ],
    'google_search' => [
        'api_key' => 'YOUR_GOOGLE_API_KEY',
        'cx'      => 'YOUR_SEARCH_ENGINE_ID',
    ],
    'serpapi' => [
        'api_key' => 'YOUR_SERPAPI_KEY',
    ],
    'gemini' => [
        'api_key' => 'YOUR_GEMINI_API_KEY',
    ],
    'virustotal' => [
        'api_key' => 'YOUR_VIRUSTOTAL_API_KEY',
    ],
];
```

## API Reference Table

| API | Config Key | Cost | Rate Limit | Phase | Status | Signup URL |
|-----|-----------|------|------------|-------|--------|------------|
| HaveIBeenPwned v3 | `hibp.api_key` | $3.50/month | ~10 req/min | Phase 1 | Test key only | https://haveibeenpwned.com/API/Key |
| GitHub REST API v3 | `github.token` | Free | 5,000 req/hr (with token) | Phase 1 | Active | https://github.com/settings/tokens |
| IPInfo.io | `ipinfo.token` | Free tier | 50,000 req/month | Phase 1 | Active | https://ipinfo.io/signup |
| Google Gemini (gemini-2.5-flash) | `gemini.api_key` | Free tier | ~20 req/day | Phase 1 | Active | https://aistudio.google.com/app/apikey |
| VirusTotal v3 | `virustotal.api_key` | Free tier | 4 req/min, 500/day | Phase 2 | Active | https://www.virustotal.com/gui/join-us |
| SerpAPI (Google Search) | `serpapi.api_key` | Free tier (250 searches/mo) | 250 req/month free | Phase 5 | Active | https://serpapi.com/ |
| ~~Google Custom Search~~ | ~~`google_search.api_key`~~ | ~~Deprecated~~ | -- | Phase 2 | Replaced by SerpAPI | -- |

**No API key required:** WHOIS (direct socket), DNS (PHP built-in), SSL (stream_socket_client), Gravatar (public API, hash-based), Username OSINT (HTTP HEAD checks), PhoneInfo (local country prefix table, no external API).

## Phase 1 Keys

### GitHub Personal Access Token -- Active

1. Go to https://github.com/settings/tokens
2. Click "Generate new token (classic)"
3. Select scopes: no scopes needed for public data (but `read:user` gives slightly more data)
4. Copy the token into `config/api_keys.php` under `github.token`

Works without a token but rate-limited to 60 requests/hour instead of 5,000.

### HaveIBeenPwned API Key -- Test Key Only

1. Go to https://haveibeenpwned.com/API/Key
2. Purchase an API key ($3.50/month, billed via Stripe)
3. Copy the key into `config/api_keys.php` under `hibp.api_key`

Currently using a test key that only works with `@hibp-integration-tests.com` addresses. A paid key is needed for real email lookups.

### IPInfo Token -- Active

1. Go to https://ipinfo.io/signup
2. Create a free account
3. Copy the token from the dashboard into `config/api_keys.php` under `ipinfo.token`

Free tier allows 50,000 lookups/month, which is more than sufficient for development and light usage.

### Google Gemini -- Active

1. Go to https://aistudio.google.com/app/apikey
2. Create an API key
3. Add to `config/api_keys.php` under `gemini.api_key`

Uses the `gemini-2.5-flash` model (upgraded from `gemini-2.0-flash`). Free tier: approximately 20 requests/day. Thinking budget is disabled, max output tokens set to 1500. Was previously blocked on quota; now active.

## Phase 2 Keys

### VirusTotal -- Active (Free Tier)

1. Sign up at https://www.virustotal.com/gui/join-us
2. Get the API key from your profile
3. Add to `config/api_keys.php` under `virustotal.api_key`

Free tier: 4 requests/minute, 500 requests/day.

### SerpAPI (Google Search) -- Active

1. Sign up at https://serpapi.com/
2. Get your API key from the dashboard
3. Add to `config/api_keys.php` under `serpapi.api_key`

Free tier: 250 searches/month. Paid: $75/month for 5,000 searches. Returns real Google results as structured JSON. Replaced Google Custom Search API which was returning 403 errors.

### ~~Google Custom Search~~ (Deprecated)

Replaced by SerpAPI in Phase 5. The old module (`modules/google_search.php`) still exists but is no longer loaded by the orchestrator.

### Modules Without API Keys

The following Phase 2 modules require no API keys:

- **WHOIS** -- Direct socket connections to WHOIS servers (port 43)
- **DNS** -- Uses PHP's built-in `dns_get_record()` function
- **SSL** -- Uses PHP's `stream_socket_client` for direct certificate inspection
- **Gravatar** -- Public API using MD5 email hashes, no auth required
- **Username OSINT** -- HTTP HEAD requests to check profile existence on 20 platforms

### Phase 3 Modules Without API Keys

- **PhoneInfo** -- Local phone number validation and country detection using a built-in table of 80+ international dialing prefixes. No external API calls.

## Design Decisions

- Clearbit and FullContact were evaluated but skipped for legal/privacy reasons.
- Web scraping was skipped -- only official APIs and public data sources are used (100% legal OSINT).
- All modules handle missing API keys gracefully with yellow warnings suppressed for config/billing errors.

## Notes

- `config/api_keys.php` and `config/database.php` are listed in `.gitignore` and must never be committed.
- Modules with missing or empty API keys return a `skipped` status and are handled gracefully by the pipeline. Yellow warning cards are suppressed for known config/billing issues.
- The `SearchController` loads keys via `require` at construction time: `$this->apiKeys = require 'config/api_keys.php'`.
