# LAMPIRAN C: PENGUJIAN INTEGRASI (Integration Testing) — SIMPA

---

## Pendahuluan

Lampiran ini mendokumentasikan test cases untuk **Major Feature Set 12: Deployment Configuration Management** yang dikecualikan dari pengujian fungsional utama (BAB IV) dan direkomendasikan untuk dilakukan pada fase **Integration Testing** atau **System Testing**.

Alasan pengecualian dari blackbox testing utama telah dijelaskan di BAB IV.E. Bagian ini menyediakan skenario testing yang sesuai untuk validasi konfigurasi dan integrasi sistem.

---

## A. MAJOR FEATURE SET: DEPLOYMENT CONFIGURATION MANAGEMENT

### 1. Feature dalam Major Feature Set

| Feature Set | Feature | ID Fitur | Deskripsi |
|---|---|---|---|
| Generating Frontend Configuration | Membangkitkan daftar module project | F-12-01 | Auto-generate daftar module project berdasarkan struktur frontend aplikasi |
| | Membangkitkan konfigurasi URL | F-12-02 | Generate URL configuration untuk endpoints yang digunakan frontend |
| | Membangkitkan routing API Gateway | F-12-03 | Generate API Gateway route untuk frontend access |
| Generating Backend Configuration | Membangkitkan konfigurasi database | F-12-04 | Generate database connection config (host, port, database name, credentials) |
| | Membangkitkan konfigurasi object storage | F-12-05 | Generate MinIO/S3 storage config untuk file upload |
| | Membangkitkan routing API Gateway | F-12-06 | Generate API Gateway route untuk backend access |
| Generating DevOps Configuration | Membangkitkan database staging config | F-12-07 | Generate database config untuk environment staging |
| | Membangkitkan database production config | F-12-08 | Generate database config untuk environment production |
| | Membangkitkan storage config MinIO | F-12-09 | Generate MinIO storage config per environment |

---

## B. ALASAN INTEGRATION TESTING vs BLACKBOX TESTING

### 1. Perbedaan Karakteristik

| Aspek | Blackbox Testing (BAB IV) | Integration Testing (Lampiran C) |
|---|---|---|
| **Focus** | User behavior, business logic, feature functionality | System interaction, component integration, configuration validation |
| **Scope** | Single feature atau feature set isolated | Multiple components working together |
| **Input Validation** | User input via UI atau API parameters | Configuration files, environment variables, API responses antar service |
| **Output Verification** | User-observable output (UI display, message) | System state, configuration files, side effects (file creation, DB writes) |
| **Test Environment** | Simplified, dengan mock data | Realistic, dengan actual services (database, file storage, API gateway) |
| **Dependencies** | Single service (backend atau frontend) | Multiple services (backend, frontend, database, object storage, API gateway) |

### 2. Mengapa Deployment Config Management Masuk Integration Testing

**Feature Output yang Kompleks:**
- Generate config menghasilkan konfigurasi teknis (JSON, YAML, ENV) yang harus divalidasi struktur dan isinya dengan benar.
- Validasi memerlukan pengetahuan tentang schema konfigurasi masing-masing service (Laravel config schema, Vue.js config schema, API Gateway schema, dll.).

**Dependency pada Multiple Services:**
- Validasi konfigurasi generated seringkali memerlukan actual test di environment staging atau sandbox.
- Perlu verifikasi bahwa konfigurasi yang di-generate dapat di-read oleh service yang dituju.

**Non-Functional Output:**
- Output berupa file konfigurasi tidak observable oleh user melalui UI.
- Verifikasi memerlukan akses file system atau API khusus untuk retrieve generated config.

---

## C. SKENARIO INTEGRATION TESTING

### IT-12-01: Generate Frontend Configuration — Validasi Module List dan URL Config

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | IT-12-01 |
| **Major Feature Set** | Deployment Configuration Management |
| **Feature Set** | Generating Frontend Configuration |
| **Feature yang Diuji** | F-12-01, F-12-02 (Generate module list dan URL config) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam auto-generate konfigurasi frontend berdasarkan struktur aplikasi, meliputi: (1) Generate daftar module project (component, page, store), (2) Generate URL configuration (API base URL, endpoint URL), dan (3) Validasi struktur konfigurasi JSON/YAML yang di-generate. |
| **Prekondisi** | Aplikasi sudah ada dengan status `pengembangan` atau `siap_deploy`; sistem memiliki akses ke repository struktur frontend; konfigurasi template sudah terkonfigurasi di sistem. |
| **Langkah Pengujian** | 1. Dari halaman detail aplikasi atau admin panel konfigurasi, klik "Generate Frontend Config"<br>2. Sistem menganalisis struktur frontend dan menghasilkan konfigurasi<br>3. Konfigurasi ditampilkan dalam format JSON/YAML untuk preview<br>4. User dapat download atau copy konfigurasi<br>5. Validasi: Periksa struktur JSON/YAML dengan JSON schema validator<br>6. Verifikasi: Bandingkan modul yang di-generate dengan struktur actual di source code |
| **Data Pengujian** | Aplikasi ID: `16` (SIAP);<br>Expected modules: src/components, src/views, src/composables, src/stores, dst.<br>Expected endpoints: /api/aplikasi, /api/aplikasi/{id}, /api/login, dll. |
| **Hasil yang Diharapkan** | Sistem menghasilkan konfigurasi JSON yang valid, berisi semua module yang teridentifikasi di frontend, dan berisi URL endpoints yang benar sesuai backend API. |
| **Kriteria Penerimaan** | 1. Endpoint `GET /api/aplikasi/{id}/config/frontend` atau similar status 200 OK<br>2. Response body berupa valid JSON/YAML dengan struktur: `{ modules: [...], endpoints: [...], baseUrl: "..." }`<br>3. JSON dapat di-parse tanpa error<br>4. Daftar modules mencakup setidaknya 5+ items (components, views, stores, dll.)<br>5. Daftar endpoints mencakup minimal 10+ API endpoints |
| **Aktor/Role** | DevOps Developer, Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Perlu test environment dengan NodeJS untuk validasi frontend config; bisa menggunakan linting tool untuk validate JSON schema. |

---

### IT-12-02: Generate Backend Configuration — Validasi Database Config dan Kredensial

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | IT-12-02 |
| **Major Feature Set** | Deployment Configuration Management |
| **Feature Set** | Generating Backend Configuration |
| **Feature yang Diuji** | F-12-04, F-12-05 (Generate database dan object storage config) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam auto-generate backend configuration termasuk database credentials, host, port, database name, object storage config (MinIO host, access key, secret key), dan memverifikasi bahwa konfigurasi yang di-generate dapat be-parse oleh Laravel config loader. |
| **Prekondisi** | Aplikasi ada dengan status `pengembangan` atau lebih; environment template konfigurasi sudah tersimpan di sistem; akses ke database metadata (host, port, credentials template) tersedia. |
| **Langkah Pengujian** | 1. Dari admin panel atau devops dashboard, klik "Generate Backend Config"<br>2. Pilih environment: `staging` atau `production`<br>3. Sistem generate .env config atau Laravel config array<br>4. Preview konfigurasi yang akan di-generate<br>5. Download atau copy konfigurasi<br>6. Validasi struktur: Parse config menggunakan Laravel config parser<br>7. Verifikasi nilai-nilai: Database host, port, database name, credentials sesuai environment<br>8. Validasi MinIO config: Host, port, access key, secret key ter-fill dengan benar |
| **Data Pengujian** | Aplikasi ID: `16`; Environment: `staging`; Expected DB Host: `localhost` atau `staging-db.internal`; Expected DB Name: `simpa_staging`; MinIO Host: `minio-staging:9000`; Credentials: dari template yang dikonfigurasi |
| **Hasil yang Diharapkan** | Sistem menghasilkan .env file atau config array yang valid dan dapat langsung digunakan oleh Laravel untuk koneksi database dan object storage tanpa error. |
| **Kriteria Penerimaan** | 1. Endpoint `GET /api/aplikasi/{id}/config/backend?environment=staging` status 200<br>2. Response body berisi valid .env format atau config array<br>3. Config dapat di-parse oleh Laravel config loader tanpa error<br>4. Database connection test: Laravel dapat connect ke database menggunakan config yang di-generate<br>5. MinIO connection test: Laravel dapat authenticate ke MinIO menggunakan config yang di-generate<br>6. Tidak ada hardcoded password atau credential yang sensitive di config file (sudah disubstitute dengan env var reference) |
| **Aktor/Role** | DevOps Developer |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Perlu integration test environment dengan actual database dan MinIO service untuk verifikasi koneksi; recommend menggunakan Docker Compose untuk setup test environment. |

---

### IT-12-03: Generate API Gateway Configuration — Validasi Routing Rules

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | IT-12-03 |
| **Major Feature Set** | Deployment Configuration Management |
| **Feature Set** | Generating Frontend Configuration + Backend Configuration (Routing) |
| **Feature yang Diuji** | F-12-03, F-12-06 (Generate API Gateway routing) |
| **Deskripsi Pengujian** | Menguji kemampuan dalam auto-generate konfigurasi API Gateway (jika ada) atau reverse proxy configuration yang mendefinisikan routing rules untuk frontend dan backend, dan memverifikasi bahwa routing berfungsi correct saat di-apply ke API Gateway instance. |
| **Prekondisi** | Aplikasi ada; API Gateway atau reverse proxy (misal Nginx, Kong, AWS API Gateway) sudah tersedia di test environment; template routing rules sudah dikonfigurasi di sistem. |
| **Langkah Pengujian** | 1. Dari DevOps dashboard, klik "Generate API Gateway Config"<br>2. Sistem analyze aplikasi dan generate routing rules<br>3. Config berisi rules untuk: frontend static files, backend API routes, authentication endpoints, dll.<br>4. Preview config dalam format JSON atau YAML<br>5. Deploy config ke API Gateway test instance<br>6. Test: Send HTTP request ke API Gateway endpoint<br>7. Verifikasi routing: Request ter-forward ke correct backend/frontend service<br>8. Verifikasi headers: Auth headers, CORS headers sesuai config |
| **Data Pengujian** | Aplikasi ID: `16`; Frontend static path: `/app`, backend API path: `/api`; Expected routing: `/app/*` → frontend service, `/api/*` → backend service |
| **Hasil yang Diharapkan** | API Gateway config di-generate dengan benar, dan routing berfungsi proper saat di-deploy ke API Gateway instance. |
| **Kriteria Penerimaan** | 1. Endpoint `GET /api/aplikasi/{id}/config/apigw` status 200<br>2. Response body berisi valid API Gateway routing rules<br>3. Config dapat di-import ke API Gateway tool tanpa error<br>4. Test request ke `/app/` ter-route ke frontend, `/api/aplikasi` ter-route ke backend<br>5. Response headers (CORS, Authentication) sesuai config<br>6. Error responses (404, 403) handled sesuai policy |
| **Aktor/Role** | DevOps Developer |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Perlu test environment dengan actual API Gateway; recommend staging environment mirror dari production setup. |

---

### IT-12-04: Integration Test — End-to-End Configuration Flow

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | IT-12-04 |
| **Major Feature Set** | Deployment Configuration Management |
| **Feature Set** | All Feature Sets (End-to-End Configuration) |
| **Feature yang Diuji** | F-12-01 sampai F-12-09 (semua fitur generate config) |
| **Deskripsi Pengujian** | Menguji alur end-to-end complete: (1) User generate frontend config, (2) User generate backend config untuk staging, (3) User generate backend config untuk production, (4) User generate API Gateway config, (5) Deploy semua config ke staging environment, (6) Test aplikasi berfungsi normal di staging dengan config yang baru di-generate. |
| **Prekondisi** | Aplikasi ada dengan lengkap; staging dan production environments tersedia; deployment automation tools (CI/CD) sudah tersedia; monitoring/logging untuk staging environment aktif. |
| **Langkah Pengujian** | 1. DevOps user login ke sistem<br>2. Generate semua config (frontend, backend staging, backend production, API gateway)<br>3. Review semua generated configs<br>4. Deploy configs ke staging environment via automation script<br>5. Monitor deployment progress<br>6. Smoke test aplikasi di staging:<br>   a. Open aplikasi di browser<br>   b. Test login dengan staging credentials<br>   c. Test CRUD aplikasi (create, list, view, update, delete)<br>   d. Test file upload (dokumen)<br>   e. Verify database writes terrecord di staging DB<br>   f. Verify file uploads tersimpan di staging MinIO<br>7. Monitor application logs untuk errors<br>8. Verify semua endpoints accessible dan responding correctly |
| **Data Pengujian** | Aplikasi ID: `33` (TA-01); Environment: Staging; Test User: `tim_implementasi@example.com`; Test Data: Create aplikasi baru, upload dokumen, update status |
| **Hasil yang Diharapkan** | Aplikasi dapat di-deploy dengan generated config ke staging environment tanpa error, dan berfungsi normal dengan semua fitur accessible dan operational. |
| **Kriteria Penerimaan** | 1. Config generation untuk semua komponen sukses (status 200)<br>2. Deployment script berhasil apply configs (exit code 0)<br>3. Frontend accessible dan load normal (status 200, page load time < 3s)<br>4. Backend API responsive (status 200 untuk GET endpoints, 201 untuk POST)<br>5. Database operations berfungsi (insert, update, delete records)<br>6. File upload dan download berfungsi<br>7. No errors di application logs (error rate = 0%)<br>8. No connectivity issues (latency < 500ms) |
| **Aktor/Role** | DevOps Developer |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Test case ini adalah critical path untuk production deployment readiness; recommend dilakukan minimal 1x sebelum production release. |

---

## D. TEMPLATE RINGKASAN INTEGRATION TESTING

### Tabel C.1: Hasil Integration Testing — Deployment Configuration Management

| ID Pengujian | Deskripsi | Status | Notes |
|---|---|---|---|
| IT-12-01 | Generate Frontend Configuration | [ ] PASS [ ] FAIL [ ] BLOCKED | |
| IT-12-02 | Generate Backend Configuration | [ ] PASS [ ] FAIL [ ] BLOCKED | |
| IT-12-03 | Generate API Gateway Configuration | [ ] PASS [ ] FAIL [ ] BLOCKED | |
| IT-12-04 | End-to-End Configuration Flow | [ ] PASS [ ] FAIL [ ] BLOCKED | |
| **TOTAL** | **4 Test Cases** | **__ PASS, __ FAIL, __ BLOCKED** | **Success Rate: ___%** |

---

## E. KESIMPULAN LAMPIRAN

Pengujian integrasi untuk Deployment Configuration Management dirancang untuk memverifikasi bahwa sistem dapat menghasilkan konfigurasi yang valid dan siap deploy ke berbagai environment (staging, production) tanpa error.

Test cases di lampiran ini dapat dijalankan setelah pengujian fungsional (BAB IV) selesai dan sebelum melakukan deployment ke production.

---

**End of Appendix C**

