# Flash-Sale Checkout — Concurrency-Safe Stock Reservation

This project implements a minimal **flash-sale checkout system** focused on **correctness under extreme concurrency**.
It ensures **no overselling**, safe **stock holds**, **hold expiry**, and **idempotent webhook processing**.

---

## 📌 1. Assumptions & Invariants Enforced

### **Stock & Ordering Rules**

* A product’s stock **can never go negative**.
* Stock updates and order creation use:

  * database transactions
  * `SELECT … FOR UPDATE` row-level locking
* Even under parallel requests, **only one order succeeds** when stock is limited.

### **Hold System**

* A Hold reserves **1 unit** of stock for **2 minutes**.
* A Hold is valid only if:

  * it is **not expired**
  * it is **not used**
  * it still corresponds to available stock
* Expired holds **automatically restore stock**.
* A hold can be consumed only once.

### **Webhook Guarantees**

* Each webhook has a unique `event_id`.
* Webhooks are **100% idempotent**:

  * duplicates are ignored safely.
* Webhooks may arrive **before** order creation — the system still processes them correctly.

### **Oversell Protection**

* Even with dozens of parallel holds, tests guarantee:

  * **1 order succeeds**
  * all others fail with a proper error message:

    ```
    { "message": "No available stock left for the product" }
    ```

---

## 📦 2. How to Run the Project

### **Requirements**

* PHP 8.1+
* Composer
* Laravel CLI
* SQLite or MySQL

---

### **Installation**

```bash
git clone <your-repo-url>
cd FlashSaleCheckout
composer install
cp .env.example .env
php artisan key:generate
```

---

### **Database Setup**

#### **Option A — SQLite (recommended for fast setup)**

```bash
touch database/database.sqlite
```

In `.env`:

```
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

---

### **Migrations & Seeders**

```bash
php artisan migrate --seed
```

Seeds include:

* a product with limited stock
* test-ready data structure

---

## 🧪 3. Running Automated Tests

This project includes feature tests covering:

✔ Parallel holds → **no oversell**
✔ Hold expiry restores stock
✔ Webhook idempotency
✔ Webhook arriving before order creation

Run the full test suite:

```bash
php artisan test --env=testing
```

Expected test output:

```
✓ parallel holds no oversell
✓ hold expiry releases stock
✓ webhook idempotency
✓ webhook before order creation
```

---

## 📊 4. Logs & Metrics

### **Application Logs**

Stored in:

```
storage/logs/laravel.log
```

Includes:

* webhook activity
* expired holds
* concurrency attempts
* lock retries
* order creation errors

---

### **Test Logs**

During tests:

```
storage/logs/testing.log
```

Logs:

* parallel request outcomes
* hold validation
* stock calculations
* duplicate webhook detection

---

### **Enable SQL Debug Logging**

In `.env`:

```
APP_DEBUG=true
LOG_LEVEL=debug
```

---

## 🗂 Project Structure

```
app/
 ├─ Models/
 ├─ Http/Controllers/
 └─ Services/

database/
 ├─ migrations/
 ├─ seeders/
 └─ factories/

tests/
 └─ Feature/OrderPaymentTest.php
```
