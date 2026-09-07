# Members & médiathèque

## Member entity

**Statuses** (`Entity/Enum/MemberStatus.php`): `PENDING_VALIDATION`, `ACTIVE`,
`REJECTED`; default `ACTIVE`.

- **Status is never set by the admin Member CRUD** — `CreateUpdateMemberUseCase`
  writes no status, so an admin-created member is `ACTIVE` by construction. All
  transitions live in the **licence** domain (submit → `PENDING_VALIDATION`,
  approve → `ACTIVE`, reject → `REJECTED`).
- Only `ACTIVE` members appear in licencié lists; the others stay in the
  "Demandes de licence" flow.

Fields worth knowing:

- **Gender** (`male`/`female`/`other`) is a real enum column.
- **Nationality** is a **string column**, not an enum column — the enum
  (`MemberNationality`) is used only as a validation whitelist via
  `Assert\Choice(callback: [MemberNationality::class, 'values'])`. The French
  adjective (`'Française'`, …) is what's persisted.
- **Address** and **LegalRepresentative** are embeddables (prefixes `address_`,
  `legal_rep_`); all fields default to `''`, never null. **Empty legal-rep
  strings ⇒ adult / no legal rep** — there's no null and no minor flag. Neither
  the address details nor the legal rep are settable via the admin Member CRUD;
  they come only from the public licence request.
- **`licenseNumber`** (nullable free string) is distinct from the `License`
  entities collection.
- **`isLicensePaid($season)`** is derived (no stored column): true if any
  `License` is `PAYEE` (restricted to `$season` if given). `toArray($season)`
  threads the season through so `licensePaid` reflects the requested season.
- **`color`** is an auto-generated random hex at construction (overridable).
- **`hasTeam`** compares by object identity first, then id — so two unpersisted
  teams (both id `null`) aren't treated as equal.

Creation/update: keyed off `command->id` null-ness; update 404s if not found.
At least one team is required; `teamIds` is deduped and each must resolve (else
the whole op 404s); `setTeams` is a full replace.

**Email is NOT deduped on member create/update** — `findOneByEmailExcluding`
exists but is used only by the licence-review flow to flag a re-inscription
duplicate. The admin can create two members with the same email.

**Deletion** (`DELETE /api/member/{id}`, `ROLE_SUPER_ADMIN`,
`DeleteMemberUseCase`) is a **soft delete**: nothing is destroyed, rows are
stamped with `deleted_at` and hidden. Payments, the médiathèque tree and the
Bunny objects survive untouched and stay visible —
`MemberApiTest::testDeleteKeepsLicensesPaymentsAndMedia` pins that down.

The mechanism is **`gedmo/doctrine-extensions`** (via
`stof/doctrine-extensions-bundle`), not hand-written code. Each participating
entity carries `#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]` and the
`Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity` trait. Two pieces do the work:

- the **listener** turns `$em->remove($x)` into a `deletedAt` stamp — which is
  why the use case is an ordinary `remove()` + `flush()`;
- the **`softdeleteable` SQL filter** appends `deleted_at IS NULL` to every DQL
  query touching the entity, joins included.

Both are wired in `config/packages/stof_doctrine_extensions.yaml`. Note the
bundle enables the *listener* but **not the filter** — the `doctrine.orm.filters`
block in that same file is what turns the filter on. Drop it and deletion still
stamps the row, but deleted members reappear everywhere, silently.

### What cascades, and why

Gedmo does **not** cascade a soft delete, so `DeleteMemberUseCase` stamps three
entities explicitly. The rule that decides membership: **an entity needs soft
delete when it is reachable by a key of its own**, without going through the
member. Anything only reachable *through* the member is already unreachable once
the member is hidden.

| Entity | Soft-deleted? | Reachable on its own by… |
| --- | --- | --- |
| `Member` | yes | its id |
| `License` | yes | its `access_token` (public payment magic link) |
| `AppUser` | yes | its login email |
| `Payment` | **no** | only via its licence (webhook resolves the licence first) |
| `MemberDocument` | **no** | only via `/api/member/{id}/media/...` |

Both cascades fix an observed **HTTP 500**, not a theoretical one:

- a surviving licence pointed at a hidden member, and `License::getMember()` is
  non-nullable → `EntityNotFoundException` on proxy init. The public payment
  link now answers a clean `404 Licence introuvable`
  (`testDeleteAlsoHidesTheLicenceFromItsPublicMagicLink`).
- a surviving account did the same through `AppUser::toArray()` on
  `GET /api/user/me` — and, worse, **kept working**: the person could still log
  in. Deleting the account closes both. The security provider is an `entity`
  provider, so it goes through the ORM and the filter applies: an already-issued
  JWT stops working immediately (the user is reloaded from the provider on every
  request) and a password login returns 401
  (`testTheLinkedAccountCanNoLongerAuthenticate`).

The **join tables are actually emptied**, not stamped: `member_team` and
`app_user_team` are link tables, not entities, so neither the filter nor the
`deleted_at` stamp reaches them. Reads don't leak either way — Doctrine applies
the filter to collection loading too, so `Team::$coaches` already came back
without the deleted account — but leaving the rows would assert a team
membership that no longer holds, and any raw-SQL count over those tables would
over-count. Regression test: `MemberApiTest::testDeleteClearsTeamJoinRows`.

The `app_user.member` link is **kept**, not nulled, so the pair stays
restorable (its teams, however, are not — they would have to be re-assigned). Note the account's email stays taken by the hidden row under the
unique index: re-creating an account with the same address fails until the old
one is restored.

> ### ⚠️ The filter covers DQL only — never raw SQL
>
> `MemberRepository::getStats()` builds three aggregates through
> `getConnection()`, and those bypass the filter entirely. They carry an
> explicit `member.deleted_at IS NULL` in the shared `$popSql` predicate
> (`MemberRepository.php:165`). Without it the dashboard reported `total: 0`
> while `byGender` still counted the deleted member and `age.average` stayed at
> its old value — a real, observed inconsistency, not a hypothetical.
> The predicate also carries `l.deleted_at IS NULL` for the licence sub-select.
> **Any future query going through `getConnection()` on these tables must carry
> those conditions itself.** Regression test:
> `StatsApiTest::testSoftDeletedMemberLeavesEveryAggregate`.

Two more consequences, both verified against the running app rather than
assumed:

- **Admin lists**: the licence list INNER JOINs the member and the user list
  LEFT JOINs it; on the LEFT JOIN Doctrine puts the condition in the `ON`
  clause, so a *live* account linked to nothing still lists fine. Deleted
  licences and accounts simply drop out.
- **`find()` and the identity map**: the filter guards queries, not the identity
  map. A deleted row still surfaces through an association loaded earlier in the
  *same* request. In a fresh request the delete use case's own `find()` is
  filtered, so **deleting twice returns 404** — that is intended.

To reach deleted rows (restoration, admin task):
`$em->getFilters()->disable('softdeleteable')`. **There is no restore route or
UI yet** — the Gedmo trait exposes `setDeletedAt(null)`, nothing else, and
restoring a member means restoring its licences and account too.

## Médiathèque tree

A **self-referential tree** on one entity `MemberDocument`, two natures via
`MemberDocumentType`: `FOLDER` (container) vs `DOCUMENT` (may carry a file).

- **Nodes are addressed publicly by UUID v4**, not the auto-increment id —
  deliberately non-enumerable so URLs don't leak. `toArray()` even exposes
  `uuid` as `'id'`. Every lookup goes through
  `findOneOwnedBy($member, $uuid)`, which enforces ownership (a UUID from
  another member's tree 404s).
- Tree shape: first level = a **per-season folder** (`systemKey='season'`,
  carrying the `season` string). Below that:
  - **`ROOT_FOLDERS`** (season-independent, always present): `identity`
    ("Identité") → docs `identity_photo`, `id_card`.
  - **`SEASON_DOCUMENTS`** (pre-created in each season folder): `license`,
    `medical_certificate`, `attestation`.
  - Both defined in `MemberMediaDefaults.php` — **to add a default slot, add an
    entry there, nothing else to touch** (the seeder iterates generically).

**Seeding is lazy + idempotent** (`MemberMediaSeeder`): invoked on **GET media**
(not at member creation). It ensures root folders + the **current** season
folder, then the caller flushes (the seeder never flushes itself). Only the
current season folder is auto-seeded — past/future season folders exist only if
they were current when someone opened the media.

**Default nodes are `protected=true`** → not renamable, not deletable (but their
file is still manageable — see below).

**Physical storage** (`MemberMediaStorage`) — single entry point for every
write, read and delete. Files live on **Bunny Storage** (HTTP API, region
DE/Falkenstein), laid out **one folder per member**:

```
/member-media/<member id>/<uuid v4>.<ext>
```

`storedName` holds that whole relative path, so reads and deletes need nothing
else — and legacy flat names (`<uuid>.<ext>`) still resolve, no migration
required for existing rows. The user-facing download name is `originalName`.
Uploads are max **10M**, mimetypes `application/pdf`, `image/png`, `image/jpeg`.

`store()` therefore takes the member id. The one place a file changes owner is
`ApproveLicenseUseCase` merging a request into an existing member: it calls
`copyTo()`, which GET+PUTs the object into the target member's folder (Bunny has
no server-side copy) so the file follows instead of being orphaned under the
duplicate record that is deleted right after.

> **Never delete a stored file before the flush that records its new name.**
> `ApproveLicenseUseCase` collects every file the merge makes obsolete — the
> replaced piece on the target member, and the source copy — and deletes them
> **after** `flush()`, swallowing storage errors (the licence is already
> approved; an orphan object must not turn into a 502). Deleting earlier means a
> later failure in the same request leaves the DB pointing at objects already
> gone from the zone — files lost. Regression test:
> `LicenseAdminApiTest::testApproveWithReplaceLosesNoFileWhenTheStorageFailsMidMerge`,
> which fails a copy mid-merge via `FakeBunnyStorageClient::failPutsFromCall()`.
>
> The same ordering applies to the two upload paths — `UploadDocumentFileUseCase`
> (médiathèque) and `UploadLicenseRequestDocumentUseCase` (public inscription):
> both keep the old `storedName` aside, store, flush, then delete. Regression
> test: `MemberMediaApiTest::testAFailedReplacementKeepsThePreviousFile`.

**There is no local-disk fallback, on purpose.** The backend runs as several k8s
pods, so a file written to one pod's disk 404s from the others.

**Zone and key are admin settings, not env vars** — same pattern as HelloAsso.
`BunnyConfigProvider` (`src/Common/Service/`) reads `bunny_storage_url` /
`bunny_storage_key` from the `setting` table and is the only source
`MemberMediaStorage` consults. They are edited under *Paramètres → Stockage des
fichiers* (`GET`/`PUT /api/settings/bunny`, `ROLE_SUPER_ADMIN`); the key is
write-only — the API returns `storageKeyDefined`, never the value. An unconfigured
zone fails every upload and download with a 502 instead of losing files.

In tests, `ApiTestCase::setUp()` writes a dummy zone into the `setting` table and
`FakeBunnyStorageClient` (`when@test`, `config/services.yaml`) swaps only the HTTP
transport for an in-memory zone — the real service stays under test, and nothing
ever hits the network.

**Files are never public.** No CDN pull zone is attached to the storage zone —
the zone is only readable with the `AccessKey`, which lives in the backend. The
only reads go through the authenticated routes below, all of which call
`MemberMediaStorage::response()`:

| Route | Guard |
| --- | --- |
| `GET /api/member/{id}/profile-picture` | `ROLE_ADMIN` |
| `DELETE /api/member/{id}` | `ROLE_SUPER_ADMIN` |
| `GET /api/member/{id}/media/node/{uuid}/download` | `ROLE_SUPER_ADMIN` |
| `GET /api/team/my-team/license/{memberId}` | `ROLE_ADMIN` + shares a team |
| `GET /api/team/my-team/member/{memberId}/profile-picture` | `ROLE_ADMIN` + shares a team |

`response()` returns `null` when the file is missing (each caller keeps its own
404 shape) and throws `UseCaseException(502)` on a Bunny outage. On the Bunny
backend it streams (`StreamedResponse` over the HTTP client) and takes the
`Content-Type` from the DB, since Bunny serves everything as octet-stream.
`store()` uploads **before** the caller flushes, so a failed upload can't leave
a DB row pointing at a missing file.

The download name is the user's original filename, so `response()` always passes
`makeDisposition()` an explicit ASCII fallback — given none, Symfony reuses the
name itself and throws `InvalidArgumentException` on the first accent.

The CSV game import (`POST /api/game/import`) does **not** use this service: it
reads the multipart temp file in-memory within the same request and never stores
it — already pod-safe.

## Media operations

- **Create folder** — parent optional, must be an owned folder if given.
- **Create document** — requires a file; trims & rejects empty name; parent must
  be an owned folder.
- **Upload / replace file** — (re)attaches a file to any existing document node
  **including protected default slots**; rejects folders; **deletes the previous
  file from the storage zone after the flush** (no orphan, no loss — see the
  invariant above).
- **Delete file only** — deletes the stored file and nulls the metadata but
  **keeps the node**; this is how you empty a protected default slot.
- **Rename node** / **Delete node** — both blocked on protected nodes. Delete
  recursively deletes the stored files of the node and all descendants **before**
  the DB remove, because the DB cascade (`onDelete: CASCADE` + `orphanRemoval`)
  drops rows but **never touches the storage zone**.

> **File-orphan cleanup is application-code responsibility.** Deleting a member
> cascades the whole tree at DB level but **leaves files in the storage zone** — no
> application hook runs on member deletion.

Children are ordered in PHP for serialization (folders before documents, then
case-insensitive by name), not in SQL.

## Season scoping of member lists

- **Paginated licenciés** (`MemberRepository::findPaginated`): always
  `status = ACTIVE`; if a season is set (defaults to current), also requires a
  licence for that season in `[VALIDEE, EN_PAIEMENT, PAYEE]`. `licensePaid`
  filter derives from a `PAYEE` licence, season-restricted. Per-row
  `hasLicenseDocument` checks the season `license` slot's `hasFile()`.
- **By team** (`findByTeam`): same season-licence gate; season defaults to
  current.
- **GetAll** (`findAllWithTeams`): no status, no season filter — everything.
- Teams are fetch-joined **after** the cloned COUNT and paginated via Doctrine
  `Paginator`, else `setMaxResults` would truncate SQL rows, not members.

See [seasons-and-stats.md](seasons-and-stats.md) for dashboard stats built on
this population.

## Security

- **The whole Member API and the whole Média API are `ROLE_SUPER_ADMIN`**
  (class-level), including média download. The one exception:
  `profile-picture` is downgraded to `ROLE_ADMIN`.
- **Profile picture = the média tree's root `identity_photo` slot** (season-
  independent) — there is no separate avatar field on Member.
- UseCases throw `UseCaseException` with an explicit status (not
  `NotFoundHttpException`) — see the [architecture trap](architecture.md#the-trap).
