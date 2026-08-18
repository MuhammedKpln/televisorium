# Televisorium API

Televisorium exposes its data through OCS REST endpoints. All endpoints require authentication as a logged-in user (the data is per-user).

## General

- Base URL: `/ocs/v2.php/apps/televisorium`
- Send the header `OCS-APIRequest: true`
- Request and response format is JSON (`?format=json` can be added, but JSON is the default with the header)
- All endpoints require login (Basic auth or session cookie); app passwords work as well
- Every resource is scoped to the authenticated user. A user can only see and change their own items and episodes
- Authenticated requests to Bus-like hosts need a valid CSRF token if not using Basic/Bearer auth

Example using curl:

```
curl -u admin:admin -H 'OCS-APIRequest: true' \
  'http://nextcloud.local/ocs/v2.php/apps/televisorium/items'
```

## Response format

Successful responses return the HTTP status code plus the payload as JSON. Errors return a JSON object:

```json
{
  "message": "Item not found"
}
```

Status codes used:

| Code | Meaning                                   |
|------|-------------------------------------------|
| 200  | Success                                   |
| 201  | Resource created                          |
| 400  | Invalid input (bad type, status, rating)  |
| 401  | No TMDb API key configured for search/details/season |
| 404  | Resource not found                        |
| 409  | Conflict (title already in library)       |
| 502  | Upstream TMDb request failed              |

## Objects

### Item (library entry)

Returned by the item endpoints. `poster_url`, `backdrop_url` and `overview` come from TMDb.

```json
{
  "id": 1,
  "item_type": "movie",
  "title": "Dune",
  "tmdb_id": 438631,
  "year": 2021,
  "runtime": 155,
  "poster_url": "https://image.tmdb.org/t/p/w500/...",
  "backdrop_url": "https://image.tmdb.org/t/p/w780/...",
  "overview": "Paul Atreides ...",
  "status": "watching",
  "rating": 9,
  "watched_seconds": 9300,
  "created_at": 1720000000,
  "updated_at": 1720000000
}
```

Notes:

- `id` is the internal library id, `tmdb_id` is the TMDb id
- `status` is one of `watchlist`, `watching`, `watched`, `on_hold`, `dropped`
- `rating` is an integer between 0 and 10, or `null`
- For `item_type: "tv"`, the single-item endpoint additionally returns an `episodes` array (see Episode)
- When `watched_seconds` is updated without a status, the status is derived automatically: reaching the runtime sets `watched`, otherwise `watching`

### Episode

Returned by the episode endpoints.

```json
{
  "id": 3,
  "item_id": 1,
  "season_number": 1,
  "episode_number": 5,
  "title": "Part Five",
  "runtime": 155,
  "watched": true,
  "watched_seconds": 0,
  "updated_at": 1720000000
}
```

Notes:

- Setting `watched_seconds` beyond the `runtime` (in minutes) marks the episode as `watched` and resets the position
- A `watched` episode always has `watched_seconds: 0`
- The parent tv show's status is derived automatically from its episodes: all watched -> `watched`, any watched or progress -> `watching`

## Endpoints

### Items

#### List items

```
GET /ocs/v2.php/apps/televisorium/items
```

Query parameters (all optional):

- `type`: filter by `movie` or `tv`
- `status`: filter by `watchlist`, `watching`, `watched`, `on_hold`, `dropped`
- `search`: case-insensitive substring match on title

Response: `200` array of Item.

#### Get one item

```
GET /ocs/v2.php/apps/televisorium/items/{id}
```

Response: `200` Item (with nested `episodes` for tv shows). `404` if not found or not owned by the user.

#### Add an item

```
POST /ocs/v2.php/apps/televisorium/items
```

Body (JSON or form params):

| Field          | Type   | Required | Notes                                        |
|----------------|--------|----------|----------------------------------------------|
| `title`        | string | yes      |                                              |
| `item_type`    | string | yes      | `movie` or `tv`                              |
| `tmdb_id`      | int    | no       | Duplicate check: already in library -> 409   |
| `year`         | int    | no       |                                              |
| `runtime`      | int    | no       | Minutes                                      |
| `poster_url`   | string | no       |                                              |
| `backdrop_url` | string | no       |                                              |
| `overview`     | string | no       |                                              |
| `status`       | string | no       | Default `watchlist`                          |
| `rating`       | int    | no       | 0-10                                        |
| `watched_seconds` | int | no     | Default 0                                    |

Response: `201` created Item. `400` on invalid input, `409` on duplicate.

#### Update an item

```
PUT /ocs/v2.php/apps/televisorium/items/{id}
```

Body: any subset of the fields above. Special behavior:

- `status` must be one of the valid statuses
- `rating` must be 0-10 or `null`
- `watched_seconds` derives the status if no explicit `status` is given

Response: `200` updated Item. `400` on invalid input, `404` if not found.

#### Remove an item

```
DELETE /ocs/v2.php/apps/televisorium/items/{id}
```

Also deletes all episodes of the item.

Response: `200` with empty object. `404` if not found.

### Episodes

#### List episodes of a tv show

```
GET /ocs/v2.php/apps/televisorium/items/{itemId}/episodes
```

Response: `200` array of Episode, ordered by season then episode number.

#### Add an episode

```
POST /ocs/v2.php/apps/televisorium/items/{itemId}/episodes
```

Body:

| Field            | Type   | Notes                                  |
|------------------|--------|----------------------------------------|
| `season_number`  | int    | Default 1, >= 1                        |
| `episode_number` | int    | Default 1, >= 1                        |
| `title`          | string | Optional                               |
| `runtime`        | int    | Optional, minutes                      |
| `watched`        | bool   | Optional                               |
| `watched_seconds`| int    | Optional                               |

Upsert: an existing episode with the same season and episode number is updated instead of duplicated.

Response: `201` Episode. `400` on invalid input, `404` if item not found.

#### Bulk import episodes

```
POST /ocs/v2.php/apps/televisorium/items/{itemId}/episodes/bulk
```

Body:

```json
{
  "episodes": [
    { "season_number": 1, "episode_number": 1, "title": "Part One", "runtime": 155 },
    { "season_number": 1, "episode_number": 2, "title": "Part Two", "runtime": 155 }
  ]
}
```

Each entry is upserted (matched by season + episode number). Existing watched state is kept, only `title` and `runtime` of existing entries are refreshed.

Response: `200` array of Episode.

#### Update an episode

```
PUT /ocs/v2.php/apps/televisorium/episodes/{id}
```

Body: any subset of `title`, `runtime`, `season_number`, `episode_number`, `watched` (bool), `watched_seconds` (int).

Reaching `watched_seconds` >= runtime marks the episode watched. Marking an episode watched resets `watched_seconds` to 0.

Response: `200` updated Episode. `404` if not found or not owned.

#### Remove an episode

```
DELETE /ocs/v2.php/apps/televisorium/episodes/{id}
```

Response: `200` with empty object. `404` if not found.

### TMDb

These endpoints proxy The Movie Database and require a configured API key, otherwise they return `401`.

#### Search titles

```
GET /ocs/v2.php/apps/televisorium/search?query=dune
```

Response: `200`

```json
{
  "query": "dune",
  "results": [
    {
      "tmdb_id": 438631,
      "item_type": "movie",
      "title": "Dune",
      "year": 2021,
      "overview": "...",
      "poster_url": "...",
      "backdrop_url": "...",
      "runtime": 155
    }
  ]
}
```

#### Get details

```
GET /ocs/v2.php/apps/televisorium/details/{itemType}/{tmdbId}
```

`itemType` is `movie` or `tv`.

Response: `200` normalized title object. For tv shows an extra `seasons` array is included:

```json
{
  "seasons": [
    { "season_number": 1, "episode_count": 10 }
  ]
}
```

#### Get season episodes

```
GET /ocs/v2.php/apps/televisorium/season/{tmdbId}/{seasonNumber}
```

Response: `200` array of:

```json
{
  "season_number": 1,
  "episode_number": 1,
  "title": "Part One",
  "runtime": 155,
  "tmdb_id": 12345
}
```

This is the data source for the bulk episode import endpoint.

### Settings

#### Get settings

```
GET /ocs/v2.php/apps/televisorium/settings
```

Response: `200`

```json
{
  "configured": true,
  "language": "en-US"
}
```

`configured` is whether a personal TMDb API key is stored. `language` is the TMDb language tag used for all TMDb requests.

#### Save settings

```
POST /ocs/v2.php/apps/televisorium/settings
```

Body: `apiKey` and/or `language`, both optional but at least one required.

- `apiKey` is validated against TMDb; invalid key -> `400` "Invalid TMDb API key"
- `language` must match `^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$` (e.g. `en-US`); otherwise `400` "Invalid TMDb language"

Response: `200` same shape as GET.

#### Clear API key

```
DELETE /ocs/v2.php/apps/televisorium/settings
```

Removes the personal API key. The language stays unchanged.

Response: `200` same shape as GET.

## Practical notes

- Televisorium never returns your API key. `configured` only tells you whether one is set
- `watched_seconds` is set by the frontend while playing, so the API is idempotent for that field
- All timestamps are unix seconds