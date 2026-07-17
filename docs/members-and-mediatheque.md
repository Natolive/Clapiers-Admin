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

**Physical storage** (`MemberMediaStorage`): files live under
`%upload_directory%/member-media`. On-disk name is a fresh UUID v4 + guessed
extension (`storedName`); the user-facing download name is `originalName`. Uploads
are max **10M**, mimetypes `application/pdf`, `image/png`, `image/jpeg`.

## Media operations

- **Create folder** — parent optional, must be an owned folder if given.
- **Create document** — requires a file; trims & rejects empty name; parent must
  be an owned folder.
- **Upload / replace file** — (re)attaches a file to any existing document node
  **including protected default slots**; rejects folders; **deletes the previous
  file from disk first** (no orphan).
- **Delete file only** — unlinks the disk file and nulls the metadata but
  **keeps the node**; this is how you empty a protected default slot.
- **Rename node** / **Delete node** — both blocked on protected nodes. Delete
  recursively unlinks disk files of the node and all descendants **before** the
  DB remove, because the DB cascade (`onDelete: CASCADE` + `orphanRemoval`) drops
  rows but **never touches the filesystem**.

> **File-orphan cleanup is application-code responsibility.** Deleting a member
> cascades the whole tree at DB level but **leaves files on disk** — no
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
