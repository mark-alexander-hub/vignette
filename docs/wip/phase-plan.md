# Phase Plan

Development roadmap for the Vignette OSINT platform.

---

## Phase 1 -- Core Pipeline

**STATUS: COMPLETE**

### Completed

- [x] Project directory structure (`api/`, `config/`, `core/`, `modules/`, `frontend/`, `database/`)
- [x] Database schema (`database/schema.sql`) -- all 8 tables defined
- [x] Dark OSINT search dashboard at `/vignette/` with 6 search types
- [x] HIBP module (`modules/hibp.php`) -- breach lookup + password check + normalize
- [x] GitHub module (`modules/github.php`) -- profile, repos, events, email search, full lookup + normalize
- [x] IPInfo module (`modules/ipinfo.php`) -- IP geolocation, VPN/proxy/Tor detection + normalize
- [x] Gemini AI module (`modules/gemini.php`) -- AI intelligence briefing (was quota-blocked, now active)
- [x] Orchestrator (`core/orchestrator.php`) -- query type routing, task planning, execution
- [x] Aggregator (`core/aggregator.php`) -- multi-source merge, deduplication
- [x] Profiler (`core/profiler.php`) -- unified profile builder, risk scoring, risk factor analysis
- [x] Search Controller (`api/SearchController.php`) -- full pipeline: validate, save, orchestrate, aggregate, profile, respond
- [x] API router (`api/index.php`)
- [x] Search dashboard (`dashboard.php`, `frontend/js/app.js`, `frontend/css/style.css`)
- [x] Search history endpoint and dashboard
- [x] Risk scoring service (`api/Services/RiskScoringService.php`)

---

## Phase 2 -- Extended Sources and Data Quality

**STATUS: COMPLETE**

### Completed

- [x] WHOIS, DNS, SSL, Gravatar, Username OSINT, VirusTotal, Google Custom Search modules
- [x] Enhanced risk scoring (breach severity, VPN/Tor, domain age, SSL, email security, VirusTotal)
- [x] Frontend cards for all modules
- [x] Aggregator dedup improvements
- [x] Unified profile view with summary stats bar
- [x] Gemini prompt updated with all data sources
- [x] 14 bug fixes (XSS, error leaks, toggleExpand, markdown, etc.)

---

## Phase 3 -- Intelligence Layer, Performance, and Export

**STATUS: COMPLETE**

### Completed

- [x] Orchestrator optimization -- 30-second total time budget, per-source timing
- [x] Relationship mapping -- 7 cross-search connection types with confidence levels
- [x] Timeline view -- chronological events from WHOIS, SSL, GitHub, breaches
- [x] PDF export -- printable HTML reports
- [x] Expanded name search -- Username OSINT with slugified name
- [x] Phone number intelligence -- validation, country detection, type classification

---

## Phase 4 -- UX Polish, Monitoring, and Audit

**STATUS: COMPLETE (2026-03-28)**

### Completed

- [x] Search history enhancements -- filter by type/risk/text, sort by date/risk, pagination (20/page)
- [x] Saved profiles with tagging -- save searches with label/notes/tags, CRUD API, dashboard tab with tag filters
- [x] Watchlist with re-check -- add queries to watchlist, toggle active/inactive, re-check runs fresh search and compares risk delta
- [x] Replay system -- click history items to replay stored results without re-fetching APIs
- [x] Dark/light theme toggle with localStorage persistence
- [x] Loading skeleton animation during searches
- [x] Mobile responsive improvements (filters, tabs, watchlist, modals)
- [x] Toast notifications for user actions
- [x] Code audit -- 22 issues fixed:
  - XSS: inline onclick replaced with event delegation + data attributes
  - Modals built with DOM methods (no innerHTML with user data)
  - SQL LIKE wildcard escaping
  - Database.php: charset, emulate_prepares off, error handling
  - HIBP explode crash fix
  - Export error message sanitized
  - Button loading states, ESC key for modals
  - Hardcoded colors replaced with CSS variables
  - Tag pill overflow, aria labels
- [x] Username OSINT expanded to 28 platforms (added Instagram, Twitter/X, Facebook, YouTube, LinkedIn, Snapchat, Threads)
- [x] Name search enhanced -- 5 username variants (concatenated, underscore, dot, initial+last, reversed)
- [x] Google Search social URL extractor -- auto-extracts social media usernames from Google result URLs
- [x] Refactored search() into reusable executeSearch() for watchlist re-check
- [x] 14 new API routes (save-profile, saved-profiles, update/delete-saved-profile, watchlist CRUD, watchlist-recheck, replay)
- [x] Source error filtering improved -- "not found" / "not configured" no longer counted as failures

### New Files

- `frontend/js/theme.js` -- Dark/light theme toggle

### Pending

- [ ] Google Custom Search API -- enabled on GCP project but returning 403 (billing link needed)

---

## Phase 5 -- Advanced Features

**STATUS: IN PROGRESS (2026-03-29)**

### Completed

- [x] SerpAPI integration -- Replaced broken Google Custom Search API (403) with SerpAPI (`modules/serpapi.php`). Returns real Google results as structured JSON. Source name kept as `google_search` for pipeline compatibility.
- [x] Web Verified social profiles -- Google-discovered social profiles overwrite username-guessed ones in aggregator. Marked with `web_verified: true`. Frontend sorts them first with green "Web Verified" badge. Added Kaggle to extraction patterns.
- [x] Bulk search -- `POST /api/?route=bulk-search`, max 10 queries per batch. Textarea UI with Single/Bulk toggle on search page. Compact results list with summary stats. "View Full" button uses replay API. `bulk_id` column added to `searches` table.
- [x] Dashboard analytics -- 4th tab on dashboard (`GET /api/?route=analytics`). Stats row (total searches, avg risk, saved, watchlist). Charts: search-by-type pie (CSS conic-gradient), risk distribution stacked bar, daily searches over 30 days (bar chart), top 10 platforms found (bar chart). Pure CSS/HTML, no chart library.

### Remaining

- [ ] Search comparison -- side-by-side diff of two profiles (checkboxes on history, split view)
- [ ] GCP deployment -- Dockerize + Cloud Run

### Future Ideas

- [ ] Auto-recheck cron -- scheduled watchlist checks via PHP cron script
- [ ] Keyboard shortcuts -- `/` to focus search, `Esc` to clear
- [ ] Copy-to-clipboard on data values
- [ ] Search suggestions/autocomplete from history
- [ ] API authentication -- JWT or session-based for deployment
