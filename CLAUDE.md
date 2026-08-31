# Clapiers-Admin

Volleyball club admin app: **Symfony backend** (`backend/`, PHP in Docker) +
**Nuxt frontend** (`frontend/`). This file is the entry point; detailed
knowledge lives in [`docs/`](docs/README.md).

## Knowledge base — read the relevant doc before working in an area

- [`docs/architecture.md`](docs/architecture.md) — layered flow (Controller →
  Command → UseCase → Entity), the `AbstractUseCase` contract, auth. **Start here.**
- [`docs/licenses-and-payment.md`](docs/licenses-and-payment.md) — licence
  lifecycle, HelloAsso checkout/webhook.
- [`docs/members-and-mediatheque.md`](docs/members-and-mediatheque.md) — members
  & the média tree.
- [`docs/teams-and-users.md`](docs/teams-and-users.md) — roles, coach↔team,
  user↔member linking.
- [`docs/seasons-and-stats.md`](docs/seasons-and-stats.md) — season rollover,
  scoping, dashboard stats.
- [`docs/games-closures-contact-logs.md`](docs/games-closures-contact-logs.md).
- [`docs/testing.md`](docs/testing.md) — **the test workflow (hook-enforced).**

## Always-on rules (don't need to open a doc)

- **PHP only runs in Docker**: `docker compose exec php php bin/phpunit`,
  `docker compose exec php composer test`, etc.
- **Never throw a framework `HttpException` inside a UseCase `run()`** — only
  `UseCaseException($msg, $status)`. `execute()` turns anything else into a 500
  (real production bug, twice).
- **Counted licence = active membership** = status `VALIDEE`, `EN_PAIEMENT` or
  `PAYEE`; `SOUMISE`/`REFUSEE`/`REMBOURSEE` don't count. Every season stat/scope
  filters on those three.
- **Season = `AAAA-AAAA`, rolls over on 1 September.** Registration opens before
  September, so `createdAt` is a bad proxy for "this season" — use licence
  history.

## Testing (enforced by hooks in `.claude/settings.json`)

The suite runs automatically after `backend/src`/`backend/tests` edits and a
Stop guard blocks ending a turn with source changes but no test changes. Full
rules — the required cases for a new route, functional-vs-unit decision, coverage
target — are in [`docs/testing.md`](docs/testing.md). The short version:

- **Bug fix** → write the failing test first, then fix.
- **New route** → happy path + 401 + 403 + validation failure + 404.
- **New use case** → add it to `UseCaseGuardTest`'s provider.
- Target **≥95% line coverage** (currently 100%).
