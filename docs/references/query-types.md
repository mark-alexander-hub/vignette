# Query Types Reference

The platform supports six query types. Each type determines which modules the orchestrator dispatches.

## Supported Query Types

### `email`

**Modules:** HIBP, GitHub, Gravatar, Google Search

The orchestrator dispatches four tasks:

1. **HIBP** -- `HibpModule::checkBreaches(email)` checks the email against the HaveIBeenPwned breach database.
2. **GitHub** -- `GitHubModule::searchByEmail(email)` searches for GitHub accounts associated with the email. If a match is found, `fullLookup()` fetches the full profile, repos, and events.
3. **Gravatar** -- `GravatarModule::lookup(email)` fetches the avatar, display name, bio, and social links from the Gravatar profile (opt-in public data).
4. **Google Search** -- `SerpApiModule::search(email)` finds web mentions of the email address.

**Example input:** `user@example.com`

---

### `username`

**Modules:** GitHub, Username OSINT, Google Search

The orchestrator dispatches three tasks:

1. **GitHub** -- `GitHubModule::fullLookup(username)` fetches the GitHub profile, repositories, and recent public events for the given username.
2. **Username OSINT** -- `UsernameOsintModule::check(username)` performs parallel HTTP HEAD checks across 20 platforms using cURL multi to detect profile existence.
3. **Google Search** -- `SerpApiModule::search(username)` finds web mentions of the username.

**Example input:** `octocat`

---

### `ip`

**Modules:** IPInfo, VirusTotal

The orchestrator dispatches two tasks:

1. **IPInfo** -- `IpInfoModule::lookup(ip)` fetches geolocation, ISP/org, and privacy detection data (VPN/proxy/Tor/hosting).
2. **VirusTotal** -- `VirusTotalModule::lookup(ip)` checks the IP against VirusTotal's threat intelligence database for malicious/suspicious flags.

**Example input:** `8.8.8.8`

---

### `domain`

**Modules:** WHOIS, DNS, SSL, VirusTotal, Google Search, IPInfo

The orchestrator dispatches six tasks:

1. **WHOIS** -- `WhoisModule::lookup(domain)` performs socket-based WHOIS queries against 80+ TLD servers with IANA fallback, extracting registrar, dates, privacy detection, and domain age.
2. **DNS** -- `DnsModule::lookup(domain)` fetches A/AAAA/MX/TXT/NS/SOA records, detects mail providers, hosting/CDN, and computes an email security score (SPF/DKIM/DMARC analysis with DMARC subdomain lookup).
3. **SSL** -- `SslModule::lookup(domain)` inspects the TLS certificate for CA details, DV/OV/EV classification, SAN extraction, chain visualization, expiry tracking, and EC vs RSA key strength detection.
4. **VirusTotal** -- `VirusTotalModule::lookup(domain)` checks the domain against VirusTotal's security engines for malware and reputation data.
5. **Google Search** -- `SerpApiModule::search(domain)` finds web mentions of the domain.
6. **IPInfo** -- Resolves the domain to an IP via `gethostbyname()`, then calls `IpInfoModule::lookup(resolvedIp)`.

**Example input:** `example.com`

---

### `name`

**Modules:** GitHub, Username OSINT, Google Search

The orchestrator dispatches three tasks:

1. **GitHub** -- Uses the GitHub search users API (`GET /search/users?q={name}`) to find users matching the name. The top 5 results are retrieved, and `fullLookup()` is called on the first match to get the full profile.
2. **Username OSINT** -- `UsernameOsintModule::check(slugifiedName)` checks platforms using a slugified version of the name (e.g. "Linus Torvalds" becomes "linustorvalds").
3. **Google Search** -- `SerpApiModule::search(name)` finds web mentions of the name.

**Example input:** `John Doe`

---

### `phone`

**Modules:** PhoneInfo, Google Search

The orchestrator dispatches two tasks:

1. **PhoneInfo** -- `PhoneInfoModule::lookup(phone)` validates the phone number format, detects the country from 80+ international prefixes, and determines the line type (mobile, landline, toll-free).
2. **Google Search** -- `SerpApiModule::search(phone)` finds web mentions of the phone number.

**Example input:** `+1-555-0100`

---

## Query Type to Module Matrix

| Query Type | HIBP | GitHub | IPInfo | Gravatar | Username OSINT | WHOIS | DNS | SSL | VirusTotal | Google Search | PhoneInfo | Gemini AI |
|------------|------|--------|--------|----------|----------------|-------|-----|-----|------------|---------------|-----------|-----------|
| `email` | X | X | | X | | | | | | X | | X |
| `username` | | X | | | X | | | | | X | | X |
| `ip` | | | X | | | | | | X | | | X |
| `domain` | | | X | | | X | X | X | X | X | | X |
| `name` | | X | | | X | | | | | X | | X |
| `phone` | | | | | | | | | | X | X | X |

## Validation

The `SearchController` validates query types against a whitelist before processing:

```php
$validTypes = ['name', 'email', 'phone', 'username', 'ip', 'domain'];
```

Invalid types return HTTP 400 with an error message.
