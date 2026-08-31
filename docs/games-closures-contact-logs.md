# Games, salle closures, contact messages, logs

## Games

- **Venue** (`GameVenue`): backing values `home`/`away`, labels
  `Domicile`/`Extérieur`; default `HOME`. CSV import parses case-insensitively
  (`tryFrom(strtolower(...))`).
- **`meetingTime` is free-text** (e.g. "14h30"), max 10 chars — never validated
  as a real time.
- **Business limits (enforced in `CreateUpdateGameUseCase`, not DB constraints
  → race-prone):**
  - One game per team per day (`assertTeamDailyLimit`).
  - Max 3 home games per day (`assertHomeGameLimit`, HOME only; away unlimited).
  - Both take `excludeId` so an update doesn't collide with itself.
  - Two concurrent requests can both pass the check and both insert — no unique
    index backs these.
- **Role-split on update** (`:108-123`):
  - Super admin: full hydrate, can change anything.
  - Plain admin: **replanification only** — only `date` changes, and only for a
    game of one of their own teams (`user->hasTeam(...)`, else 403). The
    controller allows `update` at `ROLE_ADMIN`; the use case re-checks super-admin
    for everything beyond the date.
  - Controller guards: create/delete/import/history are `ROLE_SUPER_ADMIN`;
    update/list are `ROLE_ADMIN`.

### Game history / audit (`GameHistorySubscriber`)

- Doctrine listener on **`onFlush` + `postFlush`** (not lifecycle callbacks).
- **Two-phase write** solves the id-not-yet-assigned problem: inserts are
  collected in `onFlush` (a new Game has no id yet) and the history rows are
  built + persisted in `postFlush` where `getId()` is available. Updates/deletes
  read their id eagerly.
- **Re-entrancy guard**: `postFlush` persists rows then flushes again inside a
  `$flushing` flag, else the second flush would re-trigger `postFlush` forever.
- **Actions** (`GameHistoryAction`): `created`/`updated`/`deleted`. Payload
  shape differs: CREATED/DELETED store a full snapshot `{field: value}`; UPDATED
  stores only changed fields `{field: {old, new}}`.
- **Spurious-dirty-date gotcha**: Doctrine flags `date` dirty on every save
  (rehydrated `DateTimeImmutable`); `diff()` skips fields whose serialized values
  are equal, so a no-op save records no UPDATED row.
- **Denormalised by design**: no ORM associations, only scalars (`gameId`,
  `opponent`, `gameDate`, `teamId`, `teamName`, `actorEmail`) — survives deletion
  of the Game or actor. Append-only. Actor = `Security::getUser()` email at
  `postFlush`, null in CLI/import context.

### CSV import (`ImportGamesUseCase`)

- **MIME sniffing trap**: accepted MIME types include `text/csv`, `text/plain`,
  `application/csv`, `application/vnd.ms-excel` — a plain CSV is often sniffed as
  `text/plain`, hence its inclusion. `extensions: ['csv']` is also enforced. Max
  3 MB.
- **Parsing**: strips UTF-8 BOM, normalises CRLF, drops blank lines. Requires
  **all six headers** (`team,opponent,date,venue,meetingTime,location`; order
  free) or 422. `team` must be an existing **integer team id** (not a name).
  Date is strict `d/m/Y`.
- **Rows with the wrong column count are silently skipped** — a truncated row
  vanishes, `imported` just excludes it.
- **Import bypasses the game business limits** — the biggest inconsistency: it
  skips the one-per-day and 3-home rules the create/update path enforces. Import
  can create data the UI would reject.
- **All-or-nothing**: everything built first, then a single
  `beginTransaction`/`flush`/`commit`; any throwable → `rollback` + 500.

## Salle closures

- A gym closure period (holidays, works). **Range inclusive on both ends**;
  single-day closure has `startDate == endDate`.
- Validation order in `CreateSalleClosureUseCase`: (1) `endDate >= startDate`
  else 422; (2) overlap check else 422.
- **Overlap rule** (`hasOverlap`): inclusive test
  `startDate <= :end AND endDate >= :start` — any shared day (including a single
  shared endpoint) is rejected; no back-to-back same-day closures.
- **Access**: list is `ROLE_USER` (for calendars); create/delete are
  `ROLE_SUPER_ADMIN`. Also exposed **publicly** via `PublicController::closures`.
  No update endpoint — create/delete only.

## Contact messages

- **Public submission** lives in `PublicController::createContactMessage` (no
  auth). `ContactMessageController` only serves the authenticated **list**
  (misleading name — it does not handle submission).
- **Recaptcha** verified first (throws on failure). Dev/test bypass: `verify()`
  returns `true` when `RECAPTCHA_SECRET_KEY` is empty. **Fails closed** on HTTP
  error or `success !== true` — but a mis-set empty key in prod silently disables
  captcha.
- **Email**: after persist, sends a `TemplatedEmail` from `CONTACT_SENDER_EMAIL`
  to `CONTACT_RECIPIENT_EMAIL`, **replyTo = the submitter** so admin can reply
  directly. **Email failure is swallowed** (logged) — the message stays persisted
  and the submitter still gets success.
- **Read side is `ROLE_VIEW_MESSAGE`** (class-level). Remember `ADMIN` does NOT
  inherit `VIEW_MESSAGE` — only super admin or an explicit VIEW_MESSAGE user can
  read messages.
- Search matches full name in both orders (`CONCAT`), lowercased — name only,
  not email/subject/message.

## Logs

- **Persistence** (`Logger/DoctrineHandler`): a Monolog handler that writes each
  record to the `log` table via **raw DBAL `Connection::insert`, never the ORM**
  — so it stays reliable even when an exception has closed the EntityManager.
- Wired in `monolog.yaml` as handler `db`, channels `["!event","!doctrine"]`
  (excluding `doctrine` prevents the insert's own SQL logging from feeding back).
- **Threshold `Level::Warning`** — only warning+ is persisted.
- **Anti-recursion `$writing` flag**; **all insert errors swallowed** — logging
  must never break a request.
- **Retention: 14 days**, pruned **inline, once per process** (a
  `DELETE FROM log WHERE created_at < threshold` on the first log of a request).
  No cron — retention is best-effort, tied to log volume, not wall-clock. The
  `retentionDays` ctor arg has no env override (effectively hardcoded).
- Context is JSON-encoded (`JSON_PARTIAL_OUTPUT_ON_ERROR`); Throwables flattened,
  DateTimes to ATOM. `level` stored lowercased — the read filter must use
  lowercase.
- **Reading/clearing** (`LogController`, `ROLE_SUPER_ADMIN`): paginated
  (filter by level + message substring, limit ≤ 200) and `DELETE /api/logs` →
  full purge (`deleteAll` via DQL bulk delete).
- Test env sets `main: type: null` — no log writes in tests; `DoctrineHandler`
  is exercised only by a dedicated unit test.

## Misc traps

- **Public `/api/public/games`** clamps to a ±1 year window; the authenticated
  `GetGamesUseCase` has **no** such bound.
