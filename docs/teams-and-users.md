# Teams & users (auth / roles)

## Role hierarchy

Values (`Entity/Enum/AppUserRole.php`): `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`,
`ROLE_USER`, `ROLE_VIEW_MESSAGE` (the enum case *value* is the `ROLE_*` string).

The **real** hierarchy is `config/packages/security.yaml`, not PHP:

```
SUPER_ADMIN → [ADMIN, VIEW_MESSAGE, USER]
ADMIN       → [USER]
VIEW_MESSAGE→ [USER]
```

- **`ADMIN` does NOT inherit `VIEW_MESSAGE`** — a coach/admin cannot read
  contact messages; only SUPER_ADMIN (or an explicit VIEW_MESSAGE grant) can.
- `getRoles()` always appends `ROLE_USER` and de-dupes — everyone is at least
  USER.
- Trap: `AppUserRole::inheritedRoles()` re-declares the hierarchy in code but has
  **no callers** — dead/duplicate logic. Treat `security.yaml` as source of
  truth; the two can drift silently.

**One stored role per user (write-path invariant):** create/update overwrites
roles with `setRoles([$command->role])` — the command carries a single scalar
`role` string. Multi-role combos are impossible via the API. Trap: `role` is
**not** validated against the enum (no `Assert\Choice`) — any string is accepted
and stored.

Controller gating: all Team-admin and all User endpoints are
`ROLE_SUPER_ADMIN`; the "my-team" coach endpoints are `ROLE_ADMIN` (SUPER_ADMIN
reaches them via hierarchy); `/api/user/me` has **no** `IsGranted` (any
authenticated user).

## Coach ↔ team assignment

- `ManyToMany` in table **`app_user_team`**, **owned by `AppUser.teams`**;
  `Team.coaches` is the inverse side (`mappedBy: 'teams'`). **Persistence must go
  through the AppUser side** — editing `Team.coaches` directly would not persist.
- `CreateUpdateTeamUseCase::syncCoaches` edits the team's coach list but writes
  it on the **user** side (`user->addTeam`/`removeTeam`) precisely for this
  reason. `AppUser::addTeam/removeTeam` also mutate the inverse `Team.coaches` to
  keep memory coherent (`Team::addCoach/removeCoach` are `@internal`).
- `userIds` semantics: `null` = leave coaches untouched; `[]` = remove all;
  deduped; any unknown id throws **404** and aborts the whole sync.
- **Two different team relations exist**: coaches link via `AppUser.teams`;
  licensees (Members) link via a separate `Member.teams` ManyToMany
  (`member_team`). A coach's own linked Member (below) is unrelated to which
  teams he coaches.
- "My team" for a coach = the teams on his `AppUser.teams`. `GetMyTeamUseCase`
  lists, per team, that team's **members** for the current season.

## User ↔ member linking

- `AppUser.member` is a nullable, **unique** `OneToOne` to `Member` — one Member
  ↔ at most one user. Purpose: attach a login account to a club licensee record
  (independent of coached teams).
- `LinkMemberUseCase`: both must exist (404); rejects with **409** if the member
  is already linked to a *different* user. `UnlinkMemberUseCase` just
  `setMember(null)` — no checks.
- **Linking is a separate PATCH flow, not part of CreateUpdateUser.** The
  `memberId` is read by hand from raw JSON as `(int)($data['memberId'] ?? 0)`, so
  a missing/invalid body becomes `0` and then 404s.
- Enables sorting/filtering users by their linked member's name in pagination,
  and embeds the member in `AppUser::toArray`.

## My-team downloads (licence PDF, photo)

Both use cases enforce the same two-stage rule:

1. Caller must have ≥1 team, else **403**.
2. The target member must **share a team** with the caller, else **403**.

So any coach can download the licence/photo of **any** member of **any** team he
coaches (not limited to members he personally linked). Files come from the média
library: licence = current-season `license` slot (season-scoped); photo = root
`identity_photo` slot (season-agnostic). Missing slot/file → 404.

- These return a file response (a `StreamedResponse` piping the Bunny object),
  so they **bypass the JSON `execute()` wrapper** and call `run()` directly,
  catching `UseCaseException` → JSON and `\Throwable` → 500 by hand in the
  controller. A Bunny outage surfaces as `UseCaseException(502)`; a file missing
  from the zone is a 404.
- Licence is served as an attachment (filename fallback `'licence'`); photo is
  served inline.

## Password / auth handling

- Create requires a non-empty password (else `UseCaseException`). Update only
  re-hashes when a password is supplied. Trap: because `empty('0')` is true, a
  literal password `"0"` is silently ignored on both create and update.
- Email uniqueness enforced in app code (on create, and on update only when
  changed) plus a DB unique constraint. The command validates only
  `Assert\Email`.
- Passwords hashed via `UserPasswordHasherInterface`; `UserRepository`
  implements `PasswordUpgraderInterface` for transparent rehash-on-login.
- `AppUser::__serialize` overwrites the stored password with its CRC32C hash so
  real hashes never land in the session. `eraseCredentials()` is a deprecated
  no-op.
- **Pagination is injection-guarded**: `findPaginated` whitelists sort fields
  (default `u.email`), coerces direction to ASC/DESC, parameterises search. The
  `total` COUNT is cloned **before** ordering/limits, and the `leftJoin`+
  `addSelect('m')` is overridden by `select('COUNT(u.id)')` so the count stays
  correct despite the join. Sorting by `member.name` maps to member first/last
  name with a stable `u.email` tiebreak.
- Trap: `GetAllUsersUseCase` / `GetAllTeamsUseCase` are unpaginated `findAll()`
  (SUPER_ADMIN-only, but will scale poorly).
