# Inventory & Order Management System

> Symfony 7 · PHP 8.2+ · MySQL · Doctrine ORM · PHPUnit · JWT Authentication

A modular backend system for managing products, stock levels, and order workflows,
built as a learning project to strengthen Symfony architecture skills and apply
enterprise-grade backend patterns.

---

## Architecture

```
Modular Monolith with DDD-Inspired Layering
├── Module/Product/
│   ├── Domain/          ← Entities, VOs, Repository Interfaces, Domain Events, Exceptions
│   ├── Application/     ← Commands, Command Handlers, Queries, Query Handlers, DTOs
│   └── Infrastructure/  ← Doctrine Repository Implementations
├── Module/Inventory/    (same structure)
├── Module/Order/        (same structure)
├── Shared/
│   ├── Entity/User      ← Symfony Security user
│   ├── Exception/       ← Base ApiException → JSON error mapper
│   ├── EventListener/   ← Cross-cutting event handlers
│   └── Security/Voter/  ← RBAC voters (Product, Inventory, Order)
└── Controller/Api/V1/   ← HTTP layer only; depends on Application layer
```

**Key patterns applied:**
- SOLID: Single Responsibility per class, Dependency Inversion via Repository Interfaces
- CQRS-lite: Commands (write) and Queries (read) are separate objects with dedicated handlers
- Rich Domain Model: business rules live in Entity methods, not services
- Event-driven: domain events are collected by aggregates and dispatched by handlers after flush
- Optimistic locking on `InventoryItem` via Doctrine `@Version`
- No cross-module entity joins; Order references Product by UUID + snapshot fields only

---

## Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- OpenSSL (for JWT key generation)

### 1. Install

```bash
git clone <repo> inventory-system
cd inventory-system
cp .env.example .env
# Edit .env with your database credentials and JWT passphrase
make setup
```

### 2. Generate JWT Keys

```bash
make jwt-keys
```

Or manually:
```bash
mkdir -p config/jwt
openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
```

### 3. Run Migrations

```bash
make migrate
```

### 4. Start the Server

```bash
symfony serve
# or
php -S localhost:8000 -t public/
```

---

## API Reference

All endpoints are prefixed `/api/v1`. Protected routes require:
```
Authorization: Bearer <jwt_token>
```

---

### Authentication

#### Register
```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "SecurePass123",
  "fullName": "Alice Admin",
  "role": "ROLE_ADMIN"
}
```

#### Login (get JWT)
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "SecurePass123"
}
```
Response:
```json
{ "token": "eyJ0eXAiOiJKV1Qi..." }
```

#### Get Current User
```http
GET /api/v1/auth/me
Authorization: Bearer <token>
```

---

### Categories

```http
GET    /api/v1/categories           # List all (ROLE_VIEWER+)
GET    /api/v1/categories/{uuid}    # Get one  (ROLE_VIEWER+)
POST   /api/v1/categories           # Create   (ROLE_MANAGER+)
PUT    /api/v1/categories/{uuid}    # Update   (ROLE_MANAGER+)
DELETE /api/v1/categories/{uuid}    # Delete   (ROLE_ADMIN)
```

Create body:
```json
{ "name": "Electronics", "description": "Consumer electronics" }
```

---

### Products

```http
GET    /api/v1/products             # List (ROLE_VIEWER+) — ?page=1&limit=25&categoryUuid=...&sku=...&active=true
GET    /api/v1/products/{uuid}      # Get one (ROLE_VIEWER+)
POST   /api/v1/products             # Create (ROLE_MANAGER+)
PUT    /api/v1/products/{uuid}      # Update (ROLE_MANAGER+)
DELETE /api/v1/products/{uuid}      # Soft-delete / deactivate (ROLE_ADMIN)
```

Create/Update body:
```json
{
  "name": "Widget Pro 3000",
  "sku": "WGT-PRO-3000",
  "price": 49.99,
  "currency": "USD",
  "categoryUuid": "<category-uuid>",
  "description": "The best widget ever made"
}
```

---

### Inventory

```http
GET  /api/v1/inventory                        # List all (ROLE_VIEWER+) — ?page=1&limit=25
GET  /api/v1/inventory/low-stock              # Low-stock items (ROLE_MANAGER+)
GET  /api/v1/inventory/{productUuid}          # Get for product (ROLE_VIEWER+)
POST /api/v1/inventory/{productUuid}/adjust   # Adjust stock (ROLE_MANAGER+)
```

Adjust stock body:
```json
{
  "quantity": 50,
  "reason": "Received from Supplier ABC — PO #12345"
}
```
Use **negative** quantity to decrease stock:
```json
{ "quantity": -5, "reason": "Damaged goods write-off" }
```

---

### Orders

```http
GET  /api/v1/orders               # List (ROLE_MANAGER+) — ?page=1&limit=25&status=pending&customerEmail=...
GET  /api/v1/orders/{uuid}        # Get order with line items (ROLE_MANAGER+)
POST /api/v1/orders               # Place order (ROLE_MANAGER+)
POST /api/v1/orders/{uuid}/cancel # Cancel order (ROLE_MANAGER+)
POST /api/v1/orders/{uuid}/fulfil # Fulfil order (ROLE_ADMIN)
```

Place order body:
```json
{
  "customerEmail": "customer@example.com",
  "items": [
    { "productUuid": "<uuid>", "quantity": 2 },
    { "productUuid": "<uuid>", "quantity": 1 }
  ],
  "notes": "Please gift-wrap"
}
```

---

## Order Lifecycle

```
PENDING ──→ CONFIRMED ──→ PROCESSING ──→ FULFILLED
   │              │              │
   └──────────────┴──────────────┴──→ CANCELLED
```

- `PENDING`: Order created, items added
- `CONFIRMED`: Stock reserved, customer notified (on POST /orders)
- `PROCESSING`: Being picked/packed
- `FULFILLED`: Shipped; physical stock decremented
- `CANCELLED`: Stock reservation released

---

## Event Flow

```
POST /api/v1/orders
  PlaceOrderCommandHandler
    ├── ProductRepository.findByUuid()       — validate & get price snapshot
    ├── InventoryItem.reserve()              — throws InsufficientStockException if short
    ├── Order.confirm()                      — PENDING → CONFIRMED
    ├── flush()
    └── dispatch(OrderPlacedEvent)
          ├── OrderPlacedListener            — audit log, email stub
          └── dispatch(LowStockAlertEvent)?  — if stock ≤ threshold
                └── LowStockAlertListener    — warning log, ops alert stub

POST /api/v1/orders/{uuid}/cancel
  CancelOrderCommandHandler
    ├── Order.cancel()
    ├── InventoryItem.release()              — returns reserved stock
    ├── flush()
    └── dispatch(OrderCancelledEvent)
          └── OrderCancelledListener         — log, customer notification stub

POST /api/v1/orders/{uuid}/fulfil
  FulfilOrderCommandHandler
    ├── Order.startProcessing() → Order.fulfil()
    ├── InventoryItem.commitReserved()       — decrements onHand
    ├── flush()
    └── dispatch(OrderFulfilledEvent)
```

---

## RBAC Matrix

| Action             | ROLE_VIEWER | ROLE_MANAGER | ROLE_ADMIN |
|--------------------|:-----------:|:------------:|:----------:|
| View products      | ✓           | ✓            | ✓          |
| Create/update products | ✗       | ✓            | ✓          |
| Delete products    | ✗           | ✗            | ✓          |
| View inventory     | ✓           | ✓            | ✓          |
| Adjust stock       | ✗           | ✓            | ✓          |
| View orders        | ✗           | ✓            | ✓          |
| Place/cancel orders| ✗           | ✓            | ✓          |
| Fulfil orders      | ✗           | ✗            | ✓          |

Role hierarchy: `ROLE_ADMIN > ROLE_MANAGER > ROLE_VIEWER`

---

## Running Tests

```bash
# All tests
make test

# Unit tests only (no DB required)
make test-unit

# With coverage report
php bin/phpunit --coverage-html coverage/
```

---

## Error Response Format

All errors follow this structure:
```json
{
  "error": {
    "code": 422,
    "message": "Validation failed.",
    "violations": {
      "name": ["This value should not be blank."],
      "sku":  ["Invalid SKU format."]
    }
  }
}
```

HTTP status codes used:
- `200 OK` — successful GET / state change
- `201 Created` — successful POST creation
- `204 No Content` — successful DELETE
- `400 Bad Request` — malformed request
- `401 Unauthorized` — missing/invalid JWT
- `403 Forbidden` — authenticated but insufficient role
- `404 Not Found` — resource not found
- `409 Conflict` — duplicate SKU, insufficient stock
- `422 Unprocessable Entity` — validation errors, invalid state transition
- `500 Internal Server Error` — unexpected errors

---

## PSR Standards Applied

- **PSR-1**: Class names in StudlyCaps, method names in camelCase
- **PSR-4**: Autoloading — `App\` → `src/`, `App\Tests\` → `tests/`
- **PSR-12**: Extended coding style — `declare(strict_types=1)` on every file, 4-space indentation
