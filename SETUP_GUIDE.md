# SP Backend — Setup Guide

Complete step-by-step guide to run this project from scratch.

---

please copy the .env.example file into :.env if it is not exist create it

## Requirements

| Software | Version    | Download                                           |
| -------- | ---------- | -------------------------------------------------- |
| PHP      | 8.2 or 8.3 | https://windows.php.net/download (Thread Safe x64) |
| Composer | 2.x        | https://getcomposer.org/download                   |
| MySQL    | 8.0+       | https://dev.mysql.com/downloads/mysql              |
| Node.js  | 18+        | https://nodejs.org                                 |

---

## Step 1 — Install PHP

1. Download **PHP 8.3 Thread Safe x64** from https://windows.php.net/download
2. Extract to `C:\php`
3. Add `C:\php` to your Windows **PATH** (System → Advanced → Environment Variables → Path → New)
4. Copy `C:\php\php.ini-development` and rename it to `C:\php\php.ini`
5. Open `C:\php\php.ini` and **uncomment** (remove the `;`) these lines:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=zip
extension_dir = "ext"
```

6. Verify: open Command Prompt and run:

```bash
php --version
```

Should show `PHP 8.3.x`

---

## Step 2 — Install Composer

1. Download from https://getcomposer.org/download → run the installer
2. Verify:

```bash
composer --version
```

---

## Step 3 — Install MySQL

1. Download MySQL 8.0 installer from https://dev.mysql.com/downloads/mysql
2. Install with default settings
3. Set root password (remember it)
4. Open MySQL Workbench or command line and create the database:

```sql
CREATE DATABASE sp_capstone_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Step 4 — Clone / Copy the project

Put the project folder somewhere, e.g. `C:\projects\SP-Back`

---

## Step 5 — Install PHP dependencies

Open terminal in the project folder:

```bash
composer install
```

---

## Step 6 — Configure environment

Copy the env file:

```bash
cp .env.example .env
```

Open `.env` and set:

```env
APP_KEY=          ← leave blank for now, will generate below

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sp_capstone_db
DB_USERNAME=root
DB_PASSWORD=your_mysql_root_password

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=317939
REVERB_APP_KEY=glqnglm8mawxfczcr1gz
REVERB_APP_SECRET=your_secret_here
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Generate app key:

```bash
php artisan key:generate
```

---

## Step 7 — Run all database migrations

This creates all tables in the correct order:

```bash
php artisan migrate
```

If you want to start fresh (drop everything and re-create):

```bash
php artisan migrate:fresh
```

---

## Step 8 — Seed the database (optional, for test data)

```bash
php artisan db:seed
```

---

## Step 9 — Run the application

You need **3 separate terminal windows** all running at the same time:

### Terminal 1 — Laravel HTTP server

```bash
php artisan serve
```

Runs on: `http://127.0.0.1:8000`

### Terminal 2 — Reverb WebSocket server (for live chat)

```bash
php artisan reverb:start
```

Runs on: `ws://127.0.0.1:8080`

### Terminal 3 — Queue worker (for notifications)

```bash
php artisan queue:work
```

> All 3 must stay open and running. Do not close them.

---

## Step 10 — Verify everything is working

Open browser and go to:

```
http://127.0.0.1:8000/api/v1/health
```

Should return `{ "status": "ok" }`

---

## Common Errors

### `php: command not found`

→ PHP is not in PATH. Redo Step 1 point 3, then restart the terminal.

### `SQLSTATE[HY000] [1049] Unknown database`

→ You didn't create the database. Run the SQL in Step 3.

### `SQLSTATE[HY000] [1045] Access denied`

→ Wrong DB_PASSWORD in `.env`.

### `Call to undefined function imagecreatefromjpeg()`

→ GD extension is not enabled. Redo Step 1 point 5, make sure `extension=gd` is uncommented.

### `No application encryption key has been specified`

→ Run `php artisan key:generate`

### `Class not found` errors

→ Run `composer install` again.

### WebSocket not connecting

→ Make sure `php artisan reverb:start` is running in a separate terminal and `BROADCAST_CONNECTION=reverb` is set in `.env`.

---

## Migrations — what each file creates

| File                                    | What it creates                                                                                                        |
| --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `0001_01_01_000001_create_cache_table`  | Cache table                                                                                                            |
| `0001_01_01_000002_create_jobs_table`   | Queue jobs table                                                                                                       |
| `2026_04_16_000000_create_final_schema` | **Main schema** — all tables (users, providers, services, bookings, chats, reviews, zones, areas, notifications, etc.) |
| `2026_04_21_*`                          | Avatar URL, location columns on providers                                                                              |
| `2026_04_21_192835_*`                   | day_of_week as string in availability                                                                                  |
| `2026_04_21_194548_*`                   | avatar_url as LONGTEXT on providers                                                                                    |
| `2026_04_21_194757_*`                   | image columns as LONGTEXT (users, certifications, documents, project images)                                           |
| `2026_04_23_182244_*`                   | icon_url as LONGTEXT on categories and services                                                                        |
| `2026_04_23_193000_*`                   | price column on bookings                                                                                               |
| `2026_04_23_195239_*`                   | provider_earnings table                                                                                                |

> `php artisan migrate` runs all of them automatically in order. You do not need to run them one by one.

---

## API Base URL

All endpoints are prefixed with:

```
http://127.0.0.1:8000/api/v1/
```

Authentication: send `Authorization: Bearer {token}` in every request header after login.
customer : user name : ali@gmil.com : admin123
provider: hisoft.agency@gmail.com : admin1234
superadmin: exist in the seed
