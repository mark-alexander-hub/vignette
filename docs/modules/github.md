# GitHub Module

Source: `modules/github.php`
Class: `GitHubModule`

## Overview

Fetches public GitHub profile information, repositories, and recent activity. Supports lookup by username, email search, and name search.

## API Details

| Property | Value |
|----------|-------|
| API | GitHub REST API v3 |
| Base URL | `https://api.github.com` |
| Docs | https://docs.github.com/en/rest |
| Auth | `Authorization: token {TOKEN}` header |
| Cost | Free (personal access token) |
| Rate Limit | 5,000 requests/hour with token, 60/hour without |
| Accept Header | `application/vnd.github.v3+json` |

## Query Types Handled

| Query Type | Method Used |
|------------|-------------|
| `username` | `fullLookup(username)` -- profile + repos + events |
| `email` | `searchByEmail(email)` then `fullLookup()` on first match |
| `name` | GitHub user search API, then `fullLookup()` on top match |

## Constructor

```php
$mod = new GitHubModule(string $token);
```

The token is read from `config/api_keys.php` under `$keys['github']['token']`. The module works without a token but is limited to 60 requests/hour.

## Methods

### `getProfile(string $username): array`

Calls `GET /users/{username}`.

Returns the full GitHub user object or `['error' => 'GitHub user not found']` on 404.

### `getRepos(string $username, int $limit = 10): array`

Calls `GET /users/{username}/repos?sort=updated&per_page={limit}`.

Returns an array of repository objects sorted by last update.

### `getEvents(string $username, int $limit = 10): array`

Calls `GET /users/{username}/events/public?per_page={limit}`.

Returns an array of recent public activity events.

### `searchByEmail(string $email): array`

Calls `GET /search/users?q={email}+in:email`.

Returns the `items` array from the search result (array of user objects matching the email).

### `fullLookup(string $username): array`

Convenience method that calls `getProfile`, `getRepos`, and `getEvents` in sequence and returns:

```php
[
    'profile' => [...],
    'repos' => [...],
    'events' => [...]
]
```

If `getProfile` returns an error, the error is returned immediately without fetching repos/events.

### `normalize(array $data): array`

Converts raw GitHub data into Vignette's standard format.

**Success case:**

```json
{
  "source": "github",
  "status": "success",
  "data": {
    "username": "octocat",
    "display_name": "The Octocat",
    "bio": "...",
    "company": "@github",
    "location": "San Francisco",
    "email": "octocat@github.com",
    "avatar_url": "https://avatars.githubusercontent.com/...",
    "profile_url": "https://github.com/octocat",
    "public_repos": 42,
    "followers": 1000,
    "following": 10,
    "created_at": "2011-01-25T18:44:36Z",
    "repos": [
      {
        "name": "repo-name",
        "description": "...",
        "language": "JavaScript",
        "stars": 100,
        "forks": 25,
        "url": "https://github.com/octocat/repo-name",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

## Integration

The orchestrator dispatches this module for `username`, `email`, and `name` query types:

- **username**: Calls `fullLookup()` directly with the query value.
- **email**: Calls `searchByEmail()` first. If a match is found, calls `fullLookup()` on the first result's `login` field.
- **name**: Uses the GitHub search users API directly (with `curl` in the orchestrator) to find users matching the name, then calls `fullLookup()` on the top match. As of Phase 3, name queries also dispatch Username OSINT with a slugified version of the name.

The aggregator extracts the following fields from GitHub results into the merged profile: `display_name`, `avatar_url`, `location`, `bio`, `email`, `username`, `profile_url` (as a social link), and `repos`.

## Error Handling

| HTTP Status | Behavior |
|-------------|----------|
| 200 | Parse and return JSON |
| 404 | Return `['error' => 'GitHub user not found']` |
| 403 | Return `['error' => 'GitHub API rate limit exceeded']` |
| Other | Return `['error' => 'GitHub API returned status {code}']` |

All requests have an 8-second timeout (reduced from 10s in Phase 3).
