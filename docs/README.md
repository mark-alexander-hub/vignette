# Vignette -- AI-Powered Digital Intelligence Platform

Documentation index for the Vignette OSINT platform.

## Architecture

- [System Overview](architecture/overview.md) -- Pipeline architecture, tech stack, file structure, data flow
- [Database Schema](architecture/database.md) -- All tables, columns, and relationships

## Module Reference

### Phase 1 (Complete)
- [HIBP Module](modules/hibp.md) -- HaveIBeenPwned breach lookups
- [GitHub Module](modules/github.md) -- GitHub profile, repo, and activity lookups
- [IPInfo Module](modules/ipinfo.md) -- IP geolocation and VPN/proxy detection
- Gemini AI Module (`modules/gemini.php`) -- AI intelligence briefing via Google Gemini

### Phase 2 (Complete)
- WHOIS Module (`modules/whois.php`) -- Socket-based WHOIS with 80+ TLDs, IANA fallback, privacy detection
- DNS Module (`modules/dns.php`) -- DNS records, mail provider detection, email security scoring
- SSL Module (`modules/ssl.php`) -- Certificate details, CA detection, DV/OV/EV classification
- Gravatar Module (`modules/gravatar.php`) -- Email to avatar, name, bio, social links
- Username OSINT Module (`modules/username_osint.php`) -- Parallel checks across 28 platforms (Instagram, Twitter/X, Facebook, YouTube, LinkedIn, TikTok, Snapchat, Threads + 20 more)
- VirusTotal Module (`modules/virustotal.php`) -- Domain/IP reputation, malware detection
- SerpAPI Web Search Module (`modules/serpapi.php`) -- Real Google results via SerpAPI (replaced broken Google Custom Search API)

### Phase 3 (Complete)
- Phone Info Module (`modules/phone_info.php`) -- Phone number validation, country detection (80+ prefixes), type classification
- RelationshipMapper (`core/relationships.php`) -- Cross-search connection discovery with 7 relationship types
- Export endpoint (`api/export.php`) -- Printable HTML intelligence reports

### Phase 4 (Complete)
- Search history enhancements -- filtering, sorting, pagination
- Saved profiles with tagging -- CRUD API, tag pills, dashboard tab
- Watchlist with re-check -- monitoring queries, risk delta comparison
- Replay system -- view stored results without re-fetching
- Dark/light theme toggle (`frontend/js/theme.js`)
- Loading skeleton, mobile responsive, toast notifications
- Code audit: 22 security/stability fixes (XSS, SQL injection, error handling)
- Name search: 5 username variants + Google social URL extraction

### Phase 5 (In Progress)
- SerpAPI Module (`modules/serpapi.php`) -- Replaced Google Custom Search with SerpAPI for real Google results as JSON
- Web Verified Profiles -- Social profiles discovered via Google results overwrite username-guessed ones, marked with "Web Verified" badge
- Bulk Search (`POST /api/?route=bulk-search`) -- Search up to 10 targets at once, compact results list with progress
- Dashboard Analytics (`GET /api/?route=analytics`) -- Stats, search-by-type pie chart, risk distribution, daily searches, top platforms

### Guides
- [Adding a Module](modules/adding-a-module.md) -- Guide for building new data source modules

## References

- [API Keys Setup](references/api-keys.md) -- API key configuration, costs, and rate limits
- [Query Types](references/query-types.md) -- Supported query types and which modules handle each

## Status

- [Phase Plan](wip/phase-plan.md) -- Development phases and current status (Phases 1-4 complete)
- [Known Issues](wip/known-issues.md) -- Known bugs, TODOs, and technical debt
