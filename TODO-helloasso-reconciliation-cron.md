# TODO — Cron de réconciliation des paiements HelloAsso (différé)

> Reporté volontairement. La feature de paiement des licences s'appuie d'abord
> sur le **webhook HelloAsso** comme source de vérité (avec son retry automatique
> jusqu'à 48 h). Ce cron est un **filet de sécurité additionnel**, à implémenter
> dans un second temps.

## Pourquoi

HelloAsso renvoie ses notifications selon `min(48h, 3 * 2^tentative)` si notre
endpoint ne répond pas `200`. C'est robuste, mais :

- il n'existe **aucun renvoi manuel** dans le back-office HelloAsso ;
- au-delà de 48 h (ou si une notif est définitivement perdue), une `License`
  peut rester bloquée en `EN_PAIEMENT` alors que le paiement a réussi.

Le cron rattrape ce cas résiduel.

## Ce que le cron doit faire

1. Sélectionner les `License` au statut `EN_PAIEMENT` dont le checkout-intent a été
   créé il y a plus de N minutes (ex. 30 min) et qui n'ont pas encore de `Payment`
   à l'état `Authorized`.
2. Pour chacune, appeler `HelloAssoClient.getCheckoutIntent($id)`
   (`GET /v5/organizations/{slug}/checkout-intents/{id}`).
3. Si l'order/payment est `Authorized` → exécuter **la même logique idempotente**
   que le webhook (créer/mettre à jour le `Payment`, passer la `License` à `PAYÉE`,
   synchroniser `Member.licensePaid`, e-mailer le reçu si pas déjà fait).
4. Si `Refused`/expiré → repasser la `License` à `VALIDÉE` (re-payable) ou marquer
   l'échec, selon la règle métier retenue.

## Implémentation envisagée

- Commande Symfony `app:helloasso:reconcile-payments` + planification
  (cron système / scheduler Symfony Messenger).
- **Réutiliser** le service de réconciliation partagé avec le webhook — ne pas
  dupliquer la logique. Idempotence sur `helloAssoOrderId` / `helloAssoPaymentId`.
- Borne de balayage configurable (env var), et logs sur chaque rattrapage.

## Pré-requis (déjà prévus dans la feature de base)

- `HelloAssoClient.getCheckoutIntent()` (sert aussi à vérifier l'authenticité du
  webhook).
- Service de réconciliation idempotent appelé par le webhook.
- Champ permettant de connaître l'instant de création du checkout-intent sur la
  `License` / le `Payment`.
