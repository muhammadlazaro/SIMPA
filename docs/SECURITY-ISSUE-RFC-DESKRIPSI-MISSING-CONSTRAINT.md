# SECURITY ISSUE REPORT: RFC deskripsi Field Missing Max Constraint

**Priority**: MEDIUM  
**Severity**: MEDIUM  
**Type**: Input Validation Gap  
**Status**: Resolved  
**Date**: June 4, 2026
**Resolved**: June 17, 2026

---

## Resolution

Field `deskripsi` di `StoreRfcRequest` dan `UpdateRfcRequest` sudah diberi validasi `max:5000`.
Regression test ditambahkan di `backend/tests/Feature/ApiContractAndAuthorizationTest.php` untuk memastikan payload `deskripsi` lebih dari 5000 karakter ditolak dengan HTTP 422.

---

## Issue Summary

Field `deskripsi` di RFC (Request For Change) tidak memiliki max constraint pada Form Request level. Ini memungkinkan input berukuran arbitrary yang bisa menyebabkan:
- Database bloat
- Performance degradation
- Potential DoS (Denial of Service)

---

## Affected Files

### 1. **StoreRfcRequest**
**Path**: `backend/app/Http/Requests/StoreRfcRequest.php`

```php
public function rules(): array {
    return [
        'aplikasi_id' => ['required','exists:aplikasis,id'],
        'tipe_rfc' => ['required','in:Medium,Standar,Minor,Major,Darurat'],
        'deskripsi' => ['nullable','string'],  // ❌ NO MAX CONSTRAINT
        'pelaksana' => ['required','in:Internal Pusdatik,Eksternal,Internal D13'],
        'status_tindaklanjut' => ['required','in:Analisa Desain,Dev-Staging,Production,UAT'],
    ];
}
```

**Line**: Baris 28  
**Current Rule**: `['nullable','string']`  
**Issue**: **TIDAK ADA `max:` constraint**

### 2. **UpdateRfcRequest**
**Path**: `backend/app/Http/Requests/UpdateRfcRequest.php`

```php
public function rules(): array {
    return [
        'aplikasi_id' => ['sometimes','required','exists:aplikasis,id'],
        'tipe_rfc' => ['sometimes','required','in:Medium,Standar,Minor,Major,Darurat'],
        'deskripsi' => ['nullable','string'],  // ❌ NO MAX CONSTRAINT
        'pelaksana' => ['sometimes','required','in:Internal Pusdatik,Eksternal,Internal D13'],
        'status_tindaklanjut' => ['sometimes','required','in:Analisa Desain,Dev-Staging,Production,UAT'],
    ];
}
```

**Line**: Baris 28  
**Current Rule**: `['nullable','string']`  
**Issue**: **TIDAK ADA `max:` constraint**

---

## Database Schema

**File**: `backend/database/migrations/2025_10_31_000100_create_rfcs_table.php`

```php
Schema::create('rfcs', function (Blueprint $table) {
    // ...
    $table->text('deskripsi')->nullable();  // ❌ Using TEXT type (unlimited size)
    // ...
});
```

**Issue**: 
- TEXT type di database memungkinkan unlimited size (up to 65,535 bytes per row)
- Tidak ada validation di application level
- Setiap RFC bisa menggunakan 65KB+ hanya untuk deskripsi

---

## Risk Analysis

### Potential Attack Vectors

1. **Database Bloat**
   - User mengirim 100 RFC dengan deskripsi 65KB each = 6.5MB data
   - Multiply dengan 1000 RFC = 65GB+ storage

2. **Performance Degradation**
   - Large TEXT fields memperlambat queries
   - Pagination becomes slower
   - Memory usage meningkat saat load data

3. **DoS (Denial of Service)**
   - Attacker bisa mengirim 10,000 large deskripsi requests
   - Server memory exhausted
   - Database locks up

### Comparison dengan Field Lain

| Field | Request Rule | Max | Type |
|:------|:-------------|:----:|:----:|
| `nama_layanan` | `required, string, max:255` | ✓ 255 | VARCHAR |
| `nama_singkat` | `required, string, max:10` | ✓ 10 | VARCHAR |
| `title` (checklist) | `required, string, max:255` | ✓ 255 | VARCHAR |
| `body` (note) | `required, string, max:5000` | ✓ 5000 | TEXT |
| `notes` (document) | `nullable, string, max:2000` | ✓ 2000 | TEXT |
| **`deskripsi` (RFC)** | **`nullable, string`** | **❌ NONE** | **TEXT** |

---

## Recommended Fix

### Option 1: Add max:5000 constraint (Recommended)
```php
// StoreRfcRequest::rules()
'deskripsi' => ['nullable', 'string', 'max:5000'],

// UpdateRfcRequest::rules()
'deskripsi' => ['nullable', 'string', 'max:5000'],
```

**Rationale**: Konsisten dengan field `body` di AplikasiNote yang juga TEXT type dengan max:5000

**Migration**: Tidak perlu (TEXT bisa menghandle 5000 chars easily)

### Option 2: Add max:2000 constraint (Conservative)
```php
'deskripsi' => ['nullable', 'string', 'max:2000'],
```

**Rationale**: Konsisten dengan field `notes` di AplikasiDocument dan StoreAplikasiChecklistRequest

---

## Implementation Steps

### Step 1: Update Form Requests

**File 1**: `backend/app/Http/Requests/StoreRfcRequest.php`

```diff
public function rules(): array {
    return [
        'aplikasi_id' => ['required','exists:aplikasis,id'],
        'tipe_rfc' => ['required','in:Medium,Standar,Minor,Major,Darurat'],
-       'deskripsi' => ['nullable','string'],
+       'deskripsi' => ['nullable','string','max:5000'],
        'pelaksana' => ['required','in:Internal Pusdatik,Eksternal,Internal D13'],
        'status_tindaklanjut' => ['required','in:Analisa Desain,Dev-Staging,Production,UAT'],
    ];
}
```

**File 2**: `backend/app/Http/Requests/UpdateRfcRequest.php`

```diff
public function rules(): array {
    return [
        'aplikasi_id' => ['sometimes','required','exists:aplikasis,id'],
        'tipe_rfc' => ['sometimes','required','in:Medium,Standar,Minor,Major,Darurat'],
-       'deskripsi' => ['nullable','string'],
+       'deskripsi' => ['nullable','string','max:5000'],
        'pelaksana' => ['sometimes','required','in:Internal Pusdatik,Eksternal,Internal D13'],
        'status_tindaklanjut' => ['sometimes','required','in:Analisa Desain,Dev-Staging,Production,UAT'],
    ];
}
```

### Step 2: Test

```bash
# Test dengan deskripsi > 5000 chars (should fail)
POST /api/rfc
{
    "aplikasi_id": 1,
    "tipe_rfc": "Medium",
    "deskripsi": "very long text...",  # 5001+ chars
    "pelaksana": "Internal Pusdatik",
    "status_tindaklanjut": "Analisa Desain"
}

# Expected: 422 Unprocessable Entity
# With error: "deskripsi" => ["Deskripsi maksimal 5000 karakter."]
```

### Step 3: Add Custom Error Message (Optional)

```php
public function messages(): array {
    return [
        'deskripsi.max' => 'Deskripsi maksimal 5000 karakter.',
    ];
}
```

---

## Testing Checklist

- [x] Add `max:5000` to both Form Requests
- [x] Test valid deskripsi (< 5000 chars) - should pass
- [ ] Test deskripsi exactly 5000 chars - should pass
- [x] Test deskripsi 5001+ chars - should fail with 422
- [ ] Verify error message displays correctly
- [ ] Test on both StoreRfcRequest and UpdateRfcRequest
- [x] Run existing tests - should all pass
- [ ] Manual API test with Postman/curl

---

## Additional Recommendations

### Consider similar audit for other fields

| Field | File | Current Rule | Recommendation |
|:------|:-----|:-------------|:----------------|
| `deskripsi` (RFC) | StoreRfcRequest | `string` | `string, max:5000` |
| `security_test_notes` (Aplikasi) | Controllers | No form request | Consider validation |
| `deployment_notes` (Aplikasi) | Controllers | No form request | Consider validation |

---

## References

- OWASP Input Validation: https://owasp.org/www-community/attacks/Command_Injection
- Laravel Validation: https://laravel.com/docs/validation#rule-max
- This finding aligns with OWASP A01:2021 - Broken Access Control (input constraint)
