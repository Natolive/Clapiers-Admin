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
- **Gated by the `inscriptions_form_open` setting** — closed → **403** avant
  tout le reste (`SubmitLicenseRequestUseCase`). À ne pas confondre avec
  `inscriptions_open`, purement indicatif (voir « Réglages d'inscription »).
- **Recaptcha enforced** — but `RecaptchaVerifier` returns `true` when
  `RECAPTCHA_SECRET_KEY` is empty (dev/test bypass). Trap: an unset key in prod
  silently disables captcha.
- Creates **both a new `Member` and a `License`** in one shot — always a fresh
  Member even for re-registrations; de-duplication is deferred to approval (see
  merge below).
- `accessToken` (magic-link) is generated at submission
  (`bin2hex(random_bytes(32))` = 64 hex, unique) but **`tokenExpiresAt` is set
  only at approval** — `tokenExpiresAt = null` means « pas d'échéance », ce qui
  laisse la phase de dépôt des pièces ouverte juste après la soumission.
- `healthDeclaration = true` means "answered NO to all health questions → no
  medical certificate needed" (defaults `false`).
- Legal representative is always stored (empty strings when adult); "required if
  minor" is **frontend-only** — the backend does not validate minority.

### Document upload

- `POST /api/public/license-request/{token}/document/{systemKey}`, `systemKey ∈
  {identity_photo, id_card, medical_certificate, attestation}` (regex-constrained).
- Constraint: max **6Mi** (unité binaire — Symfony lit `6M` comme 6 000 000
  octets, or le formulaire plafonne à 5 MiB ; le serveur garde un cran de
  marge), mimetypes = `MemberMediaStorage::MIME_TYPES` (`application/pdf`,
  `image/png`, `image/jpeg`, `image/webp`, `image/heic`, `image/heif`).
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
- **Pricing is not derived server-side** — le payload d'approbation porte le
  tarif **et** le montant (`ApproveLicensePayload`, both `Assert\Positive`) ; la
  modale admin envoie le prix du tarif choisi. Amount (centimes) is frozen onto
  the licence. **No server-side check that the amount matches the tier's
  price.**

## Approval / rejection

Whole `LicenseController` is `#[IsGranted(ROLE_SUPER_ADMIN)]` — approve, reject,
review, tiers and the paginated list are **super-admin only**.

**Approve** (`ApproveLicenseUseCase`): freeze `helloAssoTierId` + `amount`;
status → `VALIDEE`; `approvedAt`; ensure `accessToken`; `extendTokenValidity()`
(= now + `License::TOKEN_VALIDITY`, 30 j); member → `ACTIVE`; send payment-link
email (via `LicensePaymentLinkMailer`, partagé avec le renvoi de lien).

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
- Guards: licence found by `accessToken`; **token non périmé (410)**; status
  `VALIDEE` or `EN_PAIEMENT`; amount set & > 0.
- Builds a HelloAsso checkout-intent with **`metadata: {licenseId, memberId}`**
  — this is what the webhook filters on. Persists a `Payment` in state
  `WAITING`, flips licence to `EN_PAIEMENT`, returns `{redirectUrl}`.
- **Every checkout call creates a NEW `Payment` row** — repeated attempts leave
  multiple `WAITING` payments. The webhook ne prend pas « le plus récent » : il
  demande à HelloAsso quel checkout-intent contient réellement le `paymentId`
  réglé (`HandleHelloAssoWebhookUseCase::resolvePayment`), sinon un intent ancien
  payé après un plus récent laisserait la licence encaissée en `EN_PAIEMENT`.

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
- **Toute sa configuration vient de `HelloAssoConfigProvider`**, c'est-à-dire de
  la table `setting` — **il n'y a plus de variable `HELLOASSO_*`** et aucune
  reprise automatique : la config se saisit dans Paramètres → Paiement en ligne
  (tant qu'elle est vide, checkout et tarifs répondent 502). Réglage absent ou
  vide = chaîne vide, sauf `baseUrl` qui retombe sur
  `HelloAssoConfigProvider::DEFAULT_BASE_URL` (`https://api.helloasso.com`).

| Champ API | Réglage (`setting.name`) |
|-----------|--------------------------|
| `baseUrl` | `helloasso_base_url` |
| `clientId` | `helloasso_client_id` |
| `clientSecret` | `helloasso_client_secret` |
| `organizationSlug` | `helloasso_organization_slug` |
| `membershipFormType` | `helloasso_membership_form_type` |
| `membershipFormSlug` | `helloasso_membership_form_slug` |

- `GET /api/settings/helloasso` (super-admin) renvoie tout **sauf le secret** —
  seulement `clientSecretDefined`. `PUT` n'applique que les champs non vides
  (secret vide = inchangé), refuse un corps vide (422), et **purge les caches
  `helloasso.access_token` / `helloasso.form_tiers`** : sans ça un changement
  d'identifiants resterait invisible ~25 min.

## Réglages du stockage de fichiers (table `setting`)

Même schéma, via `BunnyConfigProvider` (`src/Common/Service/`) — détails du
stockage dans [`members-and-mediatheque.md`](members-and-mediatheque.md) :

| Champ | Clé en base |
|-------|-------------|
| `storageUrl` | `bunny_storage_url` |
| `storageKey` | `bunny_storage_key` |

- `GET /api/settings/bunny` (super-admin) renvoie l'URL de la zone et
  `storageKeyDefined`, **jamais la clé**. `PUT` n'applique que les champs non
  vides (clé vide = inchangée) et refuse un corps vide (422). Pas de cache à
  purger : le stockage relit les réglages à chaque appel.

## Réglages d'inscription (table `setting`)

Deux drapeaux indépendants, ouverts par défaut (seule la valeur `'0'` ferme),
lus via `InscriptionsStatusProvider` :

| Clé | Effet |
|-----|-------|
| `inscriptions_open` | **Indicatif** : badge « inscriptions ouvertes / clôturées » de l'accueil (et son lien). N'empêche rien. |
| `inscriptions_form_open` | **Effectif** : page `/inscriptions` remplacée par « Inscriptions closes » et **403** sur `POST /api/public/license-request`. |

`GET /api/public/inscriptions-status` et `GET|PUT /api/settings/inscriptions`
renvoient les deux (`{open, formOpen}`) ; le PUT n'applique que les champs
fournis et refuse (422) un corps vide. L'upload de pièces reste ouvert quand le
formulaire est fermé : une demande déjà créée doit pouvoir finir de se déposer.

## Security summary

- Admin surface (`/api/license/*`) is `ROLE_SUPER_ADMIN` (not `ADMIN`).
- Payment portal / checkout / document upload are gated **only by the opaque
  64-char `accessToken`** — no auth, mais l'échéance est appliquée : les trois
  entrées publiques répondent **410 Gone** dès que `License::isTokenExpired()`
  (règle : `tokenExpiresAt !== null && < now`). Seul recours, côté admin :
  **`POST /api/license/{id}/resend-link`** (super-admin) qui régénère un token
  neuf + 30 j et renvoie l'e-mail — l'ancien lien meurt aussitôt. Refusé en
  **409** si la licence n'est pas `VALIDEE`/`EN_PAIEMENT`.
- `GetLicenseForPayment` returns a **minimal projection** (status, season,
  amount, first/last name) — no address/PII.
