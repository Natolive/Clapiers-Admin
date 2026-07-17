# Seasons & dashboard stats

## Season format & "current season"

- **Format `AAAA-AAAA`** (two consecutive years). Regex-enforced
  `/^\d{4}-\d{4}$/` (`Validator/Season.php`); null/empty pass (optional field) —
  add `NotBlank` when required.
- **September rollover** (`SeasonResolver`, `SEASON_START_MONTH = 9`):
  `startYear = month >= 9 ? year : year - 1`. So 30/06/2026 → `2025-2026`,
  01/09/2026 → `2026-2027`.
- **Resolution priority** (`SeasonProvider`): the admin `Setting` key
  `current_season` overrides the date-computed value. `computed()` is the pure
  date value, surfaced separately as a *suggestion*.
- `SeasonProvider::set()` does two things: writes the Setting **and**
  `ensure()`s a `Season` row — selecting a season is what registers it.
- **Two validity invariants**: the regex, **plus** a consecutive-year check
  (`end === start + 1`) that lives only in `SetCurrentSeasonUseCase` (422). The
  regex alone would accept `2026-2099`. A writer bypassing that use case (e.g.
  `SeasonProvider::set` called directly) skips the consecutive check.
- `SeasonProvider::all()` returns the registered seasons (name DESC), with the
  current season prepended if not yet registered — so it's always selectable.

## Season scoping across the app

- Shared input `SeasonQuery` carries `?season=` via `#[MapQueryString]`.
- **The counted-membership rule**: a member counts for a season only with a
  licence in `[VALIDEE, EN_PAIEMENT, PAYEE]` for that season. Excludes
  past-season non-renewals and licence-less members. **Single source of truth:
  `LicenseStatus::activeMembership()`** (and `::activeMembershipValues()` for raw
  SQL) — every scope/stat query goes through it, so change the set in one place.
- **Defaults differ by list** — a real gotcha:
  - Members list, members-by-team, dashboard → default to **current** season.
  - **Licences list does NOT default** — the season is passed straight through;
    the repo skips the filter on null/`''`. So the licence list is **cross-season
    unless a season is explicitly given.** Asymmetric with everything else.
- `licensePaid` is a derived, season-scoped flag (a `PAYEE` licence for the
  season; null season = any).

## Dashboard stats (`MemberRepository::getStats`, `LicenseRepository::getStats`)

- `total` = members with a validated (`VALIDEE|EN_PAIEMENT|PAYEE`) licence for
  the season.
- **Misleading key names** — the whole population already has a validated
  licence, so:
  - `withLicense` actually = members with a **`PAYEE`** (paid) licence.
  - `withoutLicense = total - withLicense` = validated-but-not-yet-paid.
  - Neither means "no licence at all".
- `byGender`, `age` (Postgres `AGE()/EXTRACT` on `birth_date`, fixed buckets),
  `byMonth` — computed in **raw SQL**. The status list is interpolated from
  `LicenseStatus::activeMembershipValues()` (safe — enum values, no external
  input); column/table names are still literal.
- **`newThisSeason`** (`:199-205`) = in-season population with **no validated
  licence for any earlier season** (`NOT EXISTS ... lo.season < :season`).
  First-ever validated licence, **not `createdAt`**.
  - Why: registration opens *before* 1 September (outside the calendar window)
    and renewals keep the first-adhesion `createdAt`, so a `createdAt`-based
    "new member" count is wrong. Licence history is the source of truth. (This
    was the "Nouveaux cette saison stays at 0" bug — see
    `MemberRepository.php:194-198`.)
- **`byMonth` is the one metric still `createdAt`-based** and windowed to the
  season `[startYear-09-01, startYear+1-09-01)`, so it undercounts the same way
  (pre-September registrations and renewals fall outside). Left as-is
  intentionally — it's a monthly-distribution chart, not the headline counter.

### Traps

- **Lexicographic season comparison == chronological** — relied on by
  `SeasonRepository::findAllNames` (`DESC`) and `newThisSeason`'s
  `lo.season < :season`. Only valid because seasons are fixed-width 4-digit,
  same-century strings. Breaks if a non-conforming name ever reaches the DB.
- **DQL + raw SQL split in `getStats`**: `total`/`withLicense`/`newThisSeason`
  use DQL enum params; `byGender`/`age`/`byMonth` use raw SQL. Both now take the
  status set from `LicenseStatus`, so the membership set no longer drifts — but
  the raw-SQL table/column names are still literal and Postgres-specific (`AGE`,
  `EXTRACT`, `TO_CHAR`).
- All `/api/settings` and `/api/stats` are `ROLE_SUPER_ADMIN`. The current
  season is also exposed unauthenticated at `/season`.
