# Tutorial Penggunaan — Maintenance People Development System

Panduan ini untuk pengguna aplikasi internal Departemen Maintenance PT Bekaert Indonesia. Menu yang tampil di sidebar bisa berbeda-beda antar pengguna, tergantung **role** dan pengaturan dari Administrator.

<!-- hide-in-app:start -->
> **Catatan untuk developer:** dokumen ini harus selalu diperbarui bersama setiap perubahan aplikasi (fitur baru, menu, field, alur, atau perilaku yang berubah). Aturannya ada di [CLAUDE.md](../CLAUDE.md). Catat perubahan di bagian [Riwayat Pembaruan](#riwayat-pembaruan) di bawah.

## Daftar Isi

Di dalam aplikasi, daftar isi dan isi panduan **otomatis disesuaikan dengan akses pengguna yang login** (aturan penanda ada di [CLAUDE.md](../CLAUDE.md)).

- Memulai · Mengenal Tampilan · Role dan Hak Akses · Dashboard
- Panduan Tamu (Guest)
- Task · People & Organization · Competency · Training & Development · Safety · Insights & System
- Panduan Administrator · Tanya Jawab & Masalah Umum · Riwayat Pembaruan

<!-- hide-in-app:end -->

---

## Memulai

### Login
1. Buka alamat aplikasi, lalu isi **Email atau NIK** dan **Password**.
2. Klik **Log in**.

Pilihan lain di halaman login:
- **Login with Face** — muncul hanya jika perangkat ini sudah didaftarkan untuk Face Login (lihat *Face Login* di bawah).
- **Continue browsing as guest** — masuk sebagai tamu, hanya bisa **melihat** data, tidak bisa mengubah apa pun.
- **Forgot password** — minta tautan reset password lewat email.

<!-- only:not-guest -->
### Login pertama: wajib ganti password
Akun yang dibuat dengan password awal akan langsung diarahkan ke halaman **Profile**. Sebelum password diganti, hanya halaman Profile yang bisa dibuka. Isi password baru di bagian *Update Password*, lalu menu lain terbuka.
<!-- /only -->

<!-- only:not-guest -->
### Profile
Buka dari menu pengguna di pojok kanan atas.
- **Informasi profil** — nama dan email.
- **Update Password** — ganti password.
- **Face Login** — daftarkan wajah agar bisa login dan check-in training dengan kamera.
<!-- /only -->

<!-- only:not-guest -->
### Face Login
1. Buka **Profile → Face Login** dari perangkat yang akan dipakai, izinkan akses kamera.
2. Ikuti petunjuk untuk merekam wajah. Perangkat tersebut menjadi *trusted device*.
3. Di halaman login perangkat itu akan muncul tombol **Login with Face**.
4. Untuk berhenti memakainya, pilih opsi nonaktifkan di bagian yang sama.

Face Login yang sama juga dipakai untuk **check-in kehadiran training** (lihat *Kehadiran dengan Face Check-in* di bagian Training & Development, bila menu itu tersedia untuk Anda).
<!-- /only -->

### Melengkapi profil lewat QR / link onboarding
Karyawan baru bisa menerima **link onboarding** (biasanya lewat QR) dari HR. Buka link itu (tanpa login), lengkapi data profil, lalu kirim. Link punya masa berlaku; bila kedaluwarsa atau tidak ditemukan, minta HR membuat link baru dari profil karyawan.

---

## Mengenal Tampilan

- **Sidebar kiri** — menu utama dikelompokkan: *Task*, *People & Organization*, *Competency*, *Training & Development*, *Safety*, *Insights & System*. Klik nama grup untuk membuka/menutup. Di ponsel sidebar tersembunyi dan dibuka dengan tombol menu.
- **Kotak Search menu** — di atas sidebar, untuk mencari menu dengan cepat.
- **Help** — menu di bagian bawah sidebar yang membuka panduan ini langsung di dalam aplikasi, lengkap dengan daftar isi. Isi panduan menyesuaikan akun yang login: hanya fitur yang bisa Anda akses yang ditampilkan.
- **Tombol collapse** — di bawah sidebar, untuk menciutkan sidebar menjadi ikon saja.
- **Top bar** — ikon **lonceng notifikasi** (dengan angka belum dibaca) dan menu pengguna (Profile, Logout).
- **Lencana “🔒 View only”** — muncul di halaman daftar bila Anda hanya boleh melihat, tidak boleh mengedit menu itu.
- **Filter, pencarian, dan ekspor** — kebanyakan halaman daftar punya panel filter, kotak cari, dan tombol ekspor **CSV / XLSX**.

### Notifikasi
Klik lonceng untuk melihat notifikasi, lalu klik salah satu untuk langsung menuju halaman terkait. Gunakan **mark all as read** untuk menandai semuanya dibaca. Notifikasi yang dikirim antara lain:
- sertifikat yang akan segera kedaluwarsa (untuk pengelola sertifikat dan pemilik sertifikat),
- pengingat sesi training yang akan datang,
- tugas yang ditugaskan kepada Anda atau ada aktivitas baru pada tugas,
- pengumuman News baru,
- permintaan dan persetujuan edit data Overtime.

---

## Role dan Hak Akses

| Role | Cakupan data karyawan | Garis besar kemampuan |
|---|---|---|
| **Administrator** | Semua | Semua fitur, termasuk User Management, Roles, Settings, Audit Log |
| **People Development** (HR) | Semua | Melihat seluruh karyawan; mengelola training, sertifikat, dll. sesuai izin |
| **Maintenance Manager** | Diri sendiri + bawahan langsung | Mengelola job description, skill, training, development plan, safety, melihat laporan |
| **Maintenance Supervisor** | Diri sendiri + bawahan langsung | Melihat tim, mencatat overtime tim, menilai kompetensi sesuai izin |
| **Maintenance Staff** | Diri sendiri | Melihat data sendiri; boleh membuat/mengedit Job Description secara default |
| **Guest** | Semua (hanya lihat) | Tidak bisa mengubah apa pun, apa pun pengaturannya |

Dua hal yang perlu dipahami:
1. **Visibilitas data karyawan mengikuti hierarki atasan–bawahan** (satu tingkat). Supervisor/Manager melihat dirinya dan bawahan langsungnya; yang tidak punya bawahan hanya melihat dirinya. Aturan ini sama di semua modul (Employees, Skill Matrix, Gap Analysis, Training Records, Certificates, Development Plans).
2. **Hak edit diatur per pengguna dan per menu** oleh Administrator. Secara default, edit mengikuti izin role-nya; Job Description boleh diedit semua orang. Tanpa hak edit, halaman tampil dengan lencana *View only* dan tombol tambah/ubah/hapus tidak muncul.

Administrator juga dapat **menyembunyikan menu tertentu** dari pengguna tertentu (misalnya menu yang belum siap dipakai). Menu yang disembunyikan juga tidak bisa dibuka lewat URL.

---

## Dashboard

Halaman pertama setelah login. Menampilkan:
- **Kartu statistik** berisi angka nyata dari database (karyawan, organisasi, skill/kompetensi, training, sertifikat), beberapa dengan grafik kecil (misalnya karyawan Aktif vs Tidak Aktif, status gap kompetensi, distribusi status sertifikat).
- **Klik kartu** untuk langsung membuka modul terkait. Kartu hanya bisa diklik jika Anda berhak membuka modul tersebut.
- **News** — pengumuman dari Maintenance. Pengumuman yang mensyaratkan konfirmasi harus Anda **konfirmasi sudah dibaca** (lihat bagian News).

---
<!-- only:guest -->
## Panduan Tamu (Guest)

Anda masuk sebagai **tamu**. Akun tamu dipakai bersama dan bersifat **hanya-lihat**:
- Anda dapat melihat data di menu yang tersedia di sidebar, tetapi **tidak dapat menambah, mengubah, atau menghapus** apa pun — apa pun pengaturannya.
- Halaman Profile, Face Login, Task, dan Shift Comm tidak tersedia untuk tamu.
- Gunakan filter, pencarian, dan ekspor di halaman daftar untuk menelusuri data.
- Untuk mendapat akun pribadi dengan hak lebih luas, hubungi Administrator atau HR.

---
<!-- /only -->

<!-- only:tasks|shift-comm -->
## Task

<!-- only:tasks -->
### My Task
Papan tugas gaya *planner* (plan → bucket → task).

**Membuat plan**
1. Buka **Task → My Task**, klik **Create Plan**, beri nama.
2. Di dalam plan, tambahkan **bucket** (kolom) sesuai kebutuhan, misalnya *To Do*, *In Progress*, *Done*.

**Membuat dan mengelola task**
1. Tambahkan task ke bucket, lalu klik task untuk membuka detail.
2. Atur judul, deskripsi, penerima tugas (*assignee*), dan tenggat.
3. Tambahkan **checklist** untuk langkah-langkah kecil, lalu centang saat selesai.
4. Pindahkan task antar bucket dengan *drag*, atau centang **complete** bila sudah selesai.

Tugas yang ditugaskan kepada Anda muncul di bagian tugas Anda dan memicu notifikasi. Bila akun Anda belum terhubung ke data karyawan, tugas tidak bisa ditugaskan ke Anda; hubungi Administrator/HR.
<!-- /only -->

<!-- only:shift-comm -->
### Shift Comm
Catatan komunikasi antar-shift (serah terima).
1. Buka **Task → Shift Comm** → tambah entri baru.
2. Pilih **tim**, **tanggal**, dan **shift**. Tanggal dan shift menjadi bagian dari ID entri sehingga **tidak bisa diubah** setelah dibuat.
3. Isi status dan catatan, lalu simpan.
4. Entri dapat dibuka, diedit, atau dihapus sesuai hak Anda.
5. Bila ada mesin dengan problem berulang, banner kuning **WARNING** muncul di atas halaman. Banner ini **tertutup secara default** (hanya menampilkan jumlah mesin); klik banner untuk membuka/menutup rincian per mesin.
<!-- /only -->

---
<!-- /only -->
<!-- only:employees|organization-chart|qr-codes|plant-manager|organization|overtime -->
## People & Organization

<!-- only:employees -->
### Employees (Database Karyawan)
**Melihat dan mencari**
- Daftar menampilkan Foto, NIK, Nama, Supervisor, Posisi, Skill Position, dan Shift.
- Cari berdasarkan NIK atau nama.
- Filter: Business Unit, Department, Employment Type, Employment Source, Employment Status (default **Active**), Supervisor, dan Shift. Beberapa filter bisa dipilih lebih dari satu nilai.

<!-- only:edit:employees -->
**Menambah karyawan**
1. Klik **Add Employee**, isi formulir, simpan.
2. Anda juga bisa **impor** banyak karyawan sekaligus lewat fitur import.

**Menghapus** — centang beberapa baris lalu **Delete Selected**. Setiap baris diperiksa haknya satu per satu.
<!-- /only -->

**Profil karyawan** (klik nama) memakai tab:
- **Overview** — foto, Business Unit, Skill Position, Employment Source, klasifikasi Blue Collar / White Collar Management, dan data lain.
- **Job Description**, **Skills**, **Training**, **Certificates**, **Development Plans** — ringkasan modul terkait untuk karyawan itu.
<!-- only:edit:employees -->
- Tombol untuk membuat ulang **link onboarding** karyawan.
<!-- /only -->

> “Manager” tidak diisi manual; ditentukan otomatis dari rantai supervisor.
<!-- /only -->

<!-- only:organization-chart -->
### Organization Chart
Bagan organisasi berbasis foto dari hierarki supervisor.
- Tiap kotak menampilkan foto/inisial, NIK, nama, dan Skill Position.
- **Biru** = Blue Collar, **putih** = White Collar Management.
- Filter berdasarkan Business Unit, Department, dan Employment Type; legenda menampilkan jumlah tenaga kerja.
- Tim teknisi menampilkan jumlah per trade.
- Cakupan yang terlihat mengikuti hierarki (HR/Admin melihat seluruh perusahaan).
<!-- /only -->

<!-- only:qr-codes -->
### QR Codes
Menampilkan QR verifikasi sertifikat untuk tiap karyawan (cocok untuk badge).
- **Download PNG** atau **Print This Page**.
- QR membuka halaman publik verifikasi sertifikat karyawan tersebut (tanpa login).
- **Base URL** yang dipakai di QR hanya bisa diubah Administrator.
<!-- /only -->

<!-- only:plant-manager -->
### Plant Manager
Plant Manager (pemimpin tertinggi) **tidak muncul** di daftar Employees maupun total karyawan; datanya hanya dikelola di halaman ini. Isi data lalu **Save Changes**.
<!-- /only -->

<!-- only:organization -->
### Organization (Master Data)
Hanya untuk pengguna dengan izin master data (default: Administrator). Dari landing page pilih jenis data: departemen, divisi, area maintenance, tim, posisi, business unit, skill position, employment type/source/status, shift, lokasi, kategori skill, level kompetensi, dan lain-lain.
- **Create**, **Edit**, nonaktifkan, atau **Delete** data.
- Tersedia **Import** dan **Export** per jenis data.
<!-- /only -->

<!-- only:overtime -->
### Overtime
Pencatatan lembur oleh atasan untuk bawahan langsungnya.

**Mencatat lembur (Supervisor)**
1. Buka **Overtime → Create**.
2. Pilih karyawan (hanya bawahan langsung), isi waktu mulai dan selesai (selesai harus setelah mulai), **remarks** wajib, dan **jenis kompensasi**.
3. Simpan. Entri akan **terkunci**.

**Mengubah entri terkunci**
1. Pemilik entri klik **Request Edit** dan mengajukan alasan (*Submit Request*).
2. Pengelola Overtime menyetujui (**Approve**) atau menolak (**Reject**) di daftar *Awaiting approval*.
3. Jika disetujui, entri dapat diedit lalu otomatis terkunci kembali.

**Pengelola Overtime**
- Grafik *Overtime Trend* 6 bulan terakhir.
- **Export Selected (.xlsx)** dan **Mark Selected as Submitted to HR**. Tanpa pilihan, tombol memproses semua entri yang belum diserahkan.
<!-- /only -->

---
<!-- /only -->
<!-- only:job-descriptions|skills|skill-matrix|competency-gap -->
## Competency

<!-- only:job-descriptions -->
### Job Descriptions
Alur versi: **Draft → Pending Review → Active → Archived**.
1. **Create** job description untuk sebuah posisi (default semua orang boleh membuat dan mengedit draft).
2. **Submit for Review** setelah selesai.
3. **Approve** oleh Administrator/Manager (hanya role tersebut yang boleh menyetujui).
4. Untuk mengubah yang sudah aktif, gunakan **New Revision** — riwayat versi tidak ditimpa.
5. **Archive** versi yang tidak dipakai lagi.

Job description aktif tampil di tab *Job Description* pada profil karyawan.
<!-- /only -->

<!-- only:skills -->
### Skills & Competencies
Halaman landing berisi:
- **Skill Catalog** — daftar skill, bisa impor/ekspor (tersedia template).
- **Position Defaults** — skill bawaan per posisi.
- **Positions / Requirements** — skill dan level yang disyaratkan tiap posisi (tambah/hapus, impor/ekspor).
- **Skill Checklist** karyawan — dari profil karyawan, centang skill yang dikuasai.

**Skill assessment** dicatat dari profil karyawan (tab Skills): pilih skill dan level. Riwayat **tidak pernah ditimpa**; level saat ini adalah penilaian terbaru. Skill yang belum dinilai ditampilkan sebagai *not assessed* — tidak dianggap memenuhi syarat.
<!-- /only -->

<!-- only:skill-matrix -->
### Skill Matrix
Matriks karyawan × skill yang membandingkan level saat ini dengan level yang disyaratkan.
- **Gap = required − current.**
- Filter berdasarkan area, tim, posisi, kategori skill.
- Status per karyawan: *Meets*, *Development Required*, *Assessment Incomplete*, *No Requirements Defined*.
<!-- /only -->

<!-- only:competency-gap -->
### Competency Gap Analysis
Rangkuman gap tingkat departemen: skill dengan gap terbesar, posisi dan area yang paling butuh pengembangan, daftar karyawan yang punya gap, serta **saran training** (hanya saran, bukan keputusan yang disetujui).
<!-- /only -->

---
<!-- /only -->
<!-- only:training-management|training-calendar|training-records|certificates|recertification|signatories|development-plans -->
## Training & Development

<!-- only:training-management -->
### Training Management
**Program** — buat program training: kategori, tipe, penyedia, biaya, durasi, dan skill terkait.

**Sesi (jadwal)**
1. Buka program → **Create Session**; isi tanggal (selesai tidak boleh sebelum mulai) dan kapasitas maksimum.
2. Di halaman sesi, **tambah peserta** dan catat **kehadiran** tiap peserta. Jumlah peserta dibatasi kapasitas.

**Kuis**
- Pada program, kelola **bank soal** (tambah soal, impor/ekspor).
- Peserta mengerjakan kuis, dan skor tercatat. Pengelola dapat melihat jawaban peserta dan **mereset** kuis seorang peserta.
<!-- /only -->

<!-- only:training-management -->
### Kehadiran dengan Face Check-in
1. Buka halaman sesi → **Check-in**, lalu **Start Check-in** (biarkan terbuka di perangkat di lokasi training).
2. Setiap peserta menghadap kamera bergantian; wajah yang cocok otomatis ditandai **hadir**.
3. Peserta yang belum mendaftarkan Face Login atau tidak dikenali ditandai hadir **manual** dari halaman sesi.
<!-- /only -->

<!-- only:training-calendar|training-records -->
### Mengerjakan Kuis Training
Bila sesi training yang Anda ikuti punya kuis, Anda akan mengerjakannya sebagai peserta. Skor Anda tercatat; bila perlu diulang, pengelola training dapat mereset kuis Anda.
<!-- /only -->

<!-- only:training-calendar -->
### Training Calendar
Melihat jadwal dalam dua tampilan: **list** dan **grid bulanan** (navigasi antar bulan).
<!-- /only -->

<!-- only:training-records -->
### Training Records
Riwayat training per karyawan: program, tanggal, jam, kehadiran/status, skor/hasil, level kompetensi sebelum–sesudah, dan referensi sertifikat. Ada indeks global yang bisa dicari, impor, dan ekspor. Total jam training tampil di profil karyawan.
<!-- /only -->

<!-- only:certificates -->
### Certificates
- **Menambah** — dari profil karyawan (tab Certificates) atau impor massal. Isi tipe, nomor, penerbit, tanggal terbit dan kedaluwarsa, lalu unggah file (**PDF/JPG/PNG, maks 5 MB**). File disimpan privat dan hanya bisa diunduh lewat aplikasi oleh pihak yang berhak.
- **Status** dihitung otomatis dari tanggal kedaluwarsa: *Valid*, *Expiring Soon*, *Expired*, *No Expiry*, *Pending Verification*. Sertifikat yang sekadar diunggah tidak otomatis dianggap valid.
- **Verifikasi publik** — halaman publik (tanpa login) untuk memverifikasi sertifikat lewat QR.
<!-- /only -->

<!-- only:recertification -->
### Recertification
Daftar sertifikat yang perlu diperbarui. Pilih rentang waktu (termasuk *Already overdue* dan *All certificates with an expiry date*), lihat *Days Remaining*, buka sertifikat, dan **Export XLSX**.
<!-- /only -->

<!-- only:signatories -->
### Signatories
Daftar penandatangan yang dipakai pada sertifikat. Tambah/ubah lewat formulir di menu ini.
<!-- /only -->

<!-- only:development-plans -->
### Development Plans
Rencana pengembangan per karyawan: tujuan, gap kompetensi terkait, level saat ini vs target, jenis aksi (training formal, OJT, coaching, mentoring, job rotation, self-learning, practical assessment, cross-training, sertifikasi), mentor, rekomendasi program, prioritas, status, progres, dan catatan manajer/karyawan. Dibuat dari profil karyawan atau halaman indeks.
<!-- /only -->

---
<!-- /only -->
<!-- only:lototo -->
## Safety

<!-- only:lototo -->
### LOTOTO
Menu untuk pencatatan *Lock Out Tag Out*. **Saat ini masih placeholder** (modul dalam pengembangan); belum ada fitur yang bisa dipakai.
<!-- /only -->

---
<!-- /only -->
<!-- only:reports|news|settings|admin -->
## Insights & System

<!-- only:reports -->
### Reports
Pusat laporan. Sebagian laporan membuka modul yang sudah ada dengan filter dan opsi ekspor; yang lain berdiri sendiri:
- **Training Hours** (per karyawan dan per departemen)
- **Employee Development Summary**
- **Assessment History** (seluruh log penilaian kompetensi, bisa impor)
- **Combined export** (laporan gabungan modul)

Ekspor tersedia dalam **CSV** dan **XLSX** untuk Employees, Certificates, Training Records, Training Hours, dan Assessment History.
<!-- /only -->

<!-- only:news -->
### News
Pengumuman yang tampil di Dashboard (kelola oleh pengguna dengan izin News).
1. **Create** berita: judul, isi, gambar (maks 10 MB, otomatis diperkecil).
2. Pilih **gaya tampilan**: *Standard* (gambar di atas, teks di bawah) atau *Highlight* (gambar penuh dengan teks di atasnya).
3. Pilih **jenis**:
   - *Read only* — tampil ke semua orang, tidak perlu konfirmasi.
   - *Requires acknowledgement* — tampil hanya ke penerima yang dipilih; mereka wajib mengonfirmasi sudah membaca dan waktu konfirmasinya dicatat. Penerima yang sudah konfirmasi tidak bisa dihapus.
4. Opsional: **tanggal kedaluwarsa** — setelah itu berita otomatis disembunyikan dari Dashboard (tetap bisa diedit di daftar).

Penerima membuka berita lalu menekan tombol konfirmasi.
<!-- /only -->

<!-- only:settings -->
### Settings *(Administrator)*
Pengaturan aplikasi, saat ini:
- Jendela waktu **“expiring soon”** sertifikat.
- Jarak waktu **pengingat sesi training**.
<!-- /only -->

<!-- only:admin -->
### Audit Log *(Administrator)*
Catatan perubahan yang tidak bisa dihapus: siapa membuat/mengubah/menghapus data, kapan, dan field apa yang berubah. Mencakup karyawan, sertifikat, penilaian skill, job description, training record, dan development plan.
<!-- /only -->

---
<!-- /only -->
<!-- only:admin -->
## Panduan Administrator

### User Management
**Menambah pengguna**
1. **User Management → Add User**.
2. Isi nama, email, password awal, dan role.
3. Opsional: pilih **Link to Employee Record** (hanya karyawan yang belum punya akun) agar akun terhubung ke data karyawan. Bisa juga dihubungkan belakangan lewat form Employee.
4. Simpan; akun langsung dapat dipakai login.

**Mengubah pengguna** — buka **Edit**:
- ubah role,
- atur **hak edit per menu** (Employees, Organization, Job Descriptions, Skills & Competencies, Training, Certificates, Development Plans). Mencentang berarti boleh edit; tidak mencentang berarti read-only (disimpan sebagai penolakan eksplisit).

### Menu Visibility
Atur menu sidebar yang tampil per pengguna. Menu yang disembunyikan juga tidak bisa dibuka lewat URL. Tersedia pengaturan massal untuk banyak pengguna sekaligus.

### Roles & Permissions
Atur izin tiap role, atau **Reset to default** untuk mengembalikan ke bawaan.

### Provisioning akun karyawan
Akun login untuk karyawan dapat dibuat massal lewat perintah artisan `app:provision-employee-users`; akun tersebut wajib mengganti password saat login pertama.

### Tugas terjadwal
Perintah harian yang berjalan otomatis: notifikasi kedaluwarsa sertifikat dan pengingat sesi training, ringkasan mingguan untuk supervisor, serta laporan gabungan lewat email.

---
<!-- /only -->

## Tanya Jawab & Masalah Umum

**Menu yang saya cari tidak ada.**
Menu tersebut mungkin disembunyikan oleh Administrator atau role Anda tidak punya izin. Hubungi Administrator.

<!-- only:not-guest -->
**Tombol tambah/ubah/hapus tidak muncul, ada tulisan “View only”.**
Anda belum punya hak edit untuk menu itu. Minta Administrator memberi hak edit lewat User Management.
<!-- /only -->

**Saya tidak melihat karyawan lain.**
Data dibatasi hierarki: Anda hanya melihat diri sendiri dan bawahan langsung. Hanya HR dan Administrator yang melihat semua.

<!-- only:not-guest -->
**Saya hanya bisa membuka halaman Profile.**
Anda belum mengganti password awal. Ganti dulu di *Update Password*.
<!-- /only -->

<!-- only:not-guest -->
**Tombol Login with Face tidak muncul.**
Perangkat ini belum didaftarkan. Login dengan password, lalu daftar di *Profile → Face Login*.
<!-- /only -->

<!-- only:tasks -->
**Tugas tidak bisa ditugaskan ke saya.**
Akun Anda belum terhubung ke data karyawan. Hubungi Administrator.
<!-- /only -->

**Link onboarding tidak ditemukan / kedaluwarsa.**
Minta HR membuat link baru dari profil karyawan.

<!-- only:certificates -->
**Sertifikat saya berstatus Pending Verification.**
File sudah diunggah tetapi belum diverifikasi. Sertifikat tidak dianggap valid sebelum itu.
<!-- /only -->

---

## Riwayat Pembaruan

Tambahkan satu baris setiap kali aplikasi berubah dan tutorial diperbarui. Yang terbaru di atas.

| Tanggal | Perubahan pada tutorial |
|---|---|
| 2026-11-05 | Logo aplikasi (sidebar, halaman login, laporan PDF) dan favicon diganti dengan logo perisai + topi toga. Tidak ada perubahan alur kerja. |
| 2026-10-22 | Shift Comm: banner WARNING mesin bermasalah berulang kini bisa di-expand/collapse (default tertutup). |
| 2026-10-22 | Help kini menyesuaikan isi dengan akun yang login (role, menu yang tersedia/disembunyikan, hak edit): tamu hanya melihat panduan tamu, Administrator melihat panduan Administrator, dan seterusnya. Bagian bernomor dihapus dari judul; ditambah bagian *Panduan Tamu* dan *Mengerjakan Kuis Training*. |
| 2026-10-22 | Menambahkan menu **Help** di sidebar yang menampilkan tutorial ini di dalam aplikasi (bagian 2); Help dapat disembunyikan per pengguna lewat Menu Visibility. |
| 2026-10-22 | Versi awal tutorial: seluruh menu yang ada saat ini (Task, People & Organization, Competency, Training & Development, Safety, Insights & System, panduan Administrator). |
