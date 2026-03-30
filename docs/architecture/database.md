# Database Schema

Schema file: `database/schema.sql`

Database: `vignette_db` (charset `utf8mb4`, collation `utf8mb4_unicode_ci`).

Run the schema:

```bash
mysql -u mark -p vignette_db < database/schema.sql
```

## Tables

### `searches`

Stores every search query submitted through the platform.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `query_value` | `VARCHAR(255) NOT NULL` | The search input (email, username, IP, etc.) |
| `query_type` | `ENUM('name','email','phone','username','ip','domain') NOT NULL` | Type of query |
| `bulk_id` | `VARCHAR(36) NULL` | Groups searches from a bulk search batch (Phase 5) |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the search was performed |

Indexes: `idx_query_type` on `query_type`, `idx_created_at` on `created_at`, `idx_bulk_id` on `bulk_id`.

This is the root table. Most other tables reference `searches.id` via foreign key.

---

### `data_sources`

Stores the raw response from each external API for a given search. One row per source per search.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `search_id` | `INT NOT NULL` | FK to `searches.id` (CASCADE delete) |
| `source_name` | `VARCHAR(100) NOT NULL` | Module name (e.g., `haveibeenpwned`, `github`, `ipinfo`) |
| `raw_data` | `JSON` | Raw normalized data from the module |
| `status` | `ENUM('success','error','timeout','skipped') DEFAULT 'success'` | Outcome of the API call |
| `fetched_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the data was fetched |

Index: `idx_search_source` on `(search_id, source_name)`.

---

### `profiles`

Stores the aggregated, unified profile built from all source data for a search.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `search_id` | `INT NOT NULL` | FK to `searches.id` (CASCADE delete) |
| `display_name` | `VARCHAR(255)` | Best-match display name from sources |
| `avatar_url` | `TEXT` | Profile image URL (typically from GitHub) |
| `location` | `VARCHAR(255)` | Location string (from GitHub or IP geolocation) |
| `bio` | `TEXT` | Bio/description text |
| `known_emails` | `JSON` | Array of discovered email addresses |
| `known_usernames` | `JSON` | Array of discovered usernames |
| `social_links` | `JSON` | Object mapping platform names to profile URLs |
| `ai_summary` | `TEXT` | AI-generated summary (Phase 3) |
| `risk_score` | `INT DEFAULT 0` | Computed risk score (0-100) |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the profile was created |

---

### `intelligence_reports`

Stores AI-generated intelligence reports for a search.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `search_id` | `INT NOT NULL` | FK to `searches.id` (CASCADE delete) |
| `risk_score` | `INT DEFAULT 0` | Risk score at time of report generation |
| `summary` | `TEXT` | AI-generated intelligence summary |
| `model_used` | `VARCHAR(100)` | Which AI model generated the report (e.g., `gemini-pro`) |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the report was generated |

---

### `watchlist`

Stores queries that should be re-checked periodically for changes.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `query_value` | `VARCHAR(255) NOT NULL` | The query to watch |
| `query_type` | `ENUM('name','email','phone','username','ip','domain') NOT NULL` | Type of query |
| `last_checked` | `TIMESTAMP NULL` | When the watchlist item was last re-scanned |
| `active` | `BOOLEAN DEFAULT TRUE` | Whether monitoring is active |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the item was added to the watchlist |

---

### `saved_profiles`

Allows users to bookmark and annotate search results.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `search_id` | `INT NOT NULL` | FK to `searches.id` (CASCADE delete) |
| `label` | `VARCHAR(255)` | User-assigned label for the saved profile |
| `notes` | `TEXT` | Free-form notes |
| `tags` | `VARCHAR(500)` | Comma-separated tags for filtering |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the profile was saved |

---

### `conversations`

Stores AI chat exchanges tied to a specific search.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT AUTO_INCREMENT` | Primary key |
| `search_id` | `INT NOT NULL` | FK to `searches.id` (CASCADE delete) |
| `user_message` | `TEXT` | The user's message/question |
| `ai_response` | `TEXT` | The AI's response |
| `model_used` | `VARCHAR(100)` | Which AI model was used |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | When the exchange occurred |

## Relationships

```
searches (1) ---< data_sources (many)     [search_id FK, CASCADE]
searches (1) ---< profiles (many)          [search_id FK, CASCADE]
searches (1) ---< intelligence_reports (many) [search_id FK, CASCADE]
searches (1) ---< saved_profiles (many)    [search_id FK, CASCADE]
searches (1) ---< conversations (many)     [search_id FK, CASCADE]
```

All child tables cascade-delete when the parent `searches` row is removed.

The `watchlist` table is standalone -- it stores queries to re-run but does not foreign-key to any existing search.

## Notes

- All tables use `ENGINE=InnoDB`.
- JSON columns (`raw_data`, `known_emails`, `known_usernames`, `social_links`) store structured data as JSON strings.
- There are pre-existing tables from earlier development (`ai_reports`, `breach_results`, `scans`) that may still exist in the database. These are not part of the current schema and can be cleaned up. See [Known Issues](../wip/known-issues.md).
