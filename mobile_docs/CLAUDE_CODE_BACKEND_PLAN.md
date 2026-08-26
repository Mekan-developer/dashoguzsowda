# Claude Code — Backend Implementation Plan (Sowda Mobile)

Copy this file into the backend repo and run Claude Code with:

> Implement the missing Sowda mobile API endpoints described in this file.
> Follow existing Laravel conventions in this project. Do not invent unrelated endpoints.
> After each endpoint, add a feature test. Keep response shape exactly as specified.

**Mobile base URL:** `https://dashoguzsowda.com.tm/api`  
**Prefix:** `/v1`  
**Auth:** Laravel Sanctum `Authorization: Bearer {token}`  
**Locale:** `Accept-Language: tk|ru`  
**Envelope:** always `{ "data": ... }` (+ `meta` for pagination)

---

## Priority order (implement in this order)

| # | Feature | Endpoints | Mobile already wired? |
|---|---------|-----------|------------------------|
| 1 | Profile completeness | extend `GET/PUT /v1/profile` | ✅ |
| 2 | Search recent history | `GET/POST/DELETE /v1/search/recent` | ✅ |
| 3 | User preferences (onboarding) | `GET/PUT /v1/preferences` | ✅ |
| 4 | Stores | `/v1/stores/*` | ✅ |
| 5 | Tariffs | `/v1/tariffs`, `/v1/profile/subscription` | ✅ |
| 6 | Search popular | `GET /v1/search/popular` | ✅ |

This doc focuses on **1–3** (Onboarding / Search history / Profile).  
Full schemas for stores/tariffs/popular are in `docs/BACKEND_API.md`.

---

## Task 1 — Profile: `is_profile_complete`

### Goal
Mobile splash + OTP routing must know if the user finished registration.

### Change `GET /v1/profile` response

Add:

```json
{
  "data": {
    "id": 1,
    "phone": "+99361234567",
    "name": "Mekan",
    "gender": "male",
    "birth_date": "1990-05-15",
    "region_id": 1,
    "city_id": 2,
    "district_id": 3,
    "avatar": "/storage/avatars/1.webp",
    "is_profile_complete": true,
    "is_premium": false,
    "store": null,
    "tariff": { "...": "..." },
    "stats": {
      "views_count": 0,
      "likes_count": 0,
      "premium_days_left": 0
    }
  }
}
```

### Completeness rule (server-side)

`is_profile_complete = true` when ALL of:

- `name` is non-empty
- `region_id` is set
- `city_id` is set

Optional (recommended): also require `gender` and `birth_date`.

Aliases mobile accepts (prefer `is_profile_complete`):

- `is_profile_complete`
- `profile_completed`
- `is_profile_done`

### `PUT /v1/profile`

After update, recompute and return the same profile object including `is_profile_complete`.

### Auth verify note

`POST /v1/auth/verify` already returns `is_new`. Keep it.  
After verify, mobile calls `GET /v1/profile` and uses `is_profile_complete` for routing:

- new user OR incomplete → `/register`
- complete → `/home`

### DB migration sketch

```php
// optional cache column on users
$table->boolean('is_profile_complete')->default(false);
```

Or compute on the fly in a ProfileResource — both OK.

### Tests

- New user profile → `is_profile_complete: false`
- After PUT with name+region+city → `true`
- Incomplete PUT (missing city) → still `false`

---

## Task 2 — Search recent history

### Endpoints

```
GET    /v1/search/recent
POST   /v1/search/recent
DELETE /v1/search/recent
```

**Auth:** required (Bearer). Guest users keep history only on device.

### `GET /v1/search/recent`

Return up to **8** newest unique queries for the authenticated user.

```json
{
  "data": ["iPhone", "Toyota", "Nike"]
}
```

Also accept:

```json
{ "data": [{ "query": "iPhone", "searched_at": "2026-03-20T10:00:00Z" }] }
```

```json
{ "data": { "queries": ["iPhone", "Toyota"] } }
```

### `POST /v1/search/recent`

```json
{ "query": "iPhone" }
```

Rules:

1. Trim query; reject empty (`422`)
2. Deduplicate case-sensitive or case-insensitive (pick one; document it)
3. Move query to front (most recent first)
4. Cap at 8 rows per user (delete oldest)
5. Response: updated list (same shape as GET)

### `DELETE /v1/search/recent`

Clear all recent queries for the current user.

```json
{ "data": { "cleared": true } }
```

or empty `200` with `{ "data": [] }`.

### DB migration sketch

```php
Schema::create('search_recents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('query', 191);
    $table->timestamps();
    $table->unique(['user_id', 'query']);
    $table->index(['user_id', 'updated_at']);
});
```

### Controller sketch (Laravel)

```
SearchRecentController@index
SearchRecentController@store
SearchRecentController@destroy
```

Routes under `auth:sanctum` middleware group.

### Tests

- Guest → 401
- POST same query twice → one entry, moved to top
- 9th unique query → oldest dropped
- DELETE → empty list

---

## Task 3 — Preferences (Onboarding sync)

### Why

Onboarding UI (language/theme) stays **device-local**.  
Flag `onboarding_completed` can sync **after login** so a user who already finished onboarding on another device can skip it when preferences are fetched.

Mobile behavior:

1. Pre-auth: only SharedPreferences
2. On complete: write local; if already authenticated, `PUT /v1/preferences`
3. Splash when auth: `GET /v1/preferences` may promote local flag to true

### Endpoints

```
GET /v1/preferences
PUT /v1/preferences
```

**Auth:** required

### Response / body

```json
{
  "data": {
    "onboarding_completed": true
  }
}
```

PUT body:

```json
{
  "onboarding_completed": true
}
```

Aliases mobile accepts for the flag:

- `onboarding_completed`
- `onboarding_done`

### Do NOT put in this endpoint

- `locale` — mobile keeps local (user request)
- `theme` — mobile keeps local (user request)

### DB migration sketch

```php
Schema::create('user_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
    $table->boolean('onboarding_completed')->default(false);
    $table->timestamps();
});
```

Or columns on `users` table.

### Tests

- Default GET → `onboarding_completed: false`
- PUT true → GET returns true
- Unauthenticated → 401

---

## Task 4 — Profile extras already expected by mobile

Ensure `GET /v1/profile` also returns (already documented in BACKEND_API.md):

| Field | Type |
|-------|------|
| `store` | object\|null |
| `tariff` / `subscription` | object\|null |
| `stats.views_count` | int |
| `stats.premium_days_left` | int |
| `is_premium` | bool |

`PUT /v1/profile` accepts nested `store` for Premium/Business.

---

## Claude Code checklist (paste as todo)

```text
[ ] Add is_profile_complete to ProfileResource + recompute on update
[ ] Migration + model SearchRecent
[ ] GET/POST/DELETE /v1/search/recent with auth + max 8 + tests
[ ] Migration + model UserPreference
[ ] GET/PUT /v1/preferences with onboarding_completed + tests
[ ] Confirm GET /v1/profile includes store/tariff/stats fields
[ ] Run phpunit / pest for new tests
[ ] Update Bruno/OpenAPI collection if project has one
```

---

## Mobile files that already call these APIs

| Endpoint | Mobile |
|----------|--------|
| `GET /v1/profile` (`is_profile_complete`) | `profile_mapper.dart`, `splash_cubit.dart`, `auth_cubit.dart` |
| `GET/POST/DELETE /v1/search/recent` | `search_remote_data_source.dart`, `api_search_repository.dart` |
| `GET/PUT /v1/preferences` | `preferences_remote_data_source.dart`, `api_settings_repository.dart` |

When these endpoints exist with the schemas above, **no mobile code changes are required**.

---

## What stays local forever (do not build API)

| Feature | Reason |
|---------|--------|
| Cart | Explicitly local-only |
| Locale | Explicitly local-only |
| Theme | Explicitly local-only |
| Push enable toggle | Device FCM preference |
| Onboarding locale/theme UI values | Device UX before/without auth |

Only the **onboarding_completed flag** is syncable via `/v1/preferences`.
