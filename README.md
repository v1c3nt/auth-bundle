# vvbx/suite-bundle

Briques communes des applications Symfony de la suite vvbx. Aujourd'hui : connexion SSO OIDC (code + PKCE) et comptes désactivables.

- OIDC standard uniquement : endpoints lus dans `/.well-known/openid-configuration`, claims `sub`, `email`, `email_verified` lus sur `userinfo`. Aucun claim propre à un éditeur d'IdP.
- SSO optionnel : sans `OIDC_ISSUER` ou `OIDC_CLIENT_ID`, les routes SSO répondent 404 et l'app garde sa connexion locale.
- Compte rattaché au `sub`, jamais à l'e-mail. À la première connexion, un compte pré-autorisé sans `sub` est rattaché par son e-mail **vérifié** ; un compte déjà lié à un autre `sub` n'est jamais repris. Un inconnu n'a aucun droit.
- Les rôles restent dans l'app : le bundle ne fournit pas l'entité `User`.

## Installation

```bash
composer require vvbx/suite-bundle
```

```php
// config/bundles.php
Vvbx\SuiteBundle\VvbxSuiteBundle::class => ['all' => true],
```

```yaml
# config/packages/vvbx_suite.yaml
vvbx_suite:
    user_class: App\Entity\User
    # email_field: email        # propriété qui porte l'e-mail
    # target_route: admin       # après une connexion SSO réussie
    # login_route: app_login    # où afficher les erreurs SSO

# config/routes/vvbx_suite.yaml
vvbx_suite:
    resource: '@VvbxSuiteBundle/config/routes.php'
```

```yaml
# config/packages/security.yaml
security:
    providers:
        app_user_provider:
            id: Vvbx\SuiteBundle\Security\UserProvider
    firewalls:
        main:
            provider: app_user_provider
            user_checker: Vvbx\SuiteBundle\Security\UserChecker
            form_login: { login_path: app_login, check_path: app_login, enable_csrf: true }
            custom_authenticators:
                - Vvbx\SuiteBundle\Security\OidcAuthenticator
            entry_point: form_login
    access_control:
        - { path: ^/oidc/, roles: PUBLIC_ACCESS }
```

```dotenv
# vides = SSO désactivé
OIDC_ISSUER=
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=
# facultatif : scopes ajoutés à « openid email », séparés par des espaces (ex. organisation du client chez l'IdP)
OIDC_EXTRA_SCOPES=
```

## Contrat de l'app

- **Entité** : implémente `OidcUserInterface` (avec `OidcSubjectTrait` pour la colonne) et `ActivatableUserInterface`, et déclare l'unicité du `sub` :

  ```php
  #[ORM\UniqueConstraint(name: 'UNIQ_OIDC_SUBJECT', fields: ['oidcSubject'])]
  class User implements OidcUserInterface, ActivatableUserInterface, PasswordAuthenticatedUserInterface
  {
      use OidcSubjectTrait;
  ```

- **Comptes** : un service qui implémente `AccountManagerInterface` (rôles attribuables, pré-autorisation d'un compte par e-mail). L'app y applique ses propres règles (ex. nombre d'administrateurs) et lève `AccountException` en cas de refus.
- **Page de connexion** : bouton SSO si `vvbx_sso_enabled()`, vers `path('vvbx_suite_oidc_login')`. Les erreurs arrivent comme celles du formulaire (`error.messageKey|trans(error.messageData, 'security')`), traductions `oidc.*` et `user.inactive` fournies (surchargeables).

## Ce que fournit le bundle

| Classe | Rôle |
| --- | --- |
| `Oidc\OidcClient` | découverte, URL d'autorisation PKCE, échange du code, `userinfo` |
| `Security\OidcAuthenticator` | retour de l'IdP : `state`, claims, rattachement du compte |
| `Security\UserProvider` | chargement par e-mail ; un compte inactif perd sa session à la requête suivante |
| `Security\UserChecker` | refuse la connexion d'un compte inactif (mot de passe et SSO) |
| `Command\PreauthorizeUserCommand` | `vvbx:user:preauthorize <email> <role>`, relançable, appelée au provisioning |
| `Testing\FakeIdp` | IdP simulé pour les tests de l'app (`mock_response_factory`) |

## Identité de la structure

`Vvbx\SuiteBundle\Identity\Identity` est un embeddable Doctrine : l'identité de la structure cliente, de même forme dans toutes les apps de la suite. Le bundle déclare son mapping auprès de Doctrine ; l'app l'intègre dans l'entité de son choix :

```php
use Vvbx\SuiteBundle\Identity\Identity;

#[ORM\Embedded(class: Identity::class, columnPrefix: false)]
#[Assert\Valid]
private Identity $identity;
```

| Champ | Obligatoire | Format |
| --- | --- | --- |
| `name` | oui | 150 |
| `shortName` | non | 80, nom d'usage (`getDisplayName()` le préfère au nom officiel) |
| `acronym` | non | 20 |
| `street` | oui | 255, plusieurs lignes possibles |
| `postalCode` | oui | 5 chiffres (France) |
| `city` | oui | 100 |
| `email` | oui | e-mail, 180 (contact public, imprimé sur les documents) |
| `phone` | non | 20, format libre |
| `website` | non | URL, 255 |
| `siren` | non | 9 chiffres |
| `rna` | non | `W` suivi de 9 chiffres |

- Colonnes nullables : une installation neuve démarre vide, l'obligation est portée par la validation.
- Saisie normalisée par les setters : espaces de bord retirés, chaîne vide ramenée à `null`, espaces retirés du SIREN, du RNA et du code postal, RNA en majuscules.
- `getPostalAddress()` : voie, puis code postal et ville, sur plusieurs lignes.
- Une exigence propre à un module (ex. SIREN obligatoire pour délivrer des reçus fiscaux) reste dans l'app.
- Messages de validation dans le domaine `validators` (`identity.*`), surchargeables par l'app.

### SIREN hors de l'identité

`Vvbx\SuiteBundle\Validator\Siren` est la seule définition du format du SIREN (9 chiffres), utilisée par `Identity` et par toute entité d'une app qui porte un SIREN (ex. entreprise donatrice) :

```php
use Vvbx\SuiteBundle\Validator\Siren;

#[ORM\Column(length: 9, nullable: true)]
#[Siren]
private ?string $siren = null;

public function setSiren(?string $siren): static
{
    $this->siren = Siren::normalize($siren); // « 123 456 789 » accepté, chaîne vide = null
    return $this;
}
```

La valeur nulle est acceptée : l'obligation reste à l'app. Message : `identity.siren.invalid`.

## Tests de l'app

```yaml
# config/packages/framework.yaml
when@test:
    framework:
        http_client:
            mock_response_factory: Vvbx\SuiteBundle\Testing\FakeIdp

# config/services.yaml
when@test:
    services:
        Vvbx\SuiteBundle\Testing\FakeIdp: ~
```

```dotenv
# .env.test
OIDC_ISSUER=https://idp.test
OIDC_CLIENT_ID=app-test
OIDC_CLIENT_SECRET=secret-test
```

## Développement

```bash
composer install
composer test    # PHPUnit
composer stan    # PHPStan niveau 6
composer cs      # php-cs-fixer @Symfony (cs:fix pour appliquer)
```

## Licence

MIT
