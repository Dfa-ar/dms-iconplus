# IDMS Policy, Controller & Audit Log Starter (Laravel 12)

Lanjutan dari `idms-database-starter` — **semua Controller untuk setiap
modul Admin, Petugas, dan Super Admin sudah lengkap**, plus Policy, Form
Request, middleware role, dan audit log dari tahap-tahap sebelumnya.

## Dependency tambahan yang perlu di-install

```bash
composer require phpoffice/phpspreadsheet
php artisan storage:link
npm install
npm run build
```

- `phpoffice/phpspreadsheet` dipakai langsung di `PaUploadController`
  untuk baca file Excel (kalau kamu sudah/akan install
  `maatwebsite/excel`, package itu sudah membawa PhpSpreadsheet sebagai
  dependency, jadi tidak perlu install dua-duanya).
- `storage:link` wajib supaya foto bukti (`Evidence`) yang disimpan di
  disk `public` bisa diakses lewat URL publik.
- PhpSpreadsheet versi terbaru membutuhkan ekstensi PHP `gd` pada
   environment deployment. Pastikan `extension=gd` aktif di PHP/XAMPP.

## Cara pakai

1. Salin ke project Laravel kamu (struktur folder sama persis):
   - `app/Policies/*.php`
   - `app/Http/Requests/{Admin,Petugas,SuperAdmin}/*.php`
   - `app/Http/Middleware/EnsureUserHasRole.php`
   - `app/Http/Controllers/{Admin,Petugas,SuperAdmin}/*.php`
   - `app/Services/AuditLogger.php`
   - `app/Observers/PaOrderObserver.php`
2. Isi method `boot()` dari `app/Providers/AppServiceProvider.php` di sini
   → **gabungkan** (jangan timpa) ke `AppServiceProvider` project kamu.
3. Daftarkan alias middleware `role` — lihat `bootstrap-app-snippet.php`
   untuk potongan `bootstrap/app.php` (Laravel 12 tidak pakai `Kernel.php`).
4. `routes/web.php` di sini **sudah pakai Controller asli** (bukan lagi
   pseudo-code) — gabungkan bagian yang relevan ke `routes/web.php`
   project kamu, sesuaikan dengan route Auth/Breeze yang sudah ada.
5. Laravel otomatis mendeteksi Policy kalau namanya mengikuti konvensi
   `{Model}Policy` di `App\Policies` — tidak perlu didaftarkan manual.

## Dua lapis otorisasi

- **Middleware** (`role:admin`, `role:petugas`, dst) → memblokir akses ke
  seluruh menu/route. Petugas tidak bisa membuka `/admin/...` sama sekali.
- **Policy/Gate** (`$this->authorize(...)`, Form Request `authorize()`,
  `@can`) → memblokir per-record atau per-aksi. Petugas boleh buka
  `/my/tasks`, tapi cuma bisa lihat/ubah PA yang `current_officer_id`-nya
  mengarah ke dirinya sendiri.

Keduanya dipakai bersama — middleware saja tidak cukup karena tidak
mengecek kepemilikan data per baris.

## Daftar semua Controller

### Admin

| Controller | Method | Fungsi |
|---|---|---|
| `PaUploadController` | `create`, `preview`, `store` | FR-02: upload Excel/CSV PA, preview mapping header, upsert `pa_number`, laporan baris gagal |
| `DashboardController` | `index`, `regions`, `kendala`, `export` | FR-10/11/12: Control Tower, progress per wilayah, top kendala, export CSV |
| `AssignmentController` | `index`, `generate`, `reassign` | FR-05/06 + preview dan algoritma Blueprint 11.1 (lihat bagian di bawah) |
| `PaOrderController` | `index`, `show`, `correct` | Master data PA, detail, dan koreksi manual termasuk yang sudah DONE |
| `OfficerController` | resource (`index`,`create`,`store`,`edit`,`update`,`destroy`) | FR-03: master petugas, opsional sekalian buat akun login |
| `RegionController` | `index`,`store`,`update`,`destroy` | Master wilayah (kabupaten/kecamatan/kelurahan) |
| `KendalaReasonController` | `index`,`store`,`update`,`destroy` | Master alasan kendala (dropdown di form Kendala petugas) |
| `AgingReportController` | `index`, `export` | Blueprint 11.2: badge prioritas dari `sla_settings`, bukan hardcode |

### Petugas

| Controller | Method | Fungsi |
|---|---|---|
| `TaskController` | `index`, `show`, `start`, `complete`, `kendala`, `history` | FR-07/08/09 + riwayat (struktur menu 8.2) |

### Super Admin

| Controller | Method | Fungsi |
|---|---|---|
| `UserController` | resource kecuali `show` | Kelola akun & role user (nonaktifkan, bukan hapus) |
| `RoleController` | `index`,`store`,`destroy` | Kelola master role (dijaga tidak bisa hapus role yang masih dipakai) |
| `SlaSettingController` | `edit`, `update` | Konfigurasi ambang aging/SLA (single-row settings) |
| `AuditLogController` | `index` | Lihat audit log, read-only, dengan filter user/action/tanggal |

## Auto-Assignment (`AssignmentController::generate`) — FR-05, Blueprint 11.1

Bagian paling penting di sistem (yang menggantikan proses manual
Excel → WA). Alurnya persis mengikuti Blueprint:

1. **Carry-over**: hitung PA milik tiap petugas yang masih `ASSIGNED`/
   `ON_PROGRESS` dari hari-hari sebelumnya.
2. **Kuota baru** = target per petugas (default 20, bisa diubah lewat
   `target_per_officer`) dikurangi carry-over, minimal 0.
3. Ambil PA `UNASSIGNED` di wilayah yang sama (dicocokkan lewat
   `kabupaten_kota` yang sama, bukan `region_id` yang identik — lihat
   catatan `[ASUMSI]` di dalam `AssignmentController`), urut **aging
   tertinggi dulu** (`pa_date` paling lama), **dikelompokkan per
   kecamatan+kelurahan** untuk efisiensi rute.
4. Bagi **round-robin** (petugas x kelompok wilayah) sampai kuota
   terpenuhi atau PA habis.
5. Simpan `assignments`, ubah `pa_orders.current_status` jadi `ASSIGNED`,
   catat `status_logs` — semua di dalam satu `DB::transaction`.

**Idempotent**: karena query hanya mengambil PA yang masih `UNASSIGNED`,
menekan Generate berkali-kali tidak akan pernah menugaskan ulang PA yang
sudah `ASSIGNED` — otomatis aman tanpa perlu flag tambahan.

## Upload Excel & deteksi duplikat (`PaUploadController`) — FR-02

Baca file lewat PhpSpreadsheet, satu baris = satu PA. Kolom yang
diharapkan: `pa_number | customer_id | customer_name | contact_phone |
address | kabupaten_kota | kecamatan | kelurahan | pa_date`.

Setiap baris dicari berdasarkan `pa_number`. Jika belum ada, data dibuat
sebagai `UNASSIGNED`; jika sudah ada, field sumber pelanggan/wilayah
diperbarui tanpa menimpa status, assignment, atau riwayat workflow aktif.
Baris yang diperbarui dan baris gagal dicatat ke
`upload_batches.error_report_path` sebagai file teks.

File kecil diproses langsung. File dengan lebih dari 1.000 baris disimpan
sementara lalu dikirim ke `ProcessPaUpload` pada queue database. Jalankan
worker agar pemrosesan besar berjalan:

```bash
php artisan queue:work --tries=3
```

Status hasil dan laporan error tersimpan pada `upload_batches` setelah job
selesai.

## Form Request yang tersedia

| Class | Dipakai untuk | Validasi utama |
|---|---|---|
| `Petugas\CompletePaRequest` | Tombol "Selesai" | 3 foto wajib (perangkat, modem/ONT, serah terima) + nama penerima |
| `Petugas\KendalaPaRequest` | Tombol "Kendala" | alasan (harus ada di `kendala_reasons`) + keterangan wajib + foto |
| `Admin\UploadPaRequest` | Upload Excel PA | tipe file xlsx/xls/csv, maks 10 MB |
| `Admin\GenerateAssignmentRequest` | Generate Tugas Hari Ini | wilayah wajib, target per petugas (default 20) |
| `Admin\CorrectPaRequest` | Admin mengoreksi PA (termasuk yang sudah DONE) | status baru valid + **alasan koreksi wajib diisi** |
| `Admin\StoreOfficerRequest` | Tambah/edit petugas | kode pegawai unik, wilayah wajib, opsional buat akun login sekalian |
| `SuperAdmin\StoreUserRequest` | Tambah/edit user | email unik, role wajib, password wajib saat create |

Semua sudah mengecek otorisasi lewat method `authorize()` masing-masing
(memanggil Policy/Gate) — Controller tidak perlu `$this->authorize(...)`
lagi kalau parameternya sudah di-type-hint dengan Form Request ini.

## Audit log koreksi PA DONE (Blueprint 11.3 & 14)

Dua lapis pencatatan, sengaja dipisah:

1. **`PaOrderObserver`** (otomatis, di `updating()`) — begitu ada PA yang
   statusnya sebelumnya `DONE` diubah lagi oleh user manapun yang login,
   otomatis tercatat ke `audit_logs` berisi *field apa saja* yang
   berubah (`action: correct_done_pa`). Jaring pengaman, jalan walau
   perubahan lewat jalur di luar `PaOrderController::correct` (mis.
   Tinker atau fitur lain nanti).
2. **`PaOrderController::correct`** — mencatat eksplisit *alasan* koreksi
   (`correction_reason` dari `CorrectPaRequest`, `action:
   correct_done_pa_reason`) yang tidak mungkin ditebak otomatis oleh
   Observer.

Hasilnya 2 baris terpisah di `audit_logs` untuk satu aksi koreksi — kalau
mau digabung jadi satu baris, Controller perlu memanggil `AuditLogger`
sendiri dengan detail gabungan sambil Observer dinonaktifkan sementara
(lebih kompleks, belum perlu untuk Phase 1).

## Yang sudah dicakup (sesuai Blueprint bagian 4, 11, 13, 14)

- 4 role: `admin`, `petugas`, `supervisor`, `super_admin`.
- `super_admin` dibuat bypass semua ability lewat `Gate::before` —
  **[ASUMSI]**, sesuaikan kalau pembimbing mau `super_admin` dibatasi
  hanya ke "kelola user/role, konfigurasi SLA, audit log" seperti
  tertulis di tabel stakeholder Blueprint.
- "Petugas tidak dapat melihat atau mengubah PA milik petugas lain"
  (Blueprint 11.3) → `PaOrderPolicy::isOwner()` + scope query di
  `TaskController::index`.
- "PA berstatus DONE tidak dapat diubah petugas; koreksi hanya oleh
  Admin" (Blueprint 11.3) → `start`/`complete`/`reportKendala` di
  `PaOrderPolicy` menolak status `DONE`; `PaOrderController::correct`
  terpisah, hanya untuk role `admin`.
- Aging & warna prioritas (Blueprint 11.2) dibaca dari `sla_settings`,
  bisa diubah Super Admin lewat `SlaSettingController` — tidak hardcode.
- Login rate limiting: endpoint `POST /login` dibatasi 5 percobaan per menit
   berdasarkan identifier + IP melalui limiter `login` di
   `AppServiceProvider`.
- Master Data PA: daftar server-side dengan pencarian, filter status/wilayah/
   petugas/tanggal, pagination, dan detail PA.
- Preview Auto-Assignment: ringkasan kuota petugas, carry-over, kandidat PA
   tertua, dan konfirmasi generate.
- Control Tower: persentase status, progress hari ini, top kendala, eskalasi,
   dan rekap per petugas.
- PWA dasar: `public/manifest.json` dan `public/sw.js`.

## Batasan yang masih perlu dikembangkan

- Preview kolom Excel dan mapping interaktif belum tersedia; saat ini format
   header dikenali otomatis berdasarkan nama/alias kolom dan preview 10 baris
   sudah tersedia; mapping manual drag-and-drop belum tersedia.
- Ikon PWA belum disediakan, sehingga installability dasar tersedia tetapi
   branding offline masih minimal.
- Kalau nanti butuh hak akses lebih granular per-menu (bukan cuma 4
  role), baru pertimbangkan tabel `permissions` pivot atau paket seperti
  `spatie/laravel-permission` — untuk Phase 1 skema role sederhana ini
  masih cukup.

## Hasil review (sudah diperbaiki di paket ini)

1. **[FATAL] `app/Http/Controllers/Controller.php` ditambahkan** — file
   ini tidak ikut ter-zip sebelumnya. Sejak Laravel 11, base Controller
   bawaan kosong (tidak ada `AuthorizesRequests` lagi), padahal semua
   controller di sini pakai `$this->authorize(...)`. Tanpa trait ini,
   SEMUA request ke controller manapun akan Fatal Error. **Wajib ganti**
   `app/Http/Controllers/Controller.php` project kamu dengan yang di sini.
2. **`routes/web.php`**: `Route::resource('petugas', ...)` dan
   `Route::resource('kendala-reasons', ...)` ditambah `->parameters([...])`
   eksplisit. Tanpa itu, Laravel menyingularkan nama resource secara
   otomatis untuk nama parameter URL, dan untuk kata non-Inggris/berhubung
   dash hasilnya sering tidak cocok dengan nama variabel di controller
   (`Officer $officer`, `KendalaReason $kendalaReason`) — route model
   binding gagal diam-diam (bukan error, tapi $officer/$kendalaReason bisa
   jadi objek kosong, bukan dari database).
3. **`AssignmentController::generate()`**: pencarian petugas disamakan
   supaya konsisten dengan pencarian PA kandidat (sama-sama lewat teks
   `kabupaten_kota`, bukan `region_id` yang harus identik persis) — kode
   sebelumnya sendiri sudah menulis catatan [ASUMSI] soal ini tapi baru
   diterapkan ke satu sisi.
4. **`AssignmentController::reassign()`**: ditambah `AuditLogger::log(...)`
   eksplisit. Sebelumnya FR-06 (ubah assignment manual) tidak tercatat di
   mana pun, karena `PaOrderObserver` di paket ini cuma menjaring koreksi
   PA yang tadinya `DONE`, bukan reassignment biasa.
5. **`PaOrderController::correct()`**: sekarang juga menulis ke
   `status_logs` (sebelumnya cuma `audit_logs`, jadi riwayat status bolong
   untuk PA yang pernah dikoreksi), dan mengosongkan field yang jadi basi
   (`completed_at`, `kendala_reason_id`, `started_at`) sesuai status
   tujuan — supaya `$paOrder->aging` tidak salah hitung setelah dikoreksi.
6. **`TaskController::index()`**: ditambah guard untuk akun petugas yang
   belum tertaut ke baris `officers` (`$officer` null) — sebelumnya query
   `where('current_officer_id', null)` diam-diam jadi `whereNull()` dan
   menampilkan semua PA yang belum ditugaskan siapa pun.
