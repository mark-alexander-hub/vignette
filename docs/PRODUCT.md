# Vignette — AI-Powered Digital Intelligence Platform

## What is Vignette?

Vignette is an OSINT (Open Source Intelligence) platform that builds comprehensive digital profiles from publicly available data. Enter a name, email, username, phone number, IP address, or domain — and Vignette searches across 28+ platforms and data sources simultaneously, then uses AI to summarize findings into a unified intelligence report.

Think of it as a digital fingerprint scanner — it shows you what information about a person or entity is publicly accessible online.

---

## Key Capabilities

### 1. Multi-Source Intelligence Gathering

Vignette queries 12 different data sources in parallel for every search:

| Source | What It Finds |
|--------|--------------|
| **HaveIBeenPwned** | Data breaches — which breaches exposed this email, what data was leaked (passwords, addresses, phone numbers) |
| **GitHub** | Developer profile — repos, followers, activity, company, email, contribution history |
| **Gravatar** | Profile photo, bio, location, linked social accounts from email hash |
| **SerpAPI (Google)** | Web mentions — any public page that references the target, social media profiles, news articles |
| **Username OSINT** | Checks 28 social platforms simultaneously (Instagram, TikTok, Twitter/X, LinkedIn, YouTube, Reddit, GitHub, Spotify, Telegram, and 19 more) |
| **WHOIS** | Domain registration — who registered it, when, registrar, nameservers, privacy protection |
| **DNS** | DNS records, mail provider detection (Gmail, Outlook, custom), email security scoring (SPF/DKIM/DMARC) |
| **SSL Certificate** | Certificate authority, type (DV/OV/EV), expiry, key strength, subject alternative names |
| **VirusTotal** | Threat intelligence — malware flags, suspicious activity, security engine verdicts |
| **IPInfo** | IP geolocation, ISP, VPN/proxy/Tor detection, hosting provider |
| **Phone Info** | Phone number validation, country detection (80+ prefixes), line type (mobile/landline/toll-free) |
| **Gemini AI** | AI-generated intelligence briefing summarizing all findings |

### 2. Smart Profile Building

Vignette doesn't just dump raw data — it intelligently merges results from all sources into a single unified profile:

- **Identity Resolution** — Picks the best name, avatar, bio, and location from multiple sources using priority-weighted scoring
- **Social Link Discovery** — Finds real social media profiles two ways: username pattern matching (28 platforms) AND extracting actual profiles from Google results
- **Web Verification** — Profiles found through Google search results are marked as "Web Verified" and prioritized over username-guessed profiles, reducing false positives
- **Deduplication** — Emails, usernames, social links, and breaches are automatically deduplicated across sources

### 3. AI Intelligence Briefing

Every search generates an AI-powered summary (via Google Gemini) that reads like a professional intelligence brief:

- Key findings highlighted
- Risk factors explained in plain language
- Connections between data points identified
- Actionable insights surfaced

### 4. Risk Scoring (0-100)

Every profile gets an automated risk score based on:

| Factor | Points |
|--------|--------|
| Each data breach | +10 |
| Password exposed in breach | +20 |
| Sensitive breach (adult sites, health data) | +30 |
| Stealer log (malware-harvested credentials) | +25 |
| VPN/Tor/Proxy detected on IP | +10-15 |
| Domain less than 30 days old | +25 |
| Domain less than 6 months old | +10 |
| Expired SSL certificate | +20 |
| Weak SSL key | +10 |
| Missing SPF record (email spoofing risk) | +10 |
| Missing DMARC policy | +5 |
| VirusTotal malicious flags (5+) | +30 |

Risk levels: **Clean** (0) | **Low** (1-20) | **Moderate** (21-50) | **High** (51-75) | **Critical** (76-100)

### 5. Bulk Search

Search up to 10 targets simultaneously. Paste a list of emails, usernames, or any query type — Vignette runs the full intelligence pipeline on each one and presents results in a compact, scannable format with:

- Summary statistics (total, success/fail, average risk)
- Compact result cards with risk badges
- Click "View Full" to expand any result into the complete profile view

### 6. Dashboard & Monitoring

**Search History** — Full searchable, filterable history of all investigations. Filter by type, risk level, or text search. Sort by date or risk score.

**Saved Profiles** — Bookmark important investigations with custom labels, notes, and tags. Filter saved profiles by tag for easy organization.

**Watchlist** — Add targets to a monitoring watchlist. Re-check any time to see if their risk profile has changed. Shows risk score delta (e.g., "Risk: 15 -> 45, +30").

**Analytics Dashboard** — Visual overview of your intelligence work:
- Total searches, average risk score, saved profiles count
- Search volume by type (pie chart)
- Risk distribution across all profiles (stacked bar)
- Search activity over the last 30 days (bar chart)
- Top social platforms discovered (bar chart)

### 7. Intelligence Timeline

Every profile includes a chronological timeline of events:
- Domain registration and expiry dates
- SSL certificate issuance
- GitHub account creation
- Data breach dates
- Current investigation timestamp

### 8. Relationship Mapping

Vignette discovers connections between different searches. If two targets share the same email, username, IP range, registrar, hosting provider, or SSL certificate authority — it flags the relationship with confidence levels.

### 9. Export

Generate printable intelligence reports from any search result for offline review, sharing, or archival.

---

## Six Search Types

| Type | Input Example | What Vignette Checks |
|------|--------------|---------------------|
| **Email** | `john@example.com` | Breaches, GitHub, Gravatar, Google mentions |
| **Username** | `johndoe123` | GitHub profile, 28 social platforms, Google mentions |
| **Name** | `John Doe` | GitHub name search, 5 username variants across 28 platforms, Google mentions |
| **IP Address** | `8.8.8.8` | Geolocation, VPN/proxy detection, VirusTotal threat intel |
| **Domain** | `example.com` | WHOIS, DNS, SSL, VirusTotal, Google mentions, IP resolution |
| **Phone** | `+1-555-0100` | Country detection, line type, Google mentions |

---

## Use Cases

### Background Research
Check what's publicly known about a person before a meeting, interview, or partnership. See their professional presence, social footprint, and any red flags.

### Security Assessment
Evaluate the security posture of a domain — is the SSL valid? Are email security headers configured? Has the domain been flagged by security engines? How old is the registration?

### Breach Monitoring
Check if an email has been exposed in data breaches. Monitor watchlisted emails over time to detect new exposures.

### Digital Footprint Audit
See what a person or organization looks like from the outside. Useful for personal privacy audits — find out what information about you is publicly accessible.

### Due Diligence
Investigate entities before engaging. Check domain reputation, ownership history, and any security flags.

---

## Design Principles

**100% Legal OSINT** — Vignette only uses official APIs and publicly available data. No scraping, no credential stuffing, no unauthorized access, no non-consented personal data enrichment.

**Privacy-Conscious** — All data sources are public. No dark web queries, no private database access. The tool surfaces what's already available — it doesn't create new exposure.

**Transparent Sources** — Every piece of data shows where it came from. Source attribution is maintained throughout the pipeline.

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.x |
| Database | MySQL 8 |
| Frontend | Vanilla JavaScript, HTML, CSS |
| AI | Google Gemini (gemini-2.5-flash) |
| Web Search | SerpAPI (real Google results) |
| Deployment Target | Google Cloud Platform |

---

## Platform Coverage (28 Social Platforms)

Vignette checks for profile existence across:

Instagram, TikTok, Twitter/X, Facebook, YouTube, LinkedIn, Snapchat, Threads, Pinterest, Twitch, Imgur, Reddit, GitHub, Spotify, Vimeo, Telegram, Linktree, GitLab, Keybase, HackerNews, Patreon, About.me, Flickr, Medium, DEV.to, Mastodon, Gravatar, Kaggle

---

## API Endpoints

Vignette exposes a REST API for programmatic access:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/?route=search` | POST | Run a single intelligence search |
| `/api/?route=bulk-search` | POST | Run up to 10 searches simultaneously |
| `/api/?route=history` | GET | Search history with filtering and pagination |
| `/api/?route=replay` | GET | Replay a previous search from stored data |
| `/api/?route=analytics` | GET | Dashboard analytics and statistics |
| `/api/?route=save-profile` | POST | Save a search with label, notes, tags |
| `/api/?route=saved-profiles` | GET | List saved profiles with tag filtering |
| `/api/?route=watchlist` | GET | List watchlist items |
| `/api/?route=watchlist-add` | POST | Add a target to the watchlist |
| `/api/?route=watchlist-recheck` | POST | Re-run a watchlist search and compare risk delta |

---

## Status

- **Phases 1-4:** Complete — full OSINT pipeline, 12 data sources, 28-platform username checks, AI summaries, dashboard, history, watchlist, saved profiles, dark/light theme, mobile responsive
- **Phase 5:** In progress — SerpAPI integration (done), bulk search (done), dashboard analytics (done), search comparison (next), GCP deployment (next)
