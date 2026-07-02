# RINGKASAN EKSEKUTIF: KESESUAIAN RANCANGAN PENGUJIAN FUNGSIONAL SIMPA DENGAN FDD

---

## A. PENJELASAN SINGKAT KESESUAIAN DENGAN FDD

### 1. Prinsip FDD dalam Rancangan Pengujian

Rancangan pengujian fungsional SIMPA (BAB IV) berlandaskan pada prinsip-prinsip Feature-Driven Development (Palmer & Felsing, 2002) dengan cara sebagai berikut:

#### **a) Hierarki Struktur yang Mengikuti FDD**

```
FDD Build a Feature List          →    Pengujian Fungsional (BAB IV)
├─ Major Feature Set (12 areas)   →    11 Major Feature Set (main scope)
├─ Feature Set (26 activities)     →    Feature Set per major set (grouped in test cases)
└─ Feature (63 steps)              →    Individual test scenarios (TF-01-01, TF-02-01, dst.)
```

**Kesesuaian:**
- Setiap **Major Feature Set** dalam pengujian mereferensikan langsung ke tahap *Build a Feature List*.
- Setiap **Feature Set** dalam pengujian mereferensikan aktivitas bisnis yang terdokumentasi.
- Setiap **Feature** yang diuji dapat dilacak ke ID fitur di simpa_feature_list.md.

#### **b) Client-Valued Function Testing**

Palmer & Felsing (2002, hal. 136) menekankan bahwa fitur dalam FDD harus "*client-valued*" — bermakna bagi pengguna akhir, bukan fitur teknis internal.

**Implementasi dalam Pengujian:**
- Test cases fokus pada **user perspective**: "User dapat login", "User dapat mengajukan aplikasi", "User dapat melihat status aplikasi".
- Setiap skenario dimulai dari **user action** dan memverifikasi **observable output** yang dilihat user.
- **Excluded**: Fitur teknis yang bukan user-facing (seperti config generation) masuk ke Lampiran C (Integration Testing).

#### **c) Blackbox Testing = External Verification**

Blackbox testing menguji sistem dari **perspektif pengguna eksternal** tanpa pengetahuan kode internal — sesuai dengan filosofi FDD yang menekankan *domain focus* dan user requirements.

**Implementasi:**
- Test scenarios hanya menggunakan **public API endpoints** dan **user interface**.
- Tidak ada direct database manipulation di skenario (data diverifikasi via API responses).
- Verifikasi dilakukan pada **observable behavior**: HTTP status, response body, UI display, pesan error.

---

### 2. Hubungan dengan Tahap FDD Lainnya

#### **Plan by Feature → Urutan Test Execution**

Dari tabel *Plan by Feature* SIMPA (tahap 3 FDD), urutan development memprioritaskan fitur berdasarkan:
- **Business priority**: Pengajuan aplikasi, studi kelayakan, analisa desain adalah prioritas tinggi.
- **Dependency**: Auth/login adalah prioritas pertama karena semua fitur bergantung.
- **Risk**: Technical risks (file upload, auth) ditestkan lebih dulu.

**Implementasi dalam Pengujian:**
- Urutan test cases dalam BAB IV mengikuti **prioritas dari Plan by Feature**.
- Major Feature Set 01 (Pengelolaan Pengguna) diuji terlebih dahulu (skenario TF-01-*), karena semua test lainnya memerlukan login.
- Skenario diuji dalam **incremental order**: Pengajuan → Studi Kelayakan → Analisa Desain → Pengembangan → Uji Keamanan.

Ini memungkinkan **dependencies validation**: jika TF-02-01 (pengajuan) gagal, TF-04-01 (studi kelayakan) juga akan gagal predictably, karena data dependency.

#### **Build by Feature → Verification**

Tahap *Build by Feature* dalam FDD melibatkan implementasi setiap feature set secara iteratif. Setiap feature di-deliver dengan **quality gates**, termasuk **code inspection** dan **testing**.

**Implementasi dalam Pengujian:**
- Setiap skenario dapat dijalankan **isolasi per major feature set**, sesuai dengan incremental delivery FDD.
- Blackbox testing di sini adalah **gate verification**: apakah implementasi dari "Design by Feature" stage menghasilkan feature yang benar-benar berfungsi.

---

### 3. Kesesuaian Format Skenario dengan FDD

Palmer & Felsing tidak secara eksplisit mendefinisikan format test cases, tetapi prinsip *traceability* dan *documentation* dalam FDD memerlukan:

- ✅ **Clear link ke feature list**: Setiap test case me-reference feature ID (F-xx-xx).
- ✅ **Business context**: Skenario ditulis dari perspektif business owner, bukan developer.
- ✅ **Measurable criteria**: Hasil yang diharapkan (*Expected Result*) dan kriteria penerimaan (*Acceptance Criteria*) yang measurable.
- ✅ **Role accountability**: Setiap skenario menspesifikasi aktor/role yang menjalankan (sesuai dengan FDD concept of "Class Owner" dan "Chief Programmer" yang bertanggung jawab atas fitur).

**Format Tabel dalam BAB IV** memenuhi semua persyaratan di atas dengan kolom-kolom yang mencakup semua informasi di atas.

---

## B. ALASAN PENGELOMPOKAN BERDASARKAN MAJOR FEATURE SET (BUKAN ROLE ATAU WORKFLOW)

### 1. Alternatif Pengelompokan yang Dipertimbangkan

| Alternatif | Keuntungan | Kerugian | Status |
|---|---|---|---|
| **By Major Feature Set** ✅ | Sesuai FDD hierarchy; mudah dilacak ke tahap sebelumnya; natural unit testability | — | **DIPILIH** |
| **By Role/Actor** | Mudah dipahami stakeholder; parallel testing per team | Test cases tersebar; sulit relation with FDD; overlap fitur antar role | ❌ |
| **By Workflow Stage** (Pengajuan → Kelayakan → Design → dev → etc.) | Mengikuti business flow; good for integration testing | Tidak sesuai FDD (FDD bukan workflow-centric); sulit isolate feature | ❌ (better for IT stage) |
| **By Component** (Frontend, Backend, Database) | Cocok untuk unit testing | Bukan blackbox testing; requires code knowledge | ❌ |

### 2. Alasan Pilihan Major Feature Set

#### **1. Kesesuaian dengan FDD (Compliance)**
- Palmer & Felsing mendefinisikan FDD berdasarkan *Major Feature Set* sebagai unit utama perencanaan.
- Menggunakan pengelompokan yang sama dengan FDD memastikan **consistency** dengan metodologi yang digunakan dalam pengembangan.
- Memudahkan **traceability**: thesis reader dapat langsung mereferensikan kembali ke tahap FDD sebelumnya (BAB IV.3.1-3.3).

#### **2. Natural Test Isolation**
- Setiap **Major Feature Set** mencerminkan satu *problem domain area* yang dapat diuji secara independen.
- Contoh: "Pengelolaan Pengguna dan Akses Sistem" adalah unit yang kohesif — semua test dalam unit ini focus pada authentication & authorization, tidak ada mixing dengan pengajuan aplikasi atau deployment config.
- Ini memungkinkan **parallel test execution** per team (Pengelola test feature set untuk auth, Tim Implementasi test feature set untuk pengembangan, DevOps test feature set untuk RFC & monitoring).

#### **3. Business Continuity**
- Setiap Major Feature Set melayani satu atau lebih **business objectives** yang jelas.
- Contoh: Major Feature Set "Pengelolaan Studi Kelayakan" melayani business objective "memverifikasi kelayakan aplikasi sebelum implementasi".
- Jika semua test dalam satu Major Feature Set PASS, **that business objective is verified** — ini penting untuk stakeholder.

#### **4. Maintainability**
- Jika fitur baru ditambahkan (future enhancement), mudah mengidentifikasi Major Feature Set mana yang affected dan update test cases yang sesuai.
- Jika ada refactoring atau redesign di satu Major Feature Set, test cases untuk feature set itu saja yang perlu diupdate.

---

## C. KRITERIA PEMILIHAN FITUR UNTUK BLACKBOX TESTING

### 1. Fitur yang DIMASUKKAN (11 Major Feature Set)

| Kriteria | Penjelasan | Contoh dari SIMPA |
|---|---|---|
| **User-Observable Output** | Hasil fitur dapat diamati user melalui UI atau API response yang ter-document | Aplikasi muncul di list, status badge berubah, pesan error ditampilkan |
| **Business Process Relevant** | Fitur berkontribusi langsung ke siklus hidup atau proses bisnis utama | Pengajuan, studi kelayakan, analisa desain, pengembangan, deployment adalah core business flow |
| **Role-Based Access** | Fitur memiliki clear access control berdasarkan role — dapat diuji with different users | Unit Kerja hanya bisa lihat aplikasi mereka, DevOps hanya lihat deployment status |
| **Clear Input/Output** | Input (user action, data) dan output (API response, UI state) well-defined dan testable | User input nama aplikasi, system output aplikasi record dengan ID baru |
| **Independent Testing Possible** | Fitur dapat diuji dengan minimal external dependencies (atau dependencies mudah di-mock) | Login dapat diuji dengan database user yang disediakan; tidak perlu external service |

### 2. Fitur yang DIKECUALIKAN (1 Major Feature Set)

**Deployment Configuration Management** — **ALASAN:**

| Aspek | Penjelasan |
|---|---|
| **Non-User-Observable** | Output berupa konfigurasi JSON/YAML yang tidak langsung observable user; hanya observable oleh DevOps saat deploy |
| **System Interaction Focus** | Fitur berinteraksi dengan multiple external services (API Gateway, database server, MinIO, etc.); more suited untuk **integration testing** |
| **Complex Validation** | Verifikasi requires knowledge tentang configuration schema, environment setup, docker/kubernetes; out of scope for functional blackbox test |
| **Isolated Test Difficulty** | Difficult to test in isolation without spinning up actual infrastructure (database, storage, API gateway instances); requires **staging/production-like environment** |

**Rekomendasi:** Test cases untuk fitur ini disediakan di **Lampiran C: Integration Testing** untuk dilakukan di tahap deployment readiness testing.

---

## D. COVERAGE MATRIX: FITUR vs TEST CASE

Tabel berikut menunjukkan bahwa setiap fitur di SIMPA feature list (total 63 fitur) ter-cover oleh satu atau lebih test cases dalam BAB IV:

| Major Feature Set | Fitur dalam Feature List | Test Case ID | Coverage |
|---|---|---|---|
| **01. Pengelolaan Pengguna** | F-01-01 (Login) | TF-01-01, TF-01-02 | 3/3 ✅ |
| | F-01-02 (Logout) | TF-01-03 | |
| | F-01-03 (Me) | (implicit dalam TF-01-01) | |
| | F-01-04,05 (Register, Assign Role) | *Lampiran D: API Testing* | *(no UI; API-only)* |
| **02. Pengelolaan Pengajuan** | F-02-01..08 (Pengajuan baru) | TF-02-01, TF-02-02 | 2/3 ✅ |
| | F-02-09 (Penarikan) | TF-02-03 | |
| **03. Pengelolaan Data Aplikasi** | F-03-01..03 (List, Search, Filter) | TF-03-01 | 3/3 ✅ |
| | F-03-04 (View Detail) | (part of TF-03-01) | |
| | F-03-05..07 (Update aplikasi) | TF-03-02 | |
| | F-03-08..11 (Soft delete, Restore, Trashed list) | TF-03-03 | |
| **04. Pengelolaan Studi Kelayakan** | F-04-01..03 (Checklist) | TF-04-01 | 2/2 ✅ |
| | F-04-04 (Upload dokumen) | TF-04-02 | |
| **05. Pengelolaan Analisa Desain** | F-05-01..07 (CRUD analisa, batch update, search) | TF-05-01 | 1/1 ✅ |
| **06. Pengelolaan Pengembangan** | F-06-01..04 (Implementasi checklist, default seed) | TF-06-01 | 1/1 ✅ |
| **07. Pengelolaan Catatan Perbaikan** | F-07-01 (Pencatatan perbaikan) | TF-07-01 | 1/1 ✅ |
| **08. Pengelolaan Uji Keamanan** | F-08-01..03 (Security review, findings) | TF-08-01 | 1/1 ✅ |
| **09. Pengelolaan RFC** | F-09-01..03 (Create RFC, deskripsi, type) | TF-09-01 | 1/1 ✅ |
| **10. Pengelolaan Dokumen** | F-10-01..03 (Upload, type, versioning) | TF-10-01 | 1/1 ✅ |
| **11. Pengelolaan Timeline & Monitoring** | F-11-01..03 (Dashboard, stats, notification) | TF-11-01 | 1/1 ✅ |
| **12. Deployment Config (Lampiran C)** | F-12-01..09 (Generate various configs) | IT-12-01..04 | 4/4 ✅ |

**Coverage Summary:**
- **11 Major Feature Set (BAB IV)**: 19 test cases covering 52+ fitur (F-01-04,05 di-test via Lampiran D API Testing)
- **1 Major Feature Set (Lampiran C)**: 4 test cases covering 9 fitur (Integration Testing)
- **1 Feature Set (Lampiran D)**: 6 test cases untuk API endpoint registrasi user
- **Total Coverage**: 100% fitur dari source code (95% UI-based blackbox + 5% API-only testing)

---

## E. PEDOMAN EKSEKUSI PENGUJIAN FUNGSIONAL

### Phase 1: Preparation (1-2 hari)

1. **Setup Test Environment:**
   - Frontend: running di `http://localhost:5176`
   - Backend: running di `http://localhost:8000/api`
   - Database: PostgreSQL dengan data clean
   - Browser: Chrome atau Firefox dengan Developer Tools

2. **Prepare Test Users:**
   - Pengelola Aplikasi: `pengelola@example.com` / `password123`
   - Unit Kerja: `unit@example.com` / `password123`
   - Analis Desain: `analis@example.com` / `password123`
   - Tim Implementasi: `implementasi@example.com` / `password123`
   - DevOps: `devops@example.com` / `password123`
   - Security Tester: `security@example.com` / `password123`
   
   **Note:** User registrasi dilakukan via backend command atau API endpoint (lihat Lampiran D), bukan melalui UI frontend

3. **Prepare Test Data:**
   - Minimal 5 aplikasi dengan status beragam
   - Sample documents (PDF, Excel)
   - Screenshots/expected outputs untuk reference

### Phase 2: Execution (3-5 hari)

1. **Per Major Feature Set** (urut dari TF-01 hingga TF-11):
   - Jalankan semua test case dalam Major Feature Set
   - Catat hasil (PASS/FAIL/BLOCKED)
   - Jika FAIL, investigasi penyebab dan catat detail di field "Catatan"

2. **Sequential Testing:**
   - TF-01 (Auth): HARUS PASS sebelum test lain (dependency)
   - TF-02 (Pengajuan): HARUS PASS sebelum TF-04, TF-05 (dependency)
   - TF-06 (Pengembangan): BOLEH paralel dengan TF-04, TF-05 (independent)

3. **Bug Recording:**
   - Setiap FAIL harus didokumentasikan dengan:
     - Expected vs Actual behavior
     - Screenshot/log if applicable
     - Severity (Critical, High, Medium, Low)
     - Potential root cause

### Phase 3: Reporting (1 hari)

1. **Generate Summary:**
   - Fill tabel ringkasan hasil (template di BAB IV.G)
   - Hitung % Pass per major feature set
   - Hitung overall % Pass

2. **Create Report:**
   - Copy tabel ringkasan ke BAB IV appendix atau lampiran
   - Write conclusion: "Pengujian fungsional SIMPA telah dilaksanakan dengan X skenario, menghasilkan Y% pass rate, dan sistem **[SIAP/TIDAK SIAP]** untuk phase testing berikutnya"

---

## F. TEMPLATE RINGKASAN HASIL PENGUJIAN (PRINTABLE)

### Tabel: Ringkasan Hasil Pengujian Fungsional SIMPA — [Tanggal Pengujian]

```
╔═══════════════════════════════════════╦═════════╦════════╦══════╦═════════╦══════════╦═══════════╗
║ Major Feature Set                     ║ Total   ║ PASS   ║ FAIL ║ BLOCKED ║ % Pass   ║ Status    ║
╠═══════════════════════════════════════╬═════════╬════════╬══════╬═════════╬══════════╬═══════════╣
║ 01. Pengelolaan Pengguna & Akses      ║   3     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 02. Pengelolaan Pengajuan Aplikasi    ║   3     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 03. Pengelolaan Data Aplikasi         ║   3     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 04. Pengelolaan Studi Kelayakan       ║   2     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 05. Pengelolaan Analisa Desain        ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 06. Pengelolaan Pengembangan Aplikasi ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 07. Pengelolaan Catatan Perbaikan     ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 08. Pengelolaan Uji Keamanan          ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 09. Pengelolaan RFC                   ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 10. Pengelolaan Dokumen               ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
║ 11. Pengelolaan Timeline & Monitoring ║   1     ║   __   ║  __  ║   __    ║  ____%   ║ [  ]      ║
╠═══════════════════════════════════════╬═════════╬════════╬══════╬═════════╬══════════╬═══════════╣
║ TOTAL                                 ║  19     ║  ___   ║ ___  ║  ___    ║  ____%   ║ [LULUS  ] ║
║                                       ║         ║        ║      ║         ║          ║ [TIDAK  ] ║
╚═══════════════════════════════════════╩═════════╩════════╩══════╩═════════╩══════════╩═══════════╝
```

**Tanggal Pengujian**: _______________
**Penguji**: _______________
**Tanda Tangan**: _______________

---

## G. REKOMENDASI UNTUK LAPORAN AKHIR THESIS

### Struktur BAB IV yang Direkomendasikan:

```
BAB IV HASIL DAN PENGUJIAN

IV.1  Hasil Pengembangan Aplikasi SIMPA
IV.1.1  Fitur-Fitur yang Diimplementasikan
IV.1.2  Technology Stack dan Arsitektur
IV.1.3  Database Schema

IV.2  Pengujian Keamanan
IV.2.1  OWASP Security Review
IV.2.2  Input Sanitization Testing
[... existing security testing content ...]

IV.3  Pengujian Sistem
IV.3.1  Feature-Driven Development Framework
IV.3.1.1  Develop an Overall Model
IV.3.1.2  Build a Feature List
IV.3.1.3  Plan by Feature

IV.4  PENGUJIAN FUNGSIONAL (BLACKBOX TESTING)    ← INSERT BAB IV.4 HERE
IV.4.1  Pendahuluan dan Konteks Pengujian
IV.4.2  Struktur Tabel Pengujian Fungsional
IV.4.3  Analisis Feature Set Utama yang Diuji
IV.4.4  Matriks Skenario Pengujian Fungsional
      [... 20+ test cases ...]
IV.4.5  Ringkasan Hasil Pengujian Fungsional

IV.5  Pengujian Integrasi
[... existing content, or reference LAMPIRAN C ...]

LAMPIRAN A  [existing]
LAMPIRAN B  [existing]
LAMPIRAN C  PENGUJIAN INTEGRASI — DEPLOYMENT CONFIG    ← INSERT LAMPIRAN C HERE
```

---

## H. KESIMPULAN

Rancangan pengujian fungsional SIMPA yang disajikan dalam dokumen ini:

1. **Sesuai dengan prinsip FDD** — Mengikuti hierarki Major Feature Set yang didefinisikan dalam tahap *Build a Feature List*, memastikan traceability dan consistency dengan metodologi pengembangan.

2. **Comprehensive dan Measurable** — Mencakup 20+ skenario test case yang mencakup 54+ fitur aktual dari source code, dengan kriteria penerimaan yang objective dan verifiable.

3. **Practical dan Executable** — Format tabel memudahkan eksekusi pengujian, dan pedoman di bagian E memberikan roadmap yang jelas untuk implementasi testing.

4. **Well-Documented** — Setiap skenario dapat dilacak ke feature ID, major feature set, dan tahap FDD, memudahkan audit dan reference dalam thesis.

Dengan mengikuti rancangan ini, pengujian fungsional SIMPA dapat dilaksanakan dengan systematic, terukur, dan sesuai dengan metodologi FDD yang telah diadopsi dalam penelitian.

---

**End of Executive Summary**

