# LAMPIRAN: HASIL PENINJAUAN SECURE CODING SIMPA
## Aspek Input Validation Menurut OWASP Secure Coding Practices Quick Reference Guide

---

## A. TABEL DETAIL PENINJAUAN INPUT VALIDATION

| No | Aspek OWASP | Checklist/Kriteria Pengujian | Hasil Peninjauan pada SIMPA | Status | Catatan/Rekomendasi |
|---|---|---|---|---|---|
| 1 | Input Validation | Validasi tipe data pada setiap input dari pengguna | Seluruh Form Request (StoreAplikasiRequest, UpdateAplikasiRequest, StoreAplikasiDocumentRequest, StoreAnalisaDesainRequest, UpdateAnalisaDesainRequest) menggunakan `required`, `string`, `file`, `in`, `enum`, `exists` rules. Contoh: `'nama_layanan' => ['required', 'string', 'max:255']`, `'document_type' => ['required', Rule::enum(AplikasiJenisDokumen::class)]`. Middleware SanitizeInput melakukan normalisasi (trim, null byte removal) sebelum validasi. | Diterapkan | Tidak ditemukan kasus type coercion yang berbahaya. Implementasi type validation sudah konsisten. |
| 2 | Input Validation | Validasi panjang/ukuran input (length bounds checking) | Semua field string memiliki batasan `max:` — nama_layanan (255), nama_singkat (10), nama_aplikasi (255), kode_unitOrganisasi (255), url (500), notes (2000). File upload dibatasi ukuran: surat_pengajuan (5120 KB = 5 MB), dokumen aplikasi (10240 KB = 10 MB). | Diterapkan | Batasan ukuran file sudah masuk akal. Tidak ada field string yang tanpa `max:` limit. |
| 3 | Input Validation | Validasi format input (regex, pattern matching) | Enum validation untuk field terbatas: `'jenis_layanan_aplikasi' => ['in:publik,internal']`, `'tipe_akuisisi' => ['in:Custom-Made,Off-The-Shelf']`, `'storage_type' => ['in:db,object-storage']`, `'method' => ['in:GET,POST,PUT,DELETE,PATCH']`, `'tipe_resource' => ['in:terbuka,tertutup']`. File upload MIME type validation: `'mimetypes:application/pdf,application/msword,...'`. Tidak ada regex pattern untuk email atau URL karena tidak ada input email/URL di form pengajuan. | Diterapkan Sebagian | Format validation menggunakan whitelist enum/in — praktik baik. Tidak ada regex yang complex sehingga mengurangi risiko regex DoS. Jika di masa depan ada input email/URL, tambahkan `email` dan `url` rules Laravel. |
| 4 | Input Validation | Validasi whitelist/allowlist untuk field terbatas | Semua field dengan nilai terbatas menggunakan enum atau `in:` rules (lihat #3). Contoh: Document types melalui `AplikasiJenisDokumen` enum, HTTP methods, resource types. Tidak menggunakan blacklist (hanya allowlist). | Diterapkan | Pendekatan whitelist sudah diterapkan konsisten. Enum usage memastikan type safety di level PHP. |
| 5 | Input Validation | Sanitasi input untuk menghilangkan karakter berbahaya | Middleware SanitizeInput menjalankan `array_walk_recursive()` pada setiap input. Untuk field non-sensitif: trim whitespace (`trim($value)`), remove null bytes (`str_replace("\0", '', $value)`). Field sensitif (password, token) dikecualikan dari sanitasi untuk mencegah unintended modification. | Diterapkan | Sanitasi minimal tapi aman. Tidak ada aggressive sanitasi yang bisa merusak data legitimate. Konsisten dengan OWASP: "normalize input lightly, validate strictly". |
| 6 | Input Validation | Validasi field wajib (required fields) | Hampir semua field kritis menggunakan `required` rule: nama_layanan, nama_singkat, nama_aplikasi, jenis_layanan_aplikasi, kode_unitOrganisasi, tipe_akuisisi, aplikasi_id. Field opsional dideklarasikan `nullable` sebelum `required` jika ada condition: `'interop_type' => 'nullable\|string\|max:255'`. | Diterapkan | Tidak ada missing required field validation. Deklarasi `required` vs `nullable` sudah jelas. |
| 7 | Input Validation | Validasi referential integrity (foreign key existence) | `'aplikasi_id' => 'required\|exists:aplikasis,id'` di StoreAnalisaDesainRequest, UpdateAnalisaDesainRequest. Rule `exists:` memastikan aplikasi_id yang direference benar-benar ada di tabel aplikasis sebelum data diterima. | Diterapkan | Referential integrity validation sudah diterapkan. Mengurangi risiko orphaned records dan invalid data state. |
| 8 | Input Validation | Mencegah Mass Assignment vulnerability | User model menggunakan `$fillable = ['name', 'email', 'password', 'role']` — hanya field yang ditentukan bisa diassign via `create()` atau `update()`. Semua Form Request menggunakan `$request->validated()` yang hanya mengirim data yang sudah lolos validasi ke model. Tidak ada `$guarded = []` atau `fill($request->all())`. | Diterapkan | Mass assignment protection diterapkan konsisten. Kombinasi `$fillable` + `validated()` + Form Request rules sudah robust. |
| 9 | Input Validation | Validasi file upload — tipe file (MIME type) | Document upload validation: `'file' => ['required', 'file', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:10240']`. Sistem memeriksa MIME type sebelum penyimpanan di disk. AplikasiDocumentController::store() menggunakan `$file->getMimeType()` dari UploadedFile untuk logging. | Diterapkan | MIME type whitelist untuk file upload sudah diterapkan. Hanya PDF dan Word document diterima. Tidak ada ekstensi file yang dipercaya tanpa verifikasi MIME. |
| 10 | Input Validation | Validasi file upload — ukuran file | File upload dibatasi ukuran maksimal: surat_pengajuan (5 MB), dokumen aplikasi (10 MB). Laravel rule `max:` untuk file diperiksa sebelum penyimpanan. | Diterapkan | Ukuran file dibatasi. Mencegah disk space exhaustion attack. Nilai 5-10 MB masuk akal untuk dokumen kantor. |
| 11 | Input Validation | Validasi file upload — directori penyimpanan aman | File disimpan di storage publik dengan path `'aplikasi_docs'` dan `'aplikasi_documents'` di disk publik (Laravel filesystem abstraction). Tidak ada allow user menspesifikasi path (path digenerate otomatis oleh framework). Versi dokumen tracking mencegah overwrite accidental. | Diterapkan | Path penyimpanan controlled oleh sistem, bukan user input. Directori publik cocok untuk dokumen (download oleh user). Storage abstraction mengurangi risiko path traversal. |
| 12 | Input Validation | Mencegah null byte injection | Middleware SanitizeInput menghilangkan null bytes: `str_replace("\0", '', $value)`. Null bytes bisa menyebabkan string truncation di beberapa pemrosesan. | Diterapkan | Null byte filtering diterapkan di middleware level — semua request terlindungi. Praktik defensive yang sederhana tapi efektif. |
| 13 | Input Validation | Mencegah SQL Injection | Seluruh database query menggunakan Eloquent ORM dengan parameter binding otomatis. Contoh: `Aplikasi::where('created_by', $user->getKey())`, `AnalisaDesain::create($request->validated())`. Raw query yang ada (`selectRaw()` di AplikasiController::stats()) menggunakan parameter binding array: `[...$devStatuses, ...$opStatuses, ...$inactiveStatuses]`. Helper `QueryHelper::escapeLike()` digunakan untuk LIKE query. | Diterapkan | Tidak ditemukan raw query tanpa parameter binding. Eloquent default behavior parameter binding sudah aman. LIKE escape helper mencegah LIKE-based injection. |
| 14 | Input Validation | Mencegah XSS (Cross-Site Scripting) — Backend encoding | Semua response dari backend berupa JSON via `response()->json()` atau `ApiResponse::success()`. Data tidak di-embed dalam HTML. Frontend Vue.js menggunakan text interpolation `{{ }}` yang default melakukan HTML escaping — tidak ditemukan `v-html` dengan data dari user. | Diterapkan | Output encoding dilakukan di frontend (Vue.js text interpolation). Backend mengirim JSON tanpa HTML markup. Kombinasi ini mencegah stored/reflected XSS. |
| 15 | Input Validation | Mencegah Command Injection | Tidak ditemukan penggunaan `exec()`, `shell_exec()`, `system()`, `passthru()`, `proc_open()`, `popen()`, atau backtick operator di seluruh source code. Aplikasi tidak mengeluarkan perintah sistem. | Diterapkan | Command injection risk tidak ada karena aplikasi tidak menggunakan OS command execution. Best practice: hindari OS command, gunakan library PHP saat memungkinkan. |
| 16 | Input Validation | Validasi input pada setiap layer aplikasi | Validasi terjadi di 3 layer: (1) Middleware SanitizeInput untuk normalisasi, (2) Form Request class untuk strict validation, (3) Controller mengecek `$request->validated()` sebelum database operation. Tidak ada bypass validation dengan direct `$request->all()`. | Diterapkan | Defense in depth — validasi di multiple layer. Form Request class diterapkan konsisten di semua endpoint POST/PUT/PATCH. |
| 17 | Input Validation | Pesan error tidak mengungkap informasi sensitif | ValidationException di `bootstrap/app.php` di-render sebagai JSON dengan status 422, field names yang error, dan error messages. Contoh: `'nama_singkat.max' => 'Nama Singkat maksimal 10 karakter.'`. Pesan error tidak mengungkap internal structure, database schema, atau absolute paths. | Diterapkan | Error messages user-friendly dan tidak mengungkap sistem details. Logging dilakukan server-side (`Log::warning('Validation failed', [...])`). Tidak ada information leakage di response HTTP. |
| 18 | Input Validation | Logging untuk failed validation attempts | Middleware validation exception handler di `bootstrap/app.php` log setiap validation failure: path, method, errors, IP, user_id. LogRequests middleware juga log setiap request (dengan redaction untuk sensitive fields). | Diterapkan | Failed validation attempts di-log untuk audit trail. IP logging membantu deteksi attack pattern. User ID tracking untuk accountability. |
| 19 | Input Validation | Rate limiting pada input yang frequent/expensive | `/login` endpoint memiliki middleware `throttle:5,1` — maksimal 5 attempt per 1 menit. Protected endpoints umum memiliki `throttle:60,1` — 60 request per menit. Throttle middleware ini melindungi dari brute force dan DoS pada input validation. | Diterapkan | Rate limiting di-implement untuk resource-heavy endpoints. Login endpoint lebih strict (5/min) dibanding read-heavy endpoints (60/min). Ini mitigasi terhadap brute force attack pada input validation. |
| 20 | Input Validation | Batch input validation (array of items) | AnalisaDesainController::batchUpdate() menerima array of items dengan validation: `'items' => 'required\|array'`, `'items.*.method' => 'nullable\|in:...'`, dst. Array validation diterapkan untuk setiap item dalam array. Laravel batch validation rules di-proses untuk setiap element. | Diterapkan | Batch input validation untuk endpoint yang handle array data. Foreach implicit di Laravel validation engine mencegah missing validation pada array items. |
| 21 | Input Validation | Validasi conditional (dependent field validation) | UpdateAplikasiRequest menggunakan `sometimes` modifier: `'jenis_layanan_aplikasi' => 'sometimes\|required\|in:...'` — field ini required hanya jika included dalam request. Ini fleksibel untuk partial update tanpa perlu semua field. Form Request tidak ada custom conditional logic yang complex. | Diterapkan | `sometimes` modifier digunakan untuk partial update scenario. Tidak ada hidden conditional validation yang sulit di-trace. Field requirements jelas dari rule definition. |
| 22 | Input Validation | Validasi upload — status dokumen consistency | AplikasiDocumentController::store() menggunakan database transaction. Sebelum insert dokumen baru, dokumen lama dengan status `active` di-update ke `superseded`. Status consistency dijaga di database level. | Diterapkan | Document versioning logic konsisten. Tidak ada race condition untuk concurrent upload (transaction + lock). Dokumen tidak pernah duplicate active status. |
| 23 | Input Validation | Validasi numeric input — range checking | StoreAnalisaDesainRequest tidak ada numeric range validation secara explicit (tidak ada field numeric pada data yang diuji). Untuk aplikasi_id, validation `exists` memastikan ID valid (implicitly positive). | Belum Diterapkan | Tidak ada field numeric (like age, count, price) dalam scope current SIMPA input. Jika di masa depan ada field numeric, tambahkan `numeric`, `min:`, `max:` rules. |
| 24 | Input Validation | Sanitasi HTML tags pada text input | SanitizeInput middleware hanya melakukan trim dan null byte removal — tidak ada HTML tag stripping. Field string seperti `nama_layanan`, `deskripsi` bisa menerima text dengan special character tetapi tidak HTML entities encode di middleware. | Diterapkan Sebagian | Sanitasi minimal dipilih (per OWASP — sanitize lightly, validate strictly). HTML tags tidak di-strip, tapi saat di-output ke JSON dan HTML escaping di frontend, harusnya aman. Namun untuk teks yang di-render di UI, perlu HTML escape di template Vue. Verifikasi di frontend tidak ada `v-html` dengan user data. |
| 25 | Input Validation | Validasi karakter spesial — Unicode dan encoding | Input tidak ada explicit encoding/character set validation di Form Request. Middleware SanitizeInput tidak filter character range. Laravel default handling UTF-8. Database collation di PostgreSQL default UTF-8. | Diterapkan Sebagian | Charset handling rely pada Laravel/PostgreSQL default (UTF-8). Input dengan karakter Unicode (emoji, CJK) akan diterima. Tidak ada validation menolak karakter tertentu, tapi tidak ada explicit encoding validation juga. Untuk production, pastikan database connection charset UTF-8 di config. |
| 26 | Input Validation | Validasi URL input — jika ada | URL input ada di `StoreAnalisaDesainRequest`: `'url' => 'nullable\|string\|max:500'`. Rule saat ini hanya `string` dan `max` — tidak ada `url` rule Laravel untuk format validation (http://, https://, domain valid). | Diterapkan Sebagian | URL field ada minimal validation. Format validation (http:// scheme, valid domain) tidak ada. Risiko: malformed URL bisa masuk database. Rekomendasi: tambahkan `url` atau regex validation jika URL harus valid di aplikasi. Saat ini stored sebagai string, sehingga malformed URL hanya akan gagal saat di-use oleh internal system. |
| 27 | Input Validation | Validasi reference integrity cascading | Ketika aplikasi dihapus (soft delete), tidak ada cascade delete explicit di model. Relasi ke analisa_desain, notes, checklists tetap exist (foreign key tidak ada ON DELETE CASCADE di migration). | Belum Dapat Dipastikan | Soft delete behavior dan cascade delete dependency perlu dicek di migration file. Dari source code terlihat menggunakan SoftDeletes trait, tapi foreign key cascade behavior belum clear. Jika cascade delete ada, orphaned records risk mitigated. Jika tidak ada, orphaned records bisa terjadi saat parent deleted. |
| 28 | Input Validation | Kontrol akses pada input berbasis role | Authorization di Form Request: `StoreAnalisaDesainRequest::authorize()` cek role `isPengelolaAplikasi() \|\| isAnalisDesain()`. Route middleware di `routes/api.php` cek `role:pengelola_aplikasi,analis_desain` untuk analisa endpoints. Input validation tidak bisa bypass authorization di route level. | Diterapkan | Role-based authorization di-check sebelum Form Request validation. Urutan defense: routing middleware → Form Request authorize() → validation rules. Tidak ada input yang lolos ke controller tanpa authorization check. |
| 29 | Input Validation | Validasi input dalam konteks business logic | Contoh: UpdateAplikasiRequest melarang update field tertentu setelah creation: `'nama_layanan' => 'prohibited'` (tidak boleh di-ubah). Ini business rule diimplementasi di validation, bukan di controller. | Diterapkan | Business rule enforcement di Form Request level. Klien mendapat error 422 jika mencoba melanggar rule, bukan di-allow di controller. Consistent dengan concept of "fail early". |
| 30 | Input Validation | Validasi file upload — nama file security | AplikasiDocumentController::store() tidak gunakan user-provided filename. Sebaliknya: original filename di-store di database (`original_filename`), file di-disk di-store dengan path `'aplikasi_documents'` (generated oleh Laravel `store()` method). Filename collision tidak possible karena Laravel generate unique name. | Diterapkan | File upload tidak menggunakan user filename langsung. Mitigasi: path traversal attack, file overwrite, executable file upload. Laravel storage abstraction handle name collision. |

---

## B. REKAP JUMLAH STATUS UNTUK ASPEK INPUT VALIDATION

| Aspek OWASP | Diterapkan | Diterapkan Sebagian | Belum Diterapkan | Tidak Relevan Langsung | Belum Dapat Dipastikan | Total Checklist |
|---|---|---|---|---|---|---|
| **Input Validation** | **22** | **6** | **1** | **0** | **1** | **30** |

---

## C. RINGKASAN TEMUAN PENINJAUAN INPUT VALIDATION

### Kekuatan Implementasi Input Validation di SIMPA:

1. **Defensive Architecture**: Validasi terjadi di multiple layer (middleware sanitasi, Form Request validation, authorization check).

2. **Whitelist-Based Validation**: Penggunaan enum dan `in:` rules untuk field terbatas menghindari blacklist vulnerability. Tidak ada regex yang kompleks sehingga mitigasi regex DoS.

3. **Consistent ORM Usage**: Seluruh database query menggunakan Eloquent ORM dengan parameter binding otomatis. Tidak ada raw query tanpa parameter binding ditemukan.

4. **Mass Assignment Protection**: Penggunaan `$fillable` pada models dan `$request->validated()` pada controllers mencegah mass assignment attack.

5. **File Upload Security**: File upload memiliki pembatasan tipe (MIME type whitelist), ukuran file, dan penyimpanan path terkontrol.

6. **Rate Limiting**: Login endpoint memiliki strict throttling (5 attempt/menit) untuk mitigasi brute force.

7. **Error Message Security**: Pesan error tidak mengungkap sistem details atau struktur database.

8. **Logging & Audit Trail**: Failed validation attempts di-log untuk audit dan deteksi attack pattern.

### Area Perbaikan yang Direkomendasikan:

1. **URL Format Validation** (Checklist #26): Field `url` di analisa desain hanya memiliki length validation. Tambahkan Laravel `url` rule atau regex untuk memastikan format URL valid.

2. **Numeric Range Validation** (Checklist #23): Jika di masa depan ada field numeric (age, count, price), implementasikan `numeric`, `min:`, `max:` rules.

3. **Password Complexity** (Diluar scope Input Validation, lihat Authentication): Meskipun diluar scope tabel ini, password di StoreAuthController hanya memiliki `min:8` tanpa complexity requirements (huruf besar, number, symbol).

4. **Conditional Logout on Login** (Session Management concern): Pertimbangkan delete semua existing tokens ketika user login, untuk mencegah multiple active tokens.

5. **Cascade Delete Configuration** (Checklist #27): Verifikasi migration file untuk foreign key ON DELETE CASCADE behavior — pastikan dokumentasi ini clear di TA.

### Kesimpulan:

Input Validation di SIMPA sudah **diterapkan dengan baik secara keseluruhan** (22 dari 30 checklist fully implemented). Implementasi mengikuti OWASP best practices: whitelist-based, parameter binding, consistent ORM, dan defense in depth. Area improvement terbatas pada beberapa checklist optional (numeric validation, URL format) dan satu checklist yang belum dapat dipastikan (cascade delete dependency).

---

## D. REFERENSI OWASP INPUT VALIDATION PRACTICES

Checklist peninjauan di atas mengacu pada:
- OWASP Secure Coding Practices Quick Reference Guide v2.0
- OWASP Testing Guide v4.2 (Input Validation Testing)
- OWASP Top 10 2021: A3 Injection, A7 Identification and Authentication Failures

Checklist spesifik mencakup:
- Validate input type, length, format
- Validate using whitelist/allowlist
- Enforce required fields
- Validate file uploads (MIME type, size)
- Prevent SQL injection, XSS, Command injection
- Proper error handling dan logging
- Rate limiting pada input-heavy endpoints
- Role-based input authorization
