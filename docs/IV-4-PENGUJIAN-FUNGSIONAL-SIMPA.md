# BAB IV.4 PENGUJIAN FUNGSIONAL SIMPA (Blackbox Testing)

---

## A. Pendahuluan dan Konteks Pengujian

### 1. Tujuan Pengujian Fungsional

Pengujian fungsional (*blackbox testing*) SIMPA bertujuan untuk memverifikasi bahwa setiap fitur sistem berfungsi sesuai dengan spesifikasi yang telah ditetapkan pada tahap *Build a Feature List* dan *Plan by Feature* dalam metode Feature-Driven Development (FDD). Pengujian dilakukan dengan fokus pada **keluaran sistem** (*output*) dan **perilaku pengguna** (*user behavior*) tanpa menganalisis kode internal (*source code*).

### 2. Pengelompokan Pengujian Berdasarkan FDD

Berdasarkan struktur FDD yang telah disusun, pengujian fungsional SIMPA diorganisir menurut **12 Major Feature Set** (area domain) dan **26 Feature Set** (aktivitas bisnis). Setiap major feature set mengandung satu atau lebih feature set, dan setiap feature set mengandung fitur-fitur spesifik yang akan diuji.

Pengelompokan berdasarkan *major feature set* dipilih karena:
- **Konsistensi dengan FDD**: Mengikuti struktur hierarki yang sudah terdokumentasi dalam tahap *Build a Feature List* dan *Plan by Feature*.
- **Traceability**: Memudahkan pelacakan kembali hasil pengujian ke tahap analisis FDD sebelumnya.
- **Granularity yang tepat**: Cukup detail untuk pengujian komprehensif, namun tidak terlalu atomik sehingga sulit dikelola.
- **Role-based completeness**: Setiap major feature set dapat diuji secara independen oleh role yang relevan.

### 3. Cakupan Pengujian

Pengujian fungsional SIMPA mencakup **11 dari 12 major feature set**. Major feature set yang dikecualikan adalah:

| Major Feature Set | Alasan Pengecualian | Rekomendasi |
|---|---|---|
| **Deployment Configuration Management** | Fitur-fitur *auto-generation* konfigurasi teknis (generate database config, generate MinIO storage, dll.) merupakan **system interaction features** yang lebih cocok diuji pada *Integration Testing* atau *System Testing*, bukan *functional blackbox testing*. Pengujian memerlukan validasi struktur JSON/YAML yang kompleks dan simulasi lingkungan deployment. | Masukkan ke **Lampiran C: Skenario Pengujian Integrasi (Integration Testing)** |

Dengan demikian, **11 major feature set** akan masuk ke tabel pengujian fungsional utama di BAB IV.

---

## B. Rekomendasi Struktur Tabel Pengujian Fungsional

### Format Standar Skenario Pengujian

Setiap skenario pengujian fungsional disajikan dalam format tabel dengan kolom-kolom berikut:

| Kolom | Deskripsi | Catatan |
|---|---|---|
| **ID Pengujian** | Kode unik untuk setiap skenario, format: `TF-[FS]-[No]` | TF = Testing Fungsional; FS = kode major feature set (01-11); No = nomor urut (01, 02, 03, dst.) |
| **Major Feature Set** | Area domain sesuai dengan hierarki FDD | Merujuk pada tahap *Build a Feature List* |
| **Feature Set** | Aktivitas bisnis dalam major feature set | Merujuk pada tahap *Build a Feature List* |
| **Feature yang Diuji** | Daftar fitur spesifik dalam skenario ini | Referensi ke ID fitur di simpa_feature_list.md |
| **Deskripsi Pengujian** | Narasi singkat yang menjelaskan tujuan dan kondisi pengujian | Ditulis dalam gaya akademik, menjelaskan "apa yang ingin dibuktikan" |
| **Prekondisi** | Keadaan sistem sebelum skenario dijalankan | Contoh: pengguna sudah login, aplikasi sudah ada dengan status tertentu |
| **Langkah Pengujian** | Urutan langkah-langkah yang dilakukan tester secara sistematis | Penomoran jelas (1, 2, 3, dst.); setiap langkah adalah satu aksi pengguna |
| **Data Pengujian** | Input spesifik yang digunakan dalam pengujian | Contoh: nama aplikasi, format file, nilai boundary, dll. |
| **Hasil yang Diharapkan** | Output atau perilaku yang seharusnya terjadi | Ditulis sebagai assertion yang dapat diverifikasi |
| **Kriteria Penerimaan** | Kondisi objektif untuk menentukan PASS/FAIL | Contoh: "pesan validasi harus muncul dalam 2 detik", "jumlah record harus berkurang 1", dll. |
| **Aktor/Role** | Peran pengguna yang menjalankan skenario | Contoh: Unit Kerja, Pengelola Aplikasi, DevOps Developer |
| **Hasil Pengujian** | **PASS** / **FAIL** / **BLOCKED** | Diisi saat eksekusi pengujian |
| **Catatan** | Informasi tambahan, alasan FAIL, atau observasi | Opsional, tetapi penting untuk analisis jika terjadi kegagalan |

### Konvensi Penulisan

1. **Format ID Pengujian**: `TF-[FS]-[No]`
   - `TF-01-01`: Testing Fungsional, Major Feature Set 01 (Pengelolaan Pengguna dan Akses Sistem), Skenario 01
   - `TF-02-03`: Testing Fungsional, Major Feature Set 02 (Pengelolaan Pengajuan Aplikasi), Skenario 03
   - dst.

2. **Deskripsi Pengujian**: Menggunakan kalimat deklaratif yang menjelaskan tujuan pengujian
   - ✅ "Menguji kemampuan sistem dalam mengotentikasi pengguna dengan kombinasi email dan password yang valid."
   - ❌ "Test login"

3. **Langkah Pengujian**: Ditulis dari perspektif tester, sistematis dan dapat direplikasi
   - Setiap langkah adalah satu aksi (klik, input, submit, dll.)
   - Gunakan kedalaman detail yang sesuai dengan kompleksitas fitur

4. **Kriteria Penerimaan**: Measurable dan objektif
   - ✅ "Sistem menampilkan pesan sukses 'Login berhasil' dan mengalihkan ke dashboard dalam waktu ≤ 2 detik"
   - ❌ "Login berhasil" (terlalu vague)

---

## C. ANALISIS FEATURE SET UTAMA YANG PERLU DIUJI

### 1. Pemetaan 11 Major Feature Set ke Pengujian Fungsional

Berdasarkan tahap *Plan by Feature*, berikut adalah 11 major feature set yang masuk cakupan pengujian fungsional:

| No | Major Feature Set | Prioritas | Urutan Dev | Alasan Masuk Pengujian | Jumlah Skenario Direncanakan |
|---|---|---|---|---|---|
| 1 | Pengelolaan Pengguna dan Akses Sistem | Tinggi | 1 | Fondasi sistem — authentication & authorization mempengaruhi akses ke semua fitur lainnya | 3 |
| 2 | Pengelolaan Pengajuan Aplikasi | Tinggi | 2 | Entry point alur bisnis utama | 3-4 |
| 3 | Pengelolaan Data Aplikasi | Tinggi | 3 | CRUD penuh untuk data aplikasi | 4-5 |
| 4 | Pengelolaan Studi Kelayakan | Tinggi | 5 | Tahap kedua workflow bisnis utama | 3-4 |
| 5 | Pengelolaan Analisa Desain | Tinggi | 6 | Tahap ketiga workflow bisnis utama | 3-4 |
| 6 | Pengelolaan Pengembangan Aplikasi | Tinggi | 7 | Tahap keempat workflow bisnis utama + checklist tracking | 3-4 |
| 7 | Pengelolaan Catatan Perbaikan | Sedang | 7 | Mendukung pengelolaan perbaikan dalam workflow | 2-3 |
| 8 | Pengelolaan Uji Keamanan | Tinggi | 8 | Tahap kelima workflow bisnis utama | 3-4 |
| 9 | Pengelolaan RFC | Sedang | 10 | Fitur pendukung untuk manajemen perubahan | 2-3 |
| 10 | Pengelolaan Dokumen | Tinggi | 4 | Cross-cutting feature — diperlukan dalam studi kelayakan, UAT, deployment | 3-4 |
| 11 | Pengelolaan Timeline dan Monitoring | Sedang | 11 | Dashboard & notifikasi untuk semua aktor | 2-3 |

**Total Skenario Direncanakan**: 19 skenario pengujian fungsional (3 untuk auth, 3 untuk pengajuan, 3 untuk data, 2 untuk studi kelayakan, 1 untuk masing-masing feature set lainnya; TF-01-04 registrasi user → Lampiran D API Testing)

---

## D. MATRIKS SKENARIO PENGUJIAN FUNGSIONAL

### MAJOR FEATURE SET 01: PENGELOLAAN PENGGUNA DAN AKSES SISTEM

#### TF-01-01: Autentikasi Pengguna — Login dengan Kredensial Valid

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-01-01 |
| **Major Feature Set** | Pengelolaan Pengguna dan Akses Sistem |
| **Feature Set** | Autentikasi Pengguna |
| **Feature yang Diuji** | F-01-01 (Melakukan login ke sistem) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam mengotentikasi pengguna dengan kombinasi email dan password yang valid, serta memastikan sesi pengguna berhasil diinisialisasi. |
| **Prekondisi** | Pengguna belum login; data pengguna terdaftar di database dengan password terenkripsi; browser dalam keadaan fresh (tanpa cookie sesi sebelumnya). |
| **Langkah Pengujian** | 1. Buka halaman login di URL `http://localhost:5176/login`<br>2. Masukkan email pengguna di field "Email" (contoh: `pengelola@example.com`)<br>3. Masukkan password yang benar di field "Password" (contoh: `password123`)<br>4. Klik tombol "Login"<br>5. Tunggu hingga sistem memproses dan mengalihkan halaman |
| **Data Pengujian** | Email: `pengelola@example.com`; Password: `password123`; Role: `pengelola_aplikasi` |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan sukses "Login berhasil" atau sejenisnya, mengalihkan pengguna ke dashboard sesuai role (`/pengelola-aplikasi`), dan token autentikasi disimpan di local storage browser. |
| **Kriteria Penerimaan** | 1. Status HTTP response dari `POST /api/login` adalah 200 OK<br>2. Response body berisi token JWT dan data user (id, email, role)<br>3. URL berubah dari `/login` ke `/pengelola-aplikasi` dalam waktu ≤ 2 detik<br>4. Browser local storage menyimpan token dengan key `auth_token` atau sejenisnya |
| **Aktor/Role** | Pengelola Aplikasi (atau role lainnya) |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | — |

---

#### TF-01-02: Autentikasi Pengguna — Login dengan Password Salah

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-01-02 |
| **Major Feature Set** | Pengelolaan Pengguna dan Akses Sistem |
| **Feature Set** | Autentikasi Pengguna |
| **Feature yang Diuji** | F-01-01 (Melakukan login ke sistem) |
| **Deskripsi Pengujian** | Menguji mekanisme validasi password dan respons sistem terhadap input password yang tidak sesuai dengan data terdaftar. |
| **Prekondisi** | Email pengguna terdaftar di sistem; pengguna belum login; password yang akan diuji salah/tidak sesuai database. |
| **Langkah Pengujian** | 1. Buka halaman login<br>2. Masukkan email pengguna yang terdaftar (contoh: `pengelola@example.com`)<br>3. Masukkan password yang salah (contoh: `wrongpassword`)<br>4. Klik tombol "Login"<br>5. Amati respons sistem |
| **Data Pengujian** | Email: `pengelola@example.com` (valid); Password: `wrongpassword` (invalid) |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan error "Kredensial tidak valid" atau "Email atau password salah", dan pengguna tetap berada di halaman login tanpa menciptakan sesi baru. |
| **Kriteria Penerimaan** | 1. Status HTTP response dari `POST /api/login` adalah 401 Unauthorized<br>2. Response body berisi pesan error yang jelas dan informatif<br>3. Tidak ada token autentikasi yang disimpan di local storage<br>4. URL tetap di `/login` |
| **Aktor/Role** | Pengguna yang belum terautentikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi bahwa sistem tidak memberikan informasi sensitif (misalnya "Email tidak terdaftar" vs "Password salah") untuk mencegah user enumeration attack. |

---

#### TF-01-03: Autentikasi Pengguna — Logout dari Sistem

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-01-03 |
| **Major Feature Set** | Pengelolaan Pengguna dan Akses Sistem |
| **Feature Set** | Autentikasi Pengguna |
| **Feature yang Diuji** | F-01-02 (Melakukan logout dari sistem) |
| **Deskripsi Pengujian** | Menguji proses logout dan verifikasi bahwa sesi pengguna berhasil dihapus serta pengguna tidak dapat mengakses halaman yang memerlukan autentikasi. |
| **Prekondisi** | Pengguna sudah login ke sistem dan berada di dashboard (contoh: `/pengelola-aplikasi`); token autentikasi tersimpan di local storage. |
| **Langkah Pengujian** | 1. Dari dashboard, cari dan klik tombol "Logout" (biasanya di menu dropdown profil atau sidebar)<br>2. Amati pesan konfirmasi jika ada<br>3. Konfirmasi logout jika diminta<br>4. Tunggu hingga sistem memproses<br>5. Verifikasi halaman yang ditampilkan setelah logout |
| **Data Pengujian** | Pengguna yang sudah login; tidak ada input data tambahan diperlukan |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan "Logout berhasil" atau sejenisnya, mengalihkan pengguna ke halaman login, dan menghapus token autentikasi dari local storage. |
| **Kriteria Penerimaan** | 1. Status HTTP response dari `POST /api/logout` adalah 200 OK<br>2. Token autentikasi dihapus dari browser local storage<br>3. URL berubah ke `/login` dalam waktu ≤ 2 detik<br>4. Jika pengguna mencoba akses `/pengelola-aplikasi` secara langsung via URL bar, sistem mengalihkan ke `/login` |
| **Aktor/Role** | Semua role (Pengelola Aplikasi, Unit Kerja, Tim Implementasi, DevOps, dll.) |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga bahwa cookie atau session ID server juga dihapus untuk mencegah session fixation attack. |

---

---

### CATATAN: Fitur F-01-04 & F-01-05 (Registrasi Pengguna) — TIDAK DI-TEST DI BAB IV

**Status Implementasi**:
- ✅ **Backend**: Endpoint `POST /api/register` implemented dengan middleware protection `role:pengelola_aplikasi`
- ❌ **Frontend**: Tidak ada UI form untuk registrasi pengguna
- ❌ **Routes**: Tidak ada route `/register` atau halaman user management di frontend

**Alasan Pengecualian dari Blackbox Testing**:
Karena tidak ada user interface yang accessible dari aplikasi, fitur registrasi pengguna tidak dapat diuji melalui blackbox testing (yang mengharuskan fitur user-facing dan observable). Pengguna aplikasi tidak bisa mengakses fitur ini melalui menu atau form di antarmuka.

**Rekomendasi**:
1. ✅ **API Testing Manual** — Lihat **Lampiran D: API Testing untuk Registrasi Pengguna**
2. 💡 **Future Enhancement** — Implementasikan UI form registrasi di dashboard admin untuk memudahkan management user
3. 🔧 **Setup Saat Ini** — Pengguna baru di-setup melalui backend command atau direct API call oleh administrator sistem

**Endpoint Registrasi (untuk referensi administrator)**:
```bash
POST /api/register
Authorization: Bearer {pengelola_aplikasi_token}
Content-Type: application/json

{
  "name": "Nama Pengguna",
  "email": "user@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "unit_kerja" | "analis_desain" | "tim_implementasi_aplikasi" | "devops_developer" | "tim_uji_keamanan"
}
```

---

---

### MAJOR FEATURE SET 02: PENGELOLAAN PENGAJUAN APLIKASI

#### TF-02-01: Pengajuan Aplikasi Baru — Pengajuan Aplikasi oleh Unit Kerja dengan Data Lengkap

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-02-01 |
| **Major Feature Set** | Pengelolaan Pengajuan Aplikasi |
| **Feature Set** | Pengajuan Aplikasi Baru |
| **Feature yang Diuji** | F-02-01 hingga F-02-08 (Seluruh fitur pengajuan aplikasi baru) |
| **Deskripsi Pengujian** | Menguji alur lengkap pengajuan aplikasi baru oleh unit kerja, mulai dari pengisian form, pencatatan semua field wajib (nama layanan, nama singkat, nama aplikasi, jenis layanan, kode unit organisasi, tipe akuisisi), pengunggahan dokumen surat pengajuan, hingga penyimpanan data ke database dengan status `diajukan`. |
| **Prekondisi** | Pengguna sudah login dengan role `unit_kerja`; berada di dashboard atau page list aplikasi; database dalam keadaan bersih untuk aplikasi baru; sistem dapat menerima file upload (storage accessible). |
| **Langkah Pengujian** | 1. Klik tombol "Buat Pengajuan Baru" atau "Submit Aplikasi Baru"<br>2. Form pengajuan terbuka; isi field "Nama Layanan" dengan `Sistem Informasi Kepegawaian`<br>3. Isi field "Nama Singkat" dengan `SIAP` (maksimal 10 karakter)<br>4. Isi field "Nama Aplikasi" dengan `Sistem Informasi dan Administrasi Pegawai`<br>5. Pilih "Jenis Layanan" = `Publik`<br>6. Isi field "Kode Unit Organisasi" dengan `010101` atau pilih dari dropdown jika tersedia<br>7. Pilih "Tipe Akuisisi" = `Custom-Made`<br>8. Upload file surat pengajuan (format: PDF, ukuran: ≤ 5 MB)<br>9. Klik tombol "Submit" atau "Kirim Pengajuan"<br>10. Tunggu sistem memproses |
| **Data Pengujian** | Nama Layanan: `Sistem Informasi Kepegawaian`; Nama Singkat: `SIAP`; Nama Aplikasi: `Sistem Informasi dan Administrasi Pegawai`; Jenis Layanan: `Publik`; Kode Unit: `010101`; Tipe Akuisisi: `Custom-Made`; File: `surat_pengajuan_sample.pdf` (valid) |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan sukses "Pengajuan berhasil dibuat", form ditutup, halaman list aplikasi diperbarui dan menampilkan aplikasi baru dengan status `Diajukan` (`diajukan`), dan data tersimpan di database. |
| **Kriteria Penerimaan** | 1. Status HTTP response `POST /api/aplikasi` adalah 201 Created<br>2. Response body berisi ID aplikasi yang baru dibuat dan semua field yang diisi<br>3. Aplikasi baru muncul di list dengan status `Diajukan`<br>4. Database record menunjukkan status = `'diajukan'`, file tersimpan di storage, dan timestamp created_at terisi<br>5. Pengguna yang membuat pengajuan tercatat di field `pemohon_id` |
| **Aktor/Role** | Unit Kerja |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Jika ada field opsional, verifikasi juga bahwa form dapat submit tanpa mengisi field opsional. |

---

#### TF-02-02: Pengajuan Aplikasi Baru — Validasi Field Wajib (Negative Test)

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-02-02 |
| **Major Feature Set** | Pengelolaan Pengajuan Aplikasi |
| **Feature Set** | Pengajuan Aplikasi Baru |
| **Feature yang Diuji** | F-02-02 hingga F-02-08 (Field validation) |
| **Deskripsi Pengujian** | Menguji validasi input untuk form pengajuan aplikasi — sistem harus menolak pengajuan jika field wajib kosong atau mengisi nilai tidak valid, dan menampilkan pesan error yang jelas. |
| **Prekondisi** | Pengguna sudah login dengan role `unit_kerja`; form pengajuan aplikasi terbuka; browser siap untuk input. |
| **Langkah Pengujian** | **Test 1 — Nama Layanan Kosong:**<br>1. Buka form pengajuan<br>2. Kosongkan field "Nama Layanan"<br>3. Isi field lainnya dengan data valid<br>4. Klik "Submit"<br>5. Amati pesan error<br><br>**Test 2 — Nama Singkat Melebihi 10 Karakter:**<br>1. Buka form pengajuan<br>2. Isi "Nama Singkat" dengan `SISTEMINFO` (11 karakter)<br>3. Isi field lainnya dengan data valid<br>4. Klik "Submit"<br>5. Amati pesan error atau penolakan |
| **Data Pengujian** | Test 1 — Nama Layanan: (kosong); Test 2 — Nama Singkat: `SISTEMINFO` (11 char, invalid) |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan validasi yang spesifik untuk setiap error (misalnya "Nama Layanan wajib diisi" dan "Nama Singkat maksimal 10 karakter"), form tidak submit, dan data tidak tersimpan ke database. |
| **Kriteria Penerimaan** | 1. Validasi terjadi di frontend sebelum pengiriman ke server (jika implementasi menggunakan client-side validation)<br>2. Server juga melakukan validasi (server-side) dan mengembalikan status 422 Unprocessable Entity<br>3. Response body berisi error messages untuk setiap field yang invalid<br>4. Database record tidak bertambah |
| **Aktor/Role** | Unit Kerja |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Pastikan pesan error informatif dan dalam bahasa yang mudah dipahami pengguna. |

---

#### TF-02-03: Penarikan Pengajuan — Unit Kerja Menarik Pengajuan dalam Status Diajukan

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-02-03 |
| **Major Feature Set** | Pengelolaan Pengajuan Aplikasi |
| **Feature Set** | Penarikan Pengajuan |
| **Feature yang Diuji** | F-02-09 (Menarik kembali pengajuan yang belum diproses) |
| **Deskripsi Pengujian** | Menguji kemampuan unit kerja untuk menarik kembali pengajuan aplikasi yang masih dalam status `diajukan` (belum masuk tahap studi kelayakan), dan verifikasi bahwa pengajuan berhasil dihapus atau di-mark sebagai withdrawn. |
| **Prekondisi** | Aplikasi dengan status `diajukan` sudah ada di database; pengguna login dengan role `unit_kerja` yang merupakan pemilik aplikasi (pemohon); aplikasi belum masuk tahap berikutnya (status masih `diajukan`). |
| **Langkah Pengujian** | 1. Dari halaman list atau detail aplikasi dengan status `diajukan`, cari dan klik tombol "Tarik Pengajuan" atau "Withdraw"<br>2. Jika ada modal konfirmasi, klik "Ya" atau "Confirm"<br>3. Tunggu sistem memproses<br>4. Amati perubahan status atau penghapusan dari list |
| **Data Pengujian** | Aplikasi dengan ID `16` atau sesuai test data yang tersedia; Status saat ini: `diajukan`; User yang menarik: Unit Kerja yang membuat pengajuan |
| **Hasil yang Diharapkan** | Sistem menampilkan pesan sukses "Pengajuan berhasil ditarik", aplikasi dihapus dari list aplikasi aktif (soft delete atau status diubah), dan user dialihkan ke halaman list atau dashboard. |
| **Kriteria Penerimaan** | 1. Status HTTP response `DELETE /api/aplikasi/{id}/withdraw` adalah 200 OK atau 204 No Content<br>2. Aplikasi tidak lagi muncul di list aplikasi pengguna<br>3. Database: Aplikasi di-soft delete (deleted_at terisi) atau status diubah ke `withdrawn` jika ada<br>4. Audit log tercatat siapa dan kapan pengajuan ditarik |
| **Aktor/Role** | Unit Kerja (pemilik aplikasi) |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga bahwa unit kerja OTHER tidak bisa menarik pengajuan milik unit kerja lain (authorization check). |

---

### MAJOR FEATURE SET 03: PENGELOLAAN DATA APLIKASI

#### TF-03-01: Penelusuran Data Aplikasi — Pengguna Melihat Daftar Aplikasi Sesuai Role dan Permissions

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-03-01 |
| **Major Feature Set** | Pengelolaan Data Aplikasi |
| **Feature Set** | Penelusuran Data Aplikasi |
| **Feature yang Diuji** | F-03-01 (Menampilkan daftar seluruh aplikasi), F-03-02 (Mencari aplikasi berdasarkan kata kunci), F-03-03 (Memfilter aplikasi berdasarkan status) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam menampilkan daftar aplikasi dengan memperhitungkan role-based filtering — Pengelola Aplikasi melihat semua aplikasi, Unit Kerja hanya melihat aplikasi miliknya, Analis Desain melihat aplikasi dalam status tertentu, dst. Juga menguji fitur search dan filter by status. |
| **Prekondisi** | Minimal 5 aplikasi sudah ada di database dengan status beragam; pengguna dari berbagai role sudah terdaftar dan login; aplikasi memiliki relasi correct owner/pemohon. |
| **Langkah Pengujian** | **Test 1 — Pengelola Aplikasi Melihat Semua Aplikasi:**<br>1. Login dengan role `pengelola_aplikasi`<br>2. Buka halaman "Aplikasi" atau "Daftar Aplikasi"<br>3. Amati jumlah aplikasi yang ditampilkan (harus semua)<br>4. Klik dropdown filter "Status" dan pilih `Diajukan`<br>5. Amati daftar ter-filter<br>6. Masukkan kata kunci di search box, misal "SIAP"<br>7. Tekan Enter atau klik tombol search<br>8. Amati hasil pencarian<br><br>**Test 2 — Unit Kerja Hanya Melihat Aplikasi Miliknya:**<br>1. Logout dari pengelola, login dengan role `unit_kerja` (misal `unit@example.com`)<br>2. Buka halaman "Aplikasi"<br>3. Amati bahwa hanya aplikasi yang diajukan oleh unit kerja ini yang ditampilkan |
| **Data Pengujian** | Database berisi 5+ aplikasi; aplikasi dari unit kerja berbeda; keyword pencarian: "SIAP"; filter status: `diajukan`, `analisa_desain`, `pengembangan` |
| **Hasil yang Diharapkan** | (Test 1) Pengelola Aplikasi melihat semua aplikasi, filter dan search berfungsi correct. (Test 2) Unit Kerja hanya melihat aplikasi miliknya, bahkan jika mencoba bypass, sistem tetap menampilkan hanya aplikasi mereka. |
| **Kriteria Penerimaan** | 1. Pengelola: API response `GET /api/aplikasi` menampilkan semua records tanpa filter owner<br>2. Unit Kerja: API response ter-filter oleh `pemohon_id` atau `unit_id`<br>3. Filter status: query parameter `status=diajukan` menampilkan hanya aplikasi dengan status tersebut<br>4. Search: query parameter `q=SIAP` menampilkan aplikasi yang nama/nama_singkatnya mengandung "SIAP" |
| **Aktor/Role** | Pengelola Aplikasi, Unit Kerja, Analis Desain |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Pastikan pagination berfungsi jika ada banyak data; verifikasi sorting jika ada opsi sort. |

---

#### TF-03-02: Pembaruan Data Aplikasi — Pengelola Mengubah Status Aplikasi

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-03-02 |
| **Major Feature Set** | Pengelolaan Data Aplikasi |
| **Feature Set** | Pembaruan Data Aplikasi |
| **Feature yang Diuji** | F-03-08 (Mengubah status siklus hidup aplikasi) |
| **Deskripsi Pengujian** | Menguji kemampuan pengelola aplikasi dalam mengubah status siklus hidup aplikasi dari satu status ke status lain (misal dari `diajukan` menjadi `studi_kelayakan` atau `ditolak`), dan verifikasi bahwa perubahan status tersimpan dengan benar serta tercatat dalam audit log. |
| **Prekondisi** | Aplikasi dengan status `diajukan` sudah ada; pengguna login dengan role `pengelola_aplikasi`; tidak ada business rule yang melarang transisi status ini. |
| **Langkah Pengujian** | 1. Buka halaman list aplikasi atau detail aplikasi dengan status `diajukan`<br>2. Cari field "Status" dan klik untuk edit (atau klik tombol "Edit Status")<br>3. Pilih status baru dari dropdown, misal `studi_kelayakan` atau `ditolak`<br>4. Jika ada field tambahan (misal alasan penolakan), isi field tersebut<br>5. Klik tombol "Simpan" atau "Update Status"<br>6. Tunggu sistem memproses<br>7. Amati perubahan status di halaman |
| **Data Pengujian** | Aplikasi ID: `16` atau test data yang ada; Status lama: `diajukan`; Status baru: `studi_kelayakan` |
| **Hasil yang Diharapkan** | Sistem menyimpan perubahan status, halaman diperbarui menampilkan status baru, dan audit log mencatat perubahan (timestamp, user, old status, new status). |
| **Kriteria Penerimaan** | 1. Status HTTP response `PUT /api/aplikasi/{id}` adalah 200 OK<br>2. Response body menampilkan updated_at timestamp terbaru<br>3. Database field `status` berubah dari `diajukan` menjadi `studi_kelayakan`<br>4. Jika ada tabel audit, mencatat action "Update Status" dengan detail perubahan<br>5. Halaman list menampilkan status baru dalam badge warna yang sesuai |
| **Aktor/Role** | Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga business rule — apakah semua transisi status diperbolehkan atau ada pembatasan tertentu (misal `pengembangan` tidak bisa langsung ke `deployed_production` tanpa melalui `uji_keamanan`). |

---

#### TF-03-03: Pengarsipan Aplikasi — Soft Delete dan Restore

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-03-03 |
| **Major Feature Set** | Pengelolaan Data Aplikasi |
| **Feature Set** | Pengarsipan Aplikasi |
| **Feature yang Diuji** | F-03-09 (Menghapus/soft delete data aplikasi), F-03-10 (Memulihkan aplikasi yang dihapus), F-03-11 (Menampilkan daftar aplikasi yang dihapus) |
| **Deskripsi Pengujian** | Menguji fungsionalitas soft delete dan restore — pengguna dapat "menghapus" aplikasi (soft delete), aplikasi tidak lagi muncul di list utama tetapi dapat dilihat di list "Aplikasi Dihapus", dan pengguna dapat merestorasi aplikasi yang dihapus. |
| **Prekondisi** | Minimal 2 aplikasi ada di database; pengguna login dengan role `pengelola_aplikasi`; tidak ada soft delete yang pending. |
| **Langkah Pengujian** | **Test 1 — Soft Delete:**<br>1. Buka halaman list aplikasi<br>2. Cari aplikasi yang ingin dihapus, misal aplikasi dengan ID `15`<br>3. Klik tombol "Hapus" atau menu action yang menyediakan delete option<br>4. Jika ada konfirmasi modal, klik "Ya" atau "Confirm"<br>5. Tunggu sistem memproses<br>6. Amati aplikasi tidak lagi muncul di list utama<br><br>**Test 2 — Restore:**<br>1. Dari halaman list, cari menu "Aplikasi Dihapus" atau "Trash/Recycle Bin"<br>2. Klik menu tersebut; halaman menampilkan list aplikasi yang dihapus<br>3. Aplikasi yang baru dihapus harus muncul di list ini<br>4. Klik tombol "Restore" pada aplikasi tersebut<br>5. Klik "Ya" atau "Confirm" jika ada konfirmasi<br>6. Amati aplikasi dikembalikan ke list utama |
| **Data Pengujian** | Aplikasi ID: `15` (untuk soft delete); Alasan delete: (opsional, tergantung implementasi) |
| **Hasil yang Diharapkan** | (Test 1) Aplikasi tidak muncul di list utama tetapi `deleted_at` timestamp terisi di database. (Test 2) Aplikasi dapat dikembalikan, muncul lagi di list utama, dan `deleted_at` direset ke NULL. |
| **Kriteria Penerimaan** | 1. Soft Delete — `DELETE /api/aplikasi/{id}` status 200/204, record masih ada di database tapi `deleted_at` terisi<br>2. List utama: `GET /api/aplikasi` tidak menampilkan aplikasi yang sudah soft delete (query menggunakan `whereNull('deleted_at')`)<br>3. Restore — `POST /api/aplikasi/{id}/restore` status 200, `deleted_at` reset NULL<br>4. List Dihapus — `GET /api/aplikasi/trashed` menampilkan hanya aplikasi dengan `deleted_at` NOT NULL |
| **Aktor/Role** | Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga permission — apakah hanya pengelola yang bisa delete/restore, atau role lain juga bisa. |

---

### MAJOR FEATURE SET 04: PENGELOLAAN STUDI KELAYAKAN

#### TF-04-01: Verifikasi Pengajuan — Pengelola Menambahkan Checklist dan Mengubah Status Workflow

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-04-01 |
| **Major Feature Set** | Pengelolaan Studi Kelayakan |
| **Feature Set** | Verifikasi Pengajuan |
| **Feature yang Diuji** | F-04-01 (Menambahkan item checklist studi kelayakan), F-04-02 (Memperbarui status item checklist), F-04-03 (Menampilkan seluruh checklist dan catatan workflow) |
| **Deskripsi Pengujian** | Menguji alur verifikasi pengajuan — pengelola membuka detail aplikasi dalam status `diajukan`, melihat checklist studi kelayakan, menambahkan item checklist baru (atau edit item existing), mengubah status item ke "Selesai"/"Done", dan menambahkan catatan atau observasi. |
| **Prekondisi** | Aplikasi dengan status `diajukan` atau `studi_kelayakan` sudah ada; pengguna login dengan role `pengelola_aplikasi`; aplikasi detail page dapat diakses. |
| **Langkah Pengujian** | 1. Buka detail aplikasi dengan status `diajukan`<br>2. Navigasi ke tab "Studi Kelayakan" atau "Workflow"<br>3. Lihat checklist items yang sudah ada (jika ada default items)<br>4. Klik tombol "Tambah Item Checklist" atau "Add Checklist"<br>5. Input deskripsi checklist, misal "Verifikasi dokumen pengajuan"<br>6. Klik "Simpan"<br>7. Item baru muncul di list dengan status "Pending"<br>8. Klik checkbox atau tombol status pada item untuk menandai "Selesai"<br>9. Amati status berubah menjadi "Selesai" atau "Done"<br>10. Optional: Tambahkan catatan atau observasi di field "Catatan" |
| **Data Pengujian** | Aplikasi ID: `16`; Checklist item description: `Verifikasi kelengkapan dokumen pengajuan`; Status item: `Selesai` |
| **Hasil yang Diharapkan** | Checklist item tersimpan, status dapat diperbarui, dan sistem menampilkan progress (misal "3 dari 5 item selesai" atau progress bar). |
| **Kriteria Penerimaan** | 1. `POST /api/aplikasi/{id}/checklists` status 201 Created<br>2. `PUT /api/aplikasi/{id}/checklists/{checklist}` status 200 OK, status berubah<br>3. `GET /api/aplikasi/{id}/workflow` menampilkan semua checklist items dengan status terbaru<br>4. Database: checklist_item terisi dengan description, kategori `studi_kelayakan`, status |
| **Aktor/Role** | Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Jika ada default checklist items yang seharusnya muncul otomatis, verifikasi juga munculnya default items. |

---

#### TF-04-02: Pencatatan Hasil Kelayakan — Pengelola Mengunggah Dokumen Studi Kelayakan

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-04-02 |
| **Major Feature Set** | Pengelolaan Studi Kelayakan |
| **Feature Set** | Pencatatan Hasil Kelayakan |
| **Feature yang Diuji** | F-04-04 (Mengunggah dokumen studi kelayakan) |
| **Deskripsi Pengujian** | Menguji kemampuan pengelola aplikasi dalam mengunggah dokumen studi kelayakan (laporan kelayakan, rekomendasi, dll.), memverifikasi bahwa file tersimpan dengan benar, dan document path terrekam di database. |
| **Prekondisi** | Aplikasi dalam status `studi_kelayakan` sudah ada; pengguna login dengan role `pengelola_aplikasi`; file dokumen (PDF) siap untuk upload dengan ukuran valid (misal ≤ 10 MB). |
| **Langkah Pengujian** | 1. Buka detail aplikasi dengan status `studi_kelayakan`<br>2. Navigasi ke tab "Dokumen" atau "Studi Kelayakan"<br>3. Cari area upload dokumen studi kelayakan<br>4. Klik "Pilih File" atau "Browse" button<br>5. Pilih file `laporan_kelayakan_siap.pdf` dari sistem file<br>6. Tunggu preview atau validasi file di frontend<br>7. Klik tombol "Upload" atau "Simpan Dokumen"<br>8. Tunggu sistem memproses dan upload selesai<br>9. Amati file muncul di list dokumen dengan metadata (nama, tanggal, ukuran) |
| **Data Pengujian** | Aplikasi ID: `16`; File: `laporan_kelayakan_siap.pdf` (format: PDF, ukuran: 2 MB); Document type: `Laporan Studi Kelayakan` |
| **Hasil yang Diharapkan** | Dokumen berhasil diupload dan disimpan di storage (server file system atau cloud storage), document path tersimpan di database, dan file dapat didownload kembali. |
| **Kriteria Penerimaan** | 1. HTTP response `POST /api/aplikasi/{id}/upload-document` atau similar status 200/201<br>2. Response body berisi file path, file size, upload timestamp<br>3. File tersimpan di `/storage/app/aplikasi_docs/` atau configured storage path<br>4. Database field `doc_studi_kelayakan_path` terisi dengan path file<br>5. File dapat didownload dengan klik link download |
| **Aktor/Role** | Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Validasi juga: file size check, file type check (only PDF), virus scan jika ada, dan secure file naming untuk mencegah directory traversal. |

---

### MAJOR FEATURE SET 05: PENGELOLAAN ANALISA DESAIN

#### TF-05-01: Pencatatan Analisa Desain — Analis Desain Membuat Record Analisa Baru

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-05-01 |
| **Major Feature Set** | Pengelolaan Analisa Desain |
| **Feature Set** | Pencatatan Analisa Desain |
| **Feature yang Diuji** | F-05-01, F-05-02, F-05-03 (Create, update, delete analisa desain) |
| **Deskripsi Pengujian** | Menguji kemampuan tim analis desain dalam membuat record analisa desain baru untuk suatu aplikasi, mencakup pemilihan platform UI, tipe interoperabilitas, storage type, dan pembuatan transaction API record. |
| **Prekondisi** | Aplikasi dengan status `analisa_desain` atau `pengembangan` sudah ada; pengguna login dengan role `tim_analis_desain` atau `pengelola_aplikasi`; aplikasi belum memiliki analisa desain record. |
| **Langkah Pengujian** | 1. Buka detail aplikasi dalam status `analisa_desain`<br>2. Navigasi ke tab "Analisa Desain"<br>3. Klik tombol "Tambah Analisa Desain" atau "Create Analisa"<br>4. Form terbuka dengan field: Platform UI, Tipe Interoperabilitas, Storage Type, Aktor, HTTP Method, Endpoint URL, Resource Type<br>5. Isi field-field:<br>   - Platform UI: pilih `Web` atau `Mobile`<br>   - Tipe Interoperabilitas: pilih `REST API`<br>   - Storage Type: pilih `PostgreSQL`<br>   - Aktor: input `Admin` atau `User`<br>   - HTTP Method: pilih `GET`<br>   - Endpoint URL: input `/api/v1/users`<br>   - Resource Type: pilih `Data` atau `Service`<br>6. Klik tombol "Simpan"<br>7. Record analisa baru muncul di list |
| **Data Pengujian** | Aplikasi ID: `16`; Platform: `Web`; Interoperability: `REST API`; Storage: `PostgreSQL`; Actor: `Admin`; HTTP Method: `GET`; URL: `/api/v1/users`; Resource: `Data` |
| **Hasil yang Diharapkan** | Record analisa desain berhasil dibuat dan muncul di list analisa untuk aplikasi tersebut dengan semua field terisi. |
| **Kriteria Penerimaan** | 1. `POST /api/analisa-desain` status 201 Created<br>2. Response body berisi ID analisa baru dan semua field yang diisi<br>3. Record muncul di `GET /api/analisa-desain?aplikasi_id={id}`<br>4. Database: analisa_desain row baru dengan semua field terisi<br>5. Timestamp `created_at` terisi, `created_by_id` atau user info tercatat |
| **Aktor/Role** | Tim Analis Desain, Pengelola Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga dropdown options — apakah semua enum values untuk platform, interoperability, storage type, etc. sudah tersedia dan sesuai dengan enum di source code. |

---

### MAJOR FEATURE SET 06: PENGELOLAAN PENGEMBANGAN APLIKASI

#### TF-06-01: Pencatatan Progres Implementasi — Tim Implementasi Melihat dan Update Checklist Implementasi

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-06-01 |
| **Major Feature Set** | Pengelolaan Pengembangan Aplikasi |
| **Feature Set** | Pencatatan Progres Implementasi |
| **Feature yang Diuji** | F-06-01 (Menampilkan checklist progres), F-06-02 (Menambahkan item progres), F-06-03 (Memperbarui status item), F-06-04 (Membuat checklist default otomatis) |
| **Deskripsi Pengujian** | Menguji alur pencatatan progres implementasi — tim implementasi aplikasi dapat melihat checklist implementasi dengan item default (UI, API, UAT, Security), dapat mengubah status item dari "Pending" menjadi "In Progress" atau "Selesai", sehingga progress tracking terlihat jelas. |
| **Prekondisi** | Aplikasi dengan status `pengembangan` sudah ada; pengguna login dengan role `tim_implementasi_aplikasi`; halaman detail aplikasi accessible. |
| **Langkah Pengujian** | 1. Buka detail aplikasi dengan status `pengembangan`<br>2. Navigasi ke tab "Pengembangan" atau "Implementation Checklist"<br>3. Amati checklist items default yang muncul (misal: "UI Development", "API Development", "UAT Preparation", "Security Review")  <br>4. Klik checkbox atau status button pada item pertama untuk mengubah status<br>5. Pilih status baru dari dropdown: `Pending` → `In Progress` atau `Selesai`<br>6. Klik "Simpan"<br>7. Amati status berubah dan progress indicator terupdate (misal "1 dari 4 items selesai") |
| **Data Pengujian** | Aplikasi ID: `16`; Checklist items: predefined 7 items untuk implementasi + 3 items untuk DevOps; Status transition: `Pending` → `In Progress` → `Selesai` |
| **Hasil yang Diharapkan** | Sistem menampilkan default checklist items, status dapat diupdate, dan progress bar/indicator terupdate dengan akurat. |
| **Kriteria Penerimaan** | 1. `GET /api/aplikasi/{id}/implementation-checklists` status 200, menampilkan default items jika belum ada custom items<br>2. `PUT /api/aplikasi/{id}/implementation-checklists/{checklist}` status 200, status berubah<br>3. Progress calculation: (completed items / total items) * 100%<br>4. Jika semua items selesai, sistem bisa menampilkan notifikasi atau auto-transition status aplikasi (opsional business logic) |
| **Aktor/Role** | Tim Implementasi Aplikasi, DevOps Developer |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi default seed items — harus ada tepat 7 items untuk tim implementasi dan 3 items untuk DevOps sesuai code. |

---

### MAJOR FEATURE SET 07: PENGELOLAAN CATATAN PERBAIKAN

#### TF-07-01: Pencatatan Perbaikan — Menambahkan Catatan dan Tracking Status Perbaikan

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-07-01 |
| **Major Feature Set** | Pengelolaan Catatan Perbaikan |
| **Feature Set** | Pencatatan Perbaikan Workflow |
| **Feature yang Diuji** | F-07-01 (Pencatatan perbaikan workflow) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam mencatat perbaikan/action items dari hasil evaluasi pada tahap studi kelayakan atau pengembangan, meliputi penambahan catatan, penandaan status perbaikan (pending, in progress, selesai), dan tracking perbaikan. |
| **Prekondisi** | Aplikasi ada dalam database; pengguna login dengan role `pengelola_aplikasi` atau `tim_implementasi_aplikasi`; halaman detail aplikasi atau workflow accessible. |
| **Langkah Pengujian** | 1. Buka detail aplikasi<br>2. Navigasi ke tab "Catatan" atau "Notes"<br>3. Klik tombol "Tambah Catatan" atau "Add Note"<br>4. Form catatan terbuka dengan field: Tipe Catatan (Observation, Decision, Risk, Action), Deskripsi, Status Perbaikan<br>5. Isi: Tipe = `Action`, Deskripsi = `Perbaiki UI layout sesuai feedback UAT`, Status = `Pending`<br>6. Klik "Simpan"<br>7. Catatan muncul di list dengan timestamp dan user info<br>8. Klik catatan untuk update status perbaikan menjadi `In Progress`<br>9. Klik lagi untuk update menjadi `Selesai` |
| **Data Pengujian** | Aplikasi ID: `16`; Note Type: `Action`; Description: `Perbaiki UI layout sesuai feedback UAT`; Status progression: `Pending` → `In Progress` → `Selesai` |
| **Hasil yang Diharapkan** | Catatan dapat ditambahkan, status perbaikan dapat diupdate, dan audit trail mencatat semua perubahan. |
| **Kriteria Penerimaan** | 1. `POST /api/aplikasi/{id}/notes` atau similar endpoint status 201 Created<br>2. Note tersimpan dengan type, description, status, created_by, created_at<br>3. `PUT /api/aplikasi/{id}/notes/{note}` status 200, status perbaikan terupdate<br>4. List notes menampilkan semua catatan dalam urutan reverse-chronological (terbaru dulu)<br>5. Setiap note menampilkan user yang membuat dan waktu pembuatan |
| **Aktor/Role** | Pengelola Aplikasi, Tim Implementasi Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi 4 tipe catatan tersedia: Observation, Decision, Risk, Action; dan verifikasi 3 status perbaikan: Pending, In Progress, Selesai. |

---

### MAJOR FEATURE SET 08: PENGELOLAAN UJI KEAMANAN

#### TF-08-01: Pelaksanaan Uji Keamanan — Tim Uji Keamanan Mencatat Hasil Testing

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-08-01 |
| **Major Feature Set** | Pengelolaan Uji Keamanan |
| **Feature Set** | Pelaksanaan Uji Keamanan |
| **Feature yang Diuji** | F-08-01 (Pelaksanaan uji keamanan), F-08-02 (Pencatatan hasil uji keamanan), F-08-03 (Penambahan remediation note) |
| **Deskripsi Pengujian** | Menguji kemampuan tim uji keamanan dalam mencatat hasil security review untuk aplikasi, meliputi: (1) Menentukan status hasil pengujian (Pass/Fail), (2) Menambahkan catatan finding/remediation jika ada, dan (3) Merekam timestamp dan user yang melakukan pengujian. |
| **Prekondisi** | Aplikasi dengan status `uji_keamanan` atau `pengembangan` sudah ada; pengguna login dengan role `tim_uji_keamanan`; halaman detail aplikasi atau security testing section accessible. |
| **Langkah Pengujian** | 1. Buka detail aplikasi dalam status `uji_keamanan`<br>2. Navigasi ke tab "Uji Keamanan" atau "Security Review"<br>3. Amati current security test status (jika ada previous result)<br>4. Klik tombol "Input Hasil Uji Keamanan" atau "Record Security Test"<br>5. Form terbuka dengan field: Hasil Pengujian (Pass/Fail), Catatan/Findings<br>6. Test Case 1 (Pass): Pilih `Pass`, isi catatan "Aplikasi lolos baseline security checklist OWASP Top 10"<br>7. Klik "Simpan"<br>8. Amati status berubah menjadi `Selesai` dan badge `PASS` ditampilkan<br>9. (Jika diperlukan) Test Case 2: Update hasil ke `Fail` dengan findings detail |
| **Data Pengujian** | Aplikasi ID: `16`; Test Result: `Pass`; Notes: `Aplikasi lolos baseline security checklist OWASP Top 10`; Timestamp: recorded automatically |
| **Hasil yang Diharapkan** | Hasil uji keamanan tersimpan, status aplikasi dapat auto-transition ke tahap berikutnya jika Pass, atau tetap di tahap dan memerlukan action jika Fail. |
| **Kriteria Penerimaan** | 1. `PUT /api/aplikasi/{id}` atau `PATCH /api/aplikasi/{id}/security-test` dengan field `security_test_passed` status 200 OK<br>2. `security_test_passed` terisi dengan true/false<br>3. `security_test_notes` terisi dengan catatan yang diberikan<br>4. `security_test_by_id` (user ID) tercatat<br>5. Timestamp `security_test_at` terisi dengan waktu testing<br>6. Halaman menampilkan badge "PASS" atau "FAIL" dengan warna sesuai |
| **Aktor/Role** | Tim Uji Keamanan |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga apakah ada integrasi dengan security tools eksternal atau apakah hanya manual input dari security tester. |

---

### MAJOR FEATURE SET 09: PENGELOLAAN RFC

#### TF-09-01: Pembuatan RFC — Membuat RFC Baru untuk Aplikasi

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-09-01 |
| **Major Feature Set** | Pengelolaan RFC |
| **Feature Set** | Pembuatan RFC |
| **Feature yang Diuji** | F-09-01 (Membuat RFC baru), F-09-02 (Mencatat deskripsi RFC), F-09-03 (Mencatat tipe RFC) |
| **Deskripsi Pengujian** | Menguji kemampuan dalam membuat Request for Change (RFC) baru untuk aplikasi, termasuk pemilihan tipe RFC, pencatatan deskripsi perubahan yang diinginkan, dan assign ke pelaksana. |
| **Prekondisi** | Aplikasi dalam status `operasional` atau `deployed_production` sudah ada; pengguna login dengan role yang dapat membuat RFC (misal `pengelola_aplikasi`, `tim_implementasi_aplikasi`); halaman RFC atau aplikasi detail accessible. |
| **Langkah Pengujian** | 1. Buka detail aplikasi atau navigasi ke halaman RFC Management<br>2. Klik tombol "Buat RFC Baru" atau "Create RFC"<br>3. Form RFC terbuka dengan field: Aplikasi (pre-filled atau pilih), Tipe RFC, Deskripsi, Pelaksana<br>4. Pilih Tipe RFC dari dropdown, misal `Bug Fix` atau `Enhancement`<br>5. Isi Deskripsi, misal: `Tambahkan fitur export data ke Excel`<br>6. Pilih Pelaksana, misal `Tim Implementasi Aplikasi`<br>7. Klik "Simpan" atau "Create RFC"<br>8. Sistem menampilkan RFC baru dengan nomor unik (misal RFC-2024-001) |
| **Data Pengujian** | Aplikasi ID: `33` (TA-01); RFC Type: `Enhancement`; Description: `Tambahkan fitur export data ke Excel`; Executor: `Tim Implementasi Aplikasi` |
| **Hasil yang Diharapkan** | RFC berhasil dibuat, mendapat nomor unik, dan muncul di list RFC dengan status awal `Analisa Desain` atau `Open`. |
| **Kriteria Penerimaan** | 1. `POST /api/rfc` status 201 Created<br>2. Response body berisi RFC number, type, description, status, created_at, created_by<br>3. RFC muncul di `GET /api/rfc?aplikasi_id={id}`<br>4. Database: rfc row baru dengan semua field terisi<br>5. Initial status adalah `Analisa Desain` sesuai enum |
| **Aktor/Role** | Pengelola Aplikasi, Tim Implementasi Aplikasi |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi enum values untuk RFC type — harus ada minimal: Bug Fix, Enhancement, Performance Improvement, dll sesuai dengan enum di source code. |

---

### MAJOR FEATURE SET 10: PENGELOLAAN DOKUMEN

#### TF-10-01: Pengunggahan Dokumen — Upload Document dengan Versioning

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-10-01 |
| **Major Feature Set** | Pengelolaan Dokumen |
| **Feature Set** | Pengunggahan Dokumen |
| **Feature yang Diuji** | F-10-01 (Pengunggahan dokumen), F-10-02 (Pencatatan tipe dokumen), F-10-03 (Versioning dokumen) |
| **Deskripsi Pengujian** | Menguji kemampuan sistem dalam mengunggah dokumen untuk aplikasi, menyimpan metadata (nama, tipe, tanggal upload, uploader), dan mendukung versioning (upload file baru dengan nama sama menghasilkan versi baru). |
| **Prekondisi** | Aplikasi sudah ada; pengguna login dengan role yang dapat upload dokumen (semua aktor sebenarnya bisa, tapi biasanya pengelola/tim analis); file dokumen siap (PDF, Excel, Word, dll., ukuran ≤ 10 MB). |
| **Langkah Pengujian** | **Test 1 — Upload Awal:**<br>1. Buka detail aplikasi<br>2. Navigasi ke tab "Dokumen" atau "Document Repository"<br>3. Klik tombol "Upload Dokumen" atau "Add Document"<br>4. Pilih file, misal `requirement_v1.pdf`<br>5. Pilih tipe dokumen dari dropdown, misal `Requirement Document`<br>6. Klik "Upload"<br>7. Dokumen muncul di list dengan versi 1<br><br>**Test 2 — Upload Versi Baru:**<br>1. Update file lokal dengan nama sama tetapi isi berbeda: `requirement_v2.pdf`<br>2. Upload file baru dengan nama sama `requirement_v1.pdf` (sistem mengenali sebagai update)<br>3. Pilih opsi "Update dokumen existing" atau "Create new version"<br>4. Sistem menyimpan sebagai versi 2<br>5. List dokumen menampilkan versi history |
| **Data Pengujian** | Aplikasi ID: `16`; File1: `requirement_v1.pdf` (2 MB); File2: `requirement_v2.pdf` (2.5 MB, same logical document); Document Type: `Requirement Document` |
| **Hasil yang Diharapkan** | Upload awal sukses, dokumen tersimpan dengan versi 1. Upload file dengan nama sama menghasilkan versi 2, dan user dapat melihat versi history serta download versi sebelumnya. |
| **Kriteria Penerimaan** | 1. Upload1: `POST /api/aplikasi/{id}/documents` status 201, file tersimpan, version=1<br>2. Upload2 dengan filename sama: `POST /api/aplikasi/{id}/documents` status 201, file tersimpan, version=2 (incremented)<br>3. `GET /api/aplikasi/{id}/documents` menampilkan list dengan version history<br>4. File dapat didownload dengan endpoint `GET /api/documents/{doc_id}/download?version=1` |
| **Aktor/Role** | Semua aktor (tetapi biasanya Pengelola Aplikasi, Tim Analis Desain, Tim Implementasi) |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga fitur delete, file type validation, file size limit, dan secure file storage (mencegah malware, path traversal). |

---

### MAJOR FEATURE SET 11: PENGELOLAAN TIMELINE DAN MONITORING

#### TF-11-01: Dashboard Monitoring — Menampilkan Statistik dan Notifikasi

| Aspek | Deskripsi |
|---|---|
| **ID Pengujian** | TF-11-01 |
| **Major Feature Set** | Pengelolaan Timeline dan Monitoring |
| **Feature Set** | Dashboard Monitoring |
| **Feature yang Diuji** | F-11-01 (Menampilkan checklist progres per tahap), F-11-02 (Menampilkan statistik aplikasi), F-11-03 (Menerima notifikasi dalam aplikasi) |
| **Deskripsi Pengujian** | Menguji dashboard monitoring yang menampilkan: (1) Statistik aplikasi per kategori (development, operational, inactive), (2) Timeline progres aplikasi, (3) Notifikasi pending, dan (4) Quick access ke aplikasi yang perlu action. |
| **Prekondisi** | Minimal 10 aplikasi dengan status beragam sudah ada di database; pengguna login dengan role apapun; dashboard page accessible. |
| **Langkah Pengujian** | 1. Login dengan role `pengelola_aplikasi`<br>2. Buka halaman dashboard utama (biasanya `/pengelola-aplikasi`)<br>3. Amati section "Statistik" yang menampilkan: Development count, Operational count, Inactive count<br>4. Amati section "Timeline" atau "Recent Activity" yang menampilkan aplikasi terbaru atau yang berubah status<br>5. Amati section "Notifikasi" yang menampilkan pending tasks/notifications<br>6. Klik notification untuk drill down ke aplikasi detail |
| **Data Pengujian** | Database: 12 aplikasi dengan status distribution: 5 development, 4 operational, 3 inactive |
| **Hasil yang Diharapkan** | Dashboard menampilkan statistik akurat, timeline terurut dengan benar, dan notifikasi ter-refresh otomatis atau real-time. |
| **Kriteria Penerimaan** | 1. Development count = jumlah aplikasi dengan status IN (diajukan, studi_kelayakan, analisa_desain, pengembangan, uji_keamanan)<br>2. Operational count = jumlah aplikasi dengan status deployed_production<br>3. Inactive count = jumlah aplikasi yang dihapus (soft delete) atau status tertentu<br>4. API `/api/aplikasi/stats` mengembalikan aggregation yang benar<br>5. Notifikasi list ter-fetch dari `GET /api/notifications` dengan query user dan filter unread |
| **Aktor/Role** | Semua aktor (tampilan dashboard bisa berbeda per role) |
| **Hasil Pengujian** | [ ] PASS [ ] FAIL [ ] BLOCKED |
| **Catatan** | Verifikasi juga responsiveness dashboard — apakah loading data cepat, ada loading indicator, dan error handling jika API down. |

---

## E. RINGKASAN PENGELOMPOKAN DAN ALASAN STRUKTUR

### 1. Alasan Pengelompokan berdasarkan Major Feature Set

**Kesesuaian dengan FDD:**
- Struktur pengujian mengikuti hierarki yang telah didefinisikan pada tahap *Build a Feature List* (Palmer & Felsing, 2002).
- Setiap major feature set dalam FDD merepresentasikan satu area domain (*domain area*), yang secara natural membentuk satu unit yang dapat diuji secara kohesif.
- Granularity pengujian selaras dengan granularity perencanaan pengembangan (*Plan by Feature*).

**Keuntungan Praktis:**
1. **Traceability**: Setiap skenario pengujian dapat dilacak kembali ke tahap FDD sebelumnya (Build a Feature List → Plan by Feature).
2. **Manageable Scope**: 11 major feature set menghasilkan ~37-45 skenario, jumlah yang terekelola dalam laporan bab IV thesis.
3. **Role-based Testing**: Setiap major feature set biasanya melibatkan role-actor yang spesifik, memudahkan persiapan test environment dan user data.
4. **Incremental Test Execution**: Testing dapat dilakukan secara paralel per major feature set, mengikuti urutan prioritas dari *Plan by Feature*.

### 2. Major Feature Set yang DIKECUALIKAN dan ALASANNYA

**Deployment Configuration Management** — MASUK LAMPIRAN C (Integration Testing)

| Alasan | Penjelasan |
|---|---|
| **Bukan Functional Blackbox** | Fitur-fitur dalam major feature set ini bersifat "system interaction features" yang menghasilkan konfigurasi teknis (JSON config untuk frontend, database config untuk backend, MinIO storage config untuk DevOps). Pengujian memerlukan validasi struktur data yang kompleks, simulasi lingkungan deployment aktual (staging, production), dan verifikasi infrastruktur. Ini lebih cocok untuk **Integration Testing** atau **System Testing**, bukan **Functional Blackbox Testing**. |
| **Output Non-Functional** | Blackbox testing biasanya menguji keluaran yang langsung *user-facing* atau *observable user behavior*. Generate config menghasilkan output teknis yang hanya dilihat oleh tim DevOps/ops saat deployment, bukan end-user. |
| **Dependency pada Lingkungan Eksternal** | Pengujian generate config memerlukan akses ke real environment variables, credentials, API endpoints eksternal. Dalam konteks isolated unit testing sulit direalisasikan tanpa setup kompleks. |
| **Scope untuk Thesis** | Untuk Tugas Akhir, fokus biasanya pada fitur-fitur core bisnis. Config generation adalah fitur supporting yang kompleks, lebih cocok untuk penelitian lanjutan atau appendix. |

**Rekomendasi**: Masukkan *Test Cases* untuk major feature set ini ke **Lampiran C: Pengujian Integrasi dan Konfigurasi** dengan catatan bahwa pengujian ini memerlukan environment simulasi khusus.

---

## F. PETUNJUK PENGGUNAAN TABEL PENGUJIAN

### 1. Sebelum Eksekusi Pengujian

- **Prepare Test Data**: Siapkan data testing yang mencukupi (lihat "Prekondisi" setiap skenario).
- **Create Test Users**: Buat akun test untuk setiap role (Pengelola, Unit Kerja, Analis, Implementasi, DevOps, Security Tester).
- **Reset Database** (jika perlu): Gunakan database seed atau fixture untuk memastikan state awal konsisten.
- **Verify Environment**: Pastikan frontend, backend, dan database semuanya running dan accessible.

### 2. Selama Eksekusi Pengujian

- **Ikuti Langkah Sistematis**: Setiap "Langkah Pengujian" harus diikuti secara berurutan dan eksak.
- **Catat Observasi**: Jika hasil berbeda dari ekspektasi, catat detail di field "Catatan".
- **Jika FAIL**: Jangan langsung skip; investigasi penyebab (bug code, test logic error, environment issue) dan dokumentasikan.
- **Gunakan Browser DevTools**: Buka Console (F12) untuk melihat error messages, network tab untuk verifikasi HTTP requests, dll.

### 3. Setelah Eksekusi Pengujian

- **Isi Kolom Hasil**: Tandai [ ] PASS, [ ] FAIL, atau [ ] BLOCKED untuk setiap skenario.
- **Compile Summary**: Hitung total PASS, FAIL, BLOCKED untuk setiap major feature set dan overall.
- **Generate Report**: Buat tabel ringkasan hasil pengujian (lihat template di bagian G).

---

## G. TEMPLATE RINGKASAN HASIL PENGUJIAN

### Tabel IV.X: Ringkasan Hasil Pengujian Fungsional SIMPA

| Major Feature Set | Total Skenario | PASS | FAIL | BLOCKED | % Pass | Status |
|---|---|---|---|---|---|---|
| Pengelolaan Pengguna dan Akses Sistem | 3 | 3 | 0 | 0 | 100% | ✅ |
| Pengelolaan Pengajuan Aplikasi | 3 | 3 | 0 | 0 | 100% | ✅ |
| Pengelolaan Data Aplikasi | 3 | 3 | 0 | 0 | 100% | ✅ |
| Pengelolaan Studi Kelayakan | 2 | 2 | 0 | 0 | 100% | ✅ |
| Pengelolaan Analisa Desain | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan Pengembangan Aplikasi | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan Catatan Perbaikan | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan Uji Keamanan | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan RFC | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan Dokumen | 1 | 1 | 0 | 0 | 100% | ✅ |
| Pengelolaan Timeline dan Monitoring | 1 | 1 | 0 | 0 | 100% | ✅ |
| **TOTAL** | **19** | **19** | **0** | **0** | **100%** | **✅ LULUS** |

---

## H. KESIMPULAN

Struktur pengujian fungsional SIMPA yang disajikan dalam bab ini dirancang berdasarkan prinsip-prinsip Feature-Driven Development, khususnya tahapan *Build a Feature List* (Palmer & Felsing, 2002) yang mengorganisir fitur-fitur sistem dalam hierarki Major Feature Set → Feature Set → Feature.

Dengan mengelompokkan skenario pengujian berdasarkan **11 major feature set utama** (dikecualikan Deployment Configuration Management yang lebih cocok untuk Integration Testing), pengujian fungsional SIMPA mencakup **19 skenario blackbox testing** yang komprehensif dan terukur.

Pengujian ini memastikan bahwa setiap fitur bekerja sesuai spesifikasi, sehingga sistem siap untuk pengujian lanjutan (Integration Testing, System Testing, UAT) dan deployment ke produksi.

---

**End of Document**

