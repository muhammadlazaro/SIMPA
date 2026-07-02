# LAMPIRAN D: API TESTING — FITUR REGISTRASI PENGGUNA (Manual Testing)

---

## Pendahuluan

Fitur registrasi pengguna dan penetapan role tersedia sebagai **API endpoint** yang dilindungi dengan middleware role-based access control, tetapi **tidak memiliki user interface (UI) di aplikasi frontend**.

Karena blackbox testing hanya mencakup fitur yang accessible melalui UI yang user-facing, fitur registrasi pengguna dikeluarkan dari pengujian fungsional utama (BAB IV) dan didokumentasikan di Lampiran ini untuk **API Testing Manual** oleh administrator sistem.

---

## A. INFORMASI ENDPOINT REGISTRASI

### 1. Endpoint Details

| Aspek | Detail |
|---|---|
| **Endpoint** | `POST /api/register` |
| **Base URL** | `http://localhost:8000` |
| **Full URL** | `http://localhost:8000/api/register` |
| **Method** | POST |
| **Authentication** | Required (Bearer Token) |
| **Authorization** | `role:pengelola_aplikasi` (only Pengelola Aplikasi can register new users) |
| **Content-Type** | `application/json` |

### 2. Request Headers

```bash
Authorization: Bearer {valid_jwt_token_of_pengelola_aplikasi}
Content-Type: application/json
Accept: application/json
```

### 3. Request Body Format

```json
{
  "name": "string (required, max 255)",
  "email": "string (required, unique, email format, max 255)",
  "password": "string (required, min 8, letters, uppercase, lowercase, numbers, symbols)",
  "password_confirmation": "string (required, must match password)",
  "role": "enum (required, one of: pengelola_aplikasi, unit_kerja, analis_desain, tim_implementasi_aplikasi, devops_developer, tim_uji_keamanan)"
}
```

### 4. Response Format (Success - 201 Created)

```json
{
  "success": true,
  "data": {
    "id": 10,
    "name": "Nama Pengguna Baru",
    "email": "newuser@example.com",
    "role": "unit_kerja",
    "created_at": "2026-06-02T10:30:45.000000Z"
  },
  "message": "User created successfully"
}
```

### 5. Response Format (Error - 401 Unauthorized / 403 Forbidden)

```json
{
  "success": false,
  "message": "Unauthorized" | "Access Denied",
  "errors": {
    "field_name": ["error message 1", "error message 2"]
  }
}
```

---

## B. MANUAL TEST CASES

### AT-01: Register User dengan Valid Data

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-01 |
| **Test Type** | API Testing (Manual) |
| **Feature** | F-01-04, F-01-05 (Register pengguna, assign role) |
| **Description** | Test registrasi pengguna baru dengan semua field valid, oleh Pengelola Aplikasi |
| **Prerequisites** | Pengelola Aplikasi sudah login; JWT token tersedia |
| **Test Steps** | **Using Postman or cURL:**<br>1. Buka Postman atau terminal<br>2. Create POST request ke `http://localhost:8000/api/register`<br>3. Set Authorization header: `Bearer {pengelola_token}`<br>4. Set request body dengan JSON:<br>`{`<br>`  "name": "Tim Implementasi User",`<br>`  "email": "tim-impl@example.com",`<br>`  "password": "SecurePass123!",`<br>`  "password_confirmation": "SecurePass123!",`<br>`  "role": "tim_implementasi_aplikasi"`<br>`}`<br>5. Send request<br>6. Verify response |
| **Expected Result** | HTTP Status: **201 Created**<br>Response body berisi:<br>- `success`: true<br>- `data.id`: integer (user ID)<br>- `data.email`: "tim-impl@example.com"<br>- `data.role`: "tim_implementasi_aplikasi"<br>- `data.created_at`: timestamp |
| **Acceptance Criteria** | 1. Status code = 201<br>2. User berhasil dibuat di database<br>3. User dapat login dengan email & password yang baru<br>4. Role ditetapkan dengan benar<br>5. Audit log mencatat registrasi user |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Ganti `{pengelola_token}` dengan JWT token yang valid dari login Pengelola Aplikasi |

### AT-02: Register User dengan Email Duplikat

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-02 |
| **Test Type** | API Testing (Manual) |
| **Feature** | F-01-04 (Validasi email unique) |
| **Description** | Test validasi email unique — sistem harus menolak email yang sudah terdaftar |
| **Prerequisites** | User dengan email `unit@example.com` sudah ada di database |
| **Test Steps** | 1. Siapkan request body:<br>`{`<br>`  "name": "User Duplikat",`<br>`  "email": "unit@example.com",  // email sudah ada`<br>`  "password": "SecurePass123!",`<br>`  "password_confirmation": "SecurePass123!",`<br>`  "role": "unit_kerja"`<br>`}`<br>2. Send POST ke `/api/register` dengan auth header<br>3. Verify response |
| **Expected Result** | HTTP Status: **422 Unprocessable Entity**<br>Response body:<br>`{`<br>`  "success": false,`<br>`  "message": "Validation failed",`<br>`  "errors": {`<br>`    "email": ["Email sudah terdaftar / Email has already been taken"]`<br>`  }`<br>`}` |
| **Acceptance Criteria** | 1. Status code = 422<br>2. Error message specific untuk email<br>3. User baru TIDAK dibuat di database<br>4. Existing user dengan email tersebut tidak terpengaruh |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Verifikasi bahwa error message spesifik (bukan generic "validation failed") |

### AT-03: Register User dengan Password Lemah

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-03 |
| **Test Type** | API Testing (Manual) |
| **Feature** | F-01-04 (Validasi password strength) |
| **Description** | Test password strength validation — minimum 8 char, uppercase, lowercase, numbers, symbols |
| **Test Cases** | **TC-01**: Password terlalu pendek (< 8 char): `Pass1!`<br>**TC-02**: Password tanpa uppercase: `securepass123!`<br>**TC-03**: Password tanpa numbers: `SecurePass!`<br>**TC-04**: Password tanpa symbols: `SecurePass123` |
| **Test Steps** | 1. Untuk setiap test case, siapkan request dengan password berbeda<br>2. Send POST ke `/api/register`<br>3. Verify response |
| **Expected Result** | HTTP Status: **422 Unprocessable Entity**<br>Response body:<br>`{`<br>`  "success": false,`<br>`  "message": "Validation failed",`<br>`  "errors": {`<br>`    "password": ["Password must be at least 8 characters", "must contain uppercase letter", "must contain number", "must contain symbol"]`<br>`  }`<br>`}` |
| **Acceptance Criteria** | 1. Status code = 422<br>2. Error message menunjukkan requirement yang tidak terpenuhi<br>3. User TIDAK dibuat<br>4. Setiap validasi requirement di-enforce |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Verifikasi minimum semua 4 requirement diperlukan (8 char, uppercase, lowercase, number, symbol) |

### AT-04: Register User tanpa Authorization Header

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-04 |
| **Test Type** | API Testing (Manual) - Security |
| **Feature** | F-01-04 (Authentication & Authorization) |
| **Description** | Test bahwa endpoint registrasi memerlukan valid authentication token |
| **Test Steps** | 1. Siapkan request POST ke `/api/register` dengan valid body<br>2. **JANGAN** include Authorization header<br>3. Send request<br>4. Verify response |
| **Expected Result** | HTTP Status: **401 Unauthorized**<br>Response: `{"message": "Unauthenticated"}` atau similar |
| **Acceptance Criteria** | 1. Status code = 401<br>2. User TIDAK dibuat<br>3. Endpoint tidak accept unauthenticated request |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Security test — endpoint must require authentication |

### AT-05: Register User dengan Role yang Tidak Valid (Enum Mismatch)

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-05 |
| **Test Type** | API Testing (Manual) |
| **Feature** | F-01-05 (Validasi role enum) |
| **Description** | Test bahwa hanya role yang valid (enum) yang diterima sistem |
| **Test Steps** | 1. Siapkan request dengan role tidak valid:<br>`{`<br>`  "name": "User Test",`<br>`  "email": "test@example.com",`<br>`  "password": "SecurePass123!",`<br>`  "password_confirmation": "SecurePass123!",`<br>`  "role": "admin"  // invalid role`<br>`}`<br>2. Send POST ke `/api/register`<br>3. Verify response |
| **Expected Result** | HTTP Status: **422 Unprocessable Entity**<br>Response body:<br>`{`<br>`  "success": false,`<br>`  "errors": {`<br>`    "role": ["The selected role is invalid / The role must be one of: ..."]`<br>`  }`<br>`}` |
| **Acceptance Criteria** | 1. Status code = 422<br>2. Error message clear tentang role yang valid<br>3. User TIDAK dibuat<br>4. Hanya enum values yang diterima: pengelola_aplikasi, unit_kerja, analis_desain, tim_implementasi_aplikasi, devops_developer, tim_uji_keamanan |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Verifikasi list enum values yang diterima |

### AT-06: Register User sebagai Role yang Bukan Pengelola Aplikasi

| Aspek | Deskripsi |
|---|---|
| **ID Test** | AT-06 |
| **Test Type** | API Testing (Manual) - Authorization |
| **Feature** | F-01-04 (Authorization - middleware role:pengelola_aplikasi) |
| **Description** | Test bahwa hanya Pengelola Aplikasi yang bisa register user baru; role lain ditolak |
| **Prerequisites** | 2 user sudah terdaftar: (1) Pengelola dengan role `pengelola_aplikasi`, (2) Unit Kerja dengan role `unit_kerja` |
| **Test Steps** | **Scenario 1: Login sebagai Unit Kerja**<br>1. Login sebagai unit@example.com, dapatkan token unit_kerja<br>2. Try register user baru dengan token unit_kerja<br>3. Verify response dibuat dengan forbidden status<br><br>**Scenario 2: Login sebagai Analis Desain**<br>1. Login sebagai analis@example.com, dapatkan token analis_desain<br>2. Try register user baru dengan token analis_desain<br>3. Verify response dibuat dengan forbidden status |
| **Expected Result** | HTTP Status: **403 Forbidden**<br>Response: `{"message": "This action is unauthorized / You are not authorized to perform this action"}` |
| **Acceptance Criteria** | 1. Status code = 403<br>2. User TIDAK dibuat<br>3. Hanya Pengelola Aplikasi yang bisa register user<br>4. Semua role lain ditolak dengan 403<br>5. No security bypass (e.g., header tampering) |
| **Test Result** | [ ] PASS [ ] FAIL |
| **Notes** | Critical security test — verify authorization strictly enforced |

---

## C. CONTOH CURL COMMANDS

### Register dengan Valid Data
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Authorization: Bearer YOUR_PENGELOLA_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "User Baru",
    "email": "userbaru@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "unit_kerja"
  }'
```

### Register tanpa Authorization Header (Should Fail)
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "User Baru",
    "email": "userbaru@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "unit_kerja"
  }'
```

### Register dengan Email Duplikat (Should Fail)
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Authorization: Bearer YOUR_PENGELOLA_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Duplicate Email User",
    "email": "unit@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "unit_kerja"
  }'
```

---

## D. POSTMAN COLLECTION TEMPLATE

Anda juga bisa import Postman collection berikut untuk API testing yang lebih mudah:

```json
{
  "info": {
    "name": "SIMPA API — User Registration",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Register New User",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{pengelola_token}}"
          },
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Tim Implementasi User\",\n  \"email\": \"tim-impl@example.com\",\n  \"password\": \"SecurePass123!\",\n  \"password_confirmation\": \"SecurePass123!\",\n  \"role\": \"tim_implementasi_aplikasi\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/register",
          "host": ["{{base_url}}"],
          "path": ["api", "register"]
        }
      },
      "response": []
    }
  ]
}
```

**Setup Postman Variables:**
- `base_url` = `http://localhost:8000`
- `pengelola_token` = Valid JWT token dari login Pengelola Aplikasi

---

## E. RINGKASAN TEST RESULT

| ID Test | Description | Status |
|---|---|---|
| AT-01 | Register dengan valid data | [ ] PASS [ ] FAIL |
| AT-02 | Email duplikat | [ ] PASS [ ] FAIL |
| AT-03 | Password strength validation | [ ] PASS [ ] FAIL |
| AT-04 | No Authorization header | [ ] PASS [ ] FAIL |
| AT-05 | Invalid role enum | [ ] PASS [ ] FAIL |
| AT-06 | Non-pengelola authorization | [ ] PASS [ ] FAIL |
| **TOTAL** | **6 Test Cases** | **___ PASS, ___ FAIL** |

---

## F. CATATAN PENTING

1. **Saat Setup Awal**: Pengguna pertama kali harus di-create via backend command atau langsung di database, karena sistem butuh minimal 1 Pengelola untuk bisa register user lain.

2. **Future Enhancement**: Jika ada plan untuk menambahkan UI management user di dashboard Pengelola Aplikasi, test case ini dapat dipindahkan ke BAB IV Pengujian Fungsional utama.

3. **Security Considerations**:
   - Password harus di-hash menggunakan bcrypt atau similar sebelum disimpan
   - Email harus di-validate sebagai format email valid
   - Role harus di-validate terhadap enum yang terdaftar
   - Authorization middleware harus strictly check role
   - Audit log harus merekam semua registrasi user

---

**End of Appendix D — API Testing: User Registration**

