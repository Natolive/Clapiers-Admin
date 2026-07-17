# Clapiers-Admin — backend knowledge base

Domain and architecture notes for the Symfony backend. These capture the
**non-obvious** rules, state machines and traps — read the code for the rest.

Each claim references `file:line` so you can jump straight to the source.

## Index

- [architecture.md](architecture.md) — layered flow (Controller → Command →
  UseCase → Entity), the `AbstractUseCase` contract, `UseCaseException`, auth
  stack, entity conventions. **Read this first.**
- [licenses-and-payment.md](licenses-and-payment.md) — licence lifecycle,
  public submission, admin approval/rejection + re-registration merge,
  HelloAsso checkout & webhook.
- [members-and-mediatheque.md](members-and-mediatheque.md) — member entity &
  statuses, the médiathèque tree (folders/documents), file storage, media
  operations.
- [teams-and-users.md](teams-and-users.md) — roles, coach↔team assignment,
  user↔member linking, my-team downloads, auth/password handling.
- [seasons-and-stats.md](seasons-and-stats.md) — season format & September
  rollover, season scoping, dashboard stats.
- [games-closures-contact-logs.md](games-closures-contact-logs.md) — games &
  history audit, CSV import, salle closures, contact messages, DB logging.

## Cross-cutting rules worth memorising

- **Never throw framework `HttpException` inside a UseCase `run()`** — only
  `UseCaseException($msg, $status)`. Anything else becomes a 500. See
  [architecture.md](architecture.md#the-trap).
- **Counted licence = active membership** = status `VALIDEE`, `EN_PAIEMENT` or
  `PAYEE`. `SOUMISE`/`REFUSEE`/`REMBOURSEE` don't count. Every season stat and
  scope filters on those three.
- **Season = `AAAA-AAAA`, rolls over on 1 September.** Registration opens
  *before* September, so `createdAt` is a bad proxy for "this season" — use
  licence history.
- Testing rules are enforced by hooks — see the project root `CLAUDE.md` and
  [testing.md](testing.md).
