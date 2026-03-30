# System Architecture Overview

Vignette is an OSINT (Open Source Intelligence) platform that aggregates public data from multiple sources, merges it into a unified profile, computes risk scores, and presents results on a web dashboard.

## Pipeline Architecture

Every search flows through a five-stage pipeline:

```
User Input --> Orchestrator --> Parallel Workers --> Aggregator --> Profiler --> Dashboard
```

| Stage | Component | Location | Responsibility |
|-------|-----------|----------|----------------|
| 1. Input | `SearchController` | `api/SearchController.php` | Validates input, saves search to DB, triggers pipeline |
| 2. Orchestrator | `Orchestrator` | `core/orchestrator.php` | Determines which modules to call based on query type, dispatches tasks within 30s time budget |
| 3. Workers | Module classes | `modules/*.php` | Call external APIs, return raw data (8s timeout per module) |
| 4. Aggregator | `Aggregator` | `core/aggregator.php` | Merges results from all sources, deduplicates fields |
| 5. Profiler | `Profiler` | `core/profiler.php` | Builds unified profile object, computes risk score and risk factors |
| 6. Relationships | `RelationshipMapper` | `core/relationships.php` | Queries DB for cross-search connections (7 relationship types) |

The `SearchController` ties everything together: it saves the search record, runs the orchestrator, stores raw source data, aggregates, profiles, stores the final profile, queries relationships, extracts per-source timings, and returns JSON to the frontend.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.x (procedural + class-based) |
| Database | MySQL 8 via PDO |
| Frontend | Vanilla JavaScript, HTML, CSS |
| Local Dev | XAMPP (Apache + MySQL + PHP) |
| HTTP Client | cURL (built into PHP), cURL multi for parallel requests |
| External APIs | HIBP v3, GitHub REST API v3, IPInfo.io, VirusTotal v3, Google Gemini (gemini-2.5-flash), SerpAPI (Google Search), Gravatar, PhoneInfo (local) |
| Network | Direct socket connections (WHOIS), PHP stream_socket_client for SSL inspection |

## File Structure

```
vignette/
|-- index.php                     # Landing page / search form
|-- dashboard.php                 # Results dashboard
|
|-- api/
|   |-- index.php                 # API entry point (routes requests)
|   |-- routes.php                # Route definitions
|   |-- SearchController.php      # Main search endpoint (POST /api/?route=search)
|   |-- ChatController.php        # AI chat endpoint
|   |-- Database.php              # PDO database connection wrapper
|   |-- export.php                # PDF/print export endpoint (Phase 3)
|   |-- config.php                # Legacy DB config (to be consolidated)
|   |-- intelligence/
|   |   |-- report.php            # Intelligence report generation
|   |-- Services/
|       |-- AIWorkerService.php   # AI worker integration (legacy)
|       |-- HaveIBeenPwnedService.php  # Legacy HIBP service (superseded by modules/hibp.php)
|       |-- RiskScoringService.php     # Risk score calculation
|
|-- config/
|   |-- database.php              # Active DB config (host, name, user, pass)
|   |-- api_keys.php              # API keys (gitignored)
|   |-- api_keys.example.php      # API keys template
|
|-- core/
|   |-- orchestrator.php          # Search task planner and dispatcher (30s time budget, per-source timing)
|   |-- aggregator.php            # Multi-source data merger
|   |-- profiler.php              # Unified profile builder + risk scoring
|   |-- relationships.php         # RelationshipMapper -- cross-search connection discovery (Phase 3)
|
|-- modules/
|   |-- hibp.php                  # HaveIBeenPwned module (HibpModule class)
|   |-- github.php                # GitHub module (GitHubModule class)
|   |-- ipinfo.php                # IPInfo module (IpInfoModule class)
|   |-- gemini.php                # Gemini AI module (GeminiModule class)
|   |-- whois.php                 # WHOIS module (WhoisModule class) -- socket-based, 80+ TLDs
|   |-- dns.php                   # DNS module (DnsModule class) -- records + email security scoring
|   |-- ssl.php                   # SSL module (SslModule class) -- cert inspection + chain viz
|   |-- gravatar.php              # Gravatar module (GravatarModule class) -- avatar + profile
|   |-- username_osint.php        # Username OSINT module (UsernameOsintModule class) -- 28 platforms
|   |-- virustotal.php            # VirusTotal module (VirusTotalModule class) -- threat intelligence
|   |-- serpapi.php               # SerpAPI module (SerpApiModule class) -- real Google results via SerpAPI
|   |-- google_search.php         # Google Custom Search module (deprecated, replaced by serpapi.php)
|   |-- phone_info.php            # Phone number module (PhoneInfoModule class) -- 80+ country prefixes (Phase 3)
|
|-- database/
|   |-- schema.sql                # Full database schema
|
|-- frontend/
|   |-- css/
|   |   |-- style.css             # Dashboard and page styles
|   |-- js/
|       |-- app.js                # Frontend search logic and DOM rendering
|
|-- docs/                         # This documentation
```

## Data Flow: Search to Results

A concrete walkthrough of what happens when a user searches for an email address:

1. **User submits search** -- The frontend (`app.js`) sends a POST request to `/api/?route=search` with `{ "query_value": "user@example.com", "query_type": "email" }`.

2. **SearchController validates input** -- Checks that `query_value` and `query_type` are present and that `query_type` is one of: `name`, `email`, `phone`, `username`, `ip`, `domain`.

3. **Search saved to DB** -- A row is inserted into the `searches` table with the query value, type, and timestamp.

4. **Orchestrator plans tasks** -- `Orchestrator::planTasks()` checks the query type. For `email`, it plans tasks for: `haveibeenpwned` (breach lookup), `github` (email-to-user search), `gravatar` (avatar and profile), and `google_search` (web mentions).

5. **Modules execute** -- Each task closure is executed:
   - `HibpModule::checkBreaches()` calls the HIBP API, then `normalize()` standardizes the response.
   - `GitHubModule::searchByEmail()` finds GitHub users matching the email, then `fullLookup()` fetches profile + repos + events, then `normalize()` standardizes.
   - `GravatarModule::lookup()` fetches the Gravatar profile associated with the email.
   - `SerpApiModule::search()` finds web mentions of the email via SerpAPI (real Google results).

6. **Raw results stored** -- Each source's normalized result is saved to the `data_sources` table with the search ID.

7. **Aggregator merges** -- `Aggregator::merge()` iterates over all source results, extracting display name, avatar, location, bio, emails, usernames, social links, breaches, company, and IP data into a single merged array. Deduplication uses priority-based resolution for identity fields (GitHub=10, Gravatar=8, IPInfo=5, WHOIS=3), case-insensitive dedup for emails/usernames, breach dedup by name, and social link dedup by domain. Company is extracted from GitHub, WHOIS registrant org, and OV/EV SSL certificate org fields. Social links from Username OSINT are cross-pollinated into the profile.

8. **Profiler builds profile** -- `Profiler::build()` takes the merged data and produces the final profile object with sections: `query`, `identity`, `social_links`, `github`, `repos`, `breaches`, `ip_data`, `whois_data`, `dns_data`, `ssl_data`, `virustotal_data`, `username_results`, `web_mentions`, `phone_data`, `risk`, and `meta`. Risk score is computed based on multiple factors: breach count/severity, VPN/Tor/proxy detection, domain age, domain expiry, WHOIS privacy + young domain combos, SSL issues (expired/expiring/weak keys with EC vs RSA awareness), email security (missing SPF/DMARC), and VirusTotal malicious/suspicious flags.

9. **Profile stored** -- The profile is saved to the `profiles` table.

10. **Relationships mapped** -- `RelationshipMapper::findRelationships()` queries the DB for other searches that share common data points (emails, usernames, IPs, registrars, hosting providers, SSL CAs, or identity matches). Returns up to 7 relationship types with confidence levels.

11. **JSON response returned** -- The frontend receives `{ success: true, search_id: N, profile: {...}, relationships: [...], timings: {...} }` and renders the dashboard, including the Intelligence Timeline, Related Intelligence card, and per-source timing data.
