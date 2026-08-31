# Architecture & conventions

Symfony micro-kernel app (`src/Kernel.php`). Layered:

```
Controller → Command / Input DTO → UseCase → Entity / Repository
```

`App\` is glob-registered as services (`config/services.yaml`), so every
UseCase / Controller / Validator autowires with zero config.

## Controller → Command → UseCase

Controllers are **thin dispatchers**: map the request into a DTO, build a
Command, and `return $useCase->execute(...)`. No business logic, no manual JSON
building (`LicenseController.php:28-66`, `MemberController.php:37-51`). The only
actions that do real work serve files (`MemberController.php:60-83`,
`GameController.php:130-156`).

**Two payload-mapping styles — pick by whether the Command needs the auth user:**

1. **Map straight into the Command** when it's fully derivable from the body.
   The Command implements `CommandInterface`, carries its own `#[Assert\...]`
   attributes, and is `#[MapRequestPayload]`-ed
   (`MemberController.php:38-43`, `CreateUpdateMemberCommand.php:11-42`).
2. **Map into a `Controller/Input/*` DTO, then hand-build the Command** when the
   Command needs `AppUser` (not in the request). Validation attributes then live
   on the Input DTO, not the Command
   (`GameController.php:65-109`, `CreateUpdateGameInput.php:11-26`;
   License: `ApproveLicensePayload` → `ApproveLicenseCommand`).

   > Consequence: for these flows the field list + validation rules are
   > **duplicated** across the Input DTO and the Command. Deliberate (Commands
   > carrying `AppUser` can't be request-mapped), but keep both in sync.

- GET params use `#[MapQueryString]`; an empty query string yields `null`, so
  these commands are nullable with a fallback
  (`?? new GetPaginatedLicensesCommand()`, `LicenseController.php:30-33`).
- `#[MapRequestPayload]` / `#[MapQueryString]` run the validator automatically;
  a constraint failure throws **before** the UseCase and returns **422**.
- `CommandInterface` (`Common/Command/CommandInterface.php`) is an **empty
  marker** — its only job is the generic bound `TCommand of CommandInterface|null`
  on `AbstractUseCase`.

## AbstractUseCase: `execute()` vs `run()` — the core contract

`Common/UseCase/AbstractUseCase.php`:

- **You implement `run()`** (abstract). **Controllers call `execute()`** — never
  `run()`. `execute()` wraps `run()` in try/catch and turns the return value
  into a `Response`.
- **Result serialization is convention-based** (`serializeResult`): if the
  returned object has `toArray()` it's called; arrays are mapped element-wise
  through `toArray()`; scalars pass through. So **every entity you return must
  implement `toArray()`** (`Game.php:106-119`) — there is no Symfony
  Serializer in the response path. `toArray()` recurses into
  relations/embeddables and formats dates (`Y-m-d` / `DATE_ATOM`).
- **The `"Invalid command"` guard**: `run(?CommandInterface $command = null)`
  takes the marker interface, so each `run()` re-narrows to its concrete Command
  with `instanceof` and throws `UseCaseException('Invalid command')` otherwise
  (`CreateUpdateGameUseCase.php:32-34`). This is the type-safety boundary the
  marker can't enforce — every new use case must be added to
  `UseCaseGuardTest`'s provider (see testing).

### <a name="the-trap"></a>THE TRAP: never throw framework HTTP exceptions in `run()`

`execute()` only special-cases `UseCaseException`:

- `UseCaseException` → `JsonResponse(['message' => …], $e->getCode() ?: 400)`.
- **Any other `\Throwable` → 500** (details only when `APP_ENV=dev`).

So Symfony's `NotFoundHttpException` / `AccessDeniedHttpException` / a raw
`new DateTimeImmutable('garbage')` all become a **500**, not the 404/403/422 you
intended. To signal a status you **must** throw
`UseCaseException($message, Response::HTTP_*)`. Using
`$this->createNotFoundException()` inside `run()` is a real production bug
(silent 500) — it has bitten this codebase twice.

## UseCaseException

`Common/Exception/UseCaseException.php`: extends `\Exception`; constructor
`(string $message = "", int $code = Response::HTTP_BAD_REQUEST)`. **The
exception `code` IS the HTTP status.** No extra fields, no per-field error map —
the body is just `{"message": …}`. Field-level validation errors (422) come from
Symfony's validator, not this class.

## Routing & security

- Routes via PHP attributes, auto-imported. `api_login_check` is the one
  manually-declared route.
- **Class-level `#[IsGranted]` is the default; method-level tightens it.**
  `LicenseController` / `MemberController` are class-wide `ROLE_SUPER_ADMIN`
  with a method dropping to `ROLE_ADMIN` where allowed. `GameController` has no
  class guard — every method declares its own.
- `IsGranted` uses `AppUserRole::ROLE_*` **string constants** (distinct from the
  enum cases — see entity conventions).
- **Defense-in-depth**: controller `IsGranted` is the coarse gate; UseCases
  re-check ownership/role at runtime for finer rules (e.g. an admin may only
  reschedule their own teams' games — `CreateUpdateGameUseCase.php:108-123`).

## Entity conventions

- **`IdTrait`** — auto-increment int PK + `getId()`; used in every entity.
- **`TimestampableTrait`** — `createdAt`/`updatedAt` as `DateTimeImmutable` via
  `#[PrePersist]`/`#[PreUpdate]`. **Requires `#[ORM\HasLifecycleCallbacks]` on
  the entity or timestamps silently never fire** (`Game.php:14`).
- **Value objects as embeddables** — `Address` (prefix `address_`),
  `LegalRepresentative` (prefix `legal_rep_`); public props + `toArray()`,
  constructed fresh in UseCases.
- **Backed string enums** stored via `enumType:`. Common helpers: `values()`
  for `Assert\Choice` callbacks, `label()` for UI.
- **`AppUserRole` is dual-natured**: `const ROLE_*` **strings** (for
  `#[IsGranted]` / Symfony's role system) **and** matching enum `case`s. Use the
  constants where a string role is expected, the cases where you want type
  safety.
- **`toArray()` on entities is the serialization contract** (see above).

## Auth stack

- **Lexik JWT**, stateless. RSA keypair in `config/jwt/`, **1-hour TTL**
  (`token_ttl: 3600`).
- Firewalls (`config/packages/security.yaml`): `dev`, `login`
  (`^/api/login`, `json_login` with `email`/`password`), `api` (`^/api`,
  stateless, `jwt: ~`).
- User provider: `AppUser` by `email`. `getRoles()` always guarantees
  `ROLE_USER`.
- **Role hierarchy**: `ROLE_SUPER_ADMIN ⊃ {ADMIN, VIEW_MESSAGE, USER}`;
  `ADMIN ⊃ USER`; `VIEW_MESSAGE ⊃ USER`. **`ADMIN` does NOT inherit
  `VIEW_MESSAGE`.**
  > Trap: `AppUserRole::inheritedRoles()` re-declares this hierarchy in PHP but
  > has **no callers** — `security.yaml` is the source of truth. The two can
  > drift; don't rely on the enum method.
- Access control: `^/api/login` and `^/api/public` are `PUBLIC_ACCESS`;
  everything else under `^/api` needs `ROLE_USER`, refined by `IsGranted`.
- **CORS is wide open** (`nelmio_cors.yaml`: `allow_origin: ['*']`,
  `allow_credentials: false`) — fine because auth is a Bearer header, not
  cookies. Revisit before adding any cookie-based flow.

## Other cross-cutting conventions

- **Custom validators** follow `<Name>` constraint + `<Name>Validator`
  (auto-wired). `PhoneNumber` uses `libphonenumber` and **treats null/empty as
  valid** — pair with `NotBlank` if required.
- **Dates arrive as strings** in Commands and are converted with
  `new DateTimeImmutable(...)` inside UseCases. No format validation beyond
  `NotBlank` — a malformed date throws and becomes a **500** (see the trap).
- **External services bind to an interface** — `HelloAssoClientInterface` →
  `HelloAssoClient` in prod, `FakeHelloAssoClient` in tests
  (`services.yaml`). Follow this for any third-party API so tests never hit the
  network.
- **Mandatory generic docblock** on UseCases: `@extends AbstractUseCase<Cmd>` —
  keep it accurate, it's what makes `TCommand` meaningful for static analysis.
- Test-env overrides live inline under `when@test:` (cheap password hashing,
  in-memory Bunny zone via `FakeBunnyStorageClient`, silenced logging). Note that
  redefining a service there **drops the autowiring** inherited from the `App\`
  resource — put `autowire: true` back when you only mean to swap one argument.
