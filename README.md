# Maintenance People Development System

An internal web application for the Maintenance Department of **PT Bekaert Indonesia**, used to manage maintenance employees, competency/skill matrices, job descriptions, training, and certificates.

> This project is under active development. It is not officially connected to, or endorsed by, any PT Bekaert Indonesia production system. The logo placeholder in the sidebar and login page is a stand-in for the official company logo, which can be added later.

## Current status: feature-complete, including all Roadmap follow-ons

All 7 phases from the project brief have been built, plus the Settings, notifications, `.xlsx` export, and visual calendar follow-ons identified afterwards. See [Known limitations](#known-limitations) for what's intentionally out of scope.

Implemented:
- Laravel 13 application running on **MySQL** (not SQLite/demo storage).
- Authentication (Laravel Breeze, Blade + Tailwind stack).
- Role-based access control with 5 roles (`spatie/laravel-permission`): Administrator, Maintenance Manager, Maintenance Supervisor, Maintenance Staff, People Development.
- Responsive admin layout: fixed sidebar on desktop, off-canvas sidebar on mobile, top bar with user menu.
- Dashboard with **real, database-driven** counts across every module (employees, organization, skills/competency, training, certificates) — no fabricated placeholder numbers anywhere.
- **Employee Database**: full CRUD, search/filter/pagination, a tabbed employee profile page, and role-scoped visibility enforced in the backend (`EmployeePolicy`) — Administrators/Managers/HR see everyone, Supervisors see only their own team, Staff see only themselves.
- **Organization & Master Data CRUD**: create/edit/deactivate/delete UI for departments, divisions, maintenance areas, maintenance teams, positions, employment types, employment statuses, shifts, locations, skill categories, competency levels, and the skill catalog — restricted to the `master-data.manage` permission (Administrator by default).
- **Job Descriptions**: full CRUD with a simple version/approval workflow (draft → pending review → active → archived), version history per position, and an explicit "New Revision" action instead of silently overwriting history. Shown on the employee profile's Job Description tab.
- **Skills & Competency Management**: a configurable competency level scale (not hard-coded), a skill catalog, and **Position Skill Requirements** (which skills + level each position needs).
- **Employee Skill Assessments**: an append-only assessment log (history is never lost) with a "current level" derived as the most recent assessment per skill. Missing assessments are shown explicitly as "not assessed", never silently treated as meeting a requirement.
- **Skill Matrix**: an employee × skill matrix comparing current vs. required competency, with gap calculated as `required − current`, filterable by area/team/position/skill category, and an overall per-employee status (Meets / Development Required / Assessment Incomplete / No Requirements Defined).
- **Competency Gap Analysis**: department-wide rollup of the same gap calculation — skills with the largest gaps, positions and maintenance areas needing the most development, a drill-down list of employees with gaps, and system-generated training suggestions clearly labeled as suggestions, not approved decisions. The gap logic lives in one place (`Employee::skillGapRows()`) and is reused by the employee profile, Skill Matrix, Dashboard, and this module, so "what counts as a gap" can never drift between screens.
- **Training Management**: training programs (with related skills, category/type/provider, cost/duration), scheduled sessions with a calendar/list view, and participant assignment with per-participant attendance tracking. Session dates are validated (end can't be before start), and sessions respect a configurable maximum participant count.
- **Training Records**: a per-employee training history (program, date, hours, attendance/completion status, assessment score/result, before/after competency level, certificate reference), a global searchable index, and a running total of training hours shown on the employee profile.
- **Employee Development Plans**: objective, related skill/competency gap, current vs. target competency, a development action (formal training, OJT, coaching, mentoring, job rotation, self-learning, practical assessment, cross-training, certification), optional mentor and recommended training program, priority/status/progress tracking, and manager/employee remarks — visible on the employee profile and a global index.
- **Certificate Management**: certificates with type, number, issuing organization, issue/expiry dates, and secure file upload (PDF/JPG/PNG, 5MB max) stored on a **private disk** — never a public URL. Status (Valid / Expiring Soon / Expired / No Expiry / Pending Verification) is derived from the expiry date on every read rather than stored, so it can never go stale, and a certificate is never assumed valid just because a file was attached. Downloads are streamed through an authorized controller action that re-checks the same employee-visibility rules as the profile page.
- **Reports**: a Reports hub linking every report from the brief. Reports that are just a filtered view of a module already built (Skill Matrix, Certificates, Training Records, Training Calendar, ...) link to that module directly with CSV export added, so the report can never drift out of sync with the underlying module. Three reports with no existing equivalent were built new: Training Hours (by employee and by department), Employee Development Summary, and Competency Assessment History (the full cross-employee assessment log). CSV and XLSX export are both available on Employees, Certificates, Training Records, Training Hours, and Assessment History.
- **Audit Logging**: an append-only audit trail (`AuditLog` model, `Auditable` trait) recording who created, changed, or deleted a record and which fields changed, applied to the security-sensitive models called out in the brief — employees, certificates, skill assessments, job descriptions (approvals are just status updates, so they're covered automatically), training records, and development plans. Viewable at Administration → Audit Log, restricted to Administrators.
- **Settings**: an Administrator-only page for application-wide configuration — currently the certificate "expiring soon" window and the training session reminder lead time (`app/Models/Setting.php`, a simple cached key-value store), replacing what used to be a fixed constant.
- **In-app notifications**: a daily scheduled command (`app:send-expiration-notifications`) notifies certificate managers and the employee themselves when a certificate enters its expiring-soon window, and reminds training participants ahead of an upcoming session — each notification sent exactly once. The top bar shows a live unread-count badge and a dropdown to read or mark-all-as-read, with each notification linking straight to the relevant employee tab or session.
- **Excel (.xlsx) export**: alongside the existing CSV export, Employees, Certificates, Training Records, Training Hours, and Assessment History can all be exported as genuine `.xlsx` files (`phpoffice/phpspreadsheet`).
- **Visual training calendar**: the Training Calendar page now offers a month-grid view (with month navigation) as an alternative to the original list view, toggled with `?view=grid`.
- Demo seed data (clearly fictional, not real company data), including employees linked to the demo login accounts, sample assessments, and a training/development-plan/certificate story that ties back to a real seeded competency gap so the modules show a consistent, believable narrative rather than disconnected sample rows.
- Automated tests (119 passing) covering authentication guards, role/permission checks, employee visibility scoping, master data CRUD/validation, skill assessment recording, position requirements, the skill matrix and gap analysis calculations, the job description version/approval workflow, training session date/capacity validation, participant assignment, development plan authorization, certificate upload/download/status computation, audit log entries, report calculations/CSV/XLSX export, application settings, notification delivery, and the calendar grid view.

## Technology stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Database | MySQL 8.0 |
| Frontend | Blade + Tailwind CSS + Alpine.js, bundled with Vite |
| Auth | Laravel Breeze (Blade stack) |
| Roles/Permissions | `spatie/laravel-permission` |

## Project structure (high level)

```
app/
  Enums/            Centralised role & permission names (no hard-coded strings)
  Http/Controllers/ Application controllers
  Models/           Eloquent models
database/
  migrations/       Schema definitions
  seeders/          Demo/reference data
  factories/        Model factories used by seeders and tests
resources/
  views/layouts/    app.blade.php (shell), sidebar.blade.php, topbar.blade.php
  views/components/ Reusable Blade components (nav-item, dashboard-stat, ...)
routes/web.php      Application routes
tests/Feature/      Feature tests (auth, roles, dashboard)
```

## Requirements

- PHP 8.4+ with the `pdo_mysql` and `mysqli` extensions
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8.0 (or MariaDB 10.11+)

### A note on this Codespace's PHP build

The PHP build originally provided in this Codespace did **not** include the MySQL extensions (`pdo_mysql`, `mysqli`) and there was no MySQL server installed. Both were added during setup:
- `mysql-server` was installed via `apt-get`.
- PHP was recompiled from the matching 8.4.15 source with `--with-mysqli=mysqlnd --with-pdo-mysql=mysqlnd` added to the original configure flags, then reinstalled in place at `/usr/local/php/8.4.15`.

If you rebuild this Codespace from scratch, you will need to repeat this (or install a PHP build that already ships these extensions).

## Environment configuration

Copy `.env.example` to `.env` and set your database credentials:

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maintenance
DB_USERNAME=maintenance_app
DB_PASSWORD=your_local_password
```

For local development, the database and user can be created with:

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS maintenance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'maintenance_app'@'127.0.0.1' IDENTIFIED BY 'your_local_password'; GRANT ALL PRIVILEGES ON maintenance.* TO 'maintenance_app'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

Make sure the MySQL service is running:

```bash
sudo service mysql start
```

## Database setup

```bash
php artisan migrate
php artisan db:seed
```

`db:seed` runs, in order: role/permission setup, organization master data, and demo user accounts.

## Running the application

```bash
composer install
npm install
npm run build      # or `npm run dev` while actively developing frontend
```

**Every time you reopen this Codespace**, MySQL and the dev server both need to be started again (this container has no init system to keep them running across restarts). Use:

```bash
bin/start.sh
```

This starts MySQL, waits for it to be ready, then runs `php artisan serve --host=0.0.0.0 --port=8000`. Open it via the VS Code **Ports** tab (port 8000) rather than typing the forwarded URL by hand — see [Troubleshooting](#troubleshooting) if you hit connection issues.

## Running tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`) so they don't require MySQL and don't touch your development data.

## Demo user accounts

**Local development only — do not reuse these credentials anywhere else.** All demo accounts use the password `password`.

| Role | Email |
|---|---|
| Administrator | admin@mpd.test |
| Maintenance Manager | manager@mpd.test |
| Maintenance Supervisor | supervisor@mpd.test |
| Maintenance Staff | staff@mpd.test |
| People Development | hr@mpd.test |

## Roles

- **Administrator** — full access, manages users, master data, and all modules.
- **Maintenance Manager** — manages/approves employee development, skills, training, and views reports for the department.
- **Maintenance Supervisor** — views their team, records skill assessments, recommends training.
- **Maintenance Staff** — views only their own profile, skills, training, and certificates.
- **People Development / HR** — coordinates training programs, certificates, and development reporting.

Role and permission names are centralised in `app/Enums/RoleName.php` and `app/Enums/PermissionName.php` rather than hard-coded throughout the app.

## File storage

Certificate files are stored on Laravel's **private** local disk (`storage/app/private`, the `local` disk), never in `public/`. There is no public URL to a certificate file — the only way to retrieve one is the authenticated `certificates.download` route, which re-checks the viewer's access to the certificate's employee (the same rule as the employee profile page) before streaming the file. Uploads are restricted to PDF/JPG/PNG and 5MB max (`StoreCertificateRequest`).

## Troubleshooting

- **`could not find driver` / `SQLSTATE[HY000]`** — the `pdo_mysql` PHP extension isn't loaded. Run `php -m | grep mysql` to check. See the note above about this Codespace's PHP build.
- **`Access denied for user`** — check `DB_USERNAME` / `DB_PASSWORD` in `.env` match a MySQL user with access to the `maintenance` database, and that the user is granted for the host you're connecting from (`127.0.0.1` vs `localhost` are different grants in MySQL).
- **MySQL not running / `500` error on every page** — run `bin/start.sh` (or `sudo service mysql start` on its own). This container has no init system, so MySQL does not survive a Codespace restart and must be started manually each time.
- **After logging in, the browser tries to reach `localhost` and fails ("connection refused")** — the Codespaces port-forwarding proxy rewrites the `Host` header before it reaches PHP's built-in server, which would otherwise make Laravel generate redirect/asset URLs pointing at `localhost` instead of the public forwarded URL. This is already worked around in `app/Providers/AppServiceProvider.php` (forces all generated URLs from `APP_URL`), but **`APP_URL` in `.env` must match this Codespace's actual forwarded domain** (e.g. `https://<codespace-name>-8000.app.github.dev`). If you recreate the Codespace, its name changes, so update `APP_URL` accordingly and re-run `php artisan config:clear`.
- **Xdebug connection warnings in the terminal** (`Could not connect to debugging client...`) — harmless. Xdebug is configured for step-debugging from an IDE; it prints this when no debugger is attached, but does not affect application behavior.

## Security notes

- Passwords are hashed via Laravel's default hasher (bcrypt); login is rate-limited (Breeze default, 5 attempts).
- Authorization is enforced with `spatie/laravel-permission` roles/permissions on the backend (route middleware + `$this->authorize()` in controllers), not just hidden UI elements — see `app/Enums/PermissionName.php` for the full permission list. Employee-level visibility (own/team/all) is enforced separately via `EmployeePolicy`, and certificate downloads re-check that same policy.
- Mass assignment is scoped per model via PHP `#[Fillable(...)]` attributes; every write path uses validated Form Request data, never raw `$request->all()`.
- File uploads (certificates) are restricted by MIME type and size (`StoreCertificateRequest`), stored on a private disk with no public URL, and served only through an authorized streaming download - see [File storage](#file-storage).
- An append-only audit trail (`audit_logs` table, `Auditable` trait) records create/update/delete on employees, certificates, skill assessments, job descriptions, training records, and development plans, viewable by Administrators at `/audit-logs`.
- No raw SQL string interpolation anywhere in the codebase - Eloquent/query builder throughout, so there is no SQL injection surface from user input.
- No `{!! !!}` unescaped Blade output anywhere - all dynamic content goes through `{{ }}` auto-escaping, so there is no reflected/stored XSS surface from user-entered text.
- No real PT Bekaert Indonesia employee data, credentials, or confidential information is included anywhere in this repository. All seed data is fictional.
- `.env` is git-ignored; `.env.example` contains no real secrets. Demo account passwords (`password`) are for local development only and are documented as such above.

These points reflect a manual review pass done for Phase 7, not a formal penetration test - if this application is ever considered for real deployment, have it reviewed by PT Bekaert Indonesia's security/IT team first.

## Known limitations

Scope decisions made deliberately, so a future maintainer doesn't mistake them for oversights:

- **Notifications are in-app only, not email** — `CertificateExpiringSoon` and `UpcomingTrainingSession` use Laravel's `database` notification channel only. Adding a `mail` channel later is a small change (implement `toMail()` on each notification class) since the data and scheduling are already in place.
- **No drag-and-drop training calendar** — the Training Calendar's grid view is a real month grid built with Blade/Tailwind (no JS calendar library), showing sessions on the days they occur with month navigation. It doesn't support dragging sessions between days; editing a session still goes through its own edit form.
- **`ext-gd` not actually installed** — this Codespace's PHP was compiled from source without the `gd` extension. `composer.json` declares `platform.ext-gd` so Composer will still install `phpoffice/phpspreadsheet` (which only *requires* `ext-gd` for chart/image features this project doesn't use), but a fresh environment should install the real extension if that ever changes. `ext-zip`, which `phpspreadsheet` genuinely needs at runtime, *is* installed.
- **Formal penetration testing not performed** — see the caveat at the end of [Security notes](#security-notes).

## Roadmap

All 7 phases from the original brief, plus every follow-on item identified afterwards (Settings, notifications, `.xlsx` export, visual calendar), are complete. No further items are currently planned; new ideas should be added here as they come up.
