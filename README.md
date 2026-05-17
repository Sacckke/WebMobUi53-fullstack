# My Social Network (MSN) — Système de sondage

> Travail pratique WebMobUI — Laravel 12 + Vue.js 3

---

## Lancer le projet rapidement

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Dans `.env`, vérifier / ajouter :
```env
DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000
```

```bash
# Créer la base SQLite (Windows)
New-Item -ItemType File -Path database\database.sqlite -Force

php artisan migrate

# Deux terminaux :
php artisan serve    # backend → http://localhost:8000
npm run dev          # frontend Vite
```

Créer un compte sur `/auth/register`, puis aller sur `/polls/dashboard`.

---

## Fonctionnalités du système de sondage

- Dashboard personnel : créer, éditer, supprimer, lancer ses sondages
- Paramètres : choix multiple, résultats publics, durée, modification du vote possible
- Lien de partage avec token unique pour les votants
- Page de vote publique accessible via le lien (pas besoin d'être le créateur)
- Résultats en direct via polling toutes les 5 secondes + graphique à barres
- Accès aux résultats conditionnel (propriétaire, résultats publics, déjà voté)
- Vote bloqué après la date de fin avec message clair

---

## Architecture frontend

Deux applications Vue.js distinctes, chacune montée sur un `div` dans une vue Blade :

**App 1 — Dashboard** (`poll-dashboard.js`) → `/polls/dashboard`
Gère la liste des sondages, la création et l'édition. Route protégée (auth requise).

**App 2 — Vote** (`poll-vote.js`) → `/polls/vote/{token}`
Affiche le formulaire de vote et les résultats. Route publique.

Ce choix évite un routeur Vue complexe : chaque app est simple et focalisée sur un seul usage.

### Composants
| Fichier | Rôle |
|---|---|
| `AppPollDashboard.vue` | Root dashboard (bascule liste ↔ formulaire) |
| `AppPollVote.vue` | Root page de vote + polling résultats |
| `components/PollCard.vue` | Carte d'un sondage avec actions |
| `components/PollForm.vue` | Formulaire création / édition |
| `components/PollResultsChart.vue` | Graphique à barres CSS |

### Store et composables
- **`usePollStore.js`** — ref singleton partagée entre les composants, évite le prop drilling
- **`useFetchApi.js`** — client HTTP (CSRF, headers, timeout) — fourni dans le projet de base
- **`usePolling.js`** — setInterval propre avec cleanup onUnmounted — fourni dans le projet de base

### Pourquoi un store plutôt que des props ?
`PollCard` et `AppPollDashboard` modifient la même liste. Sans store, il faudrait faire remonter les événements via plusieurs niveaux d'emit. Avec le store (ref hors fonction = singleton), les deux composants partagent directement la même référence réactive.

### Endpoints API utilisés
```
GET    /api/v1/polls              → liste des sondages (auth)
POST   /api/v1/polls              → créer un sondage (auth)
PUT    /api/v1/polls/{id}         → modifier / lancer (auth)
DELETE /api/v1/polls/{id}         → supprimer (auth)
GET    /api/v1/polls/{token}      → afficher un sondage (public)
GET    /api/v1/polls/{token}/results → résultats (public si activé)
POST   /api/v1/polls/{token}/vote → voter (auth)
```

---

# HEIG-VD DévProdMéd Course - Mini-projet

Ce dépôt contient le mini-projet à réaliser dans le cadre du cours
_"[Développement de produit média (DévProdMéd)](https://github.com/heig-vd-devprodmed-course/heig-vd-devprodmed-course)"_
enseigné à la
[Haute Ecole d'Ingénierie et de Gestion du Canton de Vaud (HEIG-VD)](https://heig-vd.ch),
Suisse.

## Objectif du mini-projet

L'objectif de ce mini-projet est de créer un réseau social simple en utilisant le
framework [Laravel](https://laravel.com/). Ce projet permettra de mettre en pratique les concepts
appris dans le cours.

## Pré-requis

Afin de lancer ce projet, une stack compatible avec Laravel, est requise.

Voici les pré-requis nécessaires :

- PHP >= 8.0.
- Composer.
- Node.js et npm.
- Une base de données (MySQL, PostgreSQL, SQLite, etc.).
- Un serveur web (Apache, Nginx, etc.).

[Laravel Herd](https://helm.sh/docs/charts/laravel/) est recommandé pour une installation facile de Laravel et de ses dépendances.

## Développement local

Pour développer et tester le mini-projet en local, voici les étapes à suivre :

1. Forker ce dépôt

2. Installer les dépendances avec npm et Composer :

    ```bash
    npm install && npm run build

    composer install
    ```

3. Copier le fichier `.env.example` en `.env`.
4. Modifier les variables d'environnement si nécessaire (optionnel).
5. Générer la clé d'application Laravel :

    ```bash
    php artisan key:generate
    ```

6. Créer le lien symbolique pour les fichiers téléversés :

    ```bash
    php artisan storage:link
    ```

7. Créer la base de données et exécuter les migrations :

    ```bash
    php artisan migrate
    ```

    S'il est nécessaire de réinitialiser la base de données, utiliser la commande `php artisan migrate:reset` puis `php artisan migrate` à nouveau.

8. Optionnel : en mode développement, il est possible de peupler la base de données avec des données fictives :

    ```bash
    php artisan db:seed
    ```

9. Démarrer le serveur de développement Laravel :

    ```bash
    composer run dev
    ```

L'application sera accessible à l'adresse <http://127.0.0.1:8000>.
