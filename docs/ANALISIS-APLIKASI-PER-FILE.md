# Analisis Aplikasi Per File

Dokumen ini merangkum isi workspace berdasarkan struktur file yang ada pada 2026-06-17. Fokusnya adalah memahami aplikasi apa yang sedang dibangun, alur bisnisnya, dan fungsi tiap file utama.

## Gambaran Umum

Aplikasi ini adalah **Sistem Manajemen Pengembangan Aplikasi** atau SIMPA/SMPA. Intinya, sistem ini mengelola siklus hidup pengajuan aplikasi dari unit kerja sampai aplikasi siap dan berhasil dideploy.

Alur besar yang terlihat dari backend dan frontend:

1. Unit kerja atau pengelola aplikasi membuat pengajuan aplikasi.
2. Pengelola aplikasi memverifikasi pengajuan dan melakukan studi kelayakan.
3. Analis desain mengisi analisa desain: UI platform, interoperabilitas, storage, aktor, transaksi/API, dan laporan analisa.
4. Tim implementasi mengerjakan pengembangan dan checklist progress.
5. Pengelola memverifikasi UAT.
6. Tim uji keamanan mengisi hasil security review.
7. DevOps mengelola status deployment staging/production.
8. Sistem menyimpan histori status, dokumen, catatan, checklist, RFC, dan notifikasi.

Teknologi utama:

- Backend: Laravel 12, PHP 8.2, Sanctum, Eloquent, PHPUnit.
- Frontend: Vue 3, Vite, Pinia, Vue Router, Axios.
- Database: MySQL/MariaDB, terlihat dari migration Laravel dan folder `smpa_db`.
- Dokumentasi: dokumen TA, SOP proses bisnis, pengujian, FDD, OWASP, diagram UML/Draw.io/PlantUML.

Role utama:

- `pengelola_aplikasi`
- `analis_desain`
- `unit_kerja`
- `tim_implementasi_aplikasi`
- `devops_developer`
- `tim_uji_keamanan`

## Temuan Arsitektur Penting

- Model pusat adalah `Aplikasi`. Hampir semua data lain punya relasi ke aplikasi: analisa desain, proyek, konfigurasi database/object storage/API gateway/frontend/backend/devops, dokumen, checklist, catatan, RFC, histori status, dan notifikasi.
- Backend memakai response API seragam melalui `ApiResponse`, termasuk response pagination standar.
- Authorization berbasis role ada di route dan middleware `RoleMiddleware`.
- Ada perhatian keamanan: middleware sanitasi input, security headers, request logging, rate limit login, validasi file upload, dan dokumen OWASP.
- Status workflow deployment distandarkan menjadi dua status resmi: `deployed_staging` untuk staging/testing dan `deployed_production` untuk aplikasi yang sudah live.
- Kontrak upload dokumen frontend sudah diselaraskan dengan backend: FormData memakai `document_type` dan `file`.
- Opsi storage analisa desain di frontend sudah mengikuti validasi backend: `db` dan `object-storage`.
- Transisi workflow penting sudah diberi gate backend berbasis dokumen/checklist: verifikasi pengajuan perlu formulir pengajuan, mulai pengembangan perlu laporan analisa desain, siap UAT perlu checklist implementasi selesai serta template UAT dan petunjuk aplikasi, verifikasi UAT sesuai perlu dokumen UAT, dan hasil uji keamanan perlu laporan uji keamanan.
- Panel dokumen dibuat bertahap/bertingkat mengikuti status workflow: dokumen pengajuan dan pendukung muncul di awal, laporan analisa desain muncul saat analisa, template UAT dan petunjuk aplikasi muncul saat implementasi, dokumen UAT muncul saat UAT, laporan uji keamanan muncul saat uji keamanan, dan dokumen akhir go-live menempatkan Berita Acara sebagai dokumen paling akhir.
- Backend sekarang ikut mengunci akses proses bisnis: unit kerja hanya dapat melihat/memperbaiki workflow miliknya sendiri, upload dokumen wajib sesuai tahap status aplikasi, security review hanya dapat diisi pada tahap uji keamanan, checklist implementasi hanya aktif pada tahap implementasi/perbaikan, deployment wajib staging sebelum production, dan endpoint deploy lama dinonaktifkan agar tidak bypass alur staging-production.
- Validasi RFC diperkuat dengan batas `deskripsi` maksimal 5000 karakter.

## Root Workspace

- `.gitignore`: Aturan ignore root untuk dependency, build output, runtime cache/log, dan database lokal.
- `tools/generate_drawio.py`: Script pembantu untuk menghasilkan atau mengubah diagram Draw.io.
- `docs/thesis/M. Lazaro FA. Al-Dzaki_TA70^1_2221101806.docx`: Dokumen TA utama.
- `docs/thesis/ta_content.txt`: Konten/naskah TA.
- `docs/references/sommerville_usecase.txt`: Catatan/referensi use case dari materi Sommerville, kemungkinan untuk landasan TA.
- `docs/references/Software Engineering Sommerville.pdf`: Referensi teori software engineering.
- `docs/references/Stephen R. Palmer, John M. Felsing - A Practical Guide to Feature-Driven Development ...pdf`: Referensi Feature-Driven Development.

## Backend Laravel

### Root Backend

- `backend/composer.json`: Dependency backend. Laravel 12, Sanctum, Tinker, PHPUnit, Pint, Sail, Pail.
- `backend/composer.lock`: Lockfile dependency Composer.
- `backend/artisan`: CLI Laravel.
- `backend/package.json`: Dependency/script Node untuk tooling frontend bawaan Laravel/Vite.
- `backend/package-lock.json`: Lockfile npm backend.
- `backend/phpunit.xml`: Konfigurasi PHPUnit.

### Bootstrap dan Routing

- `backend/bootstrap/app.php`: Bootstrap Laravel 12. Mengaktifkan route web/api/console, health check `/up`, global `SecurityHeaders`, alias middleware `role`, `sanitize`, `log.requests`, dan JSON handler untuk validation/auth/authorization/not found/server error.
- `backend/bootstrap/providers.php`: Daftar service provider Laravel.
- `backend/routes/api.php`: Definisi seluruh REST API. Memuat login, logout, register, aplikasi, analisa desain, RFC, dokumen, workflow, security review, deployment, dan notifikasi dengan proteksi Sanctum serta role.
- `backend/routes/web.php`: Route web Laravel minimal.
- `backend/routes/console.php`: Route/command console Laravel.

### Config

- `backend/config/app.php`: Konfigurasi aplikasi Laravel.
- `backend/config/auth.php`: Konfigurasi autentikasi.
- `backend/config/cache.php`: Konfigurasi cache.
- `backend/config/cors.php`: Konfigurasi CORS API/frontend.
- `backend/config/database.php`: Konfigurasi database.
- `backend/config/environment.php`: Konfigurasi domain/host teknis BSSN untuk database staging/production/local, MinIO, API gateway, dan frontend.
- `backend/config/filesystems.php`: Disk storage Laravel.
- `backend/config/logging.php`: Channel logging.
- `backend/config/mail.php`: Konfigurasi email.
- `backend/config/queue.php`: Konfigurasi queue.
- `backend/config/sanctum.php`: Konfigurasi Laravel Sanctum.
- `backend/config/services.php`: Konfigurasi layanan eksternal.
- `backend/config/session.php`: Konfigurasi session.

### Enum

- `backend/app/Enums/UserRole.php`: Enum role user dan helper daftar role.
- `backend/app/Enums/AplikasiStatus.php`: Enum status workflow aplikasi: diajukan, terverifikasi, layak, analisa desain, pengembangan, UAT, uji keamanan, siap deploy, deployed staging, deployed production, dll.
- `backend/app/Enums/AplikasiJenisDokumen.php`: Enum jenis dokumen aplikasi: formulir pengajuan, lampiran, laporan analisa desain, template UAT, petunjuk aplikasi, UAT, berita acara, rilis, laporan uji keamanan, lainnya.

### Model

- `backend/app/Models/User.php`: Model user, role helper (`isPengelolaAplikasi`, `isAnalisDesain`, `isUnitKerja`, dll), relasi aplikasi yang diajukan.
- `backend/app/Models/Aplikasi.php`: Model inti. Menyimpan data aplikasi, status workflow, metadata dokumen lama, field security review, tracking deployment, pembuat/pengubah, relasi ke semua data turunan, serta notifikasi otomatis saat status berubah.
- `backend/app/Models/AnalisaDesain.php`: Data analisa desain per aplikasi: UI platform, interop, storage, aktor, method, URL, tipe resource, aktor transaksi, creator/updater.
- `backend/app/Models/Proyek.php`: Modul proyek hasil auto-generation, misalnya backend dan frontend.
- `backend/app/Models/DatabaseConfig.php`: Konfigurasi database per aplikasi dan environment.
- `backend/app/Models/ObjectStorageConfig.php`: Konfigurasi MinIO/object storage per aplikasi.
- `backend/app/Models/ApiGatewayConfig.php`: Konfigurasi API gateway SPL/SPL-dev.
- `backend/app/Models/FrontendConfig.php`: Konfigurasi URL/modul frontend.
- `backend/app/Models/BackendConfig.php`: Konfigurasi backend local/API/database.
- `backend/app/Models/DevopsConfig.php`: Konfigurasi DevOps, database, SPL, auth, staging/production.
- `backend/app/Models/EnvironmentConfig.php`: Key-value environment per aplikasi.
- `backend/app/Models/AplikasiDocument.php`: Dokumen versi per aplikasi dengan metadata file, uploader, status active/superseded.
- `backend/app/Models/AplikasiChecklist.php`: Checklist proses seperti studi kelayakan, uji keamanan, rilis, implementation progress.
- `backend/app/Models/AplikasiNote.php`: Catatan/perbaikan/komunikasi per aplikasi, termasuk status checked.
- `backend/app/Models/AplikasiStatusHistory.php`: Histori perubahan status aplikasi.
- `backend/app/Models/AppNotification.php`: Notifikasi in-app ke user terkait aplikasi.
- `backend/app/Models/Rfc.php`: Request for Change aplikasi, tipe RFC, deskripsi, pelaksana, status tindak lanjut, creator/updater.

### Controller

- `backend/app/Http/Controllers/Controller.php`: Base controller Laravel.
- `backend/app/Http/Controllers/AuthController.php`: Login, logout, `me`, dan register user. Login memakai Sanctum token.
- `backend/app/Http/Controllers/AplikasiController.php`: CRUD aplikasi, listing/pagination/search/filter status, statistik cached, notifikasi pengajuan unit kerja, detail lengkap, soft delete, restore, withdraw pengajuan.
- `backend/app/Http/Controllers/AnalisaDesainController.php`: CRUD analisa desain, summary per aplikasi, batch update analisa desain, grouping data analisa.
- `backend/app/Http/Controllers/AplikasiDocumentController.php`: List dan upload dokumen aplikasi, versioning dokumen, supersede dokumen lama, authorization via `AplikasiDocumentAccess`.
- `backend/app/Http/Controllers/AplikasiWorkflowController.php`: Checklist, notes, implementation checklist, security review, deployment status, dan menggabungkan transition trait.
- `backend/app/Http/Controllers/Traits/HandlesAplikasiTransitions.php`: Mesin transition status: verifikasi pengajuan, perbaikan, studi kelayakan, mulai analisa, mulai pengembangan, siap UAT, verifikasi UAT, uji keamanan, perbaikan keamanan, deployment, histori.
- `backend/app/Http/Controllers/NotificationController.php`: List notifikasi, tandai satu notifikasi terbaca, tandai semua terbaca.
- `backend/app/Http/Controllers/RfcController.php`: CRUD RFC dengan pagination dan relasi aplikasi.

### Request Validation

- `backend/app/Http/Requests/StoreAplikasiRequest.php`: Validasi pembuatan aplikasi dan surat pengajuan, status dilarang diisi manual.
- `backend/app/Http/Requests/UpdateAplikasiRequest.php`: Validasi update aplikasi; field identitas utama dan status dikunci.
- `backend/app/Http/Requests/StoreAnalisaDesainRequest.php`: Validasi tambah analisa desain.
- `backend/app/Http/Requests/UpdateAnalisaDesainRequest.php`: Validasi update analisa desain.
- `backend/app/Http/Requests/StoreAplikasiDocumentRequest.php`: Validasi upload dokumen: `document_type`, `file`, mimetype PDF/DOC/DOCX, maksimal 10 MB.
- `backend/app/Http/Requests/StoreAplikasiChecklistRequest.php`: Validasi tambah checklist.
- `backend/app/Http/Requests/UpdateAplikasiChecklistRequest.php`: Validasi update checklist.
- `backend/app/Http/Requests/StoreAplikasiNoteRequest.php`: Validasi tambah catatan.
- `backend/app/Http/Requests/UpdateAplikasiNoteRequest.php`: Validasi update catatan.
- `backend/app/Http/Requests/StoreRfcRequest.php`: Validasi tambah RFC.
- `backend/app/Http/Requests/UpdateRfcRequest.php`: Validasi update RFC.

### Middleware, Helper, Support, Service

- `backend/app/Http/Middleware/RoleMiddleware.php`: Membatasi akses berdasarkan role.
- `backend/app/Http/Middleware/SanitizeInput.php`: Sanitasi input request.
- `backend/app/Http/Middleware/SecurityHeaders.php`: Menambahkan security headers.
- `backend/app/Http/Middleware/LogRequests.php`: Logging request, terutama error/kejadian penting.
- `backend/app/Http/Helpers/ApiResponse.php`: Helper standar JSON success/error/paginated/created/not found/forbidden/validation error.
- `backend/app/Http/Helpers/QueryHelper.php`: Helper query, terutama escaping karakter LIKE.
- `backend/app/Support/AplikasiDocumentAccess.php`: Policy kecil untuk siapa boleh melihat dan upload jenis dokumen tertentu.
- `backend/app/Services/AutoGenerationService.php`: Auto-generate analisa desain awal, proyek, database config, object storage, API gateway, frontend config, backend config, dan DevOps config berdasarkan data aplikasi.
- `backend/app/Providers/AppServiceProvider.php`: Provider aplikasi standar.
- `backend/app/Providers/EnvironmentServiceProvider.php`: Validasi environment/config penting.

### Database Migration

- `0001_01_01_000000_create_users_table.php`: Membuat users, password reset tokens, sessions.
- `0001_01_01_000001_create_cache_table.php`: Membuat cache dan cache locks.
- `0001_01_01_000002_create_jobs_table.php`: Membuat jobs, job batches, failed jobs.
- `2025_01_16_000010_add_roles_to_users_table.php`: Menambah role awal admin/user.
- `2025_10_16_101305_create_aplikasis_table.php`: Tabel utama aplikasi.
- `2025_10_16_101306_create_analisa_desains_table.php`: Tabel analisa desain.
- `2025_10_16_101307_create_proyeks_table.php`: Tabel proyek/modul.
- `2025_10_16_101308_create_database_configs_table.php`: Tabel konfigurasi database.
- `2025_10_16_101309_create_object_storage_configs_table.php`: Tabel konfigurasi object storage.
- `2025_10_16_101310_create_api_gateway_configs_table.php`: Tabel konfigurasi API gateway.
- `2025_10_16_101311_create_devops_configs_table.php`: Tabel konfigurasi DevOps.
- `2025_10_16_101312_create_frontend_configs_table.php`: Tabel konfigurasi frontend.
- `2025_10_16_101313_create_backend_configs_table.php`: Tabel konfigurasi backend.
- `2025_10_16_101314_create_environment_configs_table.php`: Tabel environment variable.
- `2025_10_20_044007_create_personal_access_tokens_table.php`: Tabel token Sanctum.
- `2025_10_21_093441_add_soft_deletes_to_aplikasis_table.php`: Soft delete aplikasi.
- `2025_10_21_093451_add_soft_deletes_to_analisa_desains_table.php`: Soft delete analisa desain.
- `2025_10_21_093457_add_soft_deletes_to_users_table.php`: Soft delete user.
- `2025_10_21_094231_add_indexes_to_tables.php`: Index performa.
- `2025_10_23_015013_fix_aktor_names_capitalization.php`: Normalisasi kapitalisasi aktor.
- `2025_10_23_084010_add_user_tracking_to_aplikasis_table.php`: `created_by`/`updated_by` aplikasi.
- `2025_10_23_084018_add_user_tracking_to_analisa_desains_table.php`: `created_by`/`updated_by` analisa desain.
- `2025_10_25_102137_add_database_fields_to_devops_configs_table.php`: Field database tambahan pada DevOps config.
- `2025_10_31_000001_add_application_docs_columns_to_aplikasis_table.php`: Kolom dokumen lama di aplikasi.
- `2025_10_31_000100_create_rfcs_table.php`: Tabel RFC.
- `2025_10_31_000101_add_pelaksana_to_rfcs_table.php`: Kolom pelaksana RFC.
- `2025_11_01_000001_update_users_role_to_string.php`: Role user dari enum lama ke string.
- `2025_11_04_022906_normalize_status_analisa_desain_in_aplikasis_table.php`: Normalisasi nama status analisa desain lama.
- `2026_02_22_000001_rename_legacy_roles_to_new_role_names.php`: Migrasi role legacy ke role baru.
- `2026_04_15_100000_epic_a_status_normalize_documents_and_index.php`: Normalisasi status lama, index, dan tabel dokumen aplikasi.
- `2026_04_16_000001_create_aplikasi_checklists_table.php`: Tabel checklist.
- `2026_04_16_000002_create_aplikasi_notes_table.php`: Tabel catatan.
- `2026_04_17_120000_add_security_review_fields_to_aplikasis_table.php`: Field security review.
- `2026_04_30_000001_create_app_notifications_table.php`: Tabel notifikasi.
- `2026_05_25_000001_add_deployment_tracking_to_aplikasis_table.php`: Field deployment staging/production.
- `2026_05_26_034112_create_aplikasi_status_histories_table.php`: Tabel histori status.
- `2026_05_26_053640_migrate_aplikasi_status_to_workflow_format.php`: Migrasi status ke format workflow baru.

### Seeder dan Factory

- `backend/database/seeders/DatabaseSeeder.php`: Seeder utama.
- `backend/database/seeders/UserSeeder.php`: Seeder user default/role.
- `backend/database/seeders/AplikasiSeeder.php`: Seeder data aplikasi contoh.
- `backend/database/factories/UserFactory.php`: Factory user untuk test.
- `backend/database/factories/AplikasiFactory.php`: Factory aplikasi untuk test.

### Test

- `backend/tests/TestCase.php`: Base test Laravel.
- `backend/tests/Unit/UserTest.php`: Test helper role user.
- `backend/tests/Unit/ApiResponseTest.php`: Test struktur response API.
- `backend/tests/Feature/AuthenticationTest.php`: Test login, logout, me, rate limit.
- `backend/tests/Feature/AplikasiCrudTest.php`: Test CRUD aplikasi, akses role, filter unit kerja, pengajuan notification.
- `backend/tests/Feature/AplikasiDocumentTest.php`: Test list/upload dokumen dan izin per role.
- `backend/tests/Feature/AplikasiWorkflowTest.php`: Test checklist, notes, implementation checklist, isolasi kategori.
- `backend/tests/Feature/AplikasiSecurityReviewTest.php`: Test security review oleh tim uji keamanan dan akses role.
- `backend/tests/Feature/AplikasiPerformanceTest.php`: Test query count, cache statistik, detail endpoint.
- `backend/tests/Feature/ApiContractAndAuthorizationTest.php`: Test kontrak API, RFC, authorization.

### Storage, Public, Drawio

- `backend/public/index.php`: Entry point HTTP Laravel.
- `backend/storage/**/.gitignore`: Placeholder agar folder storage Laravel masuk git.
- `backend/drawio/simpa_class_diagram.drawio`: Diagram class SIMPA.

## Frontend Vue/Vite

### Root Frontend

- `frontend/package.json`: Dependency Vue 3, Vite, Axios, Pinia, Vue Router.
- `frontend/package-lock.json`: Lockfile npm frontend.
- `frontend/index.html`: HTML entry Vite.
- `frontend/vite.config.js`: Konfigurasi Vite.
- `frontend/public/vite.svg`: Asset bawaan Vite.
- `frontend/public/bssn.png`: Logo/asset BSSN.
- `frontend/public/templates/formulir_pengajuan_template.txt`: Template formulir pengajuan.
- `frontend/public/templates/format_uat_template.txt`: Template UAT.
- `frontend/public/templates/panduan_uat.md`: Panduan UAT.

### Entry, Router, Store, Utils

- `frontend/src/main.js`: Membuat Vue app, memasang Pinia dan router.
- `frontend/src/App.vue`: Root app, membungkus router view dan toast/error boundary.
- `frontend/src/router/index.js`: Route per role, guard login, hydration `/me`, redirect user sesuai role.
- `frontend/src/lib/http.js`: Axios instance ke `/api`, injeksi token Bearer, interceptor 401.
- `frontend/src/stores/auth.js`: Pinia auth store untuk token/user di localStorage dan logout Sanctum.
- `frontend/src/stores/toast.js`: Store toast notification frontend.
- `frontend/src/stores/notifications.js`: Store notifikasi in-app.
- `frontend/src/constants/roles.js`: Mapping role ke home route dan label.
- `frontend/src/constants/status.js`: Mapping status/RFC/method ke badge, label, tooltip.
- `frontend/src/utils/dateHelper.js`: Format tanggal dan relative time Indonesia/WIB.
- `frontend/src/utils/logger.js`: Logging development.
- `frontend/src/composables/usePagination.js`: Helper nomor pagination.
- `frontend/src/composables/usePengelolaNotifications.js`: Fetch count/list pengajuan baru untuk pengelola.
- `frontend/src/style.css`: CSS global aplikasi.

### Layout

- `frontend/src/layouts/AdminLayout.vue`: Layout pengelola aplikasi dengan sidebar dan notifikasi pengajuan baru.
- `frontend/src/layouts/Admin2Layout.vue`: Layout analis desain.
- `frontend/src/layouts/UserLayout.vue`: Layout role non-pengelola seperti unit kerja, implementasi, DevOps, security.

### Komponen

- `frontend/src/components/AdminSidebar.vue`: Sidebar pengelola aplikasi.
- `frontend/src/components/Admin2Sidebar.vue`: Sidebar analis desain.
- `frontend/src/components/UserSidebar.vue`: Sidebar dinamis berdasarkan role, termasuk notifikasi dan logout.
- `frontend/src/components/AdminCardHead.vue`: Header reusable untuk card/tabel admin.
- `frontend/src/components/AdminTable.vue`: Wrapper table admin.
- `frontend/src/components/Icons.vue`: Komponen ikon internal.
- `frontend/src/components/Toast.vue`: Renderer toast dari store.
- `frontend/src/components/ErrorBoundary.vue`: Boundary error frontend.
- `frontend/src/components/DetailInfoGrid.vue`: Grid ringkasan informasi aplikasi/status.
- `frontend/src/components/AplikasiFormModal.vue`: Modal multi-step tambah/edit aplikasi, termasuk upload formulir pengajuan.
- `frontend/src/components/AnalisaDesainModal.vue`: Modal edit/lihat analisa desain: UI platform, interop, storage, aktor, transaksi, upload laporan.
- `frontend/src/components/AppDetailContent.vue`: Komponen detail aplikasi terbesar. Menampilkan tab informasi, analisa desain, konfigurasi frontend/backend/devops, dokumen, checklist, notes, workflow action, security review, deployment, dan progress status.

### Views

- `frontend/src/views/Login.vue`: Halaman login dengan validasi client dan redirect berdasarkan role.
- `frontend/src/views/AdminDashboard.vue`: Dashboard pengelola aplikasi: statistik, list aplikasi, tambah/edit/hapus, pagination/search.
- `frontend/src/views/AdminRfc.vue`: Halaman pengelola RFC: list, tambah/edit/hapus RFC, combobox aplikasi, statistik.
- `frontend/src/views/AdminAppDetail.vue`: Wrapper detail aplikasi untuk pengelola, memakai `AppDetailContent`.
- `frontend/src/views/Admin2Dashboard.vue`: Dashboard analis desain: daftar aplikasi layak/analisa desain, summary progress analisa, buka modal analisa.
- `frontend/src/views/AnalisAppDetail.vue`: Wrapper detail aplikasi untuk analis desain.
- `frontend/src/views/UnitKerjaDashboard.vue`: Dashboard unit kerja: pengajuan sendiri, tambah pengajuan, tarik pengajuan.
- `frontend/src/views/UnitKerjaAppDetail.vue`: Wrapper detail aplikasi untuk unit kerja.
- `frontend/src/views/UserDashboard.vue`: Dashboard umum untuk tim implementasi, DevOps, dan tim uji keamanan dengan filter status sesuai role.
- `frontend/src/views/UserAppDetail.vue`: Wrapper detail aplikasi untuk tim implementasi.
- `frontend/src/views/DevopsAppDetail.vue`: Wrapper detail aplikasi untuk DevOps.
- `frontend/src/views/TimUjiKeamananAppDetail.vue`: Wrapper detail aplikasi untuk tim uji keamanan.



## Database Lokal

Folder `smpa_db` berisi file fisik database MySQL/InnoDB, bukan source code aplikasi. Folder ini adalah artefak lokal mesin pengembangan dan sudah dimasukkan ke root `.gitignore`.

- `smpa_db/db.opt`: Metadata database MySQL.
- `smpa_db/*.ibd`: File tablespace InnoDB untuk tabel SIMPA seperti `users`, `aplikasis`, `analisa_desains`, `rfcs`, `aplikasi_documents`, `aplikasi_checklists`, `aplikasi_notes`, config tables, jobs/cache/session, dan token Sanctum.

## Dokumentasi

- `docs/thesis/ta_content.txt`: Konten/naskah TA.
- `docs/Activity_Diagram_SIMPA.png`: Diagram aktivitas SIMPA.
- `docs/Activity_Diagram_SIMPA_Final.drawio`: Source Draw.io diagram aktivitas.
- `docs/Proses Bisnis Pengelola Aplikasi [rev1].png`: Gambar proses bisnis pengelola aplikasi.
- `docs/sequence_diagram_pengajuan.puml`: Sequence diagram proses pengajuan.
- `docs/RINGKASAN-EKSEKUTIF-KESESUAIAN-FDD.md`: Ringkasan kesesuaian dengan FDD.
- `docs/EKSPLORASI-KOMPREHENSIF-INPUT-VALIDATION-SIMPA.md`: Analisis validasi input.
- `docs/LAMPIRAN-OWASP-INPUT-VALIDATION-REVIEW.md`: Lampiran review OWASP input validation.
- `docs/LAMPIRAN-C-INTEGRATION-TESTING-DEPLOYMENT-CONFIG.md`: Lampiran integration testing konfigurasi deployment.
- `docs/LAMPIRAN-D-API-TESTING-REGISTRASI-PENGGUNA.md`: Lampiran API testing registrasi pengguna.
- `docs/IV-4-PENGUJIAN-FUNGSIONAL-SIMPA.md`: Dokumen pengujian fungsional SIMPA.
- `docs/SECURITY-ISSUE-RFC-DESKRIPSI-MISSING-CONSTRAINT.md`: Catatan security issue constraint deskripsi RFC.
- `docs/fdd_ch4_process2.txt`: Referensi/catatan FDD chapter 4.
- `docs/fdd_ch8_raw.txt`: Referensi FDD chapter 8 mentah.
- `docs/fdd_ch8_v2.txt`: Referensi FDD chapter 8 olahan.

### SOP Proses Bisnis

- `docs/business-process-sop/00-INDEX.md`: Index SOP.
- `docs/business-process-sop/01-PENGAJUAN.md`: SOP pengajuan aplikasi.
- `docs/business-process-sop/02-STUDI-KELAYAKAN.md`: SOP studi kelayakan.
- `docs/business-process-sop/03-ANALISA-DESAIN.md`: SOP analisa desain.
- `docs/business-process-sop/04-IMPLEMENTASI.md`: SOP implementasi.
- `docs/business-process-sop/05-TESTING-DAN-SECURITY-REVIEW.md`: SOP testing dan security review.
- `docs/business-process-sop/06-GO-LIVE.md`: SOP go-live.
- `docs/business-process-sop/07-HYPERCARE.md`: SOP hypercare.
- `docs/business-process-sop/08-OPERASIONAL-DAN-CONTINUOUS-IMPROVEMENT.md`: SOP operasional dan continuous improvement.
- `docs/business-process-sop/09-DECOMMISSION.md`: SOP decommission.

## Kesimpulan Singkat

Yang sedang dibuat adalah aplikasi internal untuk mengatur tata kelola pengembangan aplikasi: mulai dari permintaan unit kerja, validasi dan studi kelayakan oleh pengelola, analisa desain teknis, implementasi, UAT, uji keamanan, deployment, RFC, dokumen, checklist, catatan, sampai notifikasi antar role.

Secara konsep, aplikasi ini sudah bukan sekadar CRUD. Ini adalah sistem workflow multi-role dengan audit trail, role-based access, document management, dan auto-generation konfigurasi teknis.
