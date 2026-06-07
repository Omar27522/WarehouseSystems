# 🧠 AI Technical Deep Dive

This document details the database schemas, query abstractions, concurrency controls, document generation formulas, and security patterns implemented in the **IQA Warehouse Systems**.

---

## 💾 Database Schema Reference

The system contains five SQLite databases situated in the `/db/` directory.

### 1. `customers.db`
- **`customers`** (Tracks B2B accounts & outreach details):
  - `customer_id` (TEXT, PRIMARY KEY): Formatted string (e.g. `CUST-XXXXXXXX`).
  - `company_name` (TEXT, NOT NULL)
  - `contact_person` (TEXT)
  - `website` (TEXT)
  - `email` (TEXT)
  - `phone` (TEXT)
  - `address` (TEXT)
  - `shipping_address` (TEXT)
  - `internal_notes` (TEXT)
  - `callback_date` (TEXT): Date representation for callback prompts.
  - `message_date` (TEXT): Date representation for outreach actions.
  - `created_at` (DATETIME): Default `CURRENT_TIMESTAMP`.

### 2. `orders.db`
- **`orders`** (Client purchase orders/batches):
  - `order_id` (TEXT, PRIMARY KEY): Formatted string (e.g. `ORD-XXXXXXXX`).
  - `customer_id` (TEXT): Foreign key linking to `customers.customer_id`.
  - `status` (TEXT): Values are `'active'` or `'finalized'`.
  - `created_at` (DATETIME)
  - `updated_at` (DATETIME)
- **`items`** (Individual hardware items added to orders):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `order_id` (TEXT, NOT NULL)
  - `customer_id` (TEXT, NOT NULL)
  - `brand` (TEXT, NOT NULL)
  - `model` (TEXT, NOT NULL)
  - `series` (TEXT, NOT NULL): Project/Series identifier.
  - `cpu` (TEXT)
  - `description` (TEXT, NOT NULL): Quality/spec details.
  - `quantity` (INTEGER, NOT NULL)
  - `unit_price` (REAL, DEFAULT `0.00`)
  - `created_at` (DATETIME)

### 3. `warehouse.db`
- **`sectors`** (Main inventory sectors):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `name` (TEXT, UNIQUE): E.g., `'Laptops'`, `'Gaming'`, `'Desktops'`, `'Electronics'`.
  - `description` (TEXT)
  - `icon` (TEXT)
  - `color_theme` (TEXT): Hex color values.
- **`inventory`** (Intaken stock items):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `user_owner` (TEXT, NOT NULL)
  - `sector` (TEXT, NOT NULL)
  - `location_code` (TEXT)
  - `brand` (TEXT, NOT NULL)
  - `model` (TEXT, NOT NULL)
  - `specs_json` (TEXT): JSON string holding specific features (e.g. RAM, GPU, OS, Battery status, BIOS configuration).
  - `quantity` (INTEGER, DEFAULT `0`)
  - `status` (TEXT)
  - `last_updated_by` (TEXT): Username of last editor.
  - `price` (REAL, DEFAULT `0.00`)
  - `created_at` (DATETIME)
  - `updated_at` (DATETIME)
- **`locations`** (Physical storage positions):
  - `location_code` (TEXT, PRIMARY KEY): E.g. `'Shelf-A'`.
  - `status` (TEXT): E.g. `'Working'`, `'Audit'`, `'Shipping'`, `'Idle'`.
  - `updated_at` (DATETIME)
- **`location_statuses`** (Configurable zone states):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `name` (TEXT, UNIQUE)
  - `color` (TEXT)

### 4. `users.db`
- **`users`** (Operator and Administrator credentials):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `username` (TEXT, UNIQUE)
  - `password` (TEXT): Hashed passwords.
  - `role` (TEXT): `'Admin'` or `'Operator'`.
  - `display_name` (TEXT)
  - `ppp_sequence_key` (TEXT)
  - `ppp_row_index` (INTEGER)
- **`audit_log`** (Audit trail table):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `timestamp` (DATETIME)
  - `user_id` (TEXT)
  - `user_name` (TEXT)
  - `module` (TEXT)
  - `action` (TEXT)
  - `target_id` (TEXT)
  - `details` (TEXT)
  - `ip_address` (TEXT)

### 5. `calendar.db`
- **`events`** (Schedule log):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `title` (TEXT, NOT NULL)
  - `description` (TEXT)
  - `event_date` (DATE, NOT NULL)
  - `start_time` (TIME, NOT NULL)
  - `end_time` (TIME, NOT NULL)
  - `color` (TEXT)
  - `customer_id` (TEXT)
  - `created_at` (TIMESTAMP)

---

## 🔗 Integrated Query Join Engine
To prevent manual SQLite `ATTACH` sequences, use:
```php
Database::queryIntegrated($primary_db, $attachments, $sql, $params);
```
- **$primary_db**: Primary connection database key name (e.g. `'orders'`).
- **$attachments**: Associative mapping array `['alias' => 'database_name']` (e.g., `['cust' => 'customers']`).
- **$sql**: SQL statement containing joined aliases (e.g., `SELECT * FROM items i LEFT JOIN cust.customers c ...`).
- **$params**: SQL parameters list.

The helper maps connection resources, binds local attachments, runs execution, and detaches the secondary databases safely.

---

## 🔒 Concurrency Controls (Optimistic Locking)
In physical environments, multiple workers may edit stock in the same location.
- **Mechanism**: The `inventory` table contains an `updated_at` timestamp.
- **Save Check**: When the edit form is loaded in `warehouse.php`, the original `updated_at` timestamp is written inside a hidden field `last_updated_at`.
- **Validation**: When submitting changes, the update query compares the hidden `last_updated_at` against the current database value. If they do not match, the query redirects with a `CONCURRENCY_ERROR` code.
- **Client Handling**: The frontend JS monitors response alerts and warns the operator that the record was modified by another user.

---

## 🏷️ Flat XML Label Generation (FODT)
To enable printing on 2"×1" labels using standard thermal label printers, the system avoids zip dependencies by utilizing the Flat XML OpenDocument format (.fodt).

### Generation Workflow (`prod/api/generate_warehouse_label.php`)
1. Fetches item specification variables (CPU specs, RAM, Storage, GPU, BIOS, notes) from the `inventory` table.
2. Escapes variables utilizing `htmlspecialchars($val, ENT_XML1, 'UTF-8')`.
3. Constructs standard FODT elements containing:
   - Page dimensions: `2in` × `1in`.
   - Fonts: Swiss/Arial family.
   - Flow contents using style paragraphs (`P1`, `P2`, `P4`).
4. Saves files to `/prod/assets/exports/labels/[Brand]_[Model]_[Gen]_ID[ID].odt`.
5. Returns a JSON URL so operators can initiate immediate local download/print actions.

---

## 🛡️ Audit Logger Resilience
The audit manager `Audit::log()` commits operational logs to the `users.db` `audit_log` database.
- **DB Conflict Fallback**: SQLite locks databases when writing from multiple concurrent client processes. If the SQLite database triggers a locking exception, `Audit::log()` catches the error, formats the log properties, and appends the details to a local log file: `/prod/logs/audit_fallback.log`.
