# README.md

## Tests d'intégration API

La suite couvre **toutes les routes de l'API** (auth, public, users, teams, members,
games, salle closures, contact messages, stats) : cas nominaux, droits (401/403),
validation (400/422) et 404.

### Lancer les tests

PHP tourne dans Docker, tout passe par le conteneur `php` :

```bash
# Tout-en-un : purge le cache test, crée la base de test, migre, lance PHPUnit
docker compose exec php composer test

# PHPUnit seul (plus rapide ; relancer `composer test` après un changement
# de config — le kernel de test tourne en debug=0 et ne recompile pas seul)
docker compose exec php php bin/phpunit

# Un seul fichier / filtre
docker compose exec php php bin/phpunit tests/Functional/GameApiTest.php
docker compose exec php php bin/phpunit --filter testCoachCannotCreateGameForAnotherTeam

# Couverture de code (résumé console + rapport HTML dans var/coverage/index.html)
docker compose exec php composer test:coverage
```

### Architecture

```
tests/
├── Support/
│   ├── ApiTestCase.php          # Classe de base : client, auth JWT, helpers JSON/upload
│   └── Builder/                 # Builders fluides (pattern Object Mother)
│       ├── AppUserBuilder.php   #   $this->aUser()->admin()->managing($team)->persist()
│       ├── TeamBuilder.php
│       ├── MemberBuilder.php
│       ├── GameBuilder.php
│       ├── SalleClosureBuilder.php
│       └── ContactMessageBuilder.php
└── Functional/                  # Une classe de test par contrôleur
```

Points clés :

- **Isolation** : `dama/doctrine-test-bundle` enveloppe chaque test dans une
  transaction annulée à la fin — aucun nettoyage manuel, la base reste vierge.
- **Base dédiée** : l'environnement `test` suffixe le nom de la base (`clapiers_db_test`),
  cf. `when@test` dans `config/packages/doctrine.yaml`.
- **Auth sans HTTP** : `actingAs($user)` / `actingAsSuperAdmin()` génèrent un vrai JWT
  Lexik directement via le service, sans round-trip `/api/login` (le login lui-même est
  testé dans `AuthenticationTest`).
- **Données** : les builders fournissent des valeurs par défaut uniques ; ne préciser
  que ce qui compte pour le test.
- **Captcha / mails** : neutralisés par `phpunit.dist.xml`
  (`RECAPTCHA_SECRET_KEY=""`, `MAILER_DSN=null://null`).
