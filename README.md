# Collaborative Task Management API (Symfony 8)

A production-minded REST API for managing projects, tasks, comments, and
notifications — built on Symfony 8 / PHP 8.4.

The goals behind this implementation were:

- **Clean architecture** — thin controllers, rich services, explicit DTOs,
  repositories isolated from the transport layer.
- **Deliberate use of design patterns** — Repository, Decorator (cache), Strategy
  (search, notification channels), and Observer (event subscribers) each solve
  a concrete problem rather than existing for their own sake.
- **Operational readiness** — Docker, Redis cache, async workers, rate limiting,
  global error handling, OpenAPI docs, and a CI pipeline that enforces quality.

---

## Table of contents

1. [Stack](#stack)
2. [Quickstart with Docker](#quickstart-with-docker)
3. [Quickstart without Docker](#quickstart-without-docker)
4. [Running tests and quality gates](#running-tests-and-quality-gates)
5. [API reference (curl)](#api-reference-curl)
6. [Architecture](#architecture)
7. [Design patterns applied](#design-patterns-applied)
8. [Trade-offs and what I would do next](#trade-offs-and-what-i-would-do-next)

---

## Stack

| Concern            | Technology                                                |
| ------------------ | --------------------------------------------------------- |
| Framework          | Symfony 8, PHP 8.4                                        |
| ORM                | Doctrine ORM 3 + Doctrine Migrations                      |
| Database           | PostgreSQL 16 (SQLite used only for the test suite)       |
| Auth               | JWT via `lexik/jwt-authentication-bundle` (stateless API) |
| Authorization      | Symfony Voters + role-based access control                |
| Cache              | Redis (tagged pool for task listings)                     |
| Async jobs         | Symfony Messenger (Doctrine transport, `worker` service)  |
| Real-time          | Symfony Mercure (per-user subscribe tokens)               |
| Full-text search   | PostgreSQL `tsvector` + GIN index; pluggable Elastic      |
| API docs           | Nelmio API Doc Bundle (Swagger UI)                        |
| Rate limiting      | `symfony/rate-limiter` (token bucket per endpoint)        |
| Tests              | PHPUnit 13, DAMA/DoctrineTestBundle, WebTestCase          |
| Static analysis    | PHPStan level 6                                           |
| Coding standards   | PHP-CS-Fixer (PSR-12 + Symfony)                           |
| CI                 | GitHub Actions (quality + matrix tests + Docker build)    |

---

## Quickstart with Docker

Everything — Postgres, Redis, Mercure, the Messenger worker, and the API itself
— is orchestrated through `compose.yaml`.

```bash
# from the repository root
cd task-api-symfony

# build the image and start the stack in the background
docker compose up -d --build

# run migrations against the containerised database
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# generate the JWT keypair (only needed once)
docker compose exec app php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

Services:

| Service         | Port   | Purpose                                             |
| --------------- | ------ | --------------------------------------------------- |
| `web` (nginx)   | `8080` | Public entrypoint                                   |
| `app` (php-fpm) | —      | Symfony application                                 |
| `worker`        | —      | Messenger consumer for async notifications          |
| `database`      | —      | PostgreSQL 16                                       |
| `redis`         | —      | Tagged cache pool for task listings                 |
| `mercure`       | `1337` | Mercure hub for real-time notification delivery     |
| `elasticsearch` | —      | Optional. Enabled with `--profile elastic`.         |

Once up, the API is reachable at [http://localhost:8080/api](http://localhost:8080/api)
and Swagger UI at [http://localhost:8080/api/docs](http://localhost:8080/api/docs).

To enable Elasticsearch-backed search, start the profile and flip the driver:

```bash
docker compose --profile elastic up -d elasticsearch
docker compose exec app sh -c 'APP_ENV=dev SEARCH_DRIVER=elastic php bin/console cache:clear'
```

## Quickstart without Docker

Requirements: PHP 8.4, Composer 2, PostgreSQL 16, Redis (optional — falls back
to the filesystem cache in dev).

```bash
cd task-api-symfony
composer install
cp .env .env.local   # then edit DATABASE_URL, REDIS_URL, etc.

php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate --no-interaction

symfony server:start --no-tls --port=8000
```

The Messenger worker must be run separately for async notifications:

```bash
php bin/console messenger:consume async -vv
```

---

## Running tests and quality gates

```bash
# Unit tests (fast, no DB)
php vendor/bin/phpunit --testsuite=unit

# Integration tests (WebTestCase, SQLite)
php vendor/bin/phpunit --testsuite=integration

# Everything
php vendor/bin/phpunit

# Static analysis
php -d memory_limit=1G vendor/bin/phpstan analyse

# Coding standards (dry run)
php vendor/bin/php-cs-fixer fix --dry-run --diff
```

The test bootstrap rebuilds the SQLite schema once per suite;
`DAMA\DoctrineTestBundle` then wraps each test in a transaction that is rolled
back afterwards, so tests stay isolated without expensive teardown.

---

## API reference (curl)

All endpoints accept and return JSON. Responses follow a consistent envelope:

```json
{ "success": true, "data": { /* ... */ } }
{ "success": false, "error": "message", "details": [ /* ... */ ] }
```

### Auth

**Register**

```bash
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alice@example.com",
    "password": "supersecret1",
    "name": "Alice",
    "role": "member"
  }'
```

**Login (returns JWT)**

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"supersecret1"}' \
  | jq -r '.token')
```

**Current user + Mercure subscribe token**

```bash
curl http://localhost:8080/api/me -H "Authorization: Bearer $TOKEN"
```

### Projects

```bash
curl -X POST http://localhost:8080/api/projects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Backend","description":"API project"}'

curl http://localhost:8080/api/projects -H "Authorization: Bearer $TOKEN"

curl -X POST http://localhost:8080/api/projects/1/members \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 2}'
```

### Tasks

```bash
# Create
curl -X POST http://localhost:8080/api/projects/1/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Ship it","status":"todo","due_date":"2026-05-01"}'

# List (filterable + cached)
curl "http://localhost:8080/api/projects/1/tasks?status=todo&sort=-due_date&page=1&per_page=20" \
  -H "Authorization: Bearer $TOKEN"

# Global full-text search
curl "http://localhost:8080/api/tasks/search?q=deploy" \
  -H "Authorization: Bearer $TOKEN"

# Assign
curl -X POST http://localhost:8080/api/tasks/1/assign \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"assignee_id": 2}'

# Update
curl -X PATCH http://localhost:8080/api/tasks/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"in-progress"}'
```

### Comments

```bash
curl -X POST http://localhost:8080/api/tasks/1/comments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Looks good to me"}'
```

### Notifications

```bash
# Unseen list
curl http://localhost:8080/api/notifications/unseen \
  -H "Authorization: Bearer $TOKEN"

# Mark one read
curl -X POST http://localhost:8080/api/notifications/1/read \
  -H "Authorization: Bearer $TOKEN"
```

For real-time delivery the client subscribes to the Mercure topic returned by
`/api/me` (`user/{id}/notifications`) using the `subscribe_token` JWT.

---

## Architecture

```
HTTP  ─▶ Controller  ─▶ Service  ─▶ Repository  ─▶ Doctrine
                           │
                           ├── EventDispatcher ─▶ Subscriber ─▶ Messenger ─▶ Worker
                           │                                                  │
                           │                                                  └─▶ NotificationChannel[]
                           └── CacheInvalidator (decorator)
```

**Controllers** (`src/Controller/Api`) are thin. They:

- Parse JSON into DTOs (`src/Dto/**`).
- Run Validator constraints declared on the DTO.
- Delegate to a service.
- Authorize via `denyAccessUnlessGranted()` backed by Voters.
- Return a standardized envelope through the `ApiJsonResponse` trait.

**Services** (`src/Service/**`) own business logic. They:

- Mutate aggregates, flush the EntityManager, and dispatch domain events.
- Never return Doctrine entities to controllers — always DTOs / arrays.
- Never touch the cache directly; they call `TaskListCacheInvalidatorInterface`.

**Repositories** (`src/Repository/**`) contain persistence logic and listing
queries with filters/sort/pagination. `TaskRepositoryInterface` isolates the
shape used by the cache decorator from Doctrine specifics.

**Security**

- Authentication is JSON-login via the Lexik JWT bundle; the API is stateless
  (`security.firewalls.api.stateless: true`).
- Authorization is expressed as three Voters (`ProjectVoter`, `TaskVoter`,
  `CommentVoter`). Role checks (`admin`, `reviewer`, `member`) compose with
  ownership and membership checks inside the voter — controllers don't
  reimplement that logic.

**Error handling**

A single `ApiExceptionSubscriber` converts every exception produced under
`/api/*` into the standard `{success, error, details}` envelope. Validation
failures, `AccessDeniedException`, authentication failures, HTTP exceptions,
and otherwise-uncaught errors are all normalized.

**Rate limiting**

`RateLimiterSubscriber` enforces token-bucket limits on the sensitive routes
(login, register, comment create, task search) using the built-in
`symfony/rate-limiter`. Tests raise the thresholds so they don't interfere with
deterministic runs.

**Real-time**

The Mercure hub is reverse-proxied at `/.well-known/mercure`. Clients call
`/api/me` to receive their personal topic and a per-user subscribe JWT minted
by `MercureTokenProvider` using the hub secret. The `MercureChannel` publishes
notifications onto that topic as soon as the worker picks up a
`SendNotificationMessage`.

---

## Design patterns applied

Every pattern below exists to solve a specific concrete problem.

### 1. Repository

Doctrine `ServiceEntityRepository` subclasses encapsulate all query logic.
`TaskRepository::findForListing` builds filter-aware queries that are unit
testable and replaceable behind `TaskRepositoryInterface`.

### 2. Decorator — caching

`CachedTaskRepository` (`src/Cache`) decorates `TaskRepository`, wrapping
`findForListing()` in a tagged cache pool (`cache.task_lists`, Redis in prod,
filesystem in dev). Writes in `TaskService` call `invalidateProject(int)` which
clears the tag for that project. Services never know there is a cache.

### 3. Strategy — search backends

`TaskSearchInterface` has two implementations:

- `PostgresTaskSearch` — uses `to_tsvector(...) @@ plainto_tsquery(...)` with a
  GIN index on production, and falls back to `LIKE` on SQLite for tests.
- `ElasticTaskSearch` — calls an Elasticsearch 8 cluster directly through
  `symfony/http-client` (no FOS bundle, smaller surface area).

`TaskSearchFactory` picks the implementation based on the `SEARCH_DRIVER`
environment variable.

### 4. Strategy — notification channels

`NotificationChannelInterface` is a service-tag point. `InAppChannel` persists
the notification record (used by the `/api/notifications` endpoints) and
`MercureChannel` pushes a JSON event to the user's Mercure topic. Adding Slack
or email would be a new channel — no other code has to change.

### 5. Observer — domain events

`TaskAssignedEvent`, `TaskUpdatedEvent`, and `CommentCreatedEvent` are
dispatched from services and caught by `NotificationSubscriber`, which converts
them into async `SendNotificationMessage` messages. This keeps notification
concerns out of the service layer entirely and lets us add more subscribers
(auditing, analytics, webhooks) without touching the services.

### 6. Voter (Symfony)

Authorization attributes (`PROJECT_VIEW`, `TASK_ASSIGN`, …) are resolved by
voters. Controllers simply ask `denyAccessUnlessGranted()`, which routes
through the Security component and the voters — no permission logic leaks
into controllers.

### 7. DTO / Command

Every write endpoint hydrates a DTO (`CreateTaskRequest`, `UpdateTaskRequest`,
`SaveCommentRequest`, …). The DTO is what `ValidatorInterface` validates, and
the service accepts the DTO — not the raw request — which keeps the HTTP layer
out of the business layer.

---

## Trade-offs and what I would do next

- **SQLite for tests.** The test suite uses SQLite so it runs without external
  services. This means the Postgres-specific `tsvector` path is only exercised
  in integration environments — the `LIKE` fallback keeps `PostgresTaskSearch`
  correct on SQLite but means you should still run the suite against Postgres
  before a release. Given more time I would add a Postgres-backed CI job in
  parallel with the SQLite one.
- **Cache invalidation granularity.** Listings are tagged per project. If a
  project is very hot we would eventually want finer tags (per filter-shape),
  or a short TTL combined with stale-while-revalidate.
- **Mercure delivery.** The `MercureChannel` publishes after the notification is
  persisted; we do not currently retry a failed hub publish. The Messenger
  failure transport catches it, but a Slack/email fallback channel would be a
  more robust "at least one delivery" strategy.
- **Role set.** The three roles (`admin`, `reviewer`, `member`) live in a PHP
  enum. A richer deployment would likely move permission granularity onto
  per-project memberships (owner/maintainer/viewer) — the Voters are the place
  to evolve that without rewriting controllers.
- **Search indexing.** `TaskSearchIndexer` hooks Doctrine lifecycle events to
  keep Elasticsearch in sync. For Postgres FTS that's a no-op because the
  index reads directly from the source table — a real deployment would add a
  materialized `tsvector` column and a trigger if query performance matters.
- **Coverage.** The current suite covers voters, DTOs, cache decorator,
  notification subscriber and all API endpoints end-to-end. Adding more
  service-level tests (covering edge cases in `ProjectService::addMember`,
  `TaskService::update` with partial payloads, etc.) would push coverage
  higher with little churn.

---

## Repository layout

```
src/
  Cache/                 # TaskListCacheInvalidatorInterface + Decorator
  Controller/Api/        # Thin HTTP layer
  Dto/                   # Request + Resource DTOs
  Entity/                # Doctrine entities
  Enum/                  # Value objects (UserRole, TaskStatus, NotificationType)
  Event/                 # Domain events
  EventListener/         # Doctrine listeners (search indexing)
  EventSubscriber/       # Rate limiting, exception handling, notifications
  Messenger/             # Async messages + handlers
  Notification/          # Strategy implementations (InApp, Mercure)
  Repository/            # Doctrine repositories + interfaces
  Search/                # PostgresTaskSearch, ElasticTaskSearch, Factory
  Security/Voter/        # ProjectVoter, TaskVoter, CommentVoter
  Service/               # Business logic (Auth, Project, Task, Comment, Notification, Mercure)

tests/
  Unit/                  # Pure unit tests (no kernel)
  Integration/           # WebTestCase end-to-end
```
# Task-api-Symfony
