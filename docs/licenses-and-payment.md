# Licences & payment

## Lifecycle

`LicenseStatus` (`Entity/Enum/LicenseStatus.php`): `SOUMISE`, `VALIDEE`,
`REFUSEE`, `EN_PAIEMENT`, `PAYEE`, `REMBOURSEE`. New licence defaults to
`SOUMISE`.

| From | To | Trigger | File |
|------|----|---------|------|
| (new) → `SOUMISE` | public submission | `SubmitLicenseRequestUseCase.php:61` |
| `SOUMISE` → `VALIDEE` | admin approve | `ApproveLicenseUseCase.php:76` |
| `SOUMISE` → `REFUSEE` | admin reject | `RejectLicenseUseCase.php:51` |
| `VALIDEE`/`EN_PAIEMENT` → `EN_PAIEMENT` | public checkout | `CreateCheckoutUseCase.php:64` |
| `EN_PAIEMENT` → `PAYEE` | webhook, HelloAsso `Authorized` | `HandleHelloAssoWebhookUseCase.php:110` |
| `EN_PAIEMENT` → `VALIDEE` (rollback) | webhook, HelloAsso `Refused` | `HandleHelloAssoWebhookUseCase.php:120` |

**Counted as active membership** (`MemberRepository.php:59-61`): member
`status = ACTIVE` **and** a licence in `[VALIDEE, EN_PAIEMENT, PAYEE]`. So
**`VALIDEE` (unpaid) and `EN_PAIEMENT` already count** — payment is not required
to appear as a member. `SOUMISE`/`REFUSEE`/`REMBOURSEE` never count. Member
status is coupled to licence transitions: `PENDING_VALIDATION` on submit,
`ACTIVE` on approve, `REJECTED` on reject.

Invariants / traps:

- **`REMBOURSEE` and `PaymentState::REFUNDED` are dead code** — never set
  anywhere. Refunds are entirely unimplemented server-side (no admin action, no
  webhook branch, no client method). The docblock's refund path is aspirational.
- **Approve and reject both guard strictly on `SOUMISE`**
  (`ApproveLicenseUseCase.php:65`, `RejectLicenseUseCase.php:47`) → **409**
  otherwise. One-way: no un-approve, un-reject, or re-review.
- A refused payment resets to `VALIDEE` (not `EN_PAIEMENT`), so the member can
  retry checkout (`CreateCheckout` accepts both `VALIDEE` and `EN_PAIEMENT`).
- One licence per member per season is assumed (`findOneBy(member, season)`) but
  **not enforced at the DB level** — only the merge path checks it.
- Money is **centimes** end-to-end; euros only in email formatting.

## Public submission

- `POST /api/public/license-request` (`PublicController.php:43`, `PUBLIC_ACCESS`).
- **Recaptcha enforced** — but `RecaptchaVerifier` returns `true` when
  `RECAPTCHA_SECRET_KEY` is empty (dev/test bypass). Trap: an unset key in prod
  silently disables captcha.
- Creates **both a new `Member` and a `License`** in one shot — always a fresh
  Member even for re-registrations; de-duplication is deferred to approval (see
  merge below).
- `accessToken` (magic-link) is generated at submission
  (`bin2hex(random_bytes(32))` = 64 hex, unique) but **`tokenExpiresAt` is set
  only at approval** — the token has no expiry during the upload phase, and in
  fact **is never checked anywhere** (see security).
- `healthDeclaration = true` means "answered NO to all health questions → no
  medical certificate needed" (defaults `false`).
- Legal representative is always stored (empty strings when adult); "required if
  minor" is **frontend-only** — the backend does not validate minority.

### Document upload

- `POST /api/public/license-request/{token}/document/{systemKey}`, `systemKey ∈
  {identity_photo, id_card, medical_certificate, attestation}` (regex-constrained).
- Constraint: max **5M**, mimetypes `application/pdf`, `image/png`, `image/jpeg`.
  `identity_photo` must be an image (PDF rejected).
- **Routes into the member's médiathèque** (there is no separate "request
  document" store): `identity_photo`/`id_card` → root "Identité" folder
  (season-independent); `medical_certificate`/`attestation` → the licence's
  season folder (`UploadLicenseRequestDocumentUseCase.php:27-28`).
- Uploading **deletes the previous file in the slot first** (overwrite, not
  versioned).
- `medical_certificate` upload also stamps `license.medicalCertificateFileName`
  as a denormalised "certificat déposé" admin badge (only for that slot).

### Tiers / pricing

- `GET /api/license/tiers` (admin) → `HelloAssoClient::getFormTiers()` reads the
  **`tiers`** key of the public HelloAsso form endpoint (not `items` = sold
  articles), maps to `{id, label, amount}` (amount = `price`), cached 10 min.
- **Pricing is not derived** — at approval the admin picks a tier **and types
  the amount manually** (`ApproveLicensePayload`, both `Assert\Positive`). Amount
  (centimes) is frozen onto the licence. **No server-side check that the amount
  matches the tier's price.**

## Approval / rejection

Whole `LicenseController` is `#[IsGranted(ROLE_SUPER_ADMIN)]` — approve, reject,
review, tiers and the paginated list are **super-admin only**.

**Approve** (`ApproveLicenseUseCase.php:74-88`): freeze `helloAssoTierId` +
`amount`; status → `VALIDEE`; `approvedAt`; ensure `accessToken`; set
`tokenExpiresAt = now + 30 days`; member → `ACTIVE`; send payment-link email.

- Magic link = `{APP_FRONTEND_URL}/licence/{accessToken}`. Note **`/licence/`
  (French)** here and in the checkout `backUrl`/`returnUrl`, whereas the API
  path is `/license/` (English) — the frontend must route `/licence/`.
- Email failure is caught & logged only — **does not roll back approval**.

**Re-registration merge** (`replaceMemberId`, `ApproveLicenseUseCase.php:69-135`)
— the subtle part. The admin explicitly chooses to merge into an existing member
(surfaced by `GetLicenseReview` via `findOneByEmailExcluding`, same email):

1. Guard: existing member's email must case-insensitively match the request
   (else 422) and must not already have a licence this season (else 409).
2. **Move uploaded files** from the duplicate member's slots into the existing
   member's slots (root `identity_photo`/`id_card`, season
   `medical_certificate`/`attestation`), deleting any file already in the
   destination.
3. Copy coordinates onto the existing member, repoint the licence to it, then
   **delete the duplicate source member** (DB cascade drops its now-empty
   médiathèque rows — the moved physical files live under the target's slots and
   are untouched).

**Reject** (`RejectLicenseUseCase.php:51-57`): status → `REFUSEE`, store
free-text `rejectionReason`, member → `REJECTED`, send rejection email (failure
logged only). No token/media cleanup.

## HelloAsso payment

### Checkout (`CreateCheckoutUseCase`)

- `POST /api/public/license/{token}/checkout` (public).
- Guards: licence found by `accessToken`; status `VALIDEE` or `EN_PAIEMENT`;
  amount set & > 0. **No `tokenExpiresAt` check** — an expired token still works.
- Builds a HelloAsso checkout-intent with **`metadata: {licenseId, memberId}`**
  — this is what the webhook filters on. Persists a `Payment` in state
  `WAITING`, flips licence to `EN_PAIEMENT`, returns `{redirectUrl}`.
- **Every checkout call creates a NEW `Payment` row** — repeated attempts leave
  multiple `WAITING` payments; reconciliation uses "most recent `WAITING`"
  (`PaymentRepository.php:27-33`, `ORDER BY id DESC`).

### Webhook (`HandleHelloAssoWebhookUseCase`)

- `POST /api/public/helloasso/webhook` (public). The URL is org-global, so
  unrelated events are expected and ignored with 200.
- **No signature / HMAC verification.** Security rests on (a) filtering by
  `metadata.licenseId` and (b) **re-fetching truth from HelloAsso** rather than
  trusting the payload. A forged webhook can't fake a payment (state is
  re-confirmed via API) but there is no signature check — know this before
  relying on it.
- Flow: ignore unless `eventType === 'Payment'`; extract `licenseId` from
  metadata; **idempotency** via unique `helloAssoPaymentId` column — an already
  `AUTHORIZED` payment short-circuits to `already_processed`; find the `WAITING`
  payment; **call `getCheckoutIntent()` and read the confirmed state from
  `order.payments[]`**, never the payload. Map: `Authorized` → Payment
  `AUTHORIZED` + receipt email + licence `PAYEE`; `Refused` → Payment `REFUSED`
  + licence back to `VALIDEE`; else ignored. Full `rawPayload` stored for audit.
- **Idempotency gotcha**: only the `AUTHORIZED` terminal case is guarded. A
  repeated `Refused` re-writes state each time; once a payment leaves `WAITING`,
  later legitimate webhooks find nothing and are ignored.

### HelloAsso client

- OAuth2 client-credentials; access token cached ~25 min (under the 30 min
  validity). All HTTP/OAuth failures → `UseCaseException` **502** "Erreur de
  communication avec HelloAsso".
- Interface methods: `createCheckoutIntent`, `getCheckoutIntent`,
  `getFormTiers`. **No refund method.**

## Security summary

- Admin surface (`/api/license/*`) is `ROLE_SUPER_ADMIN` (not `ADMIN`).
- Payment portal / checkout / document upload are gated **only by the opaque
  64-char `accessToken`** — no auth, and the token **never expires in practice**
  (`tokenExpiresAt` is stored but checked nowhere).
- `GetLicenseForPayment` returns a **minimal projection** (status, season,
  amount, first/last name) — no address/PII.
