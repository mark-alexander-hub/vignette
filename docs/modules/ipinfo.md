# IPInfo Module

Source: `modules/ipinfo.php`
Class: `IpInfoModule`

## Overview

Provides IP geolocation, ISP/organization data, and privacy detection (VPN, proxy, Tor, hosting). Used for both direct IP lookups and domain lookups (domain is resolved to IP first by the orchestrator).

## API Details

| Property | Value |
|----------|-------|
| API | IPInfo.io |
| Base URL | `https://ipinfo.io` |
| Docs | https://ipinfo.io/developers |
| Auth | `?token={TOKEN}` query parameter |
| Cost | Free tier: 50,000 requests/month |
| Rate Limit | 50,000/month on free tier |

## Query Types Handled

| Query Type | Method Used |
|------------|-------------|
| `ip` | `lookup(ip)` directly |
| `domain` | Orchestrator resolves domain to IP via `gethostbyname()`, then calls `lookup(ip)` |

## Constructor

```php
$mod = new IpInfoModule(string $token);
```

The token is read from `config/api_keys.php` under `$keys['ipinfo']['token']`.

## Methods

### `lookup(string $ip): array`

Validates the IP address using `FILTER_VALIDATE_IP`. If invalid, returns `['error' => 'Invalid IP address']`.

Calls `GET https://ipinfo.io/{ip}?token={token}`.

Returns the raw JSON response as an associative array, or an error array on non-200 status.

Timeout: 10 seconds.

### `normalize(array $data): array`

Converts raw IPInfo data into Vignette's standard format.

**Success case:**

```json
{
  "source": "ipinfo",
  "status": "success",
  "data": {
    "ip": "8.8.8.8",
    "hostname": "dns.google",
    "city": "Mountain View",
    "region": "California",
    "country": "US",
    "latitude": 37.386,
    "longitude": -122.0838,
    "org": "AS15169 Google LLC",
    "postal": "94035",
    "timezone": "America/Los_Angeles",
    "is_vpn": false,
    "is_proxy": false,
    "is_tor": false,
    "is_hosting": true
  }
}
```

**Notes on normalized fields:**

- `latitude` and `longitude` are parsed from the IPInfo `loc` field (format `"lat,lon"`). They are `null` if the field is absent or malformed.
- The privacy flags (`is_vpn`, `is_proxy`, `is_tor`, `is_hosting`) are derived from the `privacy` sub-object in the IPInfo response. These fields may require a paid IPInfo plan to be populated.

## Integration

The orchestrator dispatches this module in two scenarios:

**Direct IP lookup (`ip` query type):**

```php
case 'ip':
    $tasks['ipinfo'] = function () use ($queryValue) {
        $mod = new IpInfoModule($this->apiKeys['ipinfo']['token'] ?? '');
        $raw = $mod->lookup($queryValue);
        return $mod->normalize($raw);
    };
```

**Domain lookup (`domain` query type):**

The orchestrator resolves the domain to an IP using PHP's `gethostbyname()`, then passes the resolved IP to `IpInfoModule::lookup()`. The normalized result includes extra fields `resolved_domain` and `resolved_ip`. If DNS resolution fails (returns the domain string unchanged), an error is returned.

The aggregator maps IPInfo results into the `ip_data` field of the merged profile. If no location has been set from other sources, the IP's city/region/country is used as the profile location.

## VPN/Proxy/Tor Detection

The profiler uses the privacy flags from IPInfo to generate risk factors:

- `is_vpn: true` adds risk factor "VPN detected on IP"
- `is_tor: true` adds risk factor "Tor exit node detected"
- `is_proxy: true` adds risk factor "Proxy detected on IP"

These contribute to the overall risk assessment displayed on the dashboard.
