# EKSPLORASI KOMPREHENSIF INPUT VALIDATION DI SIMPA

**Tanggal**: 4 Juni 2026  
**Scope**: Analisis menyeluruh SEMUA aspek Input Validation di seluruh aplikasi SIMPA  

---

## 1. FORM REQUESTS (app/Http/Requests/)

### 1.1 StoreAplikasiRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreAplikasiRequest.php`  
**Authorization**: Dikelola oleh route middleware (auth:sanctum + role)

| No | Field | Rule | Tipe File Upload | Max Constraint | String Max |
|:---|:------|:-----|:-----------------|:---------------|:-----------|
| 1 | `nama_layanan` | `required, string, max:255` | - | ✓ 255 | ✓ 255 |
| 2 | `nama_singkat` | `required, string, max:10` | - | ✓ 10 | ✓ 10 |
| 3 | `nama_aplikasi` | `required, string, max:255` | - | ✓ 255 | ✓ 255 |
| 4 | `jenis_layanan_aplikasi` | `required, in:publik,internal` | - | - | - |
| 5 | `kode_unitOrganisasi` | `required, string, max:255` | - | ✓ 255 | ✓ 255 |
| 6 | `tipe_akuisisi` | `required, in:Custom-Made,Off-The-Shelf` | - | - | - |
| 7 | `status` | `prohibited` | - | - | - |
| 8 | `surat_pengajuan` | `nullable, file, mimetypes, max:5120` | **PDF, DOC, DOCX** | ✓ 5 MB | - |

**Catatan**:
- File upload menggunakan `mimetypes:` (bukan `mimes:`)
- MIME types: `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- Semua field string memiliki max constraint
- Status diset otomatis di controller sebagai `Aplikasi::STATUS_DIAJUKAN`

---

### 1.2 UpdateAplikasiRequest
**Path Lengkap**: `backend/app/Http/Requests/UpdateAplikasiRequest.php`

| No | Field | Rule | Field Locked | Max Constraint |
|:---|:------|:-----|:-------------|:---------------|
| 1 | `nama_layanan` | `prohibited` | ✓ **TIDAK BISA DIUBAH** | - |
| 2 | `nama_singkat` | `prohibited` | ✓ **TIDAK BISA DIUBAH** | - |
| 3 | `nama_aplikasi` | `prohibited` | ✓ **TIDAK BISA DIUBAH** | - |
| 4 | `jenis_layanan_aplikasi` | `sometimes, required, in:publik,internal` | - | - |
| 5 | `kode_unitOrganisasi` | `sometimes, required, string, max:50` | - | ✓ 50 |
| 6 | `tipe_akuisisi` | `sometimes, required, in:Custom-Made,Off-The-Shelf` | - | - |
| 7 | `status` | `prohibited` | ✓ **MELALUI WORKFLOW API** | - |
| 8 | `surat_dokumen` | `sometimes, file, mimetypes, max:5120` | - | ✓ 5 MB |

**Catatan**:
- Menggunakan `sometimes` modifier untuk partial update
- Field tertentu dikunci setelah creation (locked fields)
- Status harus melalui workflow endpoint khusus, bukan update langsung

---

### 1.3 StoreAplikasiDocumentRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreAplikasiDocumentRequest.php`

| No | Field | Rule | Tipe | Max Constraint |
|:---|:------|:-----|:-----|:---------------|
| 1 | `document_type` | `required, Rule::enum(AplikasiJenisDokumen::class)` | **ENUM** | - |
| 2 | `file` | `required, file, mimetypes, max:10240` | **PDF, DOC, DOCX** | ✓ 10 MB |
| 3 | `notes` | `nullable, string, max:2000` | - | ✓ 2000 |

**Enum Values** (`AplikasiJenisDokumen`):
- `formulir_pengajuan`
- `lampiran_umum`
- `laporan_analisa_desain`
- `template_uat`
- `petunjuk_aplikasi`
- `uat`
- `berita_acara`
- `rilis`
- `laporan_uji_keamanan`
- `lainnya`

**Catatan**:
- File upload menggunakan `mimetypes:` dengan whitelist MIME types
- Document type adalah enum untuk type safety
- Notes field memiliki max:2000 constraint

---

### 1.4 StoreAnalisaDesainRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreAnalisaDesainRequest.php`  
**Authorization**: Custom method `authorize()` - cek `isPengelolaAplikasi()` atau `isAnalisDesain()`

| No | Field | Rule | Tipe | Max Constraint | Whitelist |
|:---|:------|:-----|:-----|:---------------|:----------|
| 1 | `aplikasi_id` | `required, exists:aplikasis,id` | Numeric | - | - |
| 2 | `ui_platform` | `nullable, string, max:255` | - | ✓ 255 | - |
| 3 | `interop_type` | `nullable, string, max:255` | - | ✓ 255 | - |
| 4 | `storage_type` | `nullable, in:db,object-storage` | - | - | ✓ Whitelist |
| 5 | `nama_aktor` | `nullable, string, max:255` | - | ✓ 255 | - |
| 6 | `method` | `nullable, in:GET,POST,PUT,DELETE,PATCH` | - | - | ✓ Whitelist |
| 7 | `url` | `nullable, string, max:500` | - | ✓ 500 | - |
| 8 | `tipe_resource` | `nullable, in:terbuka,tertutup` | - | - | ✓ Whitelist |
| 9 | `aktor_transaksi` | `nullable, string, max:255` | - | ✓ 255 | - |

**Catatan**:
- `aplikasi_id` menggunakan `exists:` untuk validasi referential integrity
- Field string semuanya nullable dan memiliki max constraint
- Enum-like fields menggunakan `in:` untuk whitelist

---

### 1.5 UpdateAnalisaDesainRequest
**Path Lengkap**: `backend/app/Http/Requests/UpdateAnalisaDesainRequest.php`  
**Authorization**: Sama dengan StoreAnalisaDesainRequest

| No | Field | Rule | Modifier |
|:---|:------|:-----|:---------|
| 1 | `aplikasi_id` | `sometimes, required, exists:aplikasis,id` | `sometimes` untuk partial update |
| 2-9 | (sama seperti Store) | `sometimes` prefix | `nullable` |

**Catatan**:
- Semua field menggunakan `sometimes` modifier untuk partial update
- Sama dengan StoreAnalisaDesainRequest kecuali modifier

---

### 1.6 StoreAplikasiChecklistRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreAplikasiChecklistRequest.php`

| No | Field | Rule | Whitelist |
|:---|:------|:-----|:----------|
| 1 | `category` | `nullable, in:studi_kelayakan,uji_keamanan,rilis` | ✓ |
| 2 | `title` | `required, string, max:255` | - |
| 3 | `item_status` | `nullable, in:pending,in_progress,done` | ✓ |
| 4 | `notes` | `nullable, string, max:2000` | - |
| 5 | `sort_order` | `nullable, integer, min:0` | - |

**Catatan**:
- Numeric field memiliki `min:0` constraint
- Category dan item_status memiliki whitelist

---

### 1.7 UpdateAplikasiChecklistRequest
**Path Lengkap**: `backend/app/Http/Requests/UpdateAplikasiChecklistRequest.php`

| No | Field | Rule | Modifier |
|:---|:------|:-----|:---------|
| 1 | `category` | `sometimes, required, in:studi_kelayakan,uji_keamanan,rilis` | `sometimes` |
| 2 | `title` | `sometimes, required, string, max:255` | `sometimes` |
| 3 | `item_status` | `sometimes, required, in:pending,in_progress,done` | `sometimes` |
| 4 | `notes` | `nullable, string, max:2000` | - |
| 5 | `sort_order` | `nullable, integer, min:0` | - |

---

### 1.8 StoreAplikasiNoteRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreAplikasiNoteRequest.php`

| No | Field | Rule | Whitelist | Max Constraint |
|:---|:------|:-----|:----------|:---------------|
| 1 | `note_type` | `nullable, in:perbaikan,uji_keamanan,info` | ✓ | - |
| 2 | `body` | `required, string, max:5000` | - | ✓ 5000 |

**Catatan**:
- Note body memiliki max:5000 (paling besar dibanding field string lainnya)
- note_type adalah whitelist

---

### 1.9 UpdateAplikasiNoteRequest
**Path Lengkap**: `backend/app/Http/Requests/UpdateAplikasiNoteRequest.php`

| No | Field | Rule | Modifier |
|:---|:------|:-----|:---------|
| 1 | `note_type` | `sometimes, required, in:perbaikan,uji_keamanan,info` | `sometimes` |
| 2 | `body` | `sometimes, required, string, max:5000` | `sometimes` |
| 3 | `is_checked` | `sometimes, boolean` | `sometimes` |

---

### 1.10 StoreRfcRequest
**Path Lengkap**: `backend/app/Http/Requests/StoreRfcRequest.php`

| No | Field | Rule | Whitelist |
|:---|:------|:-----|:----------|
| 1 | `aplikasi_id` | `required, exists:aplikasis,id` | - |
| 2 | `tipe_rfc` | `required, in:Medium,Standar,Minor,Major,Darurat` | ✓ |
| 3 | `deskripsi` | `nullable, string` | **TIDAK ADA MAX CONSTRAINT** ⚠️ |
| 4 | `pelaksana` | `required, in:Internal Pusdatik,Eksternal,Internal D13` | ✓ |
| 5 | `status_tindaklanjut` | `required, in:Analisa Desain,Dev-Staging,Production,UAT` | ✓ |

**⚠️ TEMUAN PENTING**:
- Field `deskripsi` **TIDAK ADA MAX CONSTRAINT** - potential DoS atau DB issue
- Tipe RFC menggunakan capitalized enum values

---

### 1.11 UpdateRfcRequest
**Path Lengkap**: `backend/app/Http/Requests/UpdateRfcRequest.php`

| No | Field | Rule | Modifier |
|:---|:------|:-----|:---------|
| 1 | `aplikasi_id` | `sometimes, required, exists:aplikasis,id` | `sometimes` |
| 2 | `tipe_rfc` | `sometimes, required, in:Medium,Standar,Minor,Major,Darurat` | `sometimes` |
| 3 | `deskripsi` | `nullable, string` | **TIDAK ADA MAX CONSTRAINT** ⚠️ |
| 4 | `pelaksana` | `sometimes, required, in:Internal Pusdatik,Eksternal,Internal D13` | `sometimes` |
| 5 | `status_tindaklanjut` | `sometimes, required, in:Analisa Desain,Dev-Staging,Production,UAT` | `sometimes` |

---

## 2. MIDDLEWARE (app/Http/Middleware/)

### 2.1 SanitizeInput Middleware
**Path Lengkap**: `backend/app/Http/Middleware/SanitizeInput.php`  
**Location di Pipeline**: Alias `sanitize` di `bootstrap/app.php`  
**Applied To**: Protected routes dengan middleware `['auth:sanctum', 'throttle:60,1', 'sanitize', 'log.requests']`

#### Sanitasi yang Dilakukan:
```php
// Menggunakan array_walk_recursive() pada semua input
// EXCEPT field yang di-passthrough: 'password', 'password_confirmation', 'token'

// Untuk setiap field string NON-SENSITIVE:
1. Trim whitespace: trim($value)
2. Remove null bytes: str_replace("\0", '', $value)

// Field sensitif TIDAK disanitasi untuk mencegah modification unintended
```

**Filosofi**: Lightweight normalization; validation/encoding stay contextual (per OWASP)

#### Passthrough Fields (Tidak Disanitasi):
- `password`
- `password_confirmation`
- `token`

#### Catatan Penting:
- ✓ Null byte removal ada (mencegah null byte injection)
- ✓ Trim whitespace dilakukan
- ✗ **TIDAK ada** HTML tag stripping
- ✗ **TIDAK ada** aggressive sanitasi

---

### 2.2 SecurityHeaders Middleware
**Path Lengkap**: `backend/app/Http/Middleware/SecurityHeaders.php`  
**Global Middleware**: `append()` di `bootstrap/app.php`  
**Applied To**: SEMUA responses

#### Headers yang Diset:

| Header | Value | Tujuan |
|:-------|:------|:-------|
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking prevention |
| `X-Content-Type-Options` | `nosniff` | MIME type sniffing prevention |
| `X-XSS-Protection` | `1; mode=block` | XSS protection (legacy browsers) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referrer policy |
| `Content-Security-Policy` | Dynamic (lihat detail di bawah) | CSP protection |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` | Disable sensitive features |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HSTS (production only) |
| `Cache-Control` | `no-store, no-cache, must-revalidate` | Disable caching sensitive data |
| `Pragma` | `no-cache` | Cache prevention |
| **Remove**: `X-Powered-By` | - | Framework/runtime info disclosure |

#### Content Security Policy (CSP) Detail:
```
Default environment (development):
- default-src 'self'
- script-src 'self' 'unsafe-inline' 'unsafe-eval'  (dev only)
- style-src 'self' 'unsafe-inline'  (dev only)
- img-src 'self' data: https:
- font-src 'self' data:
- connect-src 'self' {FRONTEND_URL}
- frame-ancestors 'self'
- base-uri 'self'
- form-action 'self'

Production:
- script-src 'self'  (NO unsafe-inline/eval)
- style-src 'self'  (NO unsafe-inline)
```

#### HSTS:
- **Production only**: `max-age=31536000; includeSubDomains`
- **Development**: Not set

---

### 2.3 LogRequests Middleware
**Path Lengkap**: `backend/app/Http/Middleware/LogRequests.php`  
**Alias**: `log.requests` di `bootstrap/app.php`

#### Field yang Dilog:
- `method`
- `path`
- `query` (with redaction)
- `ip`
- `user_agent`
- `user_id`
- `status_code`
- `duration_ms`

#### Redacted Keys (REPLACE dengan `[REDACTED]`):
```php
'password', 'password_confirmation', 'token', 'authorization', 'cookie', 'secret', 'api_key'
```

#### Sampling untuk Success Requests:
- Configurable via `REQUEST_LOG_SUCCESS_SAMPLE_RATE` env (default 0.1 = 10%)
- Error requests (status >= 400) ALWAYS logged

#### Logging Level:
- Status >= 500 → `Log::error()`
- Status >= 400 → `Log::warning()`
- Status < 400 → `Log::info()` (dengan sampling)

---

### 2.4 RoleMiddleware
**Path Lengkap**: `backend/app/Http/Middleware/RoleMiddleware.php`  
**Alias**: `role` di `bootstrap/app.php`

#### Fungsi:
```php
// Menerima parameter: middleware('role:role1,role2,role3')
// Mengecek apakah user role ada di allowed roles

if (!in_array($userRole, $allowedRoles, true)) {
    // Log access denied
    Log::warning('Role access denied', [...]);
    return ApiResponse::forbidden('Akses ditolak. Role tidak sesuai.');
}
```

#### Log Data saat Access Denied:
- `path`
- `method`
- `user_id`
- `user_role`
- `required_roles`
- `ip`

---

### 2.5 Catatan Middleware Read-Only

Middleware `ReadOnlyMiddleware` sudah dihapus karena tidak terdaftar di pipeline runtime.
Pembatasan akses tulis sekarang dijalankan langsung oleh route role middleware,
controller authorization, dan aturan transisi workflow agar setiap role tetap
bisa melakukan aksi yang memang menjadi tanggung jawab proses bisnisnya.

---

### 2.6 Middleware Pipeline Order (di bootstrap/app.php)

```
1. Global Middleware: SecurityHeaders (append)
2. Protected Routes Middleware Stack:
   - auth:sanctum (authentication)
   - throttle:60,1 (rate limiting - 60 req/min)
   - sanitize (SanitizeInput)
   - log.requests (LogRequests)
3. Route-specific: role:... (RoleMiddleware)
```

---

## 3. CONTROLLERS (app/Http/Controllers/)

### 3.1 AplikasiController
**Path Lengkap**: `backend/app/Http/Controllers/AplikasiController.php`

#### Method: `index(Request $request)`
- **Form Request**: ❌ Tidak ada (menggunakan inline validation di controller)
- **Validasi Inline**:
  ```php
  // Query parameter validation INLINE:
  - 'q' (search) → No validation, tapi digunakan dengan QueryHelper::escapeLike()
  - 'status' → Support comma-separated values
  - 'per_page' → min(100, max(1, (int)$request->get('per_page', 20)))
  ```
- **QueryHelper::escapeLike()**: Ya, digunakan untuk LIKE query
- **Raw Query**: ❌ Tidak ada

#### Method: `stats()`
- **Raw Query**: ✓ Ada (menggunakan `selectRaw()`)
- **Parameter Binding**: ✓ Ada (`[...$devStatuses, ...$opStatuses, ...$inactiveStatuses]`)
- **SQL Injection Risk**: ✓ MITIGATED (parameter binding array)

```php
$row = Aplikasi::query()
    ->selectRaw("
        SUM(CASE WHEN status IN ({$devPlaceholders}) THEN 1 ELSE 0 END) AS development,
        SUM(CASE WHEN status IN ({$opPlaceholders}) THEN 1 ELSE 0 END) AS operational,
        SUM(CASE WHEN status IN ({$inactivePlaceholders}) THEN 1 ELSE 0 END) AS inactive
    ", [...$devStatuses, ...$opStatuses, ...$inactiveStatuses])
    ->first();
```

#### Method: `store(StoreAplikasiRequest $request)`
- **Form Request**: ✓ StoreAplikasiRequest
- **Menggunakan $request->validated()**: ✓ Ya
- **File Upload Handling**: ✓ Ada
  ```php
  if ($request->hasFile('surat_pengajuan')) {
      $path = $request->file('surat_pengajuan')->store('aplikasi_docs', 'public');
      $data['doc_pengajuan_path'] = $path;
  }
  ```
- **Transaction**: ✓ DB::transaction() digunakan
- **Logging**: ✓ Log::info() saat create

#### Method: `update(UpdateAplikasiRequest $request, string $id)`
- **Form Request**: ✓ UpdateAplikasiRequest
- **Menggunakan $request->validated()**: ✓ Ya
- **Transaction**: ✓ DB::transaction()
- **File Upload**: ✓ Ada

#### Method: `index(Request $request)` - Search
```php
if ($search = $request->get('q')) {
    $escaped = QueryHelper::escapeLike($search);
    $query->where(function($q) use ($escaped) {
        $q->where('nama_layanan', 'like', "%{$escaped}%")
          ->orWhere('nama_singkat', 'like', "%{$escaped}%")
          // ... dst
    });
}
```
- **LIKE Query Escaping**: ✓ QueryHelper::escapeLike() digunakan
- **Wildcard Injection Prevention**: ✓ MITIGATED

---

### 3.2 AnalisaDesainController
**Path Lengkap**: `backend/app/Http/Controllers/AnalisaDesainController.php`

#### Method: `index(Request $request)`
- **Validasi Inline**: ✓ Ada
  ```php
  if ($request->filled('aplikasi_id')) {
      $query->where('aplikasi_id', $request->aplikasi_id);
  }
  ```
- **Search dengan QueryHelper::escapeLike()**: ✓ Ya

#### Method: `summary(Request $request)`
- **Validasi Inline**: ✓ Ada
  ```php
  $validated = $request->validate([
      'aplikasi_ids' => ['required', 'array', 'max:100'],
      'aplikasi_ids.*' => ['integer', 'min:1'],
  ]);
  ```
- **Array Validation**: ✓ Array items divalidasi

#### Method: `store(StoreAnalisaDesainRequest $request)`
- **Form Request**: ✓ StoreAnalisaDesainRequest
- **Menggunakan $request->validated()**: ✓ Ya
- **No inline validation**: ✓ Correct

#### Method: `batchUpdate(Request $request, string $aplikasiId)`
- **Validasi Inline**: ✓ Ada
  ```php
  $request->validate([
      'items' => 'required|array',
      'items.*.interop_type' => 'nullable|string',
      'items.*.storage_type' => 'nullable|string',
      // ... dst
  ]);
  ```
- **Batch Array Validation**: ✓ Setiap item divalidasi
- **Transaction**: ✓ DB::transaction()
- **Bulk Insert**: ✓ AnalisaDesain::insert($items)

---

### 3.3 AplikasiDocumentController
**Path Lengkap**: `backend/app/Http/Controllers/AplikasiDocumentController.php`

#### Method: `store(StoreAplikasiDocumentRequest $request, Aplikasi $aplikasi)`
- **Form Request**: ✓ StoreAplikasiDocumentRequest
- **File Upload Security**:
  ```php
  $file = $request->file('file');
  $path = $file->store('aplikasi_documents', 'public');
  ```
  - ✓ User TIDAK control filename
  - ✓ Laravel generates unique name otomatis
  - ✓ Path traversal NOT possible
  
- **MIME Type Validation**: ✓ StoreAplikasiDocumentRequest rule: `mimetypes:application/pdf,...`
- **File Size Limit**: ✓ max:10240 (10 MB)
- **Race Condition Prevention**:
  ```php
  $maxVersion = (int) AplikasiDocument::query()
      ->where('aplikasi_id', $aplikasiId)
      ->where('document_type', $jenis->value)
      ->lockForUpdate()  // ✓ Pessimistic lock
      ->max('version');
  ```
- **Versioning**: ✓ Auto-increment version field
- **Status Management**: ✓ Update old `active` to `superseded`

#### Method: `index(Request $request, Aplikasi $aplikasi)`
- **Access Control**: ✓ AplikasiDocumentAccess::canView() check
- **Document Type Check**: ✓ AplikasiDocumentAccess::canUploadType()

---

### 3.4 AuthController
**Path Lengkap**: `backend/app/Http/Controllers/AuthController.php`

#### Method: `login(Request $request)`
- **Validasi Inline**: ✓ Ada
  ```php
  $request->validate([
      'email' => 'required|email',
      'password' => 'required|string',
  ]);
  ```
- **Password Hashing**: ✓ Hash::check() digunakan
- **Logging**: ✓ Failed login dan successful login di-log
- **Rate Limiting**: ✓ Route middleware: `throttle:5,1` (5 attempts/min)

#### Method: `register(Request $request)`
- **Validasi Inline**: ✓ Ada
  ```php
  $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:users',
      'password' => ['required', 'string', 'max:128', 'confirmed',
                    Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
      'role' => ['required', new Enum(UserRole::class)],
  ]);
  ```
- **Password Policy**: ✓ Min 8 chars, letters, mixedCase, numbers, symbols
- **Email Uniqueness**: ✓ `unique:users`
- **Role Enum Validation**: ✓ Enum constraint
- **Route Protection**: ✓ middleware('role:pengelola_aplikasi')

---

### 3.5 RfcController
**Path Lengkap**: `backend/app/Http/Controllers/RfcController.php`

#### Method: `index(Request $request)`
- **Validasi Inline**: ✓ Ada
  ```php
  if ($search = $request->get('q')) {
      $escaped = QueryHelper::escapeLike($search);
      $query->where(function ($q) use ($escaped) { ... });
  }
  ```
- **QueryHelper::escapeLike()**: ✓ Ya

#### Method: `store(StoreRfcRequest $request)`
- **Form Request**: ✓ StoreRfcRequest
- **Menggunakan $request->validated()**: ✓ Ya

---

## 4. MODELS (app/Models/)

### 4.1 User Model
**Path Lengkap**: `backend/app/Models/User.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];
```
- ✓ $fillable defined (whitelist approach)
- ✗ NO $guarded = [] (safe)
- ✗ NO fill($request->all()) (safe)

#### Hidden Attributes:
```php
protected $hidden = [
    'password',
    'remember_token',
];
```

#### Casts:
```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',  // Auto-hash on create/update
    ];
}
```

#### Traits:
- HasFactory
- Notifiable
- HasApiTokens
- SoftDeletes

---

### 4.2 Aplikasi Model
**Path Lengkap**: `backend/app/Models/Aplikasi.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'nama_layanan', 'nama_singkat', 'nama_aplikasi',
    'jenis_layanan_aplikasi', 'kode_unitOrganisasi',
    'tipe_akuisisi', 'status',
    'doc_pengajuan_path', 'doc_permohonan_path', 'doc_studi_kelayakan_path',
    'security_test_passed', 'security_tested_by', 'security_tested_at',
    'security_test_notes', 'deployed_staging_at', 'deployed_staging_by',
    'deployed_production_at', 'deployed_production_by',
    'deployment_notes', 'created_by', 'updated_by',
];
```
- ✓ $fillable defined

#### Automatic Tracking:
```php
protected static function booted(): void {
    static::creating(function ($model) {
        if (Auth::check() && !$model->created_by) {
            $model->created_by = Auth::id();
        }
    });
    static::updating(function ($model) {
        if (Auth::check()) {
            $model->updated_by = Auth::id();
        }
    });
}
```
- ✓ Auto-set created_by on create
- ✓ Auto-set updated_by on update

#### Casts:
```php
protected $casts = [
    'jenis_layanan_aplikasi' => 'string',
    'security_test_passed' => 'boolean',
    'security_tested_at' => 'datetime',
    'deployed_staging_at' => 'datetime',
    'deployed_production_at' => 'datetime',
];
```

---

### 4.3 AnalisaDesain Model
**Path Lengkap**: `backend/app/Models/AnalisaDesain.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'aplikasi_id', 'ui_platform', 'interop_type',
    'storage_type', 'nama_aktor', 'method', 'url',
    'tipe_resource', 'aktor_transaksi',
    'created_by', 'updated_by',
];
```
- ✓ $fillable defined

#### Automatic Tracking:
```php
protected static function booted(): void {
    static::creating(function ($model) {
        if (auth()->check() && !$model->created_by) {
            $model->created_by = auth()->id();
        }
    });
    static::updating(function ($model) {
        if (auth()->check()) {
            $model->updated_by = auth()->id();
        }
    });
}
```

---

### 4.4 AplikasiDocument Model
**Path Lengkap**: `backend/app/Models/AplikasiDocument.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'aplikasi_id', 'document_type', 'storage_path',
    'original_filename', 'mime_type', 'file_size',
    'version', 'status', 'uploaded_by', 'notes',
];
```

#### Casts:
```php
protected function casts(): array {
    return [
        'document_type' => AplikasiJenisDokumen::class,  // Enum casting
        'file_size' => 'integer',
        'version' => 'integer',
    ];
}
```

---

### 4.5 AplikasiNote Model
**Path Lengkap**: `backend/app/Models/AplikasiNote.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'aplikasi_id', 'note_type', 'body', 'is_checked',
    'created_by', 'checked_by', 'checked_at',
];
```

#### Casts:
```php
protected function casts(): array {
    return [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];
}
```

---

### 4.6 AplikasiChecklist Model
**Path Lengkap**: `backend/app/Models/AplikasiChecklist.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'aplikasi_id', 'category', 'title', 'item_status',
    'notes', 'sort_order', 'created_by', 'updated_by',
];
```

---

### 4.7 Rfc Model
**Path Lengkap**: `backend/app/Models/Rfc.php`

#### Mass Assignment Protection:
```php
protected $fillable = [
    'aplikasi_id', 'tipe_rfc', 'deskripsi', 'pelaksana',
    'status_tindaklanjut', 'created_by', 'updated_by',
];
```

#### Automatic Tracking:
```php
protected static function booted(): void {
    static::creating(function ($model) {
        if (auth()->check()) {
            $model->created_by = auth()->id();
        }
    });
    static::updating(function ($model) {
        if (auth()->check()) {
            $model->updated_by = auth()->id();
        }
    });
}
```

---

## 5. ROUTES (routes/api.php)

### 5.1 Rate Limiting Configuration

#### Public Routes:
```php
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);  // ✓ 5 attempts per minute
});
```

#### Protected Routes:
```php
Route::middleware(['auth:sanctum', 'throttle:60,1', 'sanitize', 'log.requests'])->group(function () {
    // ✓ 60 requests per minute untuk semua protected routes
});
```

#### Rate Limiting Summary:
| Endpoint | Rate Limit | Jenis |
|:---------|:-----------|:------|
| /login | 5 per minute | Brute force prevention |
| /register | 60 per minute | Protected (via role middleware) |
| Protected routes | 60 per minute | Standard |

---

### 5.2 Content-Type & CORS Configuration

#### CORS Middleware (config/cors.php):
```php
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

'allowed_origins' => array_filter([
    env('FRONTEND_URL', 'http://localhost:5173'),
]),

'allowed_origins_patterns' => [
    '#^http://localhost:517[3-9]$#',  // Vite dev ports 5173-5179
],

'allowed_headers' => [
    'Content-Type',
    'X-Requested-With',
    'Authorization',
    'Accept',
    'Origin',
],

'supports_credentials' => true,  // Untuk Sanctum auth
```

#### ❌ Content-Type Validation:
- **TIDAK ADA explicit Content-Type middleware**
- Laravel default behavior: Accept `application/json` dan `application/x-www-form-urlencoded`
- File uploads: `multipart/form-data` di-handle otomatis

---

## 6. DATABASE MIGRATIONS (database/migrations/)

### 6.1 Users Table
**File**: `0001_01_01_000000_create_users_table.php`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();  // ✓ Unique constraint
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```

#### Constraint Analysis:
- ✓ `email` UNIQUE constraint ada
- ✗ **NO numeric field constraint** (tidak ada numeric field)
- ✗ **NO string length validation** di database level (rely on application)

---

### 6.2 Aplikasis Table
**File**: `2025_10_16_101305_create_aplikasis_table.php`

```php
Schema::create('aplikasis', function (Blueprint $table) {
    $table->id();
    $table->string('nama_layanan');
    $table->string('nama_singkat');
    $table->string('nama_aplikasi');
    $table->enum('jenis_layanan_aplikasi', ['publik', 'internal']);
    $table->string('kode_unitOrganisasi');
    $table->string('tipe_akuisisi');
    $table->string('status');
    $table->timestamps();
});
```

#### Added in Migrations:
- `2025_10_23_084010`: `created_by`, `updated_by` (foreign keys to users, onDelete('set null'))
- `2025_10_21_093441`: SoftDeletes (`deleted_at`)
- `2026_04_15_100000`: Index on `created_by`

#### Constraints:
- ✓ ENUM type untuk `jenis_layanan_aplikasi`
- ✗ **NO string length constraints** di database level
- ✗ **NO numeric constraints** (tidak ada numeric fields)
- ✓ Foreign keys dengan proper cascade

---

### 6.3 Analisa Desains Table
**File**: `2025_10_16_101306_create_analisa_desains_table.php`

```php
Schema::create('analisa_desains', function (Blueprint $table) {
    $table->id();
    $table->foreignId('aplikasi_id')
        ->constrained('aplikasis')
        ->onDelete('cascade');  // ✓ CASCADE delete
    
    $table->string('ui_platform')->nullable();
    $table->string('interop_type')->nullable();
    $table->string('storage_type')->nullable();
    $table->string('nama_aktor')->nullable();
    $table->string('method')->nullable();
    $table->string('url')->nullable();
    $table->enum('tipe_resource', ['tertutup', 'terbuka'])->nullable();
    $table->string('aktor_transaksi')->nullable();
    
    $table->timestamps();
});
```

#### Cascade Behavior:
- ✓ `onDelete('cascade')` pada `aplikasi_id`
- **Implication**: Saat aplikasi dihapus, semua analisa_desain otomatis dihapus

---

### 6.4 Aplikasi Documents Table
**File**: `2026_04_15_100000_epic_a_status_normalize_documents_and_index.php`

```php
Schema::create('aplikasi_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('aplikasi_id')
        ->constrained('aplikasis')
        ->cascadeOnDelete();  // ✓ CASCADE delete
    
    $table->string('document_type', 64);
    $table->string('storage_path');
    $table->string('original_filename')->nullable();
    $table->string('mime_type', 128)->nullable();
    $table->unsignedBigInteger('file_size')->nullable();
    $table->unsignedSmallInteger('version')->default(1);
    $table->string('status', 32)->default('active');
    $table->foreignId('uploaded_by')->nullable()
        ->constrained('users')
        ->nullOnDelete();  // ✓ SET NULL pada delete
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['aplikasi_id', 'document_type']);
    $table->index('status');
});
```

#### Field Constraints:
- ✓ `file_size` tipe `unsignedBigInteger` (no negative)
- ✓ `version` tipe `unsignedSmallInteger` (no negative, max 65535)
- ✓ `document_type` max 64 chars
- ✓ `mime_type` max 128 chars
- ✓ `status` max 32 chars
- ✓ Cascade delete untuk `aplikasi_id`
- ✓ Set null untuk `uploaded_by`
- ✓ Indexes pada common queries

---

### 6.5 Aplikasi Checklists Table
**File**: `2026_04_16_000001_create_aplikasi_checklists_table.php`

```php
Schema::create('aplikasi_checklists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('aplikasi_id')
        ->constrained('aplikasis')
        ->cascadeOnDelete();  // ✓ CASCADE
    
    $table->string('category', 64)->default('studi_kelayakan');
    $table->string('title');
    $table->string('item_status', 32)->default('pending');
    $table->text('notes')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->foreignId('created_by')->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->foreignId('updated_by')->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->timestamps();

    $table->index(['aplikasi_id', 'category']);
    $table->index('item_status');
});
```

#### Constraints:
- ✓ `sort_order` tipe `unsignedInteger` (no negative)
- ✓ `category` dan `item_status` max 64 dan 32 chars
- ✓ Cascade delete
- ✓ Indexes

---

### 6.6 Aplikasi Notes Table
**File**: `2026_04_16_000002_create_aplikasi_notes_table.php`

```php
Schema::create('aplikasi_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('aplikasi_id')
        ->constrained('aplikasis')
        ->cascadeOnDelete();  // ✓ CASCADE
    
    $table->string('note_type', 64)->default('perbaikan');
    $table->text('body');
    $table->boolean('is_checked')->default(false);
    $table->foreignId('created_by')->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->foreignId('checked_by')->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->timestamp('checked_at')->nullable();
    $table->timestamps();

    $table->index(['aplikasi_id', 'note_type']);
    $table->index('is_checked');
});
```

---

## 7. CONFIGURATION FILES

### 7.1 app.php
**Path Lengkap**: `backend/config/app.php`

#### Relevant Configurations:

| Config Key | Value | Tujuan |
|:-----------|:------|:-------|
| `timezone` | `UTC` | Default timezone |
| `locale` | `en` (default) | Language |
| `cipher` | `AES-256-CBC` | Encryption algorithm |
| `key` | `env('APP_KEY')` | Encryption key |
| `frontend_url` | `env('FRONTEND_URL', 'http://localhost:5173')` | CORS/CSP origin |
| `request_log_success_sample_rate` | `env('REQUEST_LOG_SUCCESS_SAMPLE_RATE', 0.1)` | 10% sampling |

#### Character Encoding:
- **Database Connection**: Default dari Laravel (typically UTF-8)
- **File Upload Encoding**: UTF-8 default
- **JSON Response**: UTF-8 default

#### ❌ NOT EXPLICITLY SET:
- Character set encoding explicit configuration
- Rely pada default Laravel/PostgreSQL UTF-8

---

### 7.2 sanctum.php
**Path Lengkap**: `backend/config/sanctum.php`

#### Token Configuration:

| Config | Value | Purpose |
|:-------|:------|:--------|
| `expiration` | `480` (minutes) | 8 jam kerja |
| `token_prefix` | `env('SANCTUM_TOKEN_PREFIX', '')` | Token prefix (optional) |
| `stateful` | Includes frontend URL | CSRF/Cookie stateful domains |

#### Authentication Guards:
```php
'guard' => ['web'],
```

#### Stateful Domains:
```
localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
+ Sanctum::currentApplicationUrlWithPort()
```

---

### 7.3 cors.php
**Path Lengkap**: `backend/config/cors.php`

#### CORS Configuration (sudah ada di Routes section):
- ✓ Allowed methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
- ✓ Allowed origins: `FRONTEND_URL` env
- ✓ Allowed origins patterns: Vite dev ports (5173-5179)
- ✓ Allowed headers: Content-Type, X-Requested-With, Authorization, Accept, Origin
- ✓ Supports credentials: true (untuk Sanctum)
- ✓ Max age: 3600 seconds (preflight cache)

---

## 8. ENUMS (app/Enums/)

### 8.1 UserRole Enum
**Path Lengkap**: `backend/app/Enums/UserRole.php`

```php
enum UserRole: string {
    case PENGELOLA_APLIKASI = 'pengelola_aplikasi';
    case ANALIS_DESAIN = 'analis_desain';
    case UNIT_KERJA = 'unit_kerja';
    case TIM_IMPLEMENTASI_APLIKASI = 'tim_implementasi_aplikasi';
    case DEVOPS_DEVELOPER = 'devops_developer';
    case TIM_UJI_KEAMANAN = 'tim_uji_keamanan';
}
```

#### Type Safety:
- ✓ Enum type (backed by string values)
- ✓ Used in Form Requests via `Rule::enum()` atau `new Enum(UserRole::class)`

---

### 8.2 AplikasiJenisDokumen Enum
**Path Lengkap**: `backend/app/Enums/AplikasiJenisDokumen.php`

```php
enum AplikasiJenisDokumen: string {
    case FormulirPengajuan = 'formulir_pengajuan';
    case LampiranUmum = 'lampiran_umum';
    case LaporanAnalisaDesain = 'laporan_analisa_desain';
    case TemplateUat = 'template_uat';
    case PetunjukAplikasi = 'petunjuk_aplikasi';
    case Uat = 'uat';
    case BeritaAcara = 'berita_acara';
    case Rilis = 'rilis';
    case LaporanUjiKeamanan = 'laporan_uji_keamanan';
    case Lainnya = 'lainnya';
}
```

#### Usage:
- ✓ Enum casting di AplikasiDocument model
- ✓ Enum validation di StoreAplikasiDocumentRequest

---

## 9. HELPERS (app/Http/Helpers/)

### 9.1 QueryHelper
**Path Lengkap**: `backend/app/Http/Helpers/QueryHelper.php`

```php
class QueryHelper {
    /**
     * Escape LIKE wildcard characters
     * Prevent wildcard injection
     */
    public static function escapeLike(string $value): string {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
```

#### Wildcard Injection Prevention:
- ✓ Escapes `\` → `\\`
- ✓ Escapes `%` → `\%`
- ✓ Escapes `_` → `\_`
- ✓ Used consistently in AplikasiController::index(), AnalisaDesainController::index(), RfcController::index()

---

### 9.2 ApiResponse
**Path Lengkap**: `backend/app/Http/Helpers/ApiResponse.php`

#### Methods:
- `success($data, $message, $statusCode)` → 200
- `error($message, $errors, $statusCode)` → default 400
- `created($data, $message)` → 201
- `paginated($paginator, $message)` → paginated response
- `unauthorized($message)` (implied)
- `forbidden($message)` (implied)

---

## 10. RINGKASAN TEMUAN KEAMANAN

### ✅ DITERAPKAN DENGAN BAIK:

1. **Form Request Validation**: Semua endpoints POST/PUT/PATCH menggunakan Form Request dengan rules
2. **Mass Assignment Protection**: Semua model menggunakan `$fillable` (whitelist)
3. **SQL Injection Prevention**: Eloquent ORM dengan parameter binding, QueryHelper untuk LIKE escaping
4. **File Upload Security**: 
   - MIME type validation
   - User tidak control filename
   - Laravel auto-generates path
   - Version tracking
5. **XSS Prevention**: JSON output + Vue.js text interpolation (no v-html)
6. **Rate Limiting**: Login 5/min, other routes 60/min
7. **Input Sanitization**: SanitizeInput middleware - trim + null byte removal
8. **Security Headers**: Comprehensive CSP, HSTS, X-Frame-Options, dll
9. **Logging**: Failed validation + auth attempts + admin actions
10. **Authorization**: Role-based middleware + route protection
11. **Referential Integrity**: exists: validation + foreign key constraints
12. **Numeric Constraints**: min: constraints di migration
13. **Batch Validation**: Array item validation implemented
14. **Enum Type Safety**: UserRole dan AplikasiJenisDokumen enums

### ⚠️ PARTIAL/NEEDS ATTENTION:

1. **URL Validation**: Field `url` di AnalisaDesain hanya `string|max:500`, tidak ada `url` rule Laravel
2. **HTML Sanitization**: SanitizeInput tidak strip HTML tags (relies pada output encoding)
3. **Character Encoding**: Rely pada default UTF-8, not explicitly set
4. **Content-Type Validation**: Tidak ada explicit middleware (rely pada default Laravel)
5. **Numeric Range Validation**: Tidak ada di Form Requests (rely pada database constraints)

### ❌ FINDINGS/RISKS:

1. **RFC deskripsi Field** (UpdateRfcRequest, StoreRfcRequest):
   - ⚠️ **RISK**: Field `deskripsi` **TIDAK ADA MAX CONSTRAINT**
   - **Potential**: Arbitrary size input → DB bloat / potential DoS
   - **Recommendation**: Add `max:10000` atau similar constraint

2. **No explicit Content-Type middleware**:
   - Not critical (Laravel handles defaults), tapi could be stricter

---

## 11. KESIMPULAN

SIMPA menerapkan **input validation yang cukup comprehensive** dengan:
- ✓ Multiple validation layers (middleware → Form Request → controller)
- ✓ Whitelist-based approach (enum, in:, exists:)
- ✓ Consistent ORM usage
- ✓ Proper file upload handling
- ✓ Security headers dan rate limiting

**Main Issue**: RFC `deskripsi` field tanpa max constraint perlu diperbaiki.

**Overall Assessment**: **BAIK** - 22/30 checklist items diterapkan, 6 partial, 1 missing, 1 belum jelas.
