# Enterprise ERP & Gate Management System

An enterprise-grade Laravel application featuring an SAP Business One style Sales AR Invoice interface, a responsive Vehicle Gate Operations system with automated login audit logging, and a hardened Safaricom Daraja M-Pesa C2B REST API webhook integration.

---

## Architecture & Module Summary

### Task 1: ERP Web Application (Sales AR Invoice)
- **SAP Business One Layout**: Clean header, lines repeater, and financial summary footer.
- **Customer & Item Pickers**:
  - Type-ahead autocomplete suggestions on Customer Code and Customer Name.
  - Modal "Choose From List" with code-first and name-first sorting.
  - Item picker auto-populates description and unit price with 3-decimal precision.
- **Financial Calculation Engine (`App\Services\InvoiceCalculator`)**:
  - Pure domain service encapsulating line computations, discounts, and rounding.
  - Strict 3-decimal precision without floating point drift.
- **Conditional Approval Trigger**:
  - Real-time dynamic banner: `"Invoice will go for approval – Amount: {getAmount}"`.
  - Appears immediately when `Total After Discount > 10000.000`; hidden otherwise.
- **Validations**:
  - Line discount capped at 50% (`lte:50`).
  - Remarks field is mandatory.
- **Concurrency Hardening**:
  - `Invoice::createWithNextNumber()` provides automatic retry protection against unique number collision under concurrent traffic.

### Task 2: Vehicle Gate Operations
- **Login Activity Auditing (`App\Listeners\RecordSuccessfulLogin`)**:
  - Listens to `Illuminate\Auth\Events\Login` and logs `user_id`, `logged_in_at`, `ip_address`, and `user_agent` to `login_activities`.
  - Configured with database session persistence (`SESSION_DRIVER=database`).
- **Gate In Screen**:
  - Searchable vehicle dropdown with active driver assignment auto-fill.
  - Guards against double entry if vehicle is already on premises.
- **Dedicated Gate Out Screen (`/gate/gate-out`)**:
  - Vehicle dropdown **strictly** lists vehicles currently on premises (`status = 'in'`).
  - Auto-populates Driver Name, ID / Passport Number, Phone Number, and Gate In timestamp as read-only fields.
  - Captures `gated_out_at` timestamp and `gated_out_by` user ID.
- **Unified Exit Domain Service (`App\Services\RegisterGateExit`)**:
  - Single source of truth for exit transactions used by both the dedicated Gate Out screen and the list table row action.
  - Implements row locking and double-exit protection.

### Task 3: M-Pesa C2B REST API
- **Webhook Endpoints**:
  - `POST /api/mpesa/validation`
  - `POST /api/mpesa/confirmation`
  - `GET /api/mpesa/transactions/{transactionId}`
- **Security & Reliability**:
  - Rate limited at the route level (`throttle:60,1`).
  - Optional shared secret verification via `X-Callback-Secret` header.
  - Extracts all Daraja payload attributes into string fields.
  - Idempotent upsert on `transaction_id` for duplicate callback tolerance.
  - Defensive fallback logic: always returns `{ "ResultCode": 0, "ResultDesc": "Accepted" }` and preserves raw JSON payload.

---

## Quick Start & Installation

### 1. Prerequisites
- PHP 8.3+
- Composer
- Node.js & NPM
- SQLite or Microsoft SQL Server (2019 / 2022 / Azure SQL)

### 2. Setup Commands
```bash
# Clone and install dependencies
composer install
npm install && npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Run database migrations and seeders
php artisan migrate:fresh --seed
```

### 3. Default Test Credentials
- **Admin Panel URL**: `http://127.0.0.1:8000/admin`
- **Gate Operations URL**: `http://127.0.0.1:8000/gate`
- **Email**: `admin@example.com`
- **Password**: `password`

---

## Switching to Microsoft SQL Server (`sqlsrv`)

The application database schema and domain services are fully SQL Server compatible.

1. Ensure PHP extensions `pdo_sqlsrv` and `sqlsrv` are enabled.
2. In your `.env` file, update the database configuration:
```env
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=erp_assessment
DB_USERNAME=sa
DB_PASSWORD=YourStrongPassword!
DB_TRUST_SERVER_CERTIFICATE=true
```
3. Run migrations and seed data:
```bash
php artisan migrate:fresh --seed
```

---

## Testing & Verification

### Running Automated Test Suite
```bash
# Run all unit and feature tests
php artisan test

# Run code style formatting checks
vendor/bin/pint --test
```

### Test Coverage Highlights
- `Tests\Unit\InvoiceCalculatorTest`: 3-decimal precision, discount boundary math, approval threshold boundaries (9,999.999 vs 10,000.000 vs 10,000.001).
- `Tests\Feature\InvoiceTest`: Livewire form validation (discount > 50%, empty remarks), sequential document numbering, header and line persistence.
- `Tests\Feature\LoginAuditTest`: Automated capture of login timestamp, IP address, and user agent on authentication.
- `Tests\Feature\GateEntryTest`: Gate In registration and driver resolution.
- `Tests\Feature\GateOutTest`: Dedicated Gate Out filtering, auto-population, and double-exit prevention.
- `Tests\Feature\MpesaCallbackTest`: C2B validation and confirmation webhook handling, idempotency, secret authentication, and malformed payload resilience.

---

## M-Pesa C2B API Testing (cURL Examples)

### 1. Validation Callback
```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/validation \
  -H "Content-Type: application/json" \
  -d '{
    "TransactionType": "Pay Bill",
    "TransID": "VAL98765XYZ",
    "TransTime": "20260824183000",
    "TransAmount": "2500.000",
    "BusinessShortCode": "174379",
    "BillRefNumber": "INV-1001",
    "InvoiceNumber": "",
    "OrgAccountBalance": "75000.000",
    "ThirdPartyTransID": "",
    "MSISDN": "254712345678",
    "FirstName": "Jane",
    "MiddleName": "",
    "LastName": "Doe"
  }'
```

**Expected Response**:
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accepted"
}
```

### 2. Confirmation Callback (with Callback Secret)
```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/confirmation \
  -H "Content-Type: application/json" \
  -H "X-Callback-Secret: your_configured_secret" \
  -d '{
    "TransactionType": "Pay Bill",
    "TransID": "CNF12345ABC",
    "TransTime": "20260824183000",
    "TransAmount": "5000.000",
    "BusinessShortCode": "174379",
    "BillRefNumber": "INV-1002",
    "InvoiceNumber": "",
    "OrgAccountBalance": "80000.000",
    "ThirdPartyTransID": "",
    "MSISDN": "254798765432",
    "FirstName": "John",
    "MiddleName": "",
    "LastName": "Kamau"
  }'
```

### 3. Transaction Lookup Endpoint
```bash
curl -X GET http://127.0.0.1:8000/api/mpesa/transactions/CNF12345ABC
```
