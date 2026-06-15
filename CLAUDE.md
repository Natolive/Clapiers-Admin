# Testing guide (backend)

This file covers ONE topic: how to keep the backend test suite green, fast, and
at ≥95% line coverage when fixing bugs or building features. Read
`backend/README.md` for the human-facing summary.

## Running tests

PHP only exists inside Docker. From the repo root:

```bash
docker compose exec php php bin/phpunit                      # fast path (~2s)
docker compose exec php php bin/phpunit --filter testName    # one test
docker compose exec php composer test                        # + cache clear, DB create, migrations
docker compose exec php composer test:coverage               # + clover/HTML report in backend/var/coverage
```

Use `composer test` (not bare phpunit) after changing anything in
`backend/config/` or `.env.test`: the test kernel runs with debug=0 and does
NOT detect config changes by itself. Same trap for **validator constraints**
(`#[Assert\...]` attributes): their metadata is cached too — a new constraint
silently does nothing under bare phpunit until the cache is cleared.

Hooks in `.claude/settings.json` enforce this guide automatically:
- **Suite runner** (PostToolUse): re-runs the whole suite after every edit
  under `backend/src` or `backend/tests`, and runs `composer test` (cache
  clear + migrations) after edits to `backend/config/`, `.env.test` or
  `phpunit.dist.xml`. If it reports a failure, fix it before doing anything
  else — do not stack changes on a red suite.
- **Test reminder** (PostToolUse): after each `backend/src` edit, if nothing
  under `backend/tests` has changed yet, a reminder to add/update the
  matching test is injected.
- **Stop guard** (Stop): you cannot end a turn with uncommitted `backend/src`
  changes and zero `backend/tests` changes. Write the test, or if the change
  genuinely needs none (comment-only, pure rename), say so explicitly to the
  user — never skip silently.

## Architecture (backend/tests/)

- `Support/ApiTestCase.php` — base class: HTTP helpers (`getJson`, `postJson`,
  `uploadFile`…), `assertJsonResponse($status)` (returns decoded body, dumps it
  on failure), JWT auth without HTTP round-trip (`actingAsSuperAdmin()`,
  `actingAsAdmin()`, `actingAs($user)`), fake files (`fakePdf/fakePng/fakeCsv`).
- `Support/Builder/` — fluent builders with unique defaults:
  `$this->aUser()->admin()->managing($teamA, $teamB)->persist()`. Specify only
  what the test cares about; never set irrelevant fields.
- `Functional/` — one class per controller. This is the default place for any
  new test.
- `Unit/` — ONLY for branches unreachable over HTTP (see decision rule below).

Isolation: dama/doctrine-test-bundle wraps each test in a rolled-back
transaction. The DB starts empty every test — exact-count assertions are safe,
no cleanup code, no shared fixtures, ever.

## Non-negotiable rules

**New route** → add to the matching Functional class, minimum:
1. happy path asserting the response body shape (not just the status),
2. 401 unauthenticated,
3. 403 for the highest role that must NOT access it (role hierarchy:
   SUPER_ADMIN > ADMIN/VIEW_MESSAGE > USER),
4. validation failure (422 via MapRequestPayload, 400 via UseCaseException),
5. 404 for unknown ids.

**New use case** → add its class to `Unit/Application/UseCase/UseCaseGuardTest`
provider (covers the `Invalid command` guard generically). Throw
`UseCaseException($msg, $statusCode)` for errors — NEVER framework
HttpExceptions inside `run()`: `execute()` catches `\Throwable` and turns them
into 500s (this was a real production bug, twice).

**Bug fix** → write the failing test FIRST, watch it fail for the right
reason, then fix. The test is the proof; never commit a fix without one.

**Decision rule functional vs unit**: always functional (it also covers
routing, security, mapping, SQL) UNLESS the branch is shadowed in test env:
- recaptcha rejection (key forced empty → always bypassed) → stub `RecaptchaVerifier`
- mailer failure (`null://` never throws) → stub `MailerInterface`
- CSV files that libmagic won't sniff as text/csv (header-only, inconsistent
  columns) are rejected by the controller's MIME constraint before the use
  case runs → unit-test `ImportGamesUseCase` directly
- code behind a route-level `IsGranted` stricter than the use case's own check

Never write a unit test that duplicates an existing functional test.

## Coverage

Target: **≥95% lines** (currently 100%). Check with `composer test:coverage`
(text + HTML in `backend/var/coverage`; note it does NOT regenerate
`clover.xml` — pass `--coverage-clover` explicitly if you need it). If new
code drops coverage, add tests before committing — including a
`FixturesSmokeTest`-style check when touching fixtures. Keep multiline
ternaries out of `src/` (Xdebug attributes the closing line to neither
branch — extract a method instead, see `AbstractUseCase::errorDetails()`).
CI publishes the coverage badge from main.

## Known traps

- Deleting an entity through the API nulls the in-memory instance's id
  (shared identity map) → capture `$id = $entity->getId()` BEFORE the request.
- Emails: assert with `assertEmailCount()/getMailerMessage()` — the test
  transport records messages even though nothing is sent.
- Mailer/captcha/contact env vars come from `phpunit.dist.xml` (force) and
  `.env.test` (defaults). Container env vars override `.env.test` — force in
  phpunit.dist.xml when a value must win in tests.
- Same-second `createdAt` makes date-ordering assertions flaky → backdate one
  row with a DQL UPDATE (see `ContactMessageApiTest`).
- Tests authenticate via `actingAs*()` (real Lexik JWT, no login request).
  Only `AuthenticationTest` may call `/api/login`.
