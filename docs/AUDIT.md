# Audit qualité — sylius-company-accounts-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`.
> La revue qualitative (sections 2-3) a été réalisée par l'orchestrateur (lecture directe des fichiers source).

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit` | ✅ 41 tests, 929 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 35 fichiers avec suggestions (`LocallyCalledStaticMethodToNonStaticRector`) |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 paquets abandonnés (`behat/transliterator`, `php-http/message-factory`) — transitifs Sylius, aucune advisory CVE |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 35 fichiers signalés relèvent principalement de `LocallyCalledStaticMethodToNonStaticRector` (convertir les méthodes `private static` en méthodes d'instance quand elles n'utilisent pas la liaison statique tardive). Ce sont des **suggestions de modernisation non bloquantes**, non appliquées. Lancer `make rector-fix` pour les appliquer puis relancer ECS/PHPStan/tests.

## 2. Audit qualitatif (revue directe)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | `declare(strict_types=1)` partout, classes de service `final`, value objects immuables, domaine isolé. |
| Intégration Symfony / Sylius | **8/10** | Voters `@extends Voter<string, Type>`, `onFlush`/`postFlush`/`onClear`/`ResetInterface`, verrouillage DBAL pessimiste, bridge optionnel propre. |
| Sécurité (OWASP 2025) | **8/10** | Isolation B2B forte (voters + scoped repositories), CSRF, DQL paramétré, PII minimisé dans les vues API. |

### Points forts

- `declare(strict_types=1)` + classes de service `final` sur tous les fichiers de `src/` (sauf entités, par convention Sylius).
- `CompanyVoter` et `OrderVoter` : isolation inter-entreprises robuste — vérification d'ID de company, membre actif, company active.
- `MemberChangeGuard` : règle du dernier admin en pure logique domaine (sans infrastructure) + `MemberUpdater` avec `LockMode::PESSIMISTIC_WRITE` contre les démotions concurrentes.
- `CompanyChangeListener` : événements dérivés du UoW Doctrine, abandonnés en cas de flush échoué (onFlush/postFlush), reset via `ResetInterface`.
- `ShopApiAccess` : point d'entrée unique pour les vérifications d'appartenance et de permission dans tous les providers/processors API shop.
- Repositories scopés (toutes les méthodes de lecture acceptent un `CompanyInterface`) → aucun accès cross-company possible par omission.
- `CompanyMemberView` et `CompanyOrderView` : DTOs `readonly` immutables, jamais d'email ni de numéro de téléphone des collègues.

## 3. Recommandations priorisées

### 🟠 Majeur

1. **Perte silencieuse d'événements sur exception listener** (`CompanyChangeListener.php:128-135`). Le bloc `finally { $this->pending = [] }` efface les événements non encore dispatchés si un listener lève une exception pendant la boucle de dispatch — aucun log, aucune retry. → Logger les événements perdus (ou les supprimer explicitement avec un warning) avant le nettoyage dans `finally`.

2. **Paquets abandonnés** (`composer audit` : `behat/transliterator`, `php-http/message-factory`). Ce sont des transitifs Sylius — non contrôlables directement, mais à surveiller lors des montées de version. → Ouvrir un ticket de suivi ; aucune action bloquante aujourd'hui.

### 🟡 Mineur

- **`createListQueryBuilderByCompanyId(int|string $companyId)`** (`CompanyMemberRepository.php:33`) : accepte un ID brut au lieu de `CompanyInterface`, incohérent avec `findByCompany`, `countEnabledAdmins`, `findIdsInCompany`, etc. → Aligner le paramètre sur `CompanyInterface`.
- **Cast non gardé** (`CompanyMemberView.php:65`) : `(int) $member->getId()` retourne `0` si l'entité n'est pas persistée — non atteignable depuis les providers actuels mais fragile. → Ajouter un guard ou documenter l'invariant.
- **`LocallyCalledStaticMethodToNonStaticRector` (35 fichiers)** : méthodes `private static` qui ne font pas appel à la liaison tardive (`normalize()`, `group()`, `value()`, `changes()`, etc.) → appliquer `make rector-fix`, puis relancer ECS/PHPStan/tests.
- Confirmer la compatibilité des API core 2.2/2.3 (notamment le format de réponse `OrderInterface::getCheckoutCompletedAt()` et la stabilité des état `OrderCheckoutStates`).

### ℹ️ Pour information (choix assumés)

- Entités `Company` et `CompanyMember` non `final` : convention Sylius obligatoire pour permettre l'extension par les projets.
- `CompanyOrderView::placedBy` expose le nom complet du collègue (`getFullName()`) : PII minimal, explicitement documenté, cohérent avec l'UX de la page orders.
- `CompositeCompanyOrderEligibilityChecker` retourne `false` si aucun checker n'est câblé — comportement sûr par défaut (pas d'enregistrement de commande d'entreprise silencieux).

## 4. Verdict

Plugin **solide et discipliné** : outillage au vert (ECS, PHPStan level max, 41 tests unitaires avec 929 assertions), typage strict, isolation B2B par voters + repositories scopés, verrouillage pessimiste pour les opérations concurrentes sur les membres. Les deux axes d'amélioration identifiés sont le **dispatch d'événements** (perte silencieuse sur exception) et le **refactoring des `static` privés** suggéré par Rector (35 fichiers). Rien de bloquant côté outillage ; une release « production-grade » peut être envisagée après traitement du point 🟠 n°1.
