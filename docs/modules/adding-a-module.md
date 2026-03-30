# Adding a New Data Source Module

This guide covers how to add a new external data source to the Vignette platform.

## Module Interface Pattern

Every module follows the same structure:

1. A **constructor** that accepts an API key or token.
2. A **lookup method** that calls the external API and returns raw data.
3. A **normalize method** that converts raw data into Vignette's standard format.

## Step 1: Create the Module File

Create a new file in `modules/`. For example, `modules/whois.php`:

```php
<?php

class WhoisModule {

    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Primary lookup method.
     * Returns raw data array or ['error' => '...'] on failure.
     */
    public function lookup(string $domain): array {
        // Call external API using cURL
        $ch = curl_init("https://api.example.com/whois/" . urlencode($domain));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return ['error' => "API returned status $status"];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Normalize raw API data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'whois',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        return [
            'source' => 'whois',
            'status' => 'success',
            'data' => [
                // Map raw fields to your normalized structure
                'registrar' => $data['registrar'] ?? '',
                'created_date' => $data['creation_date'] ?? '',
                // ...
            ]
        ];
    }
}
```

## Normalized Output Format

All modules must return this structure from `normalize()`:

```php
[
    'source' => 'module_name',     // Unique identifier for this source
    'status' => 'success',         // One of: success, error, timeout, skipped
    'data'   => [ ... ]            // Source-specific data (any structure)
]
```

On error:

```php
[
    'source' => 'module_name',
    'status' => 'error',
    'error'  => 'Human-readable error message',
    'data'   => []
]
```

## Step 2: Register in the Orchestrator

Edit `core/orchestrator.php`:

1. Add a `require_once` at the top of the file:

```php
require_once __DIR__ . '/../modules/whois.php';
```

2. Add task entries in the `planTasks()` method under the appropriate query type `case` blocks:

```php
case 'domain':
    $tasks['whois'] = function () use ($queryValue) {
        $mod = new WhoisModule($this->apiKeys['whois']['api_key'] ?? '');
        $raw = $mod->lookup($queryValue);
        return $mod->normalize($raw);
    };
    // existing ipinfo task...
    break;
```

A module can be registered under multiple query types if it handles more than one.

## Step 3: Register Aggregation Logic

Edit `core/aggregator.php` and add a new `case` in the `merge()` method's switch statement:

```php
case 'whois':
    $merged['whois_data'] = $data;
    // Optionally extract fields into the unified profile:
    if (!empty($data['registrant_name'])) {
        $merged['display_name'] = $merged['display_name'] ?: $data['registrant_name'];
    }
    break;
```

## Step 4: Add the API Key

1. Add the key configuration to `config/api_keys.example.php`:

```php
'whois' => [
    'api_key' => 'YOUR_WHOIS_API_KEY',
],
```

2. Add the actual key to `config/api_keys.php` (gitignored).

## Step 5: Update Risk Factors (Optional)

If the new module provides data relevant to risk scoring, update `core/profiler.php` in the `analyzeRiskFactors()` method:

```php
$whoisData = $data['whois_data'] ?? [];
if (!empty($whoisData['is_privacy_protected'])) {
    $factors[] = 'WHOIS privacy protection enabled';
}
```

## Conventions

- Module class names use PascalCase: `WhoisModule`, `ClearbitModule`.
- Source names in normalized output use lowercase: `whois`, `clearbit`.
- All cURL requests should set `CURLOPT_TIMEOUT` (8 seconds is standard as of Phase 3; was 10s previously).
- All cURL requests should set `User-Agent: Vignette-OSINT-Platform`.
- All cURL requests should set `CURLOPT_SSL_VERIFYPEER => true`.
- Error responses should always include `'data' => []` to avoid null reference issues downstream.

## Existing Modules

| Module | File | API / Method | Query Types |
|--------|------|-------------|-------------|
| HIBP | `modules/hibp.php` | HaveIBeenPwned v3 API | email |
| GitHub | `modules/github.php` | GitHub REST API v3 | email, username, name |
| IPInfo | `modules/ipinfo.php` | IPInfo.io API | ip, domain |
| Gemini AI | `modules/gemini.php` | Google Gemini API | All (summarization layer) |
| WHOIS | `modules/whois.php` | Direct socket (port 43) | domain |
| DNS | `modules/dns.php` | PHP `dns_get_record()` | domain |
| SSL | `modules/ssl.php` | PHP `stream_socket_client` | domain |
| Gravatar | `modules/gravatar.php` | Gravatar public API (MD5 hash) | email |
| Username OSINT | `modules/username_osint.php` | HTTP HEAD via cURL multi | username |
| VirusTotal | `modules/virustotal.php` | VirusTotal v3 API | ip, domain |
| Google Search | `modules/google_search.php` | Google Custom Search JSON API | email, username, name, domain, phone |
| PhoneInfo | `modules/phone_info.php` | Local (no API, 80+ country prefix table) | phone |
