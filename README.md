# SVMS: Student Violation Monitoring & Management System

Laravel 13 + PostgreSQL + Blade + Bootstrap 5 + Chart.js, built for a school Student Council.

> **Status: written but not yet executed.** The code passed PHP syntax checks, a Blade directive-balance
> check and a route/name cross-check, but it has **not been run** because the build environment could not
> download Composer packages. Treat the first `php artisan test` on your machine as the real verification and
> fix anything it reports before using the system with real data. See "First run checklist" below.

---

## 1. How to install (this folder is an *overlay* on a fresh Laravel 13 project)

You already created the base project (`composer create-project laravel/laravel svms "^13.0"`). From the folder that
contains this `README.md`:

1. **Copy everything over your project**, allowing it to overwrite files. In VS Code you can drag the folders in, or in PowerShell:
   ```powershell
   # run from the folder that contains this README (adjust the destination path)
   Copy-Item -Path .\* -Destination C:\laragon\www\svms -Recurse -Force
   ```
   This replaces: `bootstrap/app.php`, `routes/web.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`,
   `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `tests/Feature/ExampleTest.php`
   and adds everything else (migrations, models, services, controllers, views, tests).

2. **Install the PDF package** (used by the PDF export; everything else works without it):
   ```bash
   composer require barryvdh/laravel-dompdf
   ```

3. **Create the PostgreSQL database** (psql or pgAdmin):
   ```sql
   CREATE USER svms WITH PASSWORD 'choose-a-strong-password';
   CREATE DATABASE svms OWNER svms;
   CREATE DATABASE svms_test OWNER svms;   -- optional, for running tests on PostgreSQL
   ```
   Make sure `extension=pdo_pgsql`, `extension=pgsql`, `extension=fileinfo`, `extension=gd` and `extension=mbstring`
   are enabled in `php.ini` (Laragon: Menu → PHP → Extensions).

4. **Edit `.env`** (replace the SQLite lines):
   ```dotenv
   APP_NAME="SVMS"
   APP_TIMEZONE=Asia/Manila
   APP_URL=http://svms.test

   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=svms
   DB_USERNAME=svms
   DB_PASSWORD=choose-a-strong-password

   # First administrator (used only by the seeder). Change the password; it must be changed again at first login.
   SEED_ADMIN_EMAIL=admin@yourschool.example
   SEED_ADMIN_PASSWORD=Use-A-Long-Temporary-Password-1
   ```

5. **Create tables and the first admin:**
   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link   # not needed for evidence (kept private), only if you add public assets later
   ```
   `db:seed` creates **one administrator** and neutral settings. It does **not** create any violation categories or
   penalties: the school enters those from its official handbook.

6. **(Optional) Load clearly-fake demo data** so you can click around. Never run this on a real database:
   ```bash
   php artisan db:seed --class=DemoDataSeeder
   ```
   Demo logins: `officer1@svms.test` / `Password!12345` (and `officer2`, `officer3`); the admin is whatever you set in `.env`.
   Demo students are labelled `DEMO-…`, and the demo categories and penalties are labelled `[DEMO]` placeholders, **not school policy**.

7. **Run it:** `php artisan serve` (or open `http://svms.test` with Laragon), then sign in.

---

## 2. First run checklist (do this before trusting it)

```bash
php artisan test                  # uses in-memory SQLite by default: fast, and a good first check
```
Then run the suite against PostgreSQL too (the real target). Create a `.env.testing`:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=svms_test
DB_USERNAME=svms
DB_PASSWORD=choose-a-strong-password
```
and comment out the two `DB_CONNECTION` / `DB_DATABASE` `<env>` lines in `phpunit.xml`, then `php artisan test` again.
**If a test fails, send me the full failure output** and I will fix the root cause.

Then do a manual walk-through (10 minutes):
1. Sign in as admin → forced password change.
2. *Disciplinary Actions* → add the penalties from your handbook.
3. *Violation Categories* → add a category and its offense rules (1st, 2nd, 3rd, "4th and succeeding").
4. *Courses & Sections* → add a course and sections. *Students* → register a student.
5. *Officer Accounts* → create an officer (note the one-time temporary password).
6. Sign in as the officer → *New Report* → search the student → submit with a photo.
7. Back as admin → open the report → check the "If approved now" preview → Approve → check the student's profile.

---

## 3. What is in the system

| Area | Admin | Officer |
|---|---|---|
| Students (register, edit, archive/restore, search, profile with history) | ✔ | view + search only |
| Courses & sections | ✔ | – |
| Violation categories, offense rules, disciplinary actions | ✔ | – |
| File a violation report (+ evidence) | – (separation of duties) | ✔ |
| Review: approve / reject / return for more info | ✔ (never your own report) | – |
| Appeals: log on a student's behalf, uphold / overturn | ✔ | – |
| Dashboard (live numbers, Chart.js) | everything | own reports |
| Reports: filters, print, CSV (opens in Excel), PDF | ✔ | – |
| Notifications (in-app) | new/resubmitted reports | status of own reports |
| Officer accounts, audit logs, settings | ✔ | – |

### Offense counting (the core rule)
* Counted **per student and per category**: categories are never combined.
* Only **approved** records count. Pending, rejected, returned and voided reports never do.
* The number is assigned **at approval**, inside a database transaction that locks the student row, and a **partial unique
  index** (`student + category + offense_number` where `status = 'approved'`) makes a duplicate number impossible even under a race.
* Next number = *highest approved number + 1*. If an appeal voids the 1st offense, the next approval becomes the 3rd (not a second "2nd"),
  and no verified record is ever renumbered.
* On approval the category name, offense number, penalty name/description and rule note are **copied onto the record**. Changing or deactivating
  rules, penalties or categories later never rewrites history.
* If no active rule exists for the next offense number, approval is **blocked** with a clear message (nothing is guessed).
* An "N and succeeding offenses" rule applies from N onward.

### Appeals / disputes
Admin logs the student's statement against an **approved** record (one pending appeal at a time). Upholding keeps the record.
Overturning marks it **voided**: it stays in history with the reason, and stops counting. Nothing is deleted; every step is in the audit log.

---

## 4. Security & privacy measures implemented

* Passwords hashed (bcrypt via Laravel); 10+ char policy for new passwords; temporary passwords force a change at first sign-in.
* Login throttling (5 failed attempts), session regeneration on login, generic failure message (no account enumeration), disabled accounts locked out immediately, even mid-session.
* Role-based access: route middleware **and** controller scoping **and** a policy (officers can only open their own reports; admins can't file reports).
* CSRF on every form (Laravel 13 `PreventRequestForgery`), Eloquent/bound queries only (no string-built SQL), Blade escaping everywhere, JS renders server data with `textContent`.
* Evidence: JPG/PNG/PDF only (extension **and** detected MIME), ≤ 5 MB each, ≤ 5 files, stored on the **private** disk under random names (not web-accessible), downloadable only through an authorised, audited route and always as an attachment.
* Append-only audit log (create/approve/reject/return/appeal/edit/login/exports/evidence downloads, with before/after values; passwords are never logged).
* Security headers (`nosniff`, frame protection, referrer policy) and `Cache-Control: no-store` on signed-in pages.
* CSV export neutralises spreadsheet-formula injection.
* Student records are **archived, never deleted**; student IDs can't be reused even after archiving.

### Data Privacy Act (RA 10173): what the software does and does not do
The system supports privacy practices (least-privilege access, audit trail, private storage, DPO contact and a retention note in *Settings*).
**It cannot make your school compliant by itself.** Before real use the school should: appoint/confirm a Data Protection Officer and enter their contact in
*Settings*; define and document retention periods; give students/parents a privacy notice and basis for processing; use HTTPS; back up the database
(and `storage/app/private`) securely; and restrict server and database access. Consider having the school's legal counsel or DPO review the setup.
Retention is a reference text only; nothing is deleted automatically.

---

## 5. Deployment notes

* Use HTTPS only; set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, `SESSION_SECURE_COOKIE=true`.
* `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`.
* Point the web server's document root to `public/`. Keep `storage/` writable but outside the web root.
* The seeder's admin password is temporary; the first login forces a change. Delete or rotate `SEED_ADMIN_*` after the first run.
* Never run `DemoDataSeeder` in production (it refuses to run when `APP_ENV=production`).
* Back up PostgreSQL (`pg_dump`) and `storage/app/private/svms/evidence` together.
* Bootstrap, Bootstrap Icons and Chart.js load from the jsDelivr CDN. For a campus network without internet, download them and serve them from `public/vendor/`, then update `resources/views/layouts/*.blade.php`.

## 6. Known limitations / deliberate decisions

* **Admin handles both review and appeals** (you said the adviser/admin role is still to be confirmed). Adding an "Adviser" role later means one new role value plus adjusting the `role:` middleware on the review and appeal routes.
* Excel export is **CSV** (opens in Excel). A native `.xlsx` export would need `maatwebsite/excel` or `openspout`.
* Notifications are in-app only (no email/SMS).
* No password-reset-by-email: the admin issues a temporary password (appropriate for a small council; avoids mail setup).
* Students do not log in; appeals are logged by the admin on their behalf, as specified.
* Automated tests have been **written but not yet run** (see top of this file).
#   P u p S t u d e n t V i o l a t i o n R e p o r t i n g S y s t e m  
 