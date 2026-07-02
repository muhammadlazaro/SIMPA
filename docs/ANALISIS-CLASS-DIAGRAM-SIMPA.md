# Analisis Class Diagram SIMPA

Dokumen ini berisi data terstruktur untuk menyusun **Class Diagram** SIMPA berdasarkan kode aktual. Fokus analisis mengikuti konsep Alan Dennis, Wixom, dan Roth: class memiliki nama, atribut, operation/method, serta relasi object-oriented. Dokumen ini bukan PlantUML, bukan Mermaid, bukan gambar diagram, dan bukan ERD.

## A. Daftar Class Utama

| Class | File model / class terkait | Tabel database | Fungsi utama dalam sistem | Layak ditampilkan pada Class Diagram | Alasan |
|---|---|---|---|---|---|
| User | `backend/app/Models/User.php` | `users` | Merepresentasikan akun/personil sistem dan role akses. | Ya | Class aktor internal sistem, memiliki operation role check dan relasi ke pengajuan, notifikasi, catatan, dokumen, status history, dan RFC. |
| Aplikasi | `backend/app/Models/Aplikasi.php` | `aplikasis` | Entitas pusat pengajuan dan pengelolaan aplikasi sampai production/nonaktif. | Ya | Aggregate utama SIMPA; menghubungkan workflow, dokumen, analisa, checklist, RFC, notifikasi, dan konfigurasi. |
| AnalisaDesain | `backend/app/Models/AnalisaDesain.php` | `analisa_desains` | Mencatat hasil analisis desain: UI, interop, storage, aktor, transaksi API. | Ya | Mewakili tahap analisis desain, bukan sekadar tabel teknis. |
| Proyek | `backend/app/Models/Proyek.php` | `proyeks` | Mewakili modul proyek frontend/backend hasil auto-generation. | Ya, terutama pada package konfigurasi/pengembangan | Menunjukkan aplikasi dapat memiliki modul proyek implementasi. |
| AplikasiDocument | `backend/app/Models/AplikasiDocument.php` | `aplikasi_documents` | Menyimpan dokumen generik per aplikasi, seperti formulir, laporan analisa, UAT, rilis. | Ya | Penting untuk fitur dokumentasi dan kontrol versi dokumen. |
| AplikasiChecklist | `backend/app/Models/AplikasiChecklist.php` | `aplikasi_checklists` | Menyimpan checklist studi kelayakan, progres implementasi, dan progres DevOps. | Ya | Merepresentasikan progres dan kontrol tahapan. |
| AplikasiStatusHistory | `backend/app/Models/AplikasiStatusHistory.php` | `aplikasi_status_histories` | Mencatat riwayat perubahan status workflow aplikasi. | Ya | Penting untuk timeline/status history, berbeda dari ERD karena menjelaskan perilaku perubahan status. |
| AplikasiNote | `backend/app/Models/AplikasiNote.php` | `aplikasi_notes` | Mencatat catatan perbaikan, informasi, dan catatan uji keamanan. | Ya | Bagian dari komunikasi dan verifikasi proses. |
| Rfc | `backend/app/Models/Rfc.php` | `rfcs` | Mencatat Request for Change pada aplikasi. | Ya | Fitur utama pengelolaan RFC. |
| AppNotification | `backend/app/Models/AppNotification.php` | `app_notifications` | Notifikasi in-app untuk user terkait status atau aksi aplikasi. | Ya | Mendukung alur informasi antar role. |
| DevopsConfig | `backend/app/Models/DevopsConfig.php` | `devops_configs` | Konfigurasi DevOps, environment, SPL/DBT, dan database pendukung. | Ya, dalam package Konfigurasi Infrastruktur | Bagian dari konfigurasi infrastruktur aplikasi. |
| FrontendConfig | `backend/app/Models/FrontendConfig.php` | `frontend_configs` | Konfigurasi modul frontend dan URL staging/production. | Ya, dalam package Konfigurasi Infrastruktur | Bagian dari konfigurasi aplikasi yang dihasilkan otomatis. |
| BackendConfig | `backend/app/Models/BackendConfig.php` | `backend_configs` | Konfigurasi backend, database, endpoint, dan status check. | Ya, dalam package Konfigurasi Infrastruktur | Bagian dari konfigurasi backend aplikasi. |
| EnvironmentConfig | `backend/app/Models/EnvironmentConfig.php` | `environment_configs` | Menyimpan pasangan nama dan nilai environment variable aplikasi. | Ya, dalam package Konfigurasi Infrastruktur | Menunjukkan konfigurasi runtime aplikasi. |
| DatabaseConfig | `backend/app/Models/DatabaseConfig.php` | `database_configs` | Konfigurasi database per deployment. | Ya, dalam package Konfigurasi Infrastruktur | Bagian penting dari konfigurasi infrastruktur. |
| ObjectStorageConfig | `backend/app/Models/ObjectStorageConfig.php` | `object_storage_configs` | Konfigurasi MinIO/object storage per environment. | Ya, dalam package Konfigurasi Infrastruktur | Bagian penting dari konfigurasi storage. |
| ApiGatewayConfig | `backend/app/Models/ApiGatewayConfig.php` | `api_gateway_configs` | Konfigurasi service dan route API gateway. | Ya, dalam package Konfigurasi Infrastruktur | Bagian penting dari konfigurasi gateway. |
| UserRole | `backend/app/Enums/UserRole.php` | Tidak ada | Enum role sistem. | Opsional sebagai enumeration | Bukan entity domain, tetapi berguna jika Class Diagram menampilkan tipe enum role. |
| AplikasiStatus | `backend/app/Enums/AplikasiStatus.php` | Tidak ada | Enum status lifecycle aplikasi. | Opsional sebagai enumeration | Berguna untuk memperjelas status workflow. |
| AplikasiJenisDokumen | `backend/app/Enums/AplikasiJenisDokumen.php` | Tidak ada | Enum jenis dokumen aplikasi. | Opsional sebagai enumeration | Berguna untuk memperjelas `document_type`. |
| AutoGenerationService | `backend/app/Services/AutoGenerationService.php` | Tidak ada | Service pembentuk data analisa, proyek, dan konfigurasi otomatis. | Opsional sebagai service class | Penting sebagai sumber operation, tetapi bukan model/entity utama. |
| AplikasiDocumentAccess | `backend/app/Support/AplikasiDocumentAccess.php` | Tidak ada | Rule akses dokumen per role, jenis dokumen, dan status aplikasi. | Tidak sebagai class utama | Lebih cocok sebagai policy/support class, bukan domain entity. |
| ApiResponse | `backend/app/Http/Helpers/ApiResponse.php` | Tidak ada | Helper response API standar. | Tidak | Class teknis API, tidak perlu masuk Class Diagram domain. |
| QueryHelper | `backend/app/Http/Helpers/QueryHelper.php` | Tidak ada | Helper escape query pencarian. | Tidak | Class teknis, bukan class domain. |
| Store/Update Form Request | `backend/app/Http/Requests/*.php` | Tidak ada | Validasi input. | Tidak sebagai class utama | Lebih tepat sebagai sumber operation validasi. |
| Middleware | `backend/app/Http/Middleware/*.php` | Tidak ada | Auth, role access, sanitasi, logging, security headers. | Tidak | Class teknis lintas sistem, cukup dicatat sebagai mekanisme akses. |

## B. Atribut Utama Setiap Class

Catatan: atribut `id`, `created_at`, `updated_at`, dan `deleted_at` bersifat teknis database. Boleh ditampilkan bila dosen meminta identitas objek, tetapi untuk Class Diagram konseptual biasanya cukup menampilkan atribut domain utama.

| Class | Atribut | Tipe data | Sumber kode | Penting ditampilkan | Catatan |
|---|---|---|---|---|---|
| User | name | string | `0001_01_01_000000_create_users_table.php`, `User::$fillable` | Ya | Nama personil. |
| User | email | string unique | `0001_01_01_000000_create_users_table.php`, `User::$fillable` | Ya | Identitas login. |
| User | password | string/hashed | migration, `User::casts()` | Tidak/opsional | Sensitif, lebih baik tidak ditampilkan detailnya. |
| User | role | string | `2025_11_01_000001_update_users_role_to_string.php`, `User::$fillable`, `UserRole` | Ya | Role-based access. |
| User | email_verified_at | timestamp nullable | migration, `User::casts()` | Tidak | Teknis auth. |
| User | remember_token | string nullable | migration | Tidak | Teknis auth. |
| User | deleted_at | timestamp nullable | `2025_10_21_093457_add_soft_deletes_to_users_table.php` | Tidak/opsional | Menunjukkan akun nonaktif, tetapi teknis soft delete. |
| Aplikasi | nama_layanan | string | `2025_10_16_101305_create_aplikasis_table.php`, `Aplikasi::$fillable` | Ya | Identitas layanan. |
| Aplikasi | nama_singkat | string | migration, fillable | Ya | Kode/nama pendek aplikasi. |
| Aplikasi | nama_aplikasi | string | migration, fillable | Ya | Nama aplikasi. |
| Aplikasi | jenis_layanan_aplikasi | enum/string | migration, `Aplikasi::$casts` | Ya | `publik` atau `internal`; memengaruhi auto-generation. |
| Aplikasi | kode_unitOrganisasi | string | migration, fillable | Ya | Unit pemilik/pengaju. |
| Aplikasi | tipe_akuisisi | string/enum request | migration, `StoreAplikasiRequest` | Ya | `Custom-Made` atau `Off-The-Shelf`. |
| Aplikasi | status | string/enum konseptual | migration, `AplikasiStatus`, constants model | Ya | Status workflow aplikasi. |
| Aplikasi | doc_pengajuan_path, doc_permohonan_path, doc_studi_kelayakan_path | string nullable | `2025_10_31_000001_add_application_docs_columns_to_aplikasis_table.php` | Opsional | Legacy/specific document path; dokumen baru memakai `AplikasiDocument`. |
| Aplikasi | security_test_passed | boolean nullable | `2026_04_17_120000_add_security_review_fields_to_aplikasis_table.php`, casts | Ya | Hasil uji keamanan. |
| Aplikasi | security_tested_by | foreignId nullable | migration, relation `securityTester()` | Ya | Penguji keamanan. |
| Aplikasi | security_tested_at | timestamp nullable | migration, casts | Ya | Waktu uji keamanan. |
| Aplikasi | security_test_notes | text nullable | migration | Ya | Catatan uji keamanan. |
| Aplikasi | deployed_staging_at, deployed_production_at | timestamp nullable | `2026_05_25_000001_add_deployment_tracking_to_aplikasis_table.php` | Ya | Status deployment. |
| Aplikasi | deployed_staging_by, deployed_production_by | foreignId nullable | migration, relations `stagingDeployer()`, `productionDeployer()` | Ya | Pelaksana deployment. |
| Aplikasi | deployment_notes | string(500) nullable | migration | Ya | Catatan deployment. |
| Aplikasi | created_by, updated_by | foreignId nullable | `2025_10_23_084010_add_user_tracking_to_aplikasis_table.php`, relations | Ya | Pemilik/pembuat dan pengubah aplikasi. |
| AnalisaDesain | aplikasi_id | foreignId | `2025_10_16_101306_create_analisa_desains_table.php`, fillable | Ya | Mengikat analisa ke aplikasi. |
| AnalisaDesain | ui_platform | string nullable | migration, fillable | Ya | Platform UI: `dws`, `layanan`. |
| AnalisaDesain | interop_type | string nullable | migration, fillable | Ya | Kebutuhan interoperabilitas. |
| AnalisaDesain | storage_type | string nullable | migration, request validation | Ya | `db` atau `object-storage`. |
| AnalisaDesain | nama_aktor | string nullable | migration, fillable | Ya | Aktor dalam desain. |
| AnalisaDesain | method | string nullable | migration, request validation | Ya | HTTP method transaksi. |
| AnalisaDesain | url | string nullable | migration, request validation | Ya | Endpoint transaksi. |
| AnalisaDesain | tipe_resource | enum/string nullable | migration, casts | Ya | `terbuka` atau `tertutup`. |
| AnalisaDesain | aktor_transaksi | string nullable | migration, fillable | Ya | Aktor transaksi. |
| AnalisaDesain | created_by, updated_by | foreignId nullable | `2025_10_23_084018_add_user_tracking_to_analisa_desains_table.php` | Opsional | Audit pembuat/pengubah. |
| Proyek | aplikasi_id | foreignId | `2025_10_16_101307_create_proyeks_table.php`, fillable | Ya | Mengikat proyek ke aplikasi. |
| Proyek | modul | string | migration, fillable | Ya | Nama modul proyek. |
| Proyek | jenis | enum/string | migration, casts | Ya | `backend` atau `frontend`. |
| AplikasiDocument | aplikasi_id | foreignId | `2026_04_15_100000_epic_a_status_normalize_documents_and_index.php`, fillable | Ya | Dokumen milik aplikasi. |
| AplikasiDocument | document_type | string/enum | migration, `AplikasiJenisDokumen`, casts | Ya | Jenis dokumen. |
| AplikasiDocument | storage_path | string | migration, fillable | Ya | Lokasi file. |
| AplikasiDocument | original_filename | string nullable | migration, fillable | Ya | Nama file asli. |
| AplikasiDocument | mime_type | string nullable | migration, fillable | Opsional | Metadata file. |
| AplikasiDocument | file_size | unsignedBigInteger nullable | migration, casts | Opsional | Metadata file. |
| AplikasiDocument | version | unsignedSmallInteger | migration, casts | Ya | Versi dokumen. |
| AplikasiDocument | status | string | migration, fillable | Ya | `active` atau `superseded`. |
| AplikasiDocument | uploaded_by | foreignId nullable | migration, relation `uploader()` | Ya | User pengunggah. |
| AplikasiDocument | notes | text nullable | migration, fillable | Opsional | Catatan upload. |
| AplikasiChecklist | aplikasi_id | foreignId | `2026_04_16_000001_create_aplikasi_checklists_table.php`, fillable | Ya | Checklist milik aplikasi. |
| AplikasiChecklist | category | string | migration, request validation | Ya | `studi_kelayakan`, `implementation_progress`, `devops_progress`, dll. |
| AplikasiChecklist | title | string | migration, fillable | Ya | Item checklist. |
| AplikasiChecklist | item_status | string | migration, request validation | Ya | `pending`, `in_progress`, `done`. |
| AplikasiChecklist | notes | text nullable | migration | Opsional | Catatan checklist. |
| AplikasiChecklist | sort_order | unsignedInteger | migration | Opsional | Pengurutan tampilan. |
| AplikasiChecklist | created_by, updated_by | foreignId nullable | migration, relations | Ya | Pembuat dan pengubah checklist. |
| AplikasiStatusHistory | aplikasi_id | foreignId | `2026_05_26_034112_create_aplikasi_status_histories_table.php`, fillable | Ya | Riwayat milik aplikasi. |
| AplikasiStatusHistory | status_sebelumnya | string nullable | migration, fillable | Ya | Status lama. |
| AplikasiStatusHistory | status_baru | string | migration, fillable | Ya | Status baru. |
| AplikasiStatusHistory | aksi | string | migration, fillable | Ya | Nama aksi workflow. |
| AplikasiStatusHistory | catatan | text nullable | migration, fillable | Ya | Catatan transisi. |
| AplikasiStatusHistory | changed_by | foreignId nullable | migration, relation `changer()` | Ya | User pengubah status. |
| AplikasiNote | aplikasi_id | foreignId | `2026_04_16_000002_create_aplikasi_notes_table.php`, fillable | Ya | Catatan milik aplikasi. |
| AplikasiNote | note_type | string | migration, request validation | Ya | `perbaikan`, `uji_keamanan`, `info`. |
| AplikasiNote | body | text | migration, fillable | Ya | Isi catatan. |
| AplikasiNote | is_checked | boolean | migration, casts | Ya | Status selesai/tercek. |
| AplikasiNote | created_by | foreignId nullable | migration, relation `creator()` | Ya | Pembuat catatan. |
| AplikasiNote | checked_by | foreignId nullable | migration, relation `checker()` | Ya | Pemeriksa catatan. |
| AplikasiNote | checked_at | timestamp nullable | migration, casts | Ya | Waktu catatan ditandai selesai. |
| Rfc | aplikasi_id | foreignId | `2025_10_31_000100_create_rfcs_table.php`, fillable | Ya | RFC milik aplikasi. |
| Rfc | tipe_rfc | enum | migration, `StoreRfcRequest` | Ya | `Medium`, `Standar`, `Minor`, `Major`, `Darurat`. |
| Rfc | deskripsi | text nullable | migration, fillable | Ya | Deskripsi perubahan. |
| Rfc | pelaksana | string nullable | `2025_10_31_000101_add_pelaksana_to_rfcs_table.php`, request validation | Ya | Pelaksana RFC. |
| Rfc | status_tindaklanjut | string | migration, request validation | Ya | Tahap tindak lanjut RFC. |
| Rfc | created_by, updated_by | unsignedBigInteger nullable | migration, model booted, relations | Ya | Pembuat dan pengubah RFC. |
| Rfc | deleted_at | timestamp nullable | `2026_06_30_100000_add_soft_deletes_to_rfcs_table.php` | Opsional | Arsip RFC, teknis soft delete. |
| AppNotification | user_id | foreignId | `2026_04_30_000001_create_app_notifications_table.php`, fillable | Ya | Penerima notifikasi. |
| AppNotification | aplikasi_id | foreignId nullable | migration, fillable | Ya | Aplikasi terkait. |
| AppNotification | type | string | migration, fillable | Ya | Jenis notifikasi. |
| AppNotification | title | string | migration, fillable | Ya | Judul notifikasi. |
| AppNotification | body | text | migration, fillable | Ya | Isi notifikasi. |
| AppNotification | is_read | boolean | migration, casts | Ya | Status baca. |
| DevopsConfig | aplikasi_id | foreignId | `2025_10_16_101311_create_devops_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| DevopsConfig | project, dbt_dev, dbt, spl_dev, spl, auth | string nullable | migration, fillable | Ya | Konfigurasi DevOps/SPL/DBT. |
| DevopsConfig | env_staging, env_production | string nullable | migration, fillable | Ya | Environment deployment. |
| DevopsConfig | db_connection, db_host, db_port, db_database, db_username | string nullable | `2025_10_25_102137_add_database_fields_to_devops_configs_table.php` | Ya | Database DevOps. |
| FrontendConfig | aplikasi_id | foreignId | `2025_10_16_101312_create_frontend_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| FrontendConfig | nama_modul | string | migration, fillable | Ya | Modul frontend. |
| FrontendConfig | local_url | string | migration, fillable | Ya | URL local/dev. |
| FrontendConfig | feat_staging_production_url | string | migration, fillable | Ya | URL staging/production. |
| FrontendConfig | check | boolean | migration, casts | Ya | Status checklist konfigurasi. |
| BackendConfig | aplikasi_id | foreignId | `2025_10_16_101313_create_backend_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| BackendConfig | deployment | enum/string | migration, casts | Ya | `local`, `staging`, `production`. |
| BackendConfig | db_connection, db_host, db_port, db_database, db_username | string | migration, fillable | Ya | Konfigurasi database backend. |
| BackendConfig | method, url_endpoint | string nullable | migration, fillable | Ya | Endpoint backend. |
| BackendConfig | check | boolean | migration, casts | Ya | Status checklist konfigurasi. |
| EnvironmentConfig | aplikasi_id | foreignId | `2025_10_16_101314_create_environment_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| EnvironmentConfig | env_name | string | migration, fillable | Ya | Nama variable. |
| EnvironmentConfig | env_value | text | migration, fillable | Ya | Nilai variable. |
| DatabaseConfig | aplikasi_id | foreignId | `2025_10_16_101308_create_database_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| DatabaseConfig | deployment | enum/string | migration, casts | Ya | `staging`, `production`, `local`. |
| DatabaseConfig | db_connection, db_host, db_port, db_database, db_username | string | migration, fillable | Ya | Detail koneksi database. |
| DatabaseConfig | db_password | string nullable | migration, fillable | Tidak/opsional | Sensitif, sebaiknya tidak ditampilkan detailnya. |
| ObjectStorageConfig | aplikasi_id | foreignId | `2025_10_16_101309_create_object_storage_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| ObjectStorageConfig | environment | enum/string | migration, casts | Ya | `minio-dev` atau `minio`. |
| ObjectStorageConfig | minio_bucket, minio_default_region, minio_endpoint, minio_url | string | migration, fillable | Ya | Detail object storage. |
| ObjectStorageConfig | minio_use_path_style_endpoint | boolean | migration, casts | Opsional | Detail teknis storage. |
| ApiGatewayConfig | aplikasi_id | foreignId | `2025_10_16_101310_create_api_gateway_configs_table.php`, fillable | Ya | Konfigurasi milik aplikasi. |
| ApiGatewayConfig | environment | enum/string | migration, casts | Ya | `spl-dev` atau `spl`. |
| ApiGatewayConfig | service_name, host, path, route_name, route_path | string | migration, fillable | Ya | Detail gateway dan routing. |

## C. Method atau Operation Setiap Class

Keterangan: "Eksplisit" berarti method benar-benar ada di model/controller/service/support. "Konseptual" berarti nama operation disarankan untuk Class Diagram, sedangkan implementasinya tersebar di controller, route, request, service, atau event model.

| Class | Operation disarankan | Sumber operation | Fungsi | Status |
|---|---|---|---|---|
| User | login() | `backend/app/Http/Controllers/AuthController.php::login()` | Autentikasi dan membuat token Sanctum. | Eksplisit di controller, konseptual untuk class User. |
| User | logout() | `AuthController::logout()` | Menghapus token aktif. | Eksplisit di controller, konseptual untuk User. |
| User | getCurrentUser() | `AuthController::me()` | Mengambil profil user aktif. | Eksplisit di controller. |
| User | registerUser() / tambahPersonil() | `AuthController::register()`, `PersonilController::store()` | Membuat akun/personil baru. | Eksplisit di controller. |
| User | updatePersonil() | `PersonilController::update()` | Mengubah nama, email, role, atau password personil. | Eksplisit di controller. |
| User | nonaktifkanPersonil() | `PersonilController::destroy()` | Menonaktifkan akun melalui soft delete dan menghapus token. | Eksplisit di controller. |
| User | hapusPermanenPersonil() | `PersonilController::forceDestroy()` | Menghapus akun permanen. | Eksplisit di controller. |
| User | pulihkanPersonil() | `PersonilController::restore()` | Mengaktifkan kembali akun soft-deleted. | Eksplisit di controller. |
| User | isAdminSistem(), isPengelolaAplikasi(), isAnalisDesain(), isUnitKerja(), isTimImplementasiAplikasi(), isDevops(), isTimUjiKeamanan() | `backend/app/Models/User.php` | Mengecek role user. | Eksplisit di model. |
| User | canAccess() | `backend/app/Http/Middleware/RoleMiddleware.php::handle()`, `AplikasiWorkflowController::canAccessWorkflowAplikasi()` | Mengecek hak akses berbasis role dan kepemilikan aplikasi. | Konseptual, implementasi di middleware/controller. |
| Aplikasi | ajukanAplikasi() | `AplikasiController::store()`, `StoreAplikasiRequest::rules()` | Membuat pengajuan aplikasi baru dan set status `diajukan`. | Eksplisit di controller, konseptual untuk Aplikasi. |
| Aplikasi | cariAplikasi() / daftarAplikasi() | `AplikasiController::index()` | Mencari, memfilter, dan memaginasi aplikasi. | Eksplisit di controller. |
| Aplikasi | lihatDetail() | `AplikasiController::show()` | Menampilkan detail aplikasi beserta relasi utama. | Eksplisit di controller. |
| Aplikasi | hitungStatistik() | `AplikasiController::stats()`, `AplikasiStatus::{developmentValues, operationalValues, inactiveValues, stoppedValues}` | Menghitung statistik development, operasional, nonaktif, dan stopped. | Eksplisit di controller/enum. |
| Aplikasi | updateInformasi() | `AplikasiController::update()`, `UpdateAplikasiRequest::rules()` | Mengubah field tertentu dan memicu auto-generation konfigurasi. | Eksplisit di controller. |
| Aplikasi | arsipkan() | `AplikasiController::destroy()` | Soft delete aplikasi yang belum operasional. | Eksplisit di controller. |
| Aplikasi | tarikPengajuan() | `AplikasiController::withdraw()` | Unit kerja menarik pengajuan sendiri selama status masih `diajukan`. | Eksplisit di controller. |
| Aplikasi | nonaktifkan() | `AplikasiController::nonaktifkan()` | Menandai aplikasi production sebagai `nonaktif` tanpa menghapus data. | Eksplisit di controller. |
| Aplikasi | pulihkan() | `AplikasiController::restore()` | Restore aplikasi soft-deleted. | Eksplisit di controller. |
| Aplikasi | daftarArsip() | `AplikasiController::trashed()` | Menampilkan aplikasi yang diarsipkan. | Eksplisit di controller. |
| Aplikasi | verifikasiPengajuan() | `HandlesAplikasiTransitions::verifikasiPengajuan()` | Menyetujui, meminta perbaikan, atau menolak pengajuan. | Eksplisit di trait controller. |
| Aplikasi | kirimUlangPengajuan() | `HandlesAplikasiTransitions::perbaikanPengajuan()` | Unit kerja mengirim ulang pengajuan setelah perbaikan. | Eksplisit di trait controller. |
| Aplikasi | simpanStudiKelayakan() | `HandlesAplikasiTransitions::studiKelayakan()` | Menentukan aplikasi layak/tidak layak. | Eksplisit di trait controller. |
| Aplikasi | mulaiAnalisaDesain() | `HandlesAplikasiTransitions::mulaiAnalisaDesain()` | Mengubah status ke tahap analisa desain. | Eksplisit di trait controller. |
| Aplikasi | mulaiPengembangan() | `HandlesAplikasiTransitions::mulaiPengembangan()` | Mengubah status ke pengembangan setelah laporan analisa desain aktif. | Eksplisit di trait controller. |
| Aplikasi | tandaiSiapUat() | `HandlesAplikasiTransitions::siapUat()` | Mengubah status ke UAT setelah checklist dan dokumen wajib lengkap. | Eksplisit di trait controller. |
| Aplikasi | verifikasiUat() | `HandlesAplikasiTransitions::verifikasiUat()` | Menentukan UAT sesuai atau perlu perbaikan. | Eksplisit di trait controller. |
| Aplikasi | selesaiPerbaikanUat() | `HandlesAplikasiTransitions::selesaiPerbaikanUat()` | Mengembalikan status ke UAT setelah perbaikan. | Eksplisit di trait controller. |
| Aplikasi | simpanHasilUjiKeamanan() | `HandlesAplikasiTransitions::hasilUjiKeamanan()`, `AplikasiWorkflowController::securityReviewUpdate()` | Menentukan hasil uji keamanan dan mencatat tester. | Eksplisit di controller/trait. |
| Aplikasi | selesaiPerbaikanKeamanan() | `HandlesAplikasiTransitions::selesaiPerbaikanKeamanan()` | Mengembalikan status ke uji keamanan setelah perbaikan. | Eksplisit di trait controller. |
| Aplikasi | lihatDeploymentStatus() | `AplikasiWorkflowController::deploymentShow()` | Menampilkan status deployment staging/production. | Eksplisit di controller. |
| Aplikasi | updateDeploymentStatus() | `AplikasiWorkflowController::deploymentUpdate()` | Mencatat deployment staging/production dan mengubah status workflow. | Eksplisit di controller. |
| Aplikasi | getTimeline() | `HandlesAplikasiTransitions::statusHistories()` | Mengambil riwayat perubahan status. | Eksplisit di trait controller, menggunakan relasi `statusHistories()`. |
| Aplikasi | kirimNotifikasiStatus() | `Aplikasi::booted()` event `updated` | Membuat `AppNotification` saat status berubah. | Eksplisit di model event. |
| AnalisaDesain | buatAnalisaDesain() | `AnalisaDesainController::store()`, `StoreAnalisaDesainRequest::rules()` | Menambahkan data analisa desain. | Eksplisit di controller/request. |
| AnalisaDesain | daftarAnalisaDesain() | `AnalisaDesainController::index()` | Mencari dan memaginasi analisa desain. | Eksplisit di controller. |
| AnalisaDesain | ringkasAnalisaDesain() | `AnalisaDesainController::summary()` | Membuat ringkasan UI/interoperabilitas/storage/aktor per aplikasi. | Eksplisit di controller. |
| AnalisaDesain | updateAnalisaDesain() | `AnalisaDesainController::update()` | Mengubah satu data analisa desain. | Eksplisit di controller. |
| AnalisaDesain | batchUpdateAnalisaDesain() | `AnalisaDesainController::batchUpdate()` | Mengganti kumpulan analisa desain untuk satu aplikasi. | Eksplisit di controller. |
| AnalisaDesain | hapusAnalisaDesain() | `AnalisaDesainController::destroy()` | Soft delete analisa desain. | Eksplisit di controller/model SoftDeletes. |
| Proyek | generateProyek() | `AutoGenerationService::generateProyek()` | Membuat modul backend/frontend otomatis dari aplikasi. | Eksplisit di service private, konseptual untuk Proyek. |
| AplikasiDocument | daftarDokumen() | `AplikasiDocumentController::index()` | Menampilkan dokumen aplikasi beserta uploader dan URL file. | Eksplisit di controller. |
| AplikasiDocument | uploadDokumen() | `AplikasiDocumentController::store()` | Mengunggah dokumen dan membuat record dokumen. | Eksplisit di controller. |
| AplikasiDocument | generateVersion() | `AplikasiDocumentController::store()` | Menghitung versi berikutnya dengan `max(version)` dan lock transaction. | Eksplisit di controller, konseptual untuk document. |
| AplikasiDocument | supersedeDokumenLama() | `AplikasiDocumentController::store()` | Mengubah dokumen aktif lama menjadi `superseded`. | Eksplisit di controller. |
| AplikasiDocument | validateFile() | `StoreAplikasiDocumentRequest::rules()` | Validasi jenis dokumen, file, mime type, ukuran, catatan. | Eksplisit di request. |
| AplikasiDocument | validasiAksesDokumen() | `AplikasiDocumentAccess::{canView, canUploadType, canUploadTypeForStatus}` | Mengecek akses view/upload berdasarkan role dan status aplikasi. | Eksplisit di support class. |
| AplikasiChecklist | tambahChecklist() | `AplikasiWorkflowController::storeChecklist()` | Menambahkan checklist studi kelayakan/uji/rls. | Eksplisit di controller. |
| AplikasiChecklist | updateStatusChecklist() | `AplikasiWorkflowController::updateChecklist()`, `implementationUpdate()` | Mengubah status atau isi checklist. | Eksplisit di controller. |
| AplikasiChecklist | hapusChecklist() | `AplikasiWorkflowController::destroyChecklist()` | Menghapus checklist. | Eksplisit di controller. |
| AplikasiChecklist | generateDefaultChecklist() | `AplikasiWorkflowController::ensureImplementationChecklistSeed()` | Membuat default progress checklist implementasi/DevOps. | Eksplisit di controller private. |
| AplikasiChecklist | lihatProgressImplementasi() | `AplikasiWorkflowController::implementationIndex()` | Menampilkan checklist implementasi berdasarkan role dan status. | Eksplisit di controller. |
| AplikasiNote | tambahCatatan() | `AplikasiWorkflowController::storeNote()` | Menambahkan catatan aplikasi. | Eksplisit di controller. |
| AplikasiNote | updateCatatan() | `AplikasiWorkflowController::updateNote()` | Mengubah isi atau tipe catatan. | Eksplisit di controller. |
| AplikasiNote | tandaiSelesai() | `AplikasiWorkflowController::updateNote()` | Jika `is_checked=true`, mengisi `checked_by` dan `checked_at`. | Eksplisit di controller. |
| AplikasiNote | hapusCatatan() | `AplikasiWorkflowController::destroyNote()` | Menghapus catatan. | Eksplisit di controller. |
| AplikasiStatusHistory | catatPerubahanStatus() | `HandlesAplikasiTransitions::recordStatusHistory()`, `AplikasiController::nonaktifkan()` | Membuat record riwayat setiap perubahan status. | Eksplisit di trait/controller, konseptual untuk history. |
| AplikasiStatusHistory | getTimeline() | `HandlesAplikasiTransitions::statusHistories()` | Menampilkan timeline perubahan status. | Eksplisit di trait controller. |
| Rfc | buatRfc() | `RfcController::store()`, `StoreRfcRequest::rules()` | Membuat RFC aplikasi. | Eksplisit di controller/request. |
| Rfc | daftarRfc() | `RfcController::index()` | Menampilkan/mencari/memfilter RFC. | Eksplisit di controller. |
| Rfc | lihatRfc() | `RfcController::show()` | Menampilkan detail RFC. | Eksplisit di controller. |
| Rfc | updateRfc() | `RfcController::update()`, `UpdateRfcRequest::rules()` | Mengubah RFC. | Eksplisit di controller/request. |
| Rfc | arsipkanRfc() | `RfcController::destroy()` | Soft delete RFC sebagai arsip. | Eksplisit di controller/model SoftDeletes. |
| AppNotification | kirimNotifikasi() | `Aplikasi::booted()` event `updated` | Membuat notifikasi role dan unit kerja ketika status berubah. | Eksplisit di model event, konseptual untuk notification. |
| AppNotification | daftarNotifikasi() | `NotificationController::index()` | Menampilkan 30 notifikasi terbaru user dan jumlah unread. | Eksplisit di controller. |
| AppNotification | tandaiDibaca() | `NotificationController::markRead()` | Menandai satu notifikasi sebagai dibaca. | Eksplisit di controller. |
| AppNotification | tandaiSemuaDibaca() | `NotificationController::markAllRead()` | Menandai semua notifikasi user sebagai dibaca. | Eksplisit di controller. |
| DevopsConfig | generateDevopsConfig() | `AutoGenerationService::generateDevopsConfig()` | Membuat konfigurasi DevOps staging/production. | Eksplisit di service private, konseptual untuk class config. |
| FrontendConfig | generateFrontendConfig() | `AutoGenerationService::generateFrontendConfig()` | Membuat konfigurasi frontend dari `Proyek`. | Eksplisit di service private, konseptual untuk class config. |
| BackendConfig | generateBackendConfig() | `AutoGenerationService::generateBackendConfig()` | Membuat konfigurasi backend lokal. | Eksplisit di service private, konseptual untuk class config. |
| DatabaseConfig | generateDatabaseConfig() | `AutoGenerationService::generateDatabaseConfig()` | Membuat konfigurasi database staging/production. | Eksplisit di service private, konseptual untuk class config. |
| ObjectStorageConfig | generateObjectStorageConfig() | `AutoGenerationService::generateObjectStorageConfig()` | Membuat konfigurasi MinIO dev/production. | Eksplisit di service private, konseptual untuk class config. |
| ApiGatewayConfig | generateApiGatewayConfig() | `AutoGenerationService::generateApiGatewayConfig()` | Membuat service dan route API gateway. | Eksplisit di service private, konseptual untuk class config. |
| EnvironmentConfig | simpanKonfigurasiEnvironment() | Tidak ada controller khusus; model ada dan relasi ada | Menyimpan nama/nilai environment bila dipakai. | Konseptual; model ada, operation CRUD eksplisit belum ditemukan. |
| AutoGenerationService | generateAllConfigurations() | `AutoGenerationService::generateAllConfigurations()` | Membuat analisa desain, proyek, dan semua konfigurasi terkait aplikasi. | Eksplisit di service. |
| AutoGenerationService | updateUIAndProyekOnly() | `AutoGenerationService::updateUIAndProyekOnly()` | Mengubah UI platform dan proyek ketika jenis layanan berubah. | Eksplisit di service. |

## D. Relasi Antarclass

| Class asal | Class tujuan | Jenis relasi Laravel | Method relasi | Foreign key | Multiplicity Class Diagram | Label relasi / verb phrase | Catatan |
|---|---|---|---|---|---|---|---|
| User | Aplikasi | hasMany | `User::aplikasiDiajukan()` | `aplikasis.created_by` | User 1 - Aplikasi 0..* | mengajukan | Eksplisit di model User dan inverse `Aplikasi::creator()`. |
| Aplikasi | User | belongsTo | `Aplikasi::creator()` | `created_by` | Aplikasi 0..* - User 0..1 | dibuat/diajukan oleh | FK nullable. |
| Aplikasi | User | belongsTo | `Aplikasi::updater()` | `updated_by` | Aplikasi 0..* - User 0..1 | diperbarui oleh | FK nullable. |
| Aplikasi | User | belongsTo | `Aplikasi::securityTester()` | `security_tested_by` | Aplikasi 0..* - User 0..1 | diuji keamanan oleh | Eksplisit di model/migration. |
| Aplikasi | User | belongsTo | `Aplikasi::stagingDeployer()` | `deployed_staging_by` | Aplikasi 0..* - User 0..1 | dideploy staging oleh | Eksplisit di model/migration. |
| Aplikasi | User | belongsTo | `Aplikasi::productionDeployer()` | `deployed_production_by` | Aplikasi 0..* - User 0..1 | dideploy production oleh | Eksplisit di model/migration. |
| Aplikasi | AnalisaDesain | hasMany | `Aplikasi::analisaDesains()` | `analisa_desains.aplikasi_id` | Aplikasi 1 - AnalisaDesain 0..* | memiliki analisa desain | Eksplisit di model. |
| AnalisaDesain | Aplikasi | belongsTo | `AnalisaDesain::aplikasi()` | `aplikasi_id` | AnalisaDesain 1 - Aplikasi 1 | milik | Eksplisit di model. |
| AnalisaDesain | User | belongsTo | `AnalisaDesain::creator()` | `created_by` | AnalisaDesain 0..* - User 0..1 | dibuat oleh | Eksplisit di model. |
| AnalisaDesain | User | belongsTo | `AnalisaDesain::updater()` | `updated_by` | AnalisaDesain 0..* - User 0..1 | diperbarui oleh | Eksplisit di model. |
| Aplikasi | Proyek | hasMany | `Aplikasi::proyeks()` | `proyeks.aplikasi_id` | Aplikasi 1 - Proyek 0..* | menghasilkan/memiliki modul proyek | Eksplisit di model. |
| Proyek | Aplikasi | belongsTo | `Proyek::aplikasi()` | `aplikasi_id` | Proyek 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | AplikasiDocument | hasMany | `Aplikasi::documents()` | `aplikasi_documents.aplikasi_id` | Aplikasi 1 - Dokumen 0..* | memiliki/mengarsipkan dokumen | Eksplisit di model. |
| AplikasiDocument | Aplikasi | belongsTo | `AplikasiDocument::aplikasi()` | `aplikasi_id` | Dokumen 1 - Aplikasi 1 | milik | Eksplisit di model. |
| AplikasiDocument | User | belongsTo | `AplikasiDocument::uploader()` | `uploaded_by` | Dokumen 0..* - User 0..1 | diunggah oleh | Eksplisit di model. |
| Aplikasi | AplikasiChecklist | hasMany | `Aplikasi::checklists()` | `aplikasi_checklists.aplikasi_id` | Aplikasi 1 - Checklist 0..* | memiliki checklist/progres | Eksplisit di model. |
| AplikasiChecklist | Aplikasi | belongsTo | `AplikasiChecklist::aplikasi()` | `aplikasi_id` | Checklist 1 - Aplikasi 1 | milik | Eksplisit di model. |
| AplikasiChecklist | User | belongsTo | `AplikasiChecklist::creator()` | `created_by` | Checklist 0..* - User 0..1 | dibuat oleh | Eksplisit di model. |
| AplikasiChecklist | User | belongsTo | `AplikasiChecklist::updater()` | `updated_by` | Checklist 0..* - User 0..1 | diperbarui oleh | Eksplisit di model. |
| Aplikasi | AplikasiStatusHistory | hasMany | `Aplikasi::statusHistories()` | `aplikasi_status_histories.aplikasi_id` | Aplikasi 1 - StatusHistory 0..* | mencatat riwayat status | Eksplisit di model. |
| AplikasiStatusHistory | Aplikasi | belongsTo | `AplikasiStatusHistory::aplikasi()` | `aplikasi_id` | StatusHistory 1 - Aplikasi 1 | milik | Eksplisit di model. |
| AplikasiStatusHistory | User | belongsTo | `AplikasiStatusHistory::changer()` | `changed_by` | StatusHistory 0..* - User 0..1 | diubah oleh | Eksplisit di model. |
| Aplikasi | AplikasiNote | hasMany | `Aplikasi::notes()` | `aplikasi_notes.aplikasi_id` | Aplikasi 1 - Note 0..* | memiliki catatan | Eksplisit di model. |
| AplikasiNote | Aplikasi | belongsTo | `AplikasiNote::aplikasi()` | `aplikasi_id` | Note 1 - Aplikasi 1 | milik | Eksplisit di model. |
| AplikasiNote | User | belongsTo | `AplikasiNote::creator()` | `created_by` | Note 0..* - User 0..1 | ditulis oleh | Eksplisit di model. |
| AplikasiNote | User | belongsTo | `AplikasiNote::checker()` | `checked_by` | Note 0..* - User 0..1 | ditandai selesai oleh | Eksplisit di model. |
| Aplikasi | Rfc | hasMany | `Aplikasi::rfcs()` | `rfcs.aplikasi_id` | Aplikasi 1 - RFC 0..* | memiliki RFC | Eksplisit di model. |
| Rfc | Aplikasi | belongsTo | `Rfc::aplikasi()` | `aplikasi_id` | RFC 1 - Aplikasi 1 | terkait dengan | Eksplisit di model. |
| Rfc | User | belongsTo | `Rfc::creator()` | `created_by` | RFC 0..* - User 0..1 | dibuat oleh | Eksplisit di model. |
| Rfc | User | belongsTo | `Rfc::updater()` | `updated_by` | RFC 0..* - User 0..1 | diperbarui oleh | Eksplisit di model. |
| User | AppNotification | tidak ada inverse di User, belongsTo dari Notification | `AppNotification::user()` | `app_notifications.user_id` | User 1 - Notification 0..* | menerima notifikasi | Relasi eksplisit di AppNotification dan migration; inverse `User::notifications()` belum ada. |
| Aplikasi | AppNotification | hasMany | `Aplikasi::notifications()` | `app_notifications.aplikasi_id` | Aplikasi 0..1 - Notification 0..* | menghasilkan notifikasi | Eksplisit di model Aplikasi dan AppNotification. |
| AppNotification | Aplikasi | belongsTo | `AppNotification::aplikasi()` | `aplikasi_id` | Notification 0..* - Aplikasi 0..1 | terkait aplikasi | FK nullable. |
| Aplikasi | DatabaseConfig | hasMany | `Aplikasi::databaseConfigs()` | `database_configs.aplikasi_id` | Aplikasi 1 - DatabaseConfig 0..* | memiliki konfigurasi database | Eksplisit di model. |
| DatabaseConfig | Aplikasi | belongsTo | `DatabaseConfig::aplikasi()` | `aplikasi_id` | DatabaseConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | ObjectStorageConfig | hasMany | `Aplikasi::objectStorageConfigs()` | `object_storage_configs.aplikasi_id` | Aplikasi 1 - ObjectStorageConfig 0..* | memiliki konfigurasi storage | Eksplisit di model. |
| ObjectStorageConfig | Aplikasi | belongsTo | `ObjectStorageConfig::aplikasi()` | `aplikasi_id` | ObjectStorageConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | ApiGatewayConfig | hasMany | `Aplikasi::apiGatewayConfigs()` | `api_gateway_configs.aplikasi_id` | Aplikasi 1 - ApiGatewayConfig 0..* | memiliki konfigurasi gateway | Eksplisit di model. |
| ApiGatewayConfig | Aplikasi | belongsTo | `ApiGatewayConfig::aplikasi()` | `aplikasi_id` | ApiGatewayConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | EnvironmentConfig | hasMany | `Aplikasi::environmentConfigs()` | `environment_configs.aplikasi_id` | Aplikasi 1 - EnvironmentConfig 0..* | memiliki environment variable | Eksplisit di model. |
| EnvironmentConfig | Aplikasi | belongsTo | `EnvironmentConfig::aplikasi()` | `aplikasi_id` | EnvironmentConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | DevopsConfig | hasMany | `Aplikasi::devopsConfigs()` | `devops_configs.aplikasi_id` | Aplikasi 1 - DevopsConfig 0..* | memiliki konfigurasi DevOps | Eksplisit di model. |
| DevopsConfig | Aplikasi | belongsTo | `DevopsConfig::aplikasi()` | `aplikasi_id` | DevopsConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | FrontendConfig | hasMany | `Aplikasi::frontendConfigs()` | `frontend_configs.aplikasi_id` | Aplikasi 1 - FrontendConfig 0..* | memiliki konfigurasi frontend | Eksplisit di model. |
| FrontendConfig | Aplikasi | belongsTo | `FrontendConfig::aplikasi()` | `aplikasi_id` | FrontendConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |
| Aplikasi | BackendConfig | hasMany | `Aplikasi::backendConfigs()` | `backend_configs.aplikasi_id` | Aplikasi 1 - BackendConfig 0..* | memiliki konfigurasi backend | Eksplisit di model. |
| BackendConfig | Aplikasi | belongsTo | `BackendConfig::aplikasi()` | `aplikasi_id` | BackendConfig 1 - Aplikasi 1 | milik | Eksplisit di model. |

## E. Package atau Pengelompokan Class

| Package | Class yang disarankan | Alasan pengelompokan |
|---|---|---|
| Pengguna dan Akses Sistem | `User`, opsional `UserRole`, opsional `AplikasiDocumentAccess` sebagai policy/support | Memuat personil, role, dan aturan akses. Agar diagram domain tidak padat, middleware dan helper tidak perlu digambar. |
| Pengelolaan Aplikasi | `Aplikasi`, `AplikasiStatus`, `AplikasiStatusHistory`, `AnalisaDesain`, `Proyek` | Menjelaskan lifecycle utama aplikasi, status, analisa desain, dan modul proyek hasil perencanaan/pengembangan. |
| Dokumentasi dan Progres | `AplikasiDocument`, `AplikasiJenisDokumen`, `AplikasiChecklist`, `AplikasiNote` | Memuat dokumen, checklist studi kelayakan/progres, dan catatan komunikasi/perbaikan. |
| RFC dan Notifikasi | `Rfc`, `AppNotification` | RFC adalah permintaan perubahan aplikasi, sedangkan notifikasi menghubungkan perubahan status dengan user penerima. |
| Konfigurasi Infrastruktur | `DatabaseConfig`, `ObjectStorageConfig`, `ApiGatewayConfig`, `DevopsConfig`, `FrontendConfig`, `BackendConfig`, `EnvironmentConfig`, opsional `AutoGenerationService` sebagai service | Relevan karena kode memiliki model dan tabel khusus konfigurasi. Untuk diagram ringkas, package ini dapat ditampilkan sebagai kumpulan class di kanan/bawah Aplikasi dengan relasi `Aplikasi 1 - 0..* Config`. |

## F. Perbedaan dengan ERD

| Aspek | Catatan |
|---|---|
| Class yang juga muncul sebagai tabel ERD | `User`, `Aplikasi`, `AnalisaDesain`, `Proyek`, `AplikasiDocument`, `AplikasiChecklist`, `AplikasiStatusHistory`, `AplikasiNote`, `Rfc`, `AppNotification`, semua class konfigurasi. |
| Class/enum yang tidak muncul sebagai tabel utama | `UserRole`, `AplikasiStatus`, `AplikasiJenisDokumen`, `AutoGenerationService`, `AplikasiDocumentAccess`, request classes, middleware, helper. |
| Class yang sebaiknya tidak masuk Class Diagram domain | `ApiResponse`, `QueryHelper`, middleware, request classes, `personal_access_tokens`, `sessions`, `jobs`, `cache`, `password_reset_tokens`. Ini lebih cocok di ERD/arsitektur teknis, bukan class domain. |
| Perbedaan utama dari ERD | ERD menekankan tabel, PK, FK, index, dan constraint. Class Diagram harus menonjolkan perilaku seperti `ajukanAplikasi()`, `verifikasiPengajuan()`, `uploadDokumen()`, `generateVersion()`, `catatPerubahanStatus()`, `generateAllConfigurations()`, `tandaiDibaca()`, dan role check pada `User`. |
| Relasi object-oriented yang penting | Relasi bukan hanya FK, tetapi juga peran: User "mengajukan" Aplikasi, Aplikasi "memiliki" Dokumen/Checklist/Catatan/RFC/Konfigurasi, User "mengunggah" Dokumen, User "mencatat" StatusHistory, Aplikasi "menghasilkan" Notifikasi. |

## G. Asumsi dan Ketidakpastian

| Area | Ketidakpastian / asumsi | Rekomendasi konfirmasi |
|---|---|---|
| Operation model vs controller | Banyak operation bisnis tidak ditulis sebagai method di model Laravel, melainkan di controller/trait/service. | Di Class Diagram, beri catatan bahwa operation seperti `ajukanAplikasi()`, `verifikasiUat()`, dan `uploadDokumen()` adalah operation konseptual yang disimpulkan dari controller. |
| EnvironmentConfig | Model dan tabel ada, relasi ke `Aplikasi` ada, tetapi controller CRUD khusus untuk environment config tidak ditemukan. | Jika ingin menampilkan operation `simpanKonfigurasiEnvironment()`, beri label konseptual atau hubungkan dengan `AutoGenerationService`. |
| Config classes | Semua config class memiliki model dan tabel, tetapi operation utama berada di `AutoGenerationService`, bukan di masing-masing model. | Pada diagram ringkas, boleh tampilkan package "Konfigurasi Infrastruktur" tanpa semua atribut detail agar tidak terlalu padat. |
| Notifikasi | Pembuatan notifikasi terjadi di event `Aplikasi::booted()` saat status berubah, bukan di `NotificationController`. | Operation `kirimNotifikasi()` sebaiknya diberi catatan "model event pada Aplikasi". |
| User inverse relations | `User` hanya memiliki `aplikasiDiajukan()` secara eksplisit. Inverse untuk notifikasi, dokumen, checklist, note, RFC, deployment, dan status changer tidak semuanya ditulis di model User. | Tetap tampilkan relasi karena FK dan relasi belongsTo ada di class tujuan, tetapi beri catatan "inverse belum didefinisikan di User". |
| AplikasiDocument download | Tidak ditemukan endpoint download langsung; controller mengembalikan `file_url` dari storage public. | Jika Class Diagram butuh operation `downloadDokumen()`, tulis sebagai konseptual/akses file URL, bukan method eksplisit. |
| Arsip aplikasi vs nonaktif aplikasi | `arsipkan()` memakai soft delete untuk aplikasi non-operasional, sedangkan `nonaktifkan()` mengubah status aplikasi production ke `nonaktif`. | Jangan samakan `delete` dengan `nonaktif`; di diagram bisa dipisah sebagai dua operation berbeda. |
| AplikasiStatus stopped | Backend statistik memiliki kategori `stopped` untuk `ditolak` dan `tidak_layak`, tetapi UI tidak menampilkan card khusus. | Jika Class Diagram tidak membahas statistik, cukup tampilkan enum statusnya. |

## Ringkasan Akhir

### Class yang Direkomendasikan Masuk Class Diagram

`User`, `Aplikasi`, `AnalisaDesain`, `Proyek`, `AplikasiDocument`, `AplikasiChecklist`, `AplikasiStatusHistory`, `AplikasiNote`, `Rfc`, `AppNotification`, `DevopsConfig`, `FrontendConfig`, `BackendConfig`, `EnvironmentConfig`, `DatabaseConfig`, `ObjectStorageConfig`, `ApiGatewayConfig`.

Enum opsional yang bisa ditampilkan bila diagram ingin lebih jelas: `UserRole`, `AplikasiStatus`, `AplikasiJenisDokumen`.

Service opsional: `AutoGenerationService`, terutama bila ingin menunjukkan bahwa konfigurasi infrastruktur dibuat otomatis.

### Class yang Tidak Perlu Masuk Class Diagram Domain

`ApiResponse`, `QueryHelper`, `RoleMiddleware`, `SanitizeInput`, `LogRequests`, `SecurityHeaders`, seluruh Form Request, tabel `sessions`, `cache`, `jobs`, `password_reset_tokens`, dan `personal_access_tokens`.

### Relasi Utama

| Relasi utama | Makna |
|---|---|
| User 1 - 0..* Aplikasi | User/unit kerja mengajukan aplikasi. |
| Aplikasi 1 - 0..* AnalisaDesain | Aplikasi memiliki detail analisis desain. |
| Aplikasi 1 - 0..* Proyek | Aplikasi menghasilkan modul proyek frontend/backend. |
| Aplikasi 1 - 0..* AplikasiDocument | Aplikasi memiliki dokumen lifecycle. |
| Aplikasi 1 - 0..* AplikasiChecklist | Aplikasi memiliki checklist studi/progres. |
| Aplikasi 1 - 0..* AplikasiStatusHistory | Aplikasi memiliki timeline perubahan status. |
| Aplikasi 1 - 0..* AplikasiNote | Aplikasi memiliki catatan perbaikan/info/uji. |
| Aplikasi 1 - 0..* Rfc | Aplikasi memiliki RFC. |
| User 1 - 0..* AppNotification | User menerima notifikasi. |
| Aplikasi 1 - 0..* Config classes | Aplikasi memiliki konfigurasi infrastruktur. |

### Operation Utama

| Area | Operation utama |
|---|---|
| User dan akses | `login()`, `logout()`, `registerUser()`, `tambahPersonil()`, `updatePersonil()`, `nonaktifkanPersonil()`, `pulihkanPersonil()`, `isPengelolaAplikasi()`, `isUnitKerja()`, `canAccess()`. |
| Aplikasi dan workflow | `ajukanAplikasi()`, `updateInformasi()`, `arsipkan()`, `tarikPengajuan()`, `nonaktifkan()`, `pulihkan()`, `verifikasiPengajuan()`, `studiKelayakan()`, `mulaiAnalisaDesain()`, `mulaiPengembangan()`, `tandaiSiapUat()`, `verifikasiUat()`, `simpanHasilUjiKeamanan()`, `updateDeploymentStatus()`, `getTimeline()`. |
| Dokumen dan progres | `uploadDokumen()`, `generateVersion()`, `supersedeDokumenLama()`, `validateFile()`, `tambahChecklist()`, `updateStatusChecklist()`, `generateDefaultChecklist()`, `tambahCatatan()`, `tandaiSelesai()`. |
| RFC dan notifikasi | `buatRfc()`, `updateRfc()`, `arsipkanRfc()`, `kirimNotifikasi()`, `tandaiDibaca()`, `tandaiSemuaDibaca()`. |
| Konfigurasi | `generateAllConfigurations()`, `generateDatabaseConfig()`, `generateObjectStorageConfig()`, `generateApiGatewayConfig()`, `generateFrontendConfig()`, `generateBackendConfig()`, `generateDevopsConfig()`. |

### Catatan Penting agar Class Diagram Tidak Sama dengan ERD

1. Jangan hanya menampilkan `id`, FK, dan tabel. Tampilkan operation bisnis yang berasal dari controller/service/trait.
2. Tampilkan verb phrase relasi seperti "mengajukan", "memiliki", "mengunggah", "mencatat", "menerima", dan "menghasilkan".
3. Pisahkan konsep `arsipkan()` dan `nonaktifkan()` karena implementasinya berbeda.
4. Gunakan package supaya konfigurasi infrastruktur tidak membuat diagram terlalu padat.
5. Beri catatan bahwa beberapa operation adalah operation konseptual, karena Laravel menempatkan perilaku di controller/service, bukan selalu di model.
