# Known Issues and TODOs

## Active Issues

### ~~Google Custom Search returning 403~~ (RESOLVED 2026-03-29)

Replaced with SerpAPI (`modules/serpapi.php`). Returns real Google results as structured JSON. 250 free searches/month. Key stored in `config/api_keys.php` under `serpapi.api_key`.

---

### HIBP using test key only

The HaveIBeenPwned API is configured with a test key that only works with `@hibp-integration-tests.com` email addresses. A paid key ($3.50/month) is needed for real email lookups.

**Impact:** Breach lookups only work with HIBP test accounts, not real emails.
**Fix:** Purchase a paid HIBP API key and update `config/api_keys.php`.

---

### Gemini free tier rate limit

The Gemini API (gemini-2.5-flash) is on the free tier, which allows approximately 20 requests/day.

**Impact:** AI Intelligence Briefing will fail after ~20 searches per day.
**Fix:** Upgrade to a paid Gemini tier or implement client-side rate tracking/caching.

---

### Username OSINT may have false positives

The Username OSINT module checks 28 platforms via HTTP GET requests and infers profile existence from HTTP status codes and body content markers. Some platforms may return 200 for non-existent profiles, redirect to login pages, or block requests.

**Impact:** Some reported platform matches may be false positives. Instagram, Facebook, LinkedIn especially may block or redirect.
**Fix:** Improve body content checks for each platform. Consider headless browser verification for high-value platforms.

---

### Duplicate database configuration files

Two database config files exist:
- `api/config.php` -- Legacy config
- `config/database.php` -- Current intended config

**Impact:** Confusion about which file is authoritative.
**Fix:** Remove `api/config.php` and update any references.

---

## Resolved Issues

### Phase 4 (2026-03-28)

- XSS vulnerabilities in dashboard onclick handlers fixed (event delegation)
- SQL LIKE wildcard injection in history search fixed
- HIBP password check crash on malformed lines fixed
- Export endpoint no longer leaks internal error details
- Database.php now uses charset, disables emulated prepares, catches connection errors
- Button loading states added to Save/Watch/Edit actions
- Hardcoded rgba colors replaced with CSS variables for light theme compatibility
- Source error bar no longer confusingly lists all sources in parentheses
- "Not found" results no longer counted as source failures
- loadingSkeleton variable scope fixed (was declared after first use)

### Phase 3

- Phone query type implemented
- Orchestrator 30s time budget with per-source timing
- Name search expanded with Username OSINT

### Phase 2

- 14 bugs fixed (see Phase 2 status in phase-plan.md)

---

## Minor TODOs

- Clean up legacy `api/Services/` files (AIWorkerService.php, HaveIBeenPwnedService.php)
- Drop unused legacy DB tables (ai_reports, breach_results, scans)
- Add `searchByName()` method to GitHub module (currently uses inline curl in orchestrator)
- Client-side rate limiting warnings for VirusTotal (4 req/min) and Gemini (20 req/day)
- No authentication -- add before any public deployment
- No rate limiting on API endpoints -- add before deployment
- CORS is `*` -- restrict to specific origins for deployment
