# PROJECT_CONTEXT.md — Maintenance People Development System

Ringkasan kondisi proyek untuk melanjutkan pekerjaan setelah context auto-compact atau sesi baru. Baca file ini dulu sebelum melanjutkan.

## Apa proyek ini

Aplikasi web **Maintenance People Development System** untuk Departemen Maintenance PT Bekaert Indonesia (brief lengkap ada di prompt sistem awal percakapan ini). Dibangun dengan **Laravel 13, PHP 8.4, MySQL 8.0, Blade + Tailwind + Alpine.js**, di dalam GitHub Codespace di `/workspaces/maintenance`.

Brief asli membagi pekerjaan jadi **7 fase**. Semua 7 fase **sudah selesai dan di-commit**. Setelah itu user minta lanjutkan **4 item Roadmap** di README (di luar 7 fase asli) — **KEEMPATNYA SEKARANG SUDAH SELESAI SECARA FUNGSIONAL**: Settings page, Notifikasi in-app, Export .xlsx (3 item ini sudah di-commit), dan Kalender visual/grid (sudah selesai + teruji, belum di-commit). README juga sudah diupdate (4 item dipindah dari "Roadmap" ke "Implemented", Known Limitations direvisi, jumlah test diupdate ke 119) — update README ini juga belum di-commit.

## Status Git

- Branch `main`, belum pernah di-push ke remote.
- 7 commit fase (Phase 1 s/d Phase 7) + 1 commit gabungan "Add Settings page, in-app notifications, and Excel export (Roadmap items 1-3)".
- **Kalender visual (item 4) sudah selesai tapi BELUM di-commit** di titik penulisan ini — cek `git status` dulu. Juga update README (pemindahan Roadmap → Implemented) belum di-commit.
- Jangan pernah pakai `git checkout .` / `git reset --hard` tanpa cek dulu.

## Cara menjalankan environment (WAJIB tiap restart Codespace)

Container ini **tidak punya init system**, jadi MySQL dan server dev tidak otomatis jalan lagi setelah restart:

```bash
sudo service mysql start   # atau: bin/start.sh (sudah dibuat, otomatis start MySQL + serve)
```

Server utama untuk user (jangan diganggu tanpa perlu) jalan di:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```
dengan `.env` `APP_URL=https://fantastic-orbit-5vvj4996g7prcp6r4-8000.app.github.dev` (URL Codespace ini — **akan berubah kalau Codespace dibuat ulang**, harus di-update manual di `.env` + `php artisan config:clear` kalau itu terjadi).

**PENTING — kenapa APP_URL harus persis begini:** proxy Codespaces mengubah header `Host` sebelum sampai ke PHP built-in server, jadi kalau tidak dipaksa, Laravel bikin URL redirect/asset yang mengarah ke `localhost` (punya user sendiri, bukan Codespace) dan gagal connect. Fix-nya ada di `app/Providers/AppServiceProvider.php` (`URL::forceRootUrl()` + `Paginator::currentPathResolver()` custom). **Jangan hapus fix ini.**

## Cara saya (Claude) memverifikasi tiap fitur

Pola yang konsisten dipakai sepanjang sesi — lanjutkan pola ini:

1. Tulis kode + migration + test PHPUnit.
2. `php artisan test` — jalankan di SQLite in-memory (config di `phpunit.xml`), cepat, tidak butuh MySQL.
3. `php artisan migrate:fresh --force && php artisan db:seed --force` di MySQL asli (dev, boleh di-fresh karena cuma data dummy).
4. `npm run build` untuk rebuild asset Tailwind (PENTING: kalau lupa, class Tailwind baru di Blade tidak akan ke-compile dan tampilan jadi polos tanpa warna — ini pernah kejadian dan ketahuan lewat screenshot).
5. Jalankan **server terpisah** di port lain (8081, 8082, ..., sudah dipakai sampai 8086) dengan `APP_URL=http://127.0.0.1:PORT` supaya bisa dites headless browser tanpa perlu login GitHub (server utama port 8000 pakai APP_URL publik yang butuh auth GitHub, jadi TIDAK BISA dites langsung dari headless browser di sesi ini).
6. Playwright (`chromium`) dipakai untuk browser check sungguhan — screenshot tiap halaman, cek tidak ada console error, kadang sampai upload/download file asli dan compare byte-per-byte.
7. Playwright terinstall di `/tmp/claude-1000/-workspaces-maintenance/*/scratchpad/browser-check/node_modules` — **folder scratchpad bisa hilang sewaktu-waktu** (pernah kejadian), kalau `node_modules` hilang tinggal `npm install playwright@1.63.0` lagi (chromium browser binary-nya biasanya masih ke-cache di `~/.cache/ms-playwright`, jadi tidak perlu download ulang berkali-kali, tapi kalau perlu: `npx playwright install --with-deps chromium`).
8. Setelah verifikasi visual OK: bersihkan server test (`pkill -f "port=XXXX"`), hapus screenshot temp, jalankan full test suite sekali lagi, baru commit.

**Prinsip penting**: jangan percaya test suite SQLite doang. Beberapa bug nyata HANYA ketahuan lewat MySQL asli atau browser sungguhan (lihat "Bug yang pernah ditemukan" di bawah).

## Bug yang pernah ditemukan & diperbaiki (jangan diulangi)

1. **MySQL identifier > 64 karakter** — nama foreign key constraint otomatis dari Laravel bisa kepanjangan untuk MySQL (limit 64 char). Kejadian di `employee_skill_assessments` (index) dan `employee_development_plans` (FK ke training_programs). Fix: kasih nama constraint eksplisit yang pendek sebagai parameter ketiga di `->constrained('table', 'id', 'nama_pendek')` atau nama index custom di `$table->index([...], 'nama_pendek')`.
2. **Ambiguous column setelah JOIN** — di `DashboardController::averageCurrentCompetencyScore()`, awalnya pakai `whereIn('id', ...)` setelah `->join()`, MySQL error "column 'id' ambiguous". Test SQLite lolos karena SQLite lebih longgar. **Ketahuan cuma lewat browser check ke MySQL asli.** Fix akhir: pakai pendekatan collection (fetch semua, `->unique()->avg()` di PHP) bukan query SQL kompleks, supaya konsisten dengan logic serupa di tempat lain.
3. **HAVING tanpa GROUP BY** — di `ReportController::trainingHours()`, `->having('some_alias', '>', 0)` tanpa GROUP BY jalan di MySQL tapi invalid di SQLite (test gagal). Fix: filter di collection PHP (`->filter()->sortByDesc()`), bukan di query.
4. **Boolean checkbox HTML tidak terkirim saat unchecked** — pattern klasik: kalau checkbox di-uncheck, field-nya tidak ada sama sekali di request. Fix konsisten: pakai `$request->boolean('field_name')` secara eksplisit di controller, jangan andalkan `$request->validated()` biasa untuk field boolean checkbox.
5. **Tailwind class baru tidak muncul sampai `npm run build`** — karena Tailwind scan file Blade saat build, bukan runtime. Kejadian beberapa kali (badge warna tidak muncul). Selalu `npm run build` setelah tambah class Tailwind baru sebelum verifikasi visual.

## Arsitektur & konvensi penting yang sudah dibangun

- **Roles & Permissions**: `spatie/laravel-permission`. 5 role: Administrator, Maintenance Manager, Maintenance Supervisor, Maintenance Staff, People Development. Nama role & permission di-enum-kan di `app/Enums/RoleName.php` dan `app/Enums/PermissionName.php` — **jangan pernah hardcode string role/permission di tempat lain**, selalu pakai `PermissionName::XXX->value`. Daftar permission per role ada di `database/seeders/RolesAndPermissionsSeeder.php`.
- **Otorisasi berlapis**: route middleware `can:permission.value` untuk akses per-modul, PLUS `EmployeePolicy` untuk scoping per-employee (staff cuma lihat diri sendiri, supervisor cuma timnya, manager/admin/HR lihat semua). `CertificateController::download()` reuse `EmployeePolicy` yang sama supaya staff tidak bisa akses sertifikat karyawan lain.
- **Master data generic**: `config/master_data.php` + `MasterDataController` — SATU controller generic untuk 12+ tabel lookup (departments, positions, skills, training-categories, dst) supaya tidak duplikasi kode CRUD. Kalau mau tambah master data baru, cukup tambah entry di config, tidak perlu controller baru.
- **Gap kompetensi**: SATU sumber logika di `Employee::skillGapRows()` — dipakai bareng oleh tab profil karyawan, Skill Matrix, Dashboard, dan Competency Gap Analysis. **Jangan bikin logika gap terpisah di tempat lain**, selalu reuse method ini.
- **CSV export**: trait `App\Concerns\ExportsCsv` dipakai di beberapa controller (`EmployeeController`, `CertificateController`, `TrainingRecordController`, `ReportController`) via query param `?export=csv`.
- **Audit log**: trait `App\Concerns\Auditable` dipasang di model-model sensitif (Employee, Certificate, EmployeeSkillAssessment, JobDescription, TrainingRecord, EmployeeDevelopmentPlan). Otomatis nyatat create/update/delete ke tabel `audit_logs`. **User model SENGAJA TIDAK pakai trait ini** (supaya password hash tidak pernah kecatat di log).
- **File storage sertifikat**: disk `local` (private, `storage/app/private`), BUKAN disk `public`. Download cuma lewat route `certificates.download` yang re-check policy. Validasi tipe file: `mimes:pdf,jpg,jpeg,png`, max 5MB.
- **Model pakai PHP Attribute `#[Fillable([...])]`** (bukan `protected $fillable = [...]`) — ini gaya Laravel 13 baru, ikuti pola yang sudah ada di semua model existing.
- **Seed data**: sengaja fiktif, TIDAK BOLEH pakai data asli PT Bekaert Indonesia. Akun demo: `admin@mpd.test`, `manager@mpd.test`, `supervisor@mpd.test`, `staff@mpd.test`, `hr@mpd.test`, semua password `password`. Seeder saling berkaitan (satu "cerita" gap kompetensi Mechanical Maintenance yang mengalir dari Phase 3 sampai Phase 5).

## Sedang dikerjakan sekarang: item Roadmap (di luar 7 fase asli)

User minta lanjutkan 4 item di README bagian Roadmap:

### 1. Settings page — ✅ SELESAI, sudah teruji, TAPI BELUM DI-COMMIT
File yang dibuat/diubah:
- `database/migrations/2026_09_15_024229_create_settings_table.php` — tabel key-value.
- `app/Models/Setting.php` — helper `Setting::get()`, `Setting::getInt()`, `Setting::set()`, dengan cache (`Cache::rememberForever`, di-clear tiap `set()`). `DEFAULTS` array: `certificate_expiring_soon_days` (60), `training_reminder_days_before` (7).
- `app/Http/Controllers/SettingController.php` — `edit()` + `update()`, form sederhana (bukan generic key-value editor).
- `resources/views/settings/edit.blade.php`.
- Routes ditambah di `routes/web.php`: `GET/PUT /settings`, gated `PermissionName::ManageSettings` (cuma Administrator).
- `resources/views/layouts/sidebar.blade.php` — nav "Settings" sudah diaktifkan (sebelumnya "Soon").
- `app/Models/Certificate.php` — constant `EXPIRING_SOON_DAYS` diganti method `Certificate::expiringSoonDays()` yang baca dari `Setting`. Semua pemakai (`CertificateController`, `DashboardController`) sudah diupdate ikut pakai method ini.
- Test: `tests/Feature/SettingTest.php` — 3 test, SEMUA LULUS (termasuk test bahwa ganti setting benar-benar mengubah status certificate).
- **Migrasi INI SUDAH DIJALANKAN** di MySQL dev (`php artisan migrate --force` sudah sukses).
- Full test suite terakhir kali dicek: 104+3 = harus 107 pass (belum di-cek ulang setelah ini, karena lanjut ke item berikutnya).

### 2. Export .xlsx — ✅ SELESAI, sudah teruji, TAPI BELUM DI-COMMIT
File yang dibuat/diubah:
- **Composer**: `phpoffice/phpspreadsheet` (^5.9) ditambahkan.
- **PENTING — masalah lingkungan yang diperbaiki**: PHP hasil compile-dari-source di Codespace ini awalnya TIDAK punya extension `zip` dan `gd`. `phpspreadsheet` butuh `ext-zip` (wajib, dipakai baca/tulis file .xlsx yang sebenarnya format ZIP) dan `ext-gd` (cuma untuk fitur gambar/chart yang TIDAK kita pakai).
  - `ext-zip`: sudah benar-benar dipasang — `sudo apt install libzip-dev`, lalu `sudo pecl install zip`, lalu tambah `extension=zip.so` ke `/usr/local/php/8.4.15/ini/php.ini`. Ini permanen selama image/container ini tidak di-rebuild ulang dari awal.
  - `ext-gd`: TIDAK dipasang beneran (perlu compile ulang PHP dari source untuk itu, terlalu berat cuma untuk fitur yang tidak dipakai). Sebagai gantinya, `composer.json` bagian `config.platform` diisi `"ext-gd": "1.0"` supaya Composer percaya extension itu "ada" dan tidak block install. **Kalau nanti fitur ekspor butuh gambar/logo di Excel, harus compile ulang PHP dengan `--with-gd` atau cari alternatif.**
  - **Kalau pindah ke devcontainer/mesin baru**: pastikan `ext-zip` beneran terpasang (bukan cuma di-declare di composer.json), karena tanpa itu generate .xlsx akan error runtime meskipun `composer install` sukses.
- `app/Concerns/ExportsSpreadsheet.php` — trait baru, method `streamXlsx($filename, $header, $rows)`, mengikuti bentuk yang sama persis dengan `ExportsCsv::streamCsv()` supaya gampang dipasang berdampingan. Header dibuat bold, lebar kolom auto-size.
- Controller yang diupdate (masing-masing sekarang `use ExportsCsv, ExportsSpreadsheet;` dan punya percabangan `csv` vs `xlsx` dari satu data `$header`/`$rows` yang sama, tidak ada duplikasi query): `EmployeeController`, `CertificateController`, `TrainingRecordController`, `ReportController` (2 tempat: `trainingHours()` dan `assessmentHistory()`).
- View yang ditambah tombol "Export XLSX" di sebelah "Export CSV": `resources/views/employees/index.blade.php`, `resources/views/certificates/index.blade.php`, `resources/views/training/records/index.blade.php`, `resources/views/reports/training-hours.blade.php`, `resources/views/reports/assessment-history.blade.php`.
- Test baru: `tests/Feature/ExportsSpreadsheetTest.php` (baca-ulang file .xlsx yang di-generate pakai `PhpOffice\PhpSpreadsheet\Reader\Xlsx`, cek header + isi sel persis sama dengan yang ditulis), plus 1 test baru di `tests/Feature/ReportTest.php` untuk content-type xlsx. **Total test suite sekarang 116, SEMUA LULUS.**
- Verifikasi browser end-to-end dengan Playwright: login, download xlsx sungguhan dari kelima halaman (Employees, Certificates, Training Records, Training Hours report, Assessment History report), semua terunduh tanpa error konsol, lalu satu file (Employees) dibuka ulang pakai PhpSpreadsheet dan dicek jumlah baris + isi kolom persis cocok dengan jumlah data di database (18 employee + 1 header = 19 baris).

### 3. Notifikasi in-app — ✅ SELESAI, sudah teruji, TAPI BELUM DI-COMMIT
Sudah dibuat (file baru, migrasi SUDAH DIJALANKAN di MySQL dev):
- `database/migrations/2026_09_15_024624_create_notifications_table.php` — tabel standar Laravel notifications (hasil `php artisan notifications:table`).
- `database/migrations/2026_09_15_024632_add_expiry_notified_at_to_certificates_table.php` — kolom `expiry_notified_at` di `certificates` (supaya tidak notif berkali-kali untuk sertifikat yang sama).
- `database/migrations/2026_09_15_024633_add_reminded_at_to_training_participants_table.php` — kolom `reminded_at` di `training_participants`.
- `app/Notifications/CertificateExpiringSoon.php` — notifikasi channel `database`, isi: nama sertifikat, nama karyawan, tanggal expired, link ke `employees.show` dengan `?tab=certificates`.
- `app/Notifications/UpcomingTrainingSession.php` — notifikasi channel `database`, link ke `training.sessions.show`.
- `app/Console/Commands/SendExpirationNotifications.php` — command `app:send-expiration-notifications`. Logic: cari certificate yang `expiry_notified_at IS NULL` dan status computed-nya `expiring_soon`, kirim ke user dengan permission `ManageCertificates` + user milik employee itu sendiri (kalau ada), lalu set `expiry_notified_at`. Untuk training: cari `TrainingParticipant` yang `reminded_at IS NULL` dan sesi mulai dalam `Setting::getInt('training_reminder_days_before')` hari ke depan, kirim ke `employee->user` kalau ada, set `reminded_at`.
- `routes/console.php` — `Schedule::command('app:send-expiration-notifications')->daily();`
- `app/Http/Controllers/NotificationController.php` — `read($id)` (mark as read lalu redirect ke `notification->data['url']`), `readAll()`.
- Routes ditambah: `POST /notifications/{id}/read`, `POST /notifications/read-all` (nama route: `notifications.read`, `notifications.read-all`).
- `resources/views/employees/show.blade.php` — baris Alpine `x-data` diubah supaya baca `?tab=` dari query string dulu, baru fallback ke `session('activeTab')`: 
  ```blade
  <div x-data="{ tab: '{{ request('tab', session('activeTab', 'overview')) }}' }" class="space-y-6">
  ```

Yang sudah diselesaikan setelah interupsi terakhir:
- ✅ `resources/views/layouts/topbar.blade.php` — bell icon diganti dropdown asli (`<x-dropdown>`), badge merah jumlah unread, list 10 notifikasi terbaru (judul/pesan/waktu), tiap item form POST ke `notifications.read`, tombol "Mark all as read" ke `notifications.read-all`.
- ✅ 3 migrasi notifikasi dijalankan di MySQL dev.
- ✅ **Bug ditemukan & diperbaiki**: `TrainingParticipant::update(['reminded_at' => ...])` dan `Certificate::update(['expiry_notified_at' => ...])` gagal diam-diam karena kolom itu tidak ada di `#[Fillable([...])]` masing-masing model — mass-update jadi no-op. Fix: pakai `forceFill([...])->save()` di `SendExpirationNotifications` untuk kedua kolom sistem ini (bukan tambah ke fillable, karena keduanya bukan field yang boleh diisi user lewat form).
- ✅ **Bug ditemukan & diperbaiki**: `Setting::allCached()` awalnya cache `Collection` lewat `Cache::rememberForever` (driver `database`) — kadang unserialize gagal jadi `__PHP_Incomplete_Class` di tengah loop (butuh 2-3 pemanggilan berturut baru muncul, jadi gampang lolos dari test cepat). Fix: cache sebagai **array biasa** (`->pluck('value','key')->all()`), bukan Collection — lebih aman untuk semua cache driver.
- ✅ Test baru: `tests/Feature/SendExpirationNotificationsTest.php` (4 test: certificate manager dapat notif sekali, employee sendiri juga dapat notif, training reminder sekali, training di luar window tidak dapat notif) dan `tests/Feature/NotificationControllerTest.php` (3 test: mark-read punya sendiri, tidak bisa mark-read punya orang lain — 404, mark-all cuma pengaruhi punya sendiri). **Total test suite sekarang 114, SEMUA LULUS.**
- ✅ Verifikasi browser end-to-end dengan Playwright di MySQL asli: seed data manual (sertifikat expiring-soon, sesi training 3 hari lagi), jalankan command manual, badge unread muncul (angka benar), dropdown menampilkan isi notifikasi, klik notifikasi menandai read + redirect ke `?tab=certificates` yang benar-benar membuka tab Certificates, "Mark all as read" mengosongkan badge. Tidak ada console error. Data uji sudah dibersihkan lagi setelah verifikasi.
- Catatan: ditemukan beberapa proses `php artisan serve` basi dari sesi-sesi sebelumnya (port 8082-8086) masih jalan di background sejak kemarin — sudah di-kill semua. Kalau nemu lagi proses serve nyangkut, cek `ps aux | grep "php -S"` dan bersihkan.

Item 3 (Notifikasi) sekarang berstatus **selesai penuh**, tinggal menunggu commit bersama item Roadmap lain.

### 4. Kalender visual (month/week grid) — ✅ SELESAI, sudah teruji, BELUM DI-COMMIT
File yang diubah:
- `app/Http/Controllers/TrainingSessionController.php` — `calendar()` sekarang cabang ke `list` (default, perilaku lama tidak berubah) atau `calendarGrid()` (baru) berdasar `?view=grid`. `calendarGrid()` terima `?month=YYYY-MM` (default bulan berjalan), bangun array minggu (Senin—Minggu, termasuk hari dari bulan sebelum/sesudah untuk mengisi grid), tiap hari berisi daftar sesi yang overlap hari itu (`$day->between($session->start_date, $session->end_date)`).
- `resources/views/training/sessions/calendar.blade.php` — toggle tombol "List"/"Grid" di kanan atas, grid view baru: navigasi Prev/Next bulan, header hari (Mon-Sun), tiap sel tanggal menampilkan sesi yang jatuh di hari itu (link ke `training.sessions.show`, warna sesuai status memakai style yang sama dengan list view), tanggal hari ini di-bold, hari di luar bulan berjalan diberi warna abu-abu. View list lama tidak diubah sama sekali (dibungkus `@else`/`@endif`), jadi regresi kecil kemungkinannya.
- Murni Blade + Tailwind, **tidak ada library JS kalender baru** — sesuai prinsip minimal-dependency, dan Known Limitations di README sudah diupdate untuk mencerminkan ini (bukan dihapus, tapi direvisi jadi soal keterbatasan grid ini, bukan soal "tidak ada grid sama sekali").
- Test baru: 3 test ditambahkan ke `tests/Feature/TrainingSessionTest.php` (default view = list, sesi muncul di tanggal yang benar pada grid, sesi dari bulan lain tidak ikut muncul). **Total test suite sekarang 119, SEMUA LULUS.**
- Verifikasi browser dengan Playwright + MySQL asli: toggle List↔Grid berfungsi, navigasi Prev/Next bulan menampilkan sesi yang benar di bulan yang benar (termasuk sesi yang melintasi 2 hari, muncul di kedua tanggal), tanggal hari ini ter-bold. Tidak ada console error.

## File-file kunci untuk orientasi cepat

- `routes/web.php` — semua route, dikelompokkan per modul, gampang dibaca urutannya sama dengan urutan fase dibangun.
- `config/master_data.php` — daftar semua master data generic.
- `app/Enums/PermissionName.php`, `app/Enums/RoleName.php` — semua nama role/permission.
- `database/seeders/DatabaseSeeder.php` — urutan seeder (penting kalau nambah seeder baru, taruh sesuai dependency).
- `README.md` — dokumentasi lengkap, **sudah diupdate**: 4 item Roadmap dipindah ke "Implemented", Known Limitations direvisi, jumlah test jadi 119, bagian Roadmap sekarang bilang semua sudah selesai.
- `bin/start.sh` — script convenience start MySQL + server.

## Status akhir: SEMUA 4 ITEM ROADMAP SUDAH SELESAI

- Item 1 (Settings), 2 (Export .xlsx), 3 (Notifikasi) — **sudah di-commit** (satu commit gabungan: "Add Settings page, in-app notifications, and Excel export (Roadmap items 1-3)").
- Item 4 (Kalender visual) — selesai + teruji, **belum di-commit**. Yang harus dilakukan berikutnya: commit perubahan kalender (`app/Http/Controllers/TrainingSessionController.php`, `resources/views/training/sessions/calendar.blade.php`, `tests/Feature/TrainingSessionTest.php`) plus update README (`README.md`) — bisa satu commit gabungan seperti pola sebelumnya, atau tanya user dulu kalau mau dipisah.
- Jangan push ke remote kecuali diminta eksplisit oleh user.
- Setelah commit ini, tidak ada lagi item Roadmap tersisa — proyek dalam kondisi feature-complete sesuai 7 fase asli + 4 follow-on.
