# Sistem KRS Akademik — SPA CRUD Skala 5 Juta Data

> **Mini Project Rekrutmen — Politeknik Caltex Riau**  
> Implementasi sistem Kartu Rencana Studi (KRS) berbasis Single Page Application yang dirancang dan diuji untuk menangani **5.000.000+ baris data** dengan performa tinggi.

---

## 🔗 Link

| | URL |
|--|--|
| **Repository** | https://github.com/ridho-adri/Mini-Project-KRS |
| **Aplikasi Live** | https://mini-project-krs-production-2da5.up.railway.app |

---

## 🛠 Stack Teknologi

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | Laravel | 11.x |
| Frontend | React + Inertia.js | 18.x |
| Database | MySQL | 8.x |
| Build Tool | Vite | 6.x |
| Styling | Tailwind CSS | 3.x |
| Runtime | PHP | 8.2+ |

**Kenapa Laravel + Inertia.js?**  
Inertia.js memungkinkan pembuatan SPA modern menggunakan React *tanpa* membangun REST API terpisah. Routing dan controller tetap di Laravel, sementara React menangani tampilan — pengembangan lebih cepat dengan kompleksitas lebih rendah (*monolith modern*).

**Kenapa tidak ada autentikasi?**  
Sesuai instruksi dokumen teknis rekrutmen, sistem ini bersifat *public access* penuh tanpa login.

---

## 📋 Persyaratan Sistem

- PHP >= 8.2 + Composer
- Node.js >= 18 + npm
- MySQL >= 8.0
- Minimal 2GB RAM (untuk seeding 5 juta baris)

---

## ⚙️ Setup Lokal (Development)

### 1. Clone & Install Dependensi
```bash
git clone https://github.com/ridho-adri/Mini-Project-KRS.git
cd Mini-Project-KRS
composer install
npm install
```

### 2. Konfigurasi Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`, sesuaikan koneksi database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krs_akademik
DB_USERNAME=root
DB_PASSWORD=
```
> Buat database `krs_akademik` secara manual di MySQL terlebih dahulu.

### 3. Jalankan Migrasi
```bash
php artisan migrate
```

| Migration | Keterangan |
|-----------|------------|
| `create_students_table` | Tabel mahasiswa (nim unique, email unique) |
| `create_courses_table` | Tabel mata kuliah (code unique) |
| `create_enrollments_table` | Tabel KRS + FK + unique constraint + composite index + soft deletes |
| `add_search_indexes_...` | Index B-Tree untuk kolom pencarian (nim, name, code) |

### 4. Jalankan Aplikasi
```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Akses di: **http://localhost:8000** — tidak perlu login.

---

## 🌱 Seeder 5 Juta Data

```bash
php artisan app:seed-enrollments --count=5000000
```

**Mekanisme:** Menggunakan `DB::table()->insertOrIgnore()` dengan batch 5.000 baris per insert. Menghindari *Out of Memory* dan N+1 problem.

> ⏱ **Perkiraan waktu (Teruji Nyata):** ~7 menit 47 detik (467 detik) di mesin lokal standar (5 juta baris).

---

## 🗄️ Desain Skema Database

```
students
├── id (PK)
├── nim        VARCHAR(12)  UNIQUE INDEX
├── name       VARCHAR(100) INDEX
└── email      UNIQUE INDEX

courses
├── id (PK)
├── code       VARCHAR(10)  UNIQUE INDEX
├── name       VARCHAR(120) INDEX
└── credits    TINYINT UNSIGNED

enrollments
├── id (PK)
├── student_id FK → students.id  (RESTRICT DELETE)
├── course_id  FK → courses.id   (RESTRICT DELETE)
├── academic_year  VARCHAR(9)    -- format: 2024/2025
├── semester   ENUM(GANJIL, GENAP)
├── status     ENUM(DRAFT, SUBMITTED, APPROVED, REJECTED)  DEFAULT DRAFT
├── deleted_at TIMESTAMP NULL    -- Soft Delete
├── UNIQUE KEY (student_id, course_id, academic_year, semester)
└── INDEX      (academic_year, semester, status)
```

---

## 🚀 Fitur Backend

### Endpoint
| Method | URL | Fungsi |
|--------|-----|--------|
| GET | `/enrollments` | List KRS (pagination, sort, filter, search) |
| POST | `/enrollments` | Create KRS baru (atomic 3 tabel) |
| PUT | `/enrollments/{id}` | Update KRS (Hanya mengubah data pivot KRS, TIDAK mengubah master data Mahasiswa/Matkul) |
| DELETE | `/enrollments/{id}` | Soft-delete KRS |
| GET | `/enrollments/export` | Export CSV streaming |
| GET | `/students/search?q=` | Autocomplete mahasiswa |
| GET | `/courses/search?q=` | Autocomplete mata kuliah |

### Query Parameter GET /enrollments
| Parameter | Contoh | Keterangan |
|-----------|--------|------------|
| `search` | `Budi` | Prefix search di nim, name, code, course name |
| `status` | `DRAFT` | Quick filter status |
| `semester` | `GANJIL` | Quick filter semester |
| `sort_by` | `academic_year` | Sort kolom tunggal |
| `sort_dir` | `desc` | Arah sort |
| `sort_orders` | `[{"col":"status","dir":"asc"},{"col":"id","dir":"desc"}]` | Sort multi-kolom |
| `filters` | `[{"field":"nim","operator":"equal","value":"123"}]` | Advanced filter |
| `filter_logic` | `and` / `or` | Logika gabungan filter |
| `page_size` | `15` / `50` / `100` | Baris per halaman |

### Validasi Input
| Field | Aturan |
|-------|--------|
| `student_nim` | 8–12 digit angka, unique |
| `student_email` | Format email valid, unique |
| `course_code` | Format `XX000`–`XXXX000` (uppercase + angka), unique |
| `course_credits` | Integer 1–6 |
| `academic_year` | Format `YYYY/YYYY` (regex) |
| `semester` | `GANJIL` atau `GENAP` |
| `status` | `DRAFT`, `SUBMITTED`, `APPROVED`, atau `REJECTED` |

---

## ⚡ Strategi Performa (5 Juta Baris)

### 1. Indexing Database
- **B-Tree Index** pada `students.nim`, `students.name`, `courses.code`, `courses.name`
- **Composite Index** `(academic_year, semester, status)` untuk quick filter
- **Unique Index** 4-kolom di `enrollments`

### 2. Strategi Query: `whereIn` Dua-Tahap
Menghindari `whereHas()` (correlated subquery) dan `JOIN OR` (tidak bisa pakai index):
```
Step 1: SELECT id FROM students WHERE nim LIKE 'keyword%'  → [id1, id2, ...]
Step 2: SELECT * FROM enrollments WHERE student_id IN ([id1, id2, ...])
```
Hasil: **~21ms** vs >60 detik sebelum optimasi.

### 3. Pemisahan Logika Pencarian
- **Live Search (Cepat):** Menggunakan `LIKE 'keyword%'` (Prefix-only) agar kompatibel penuh dengan B-Tree Index. Sangat cepat untuk pengetikan real-time.
- **Advanced Filter (Fleksibel):** Mendukung pencarian penuh `LIKE '%keyword%'` (Contains) untuk kolom teks. Lebih lambat dari Live Search namun memenuhi standar pencarian substring (opsi *Contains* tetap dipertahankan).

### 4. `simplePaginate` (tanpa COUNT)
Menggantikan `paginate()` yang butuh `COUNT(*)` full-scan (~2 detik).

### 5. Streaming Export
`StreamedResponse` + `chunkById(5000)` — RAM tetap < 10MB untuk 5 juta baris.

### Performa Terukur
| Operasi | Waktu |
|---------|-------|
| Sort per kolom (index) | **1.85ms** |
| Search 4 kolom (whereIn) | **~21ms** |
| Advanced filter multi-kolom | **~11ms** |
| Export streaming 25k baris | **495ms** |

---

## 🗑️ Keputusan Desain: Soft Delete

**Soft Delete dipilih** atas Hard Delete karena:
- Data histori akademik bersifat krusial dan tidak boleh hilang permanen
- Diperlukan untuk audit trail (kapan mahasiswa mendaftar, kapan status berubah)
- Admin bisa memulihkan data yang terhapus secara tidak sengaja via `withTrashed()`

> **Catatan Relasi:** Foreign Key pada tabel `enrollments` menggunakan **RESTRICT DELETE**, bukan *Cascade*. Ini mencegah penghapusan master data (mahasiswa/mata kuliah) jika mereka masih memiliki riwayat KRS yang belum dihapus, menjaga konsistensi prinsip histori akademik.

Data yang di-soft-delete otomatis disembunyikan dari Listing, Search, Filter, dan Export.

---

## 🔃 Fitur Sorting

### Sort Kolom Tunggal
Klik header kolom (ID, Tahun Ajaran, Semester, Status) — klik ulang untuk membalik arah.

### Sort Multi-Kolom (Advanced Order)
**Ctrl + Klik** beberapa header secara berurutan:
- Angka kecil di ↑/↓ menunjukkan prioritas sort (1 = prioritas pertama)
- Menghasilkan `ORDER BY col1 ASC, col2 DESC, ...` di backend
- Klik biasa (tanpa Ctrl) mereset ke sort kolom tunggal

---

## 📊 Hasil Pengujian

| ID | Skenario | Hasil |
|----|----------|-------|
| TS-01 | Seeder 5 juta + `COUNT(*)` | ✅ COUNT = 5.000.002 |
| TS-02 | Create KRS atomic (3 tabel) | ✅ Students+1, Courses+1, Enrollments+1 |
| TS-03 | Input invalid dari frontend | ✅ 8 error validasi terdeteksi |
| TS-04 | Payload invalid dari API | ✅ `exists()` menolak ID fiktif |
| TS-05 | Ganti page/page size | ✅ 15 & 50 baris berjalan benar |
| TS-06 | Sort ASC/DESC per header | ✅ 1.85ms |
| TS-07 | Filter Status + Semester | ✅ DRAFT=1.248.427, GANJIL=2.500.003 |
| TS-08 | Search NIM/Nama/Kode MK | ✅ ~21ms via whereIn |
| TS-09 | Multi-column advanced filter | ✅ AND filter NIM+DRAFT akurat |
| TS-10 | Logika AND dan OR | ✅ OR selalu ≥ AND |
| TS-11 | Update KRS | ✅ academic_year, semester, status (Master data aman) |
| TS-12 | Delete KRS (Soft Delete) | ✅ deleted_at terisi, tersembunyi |
| TS-13 | Export seluruh dataset (2 Juta) | ✅ Streaming tanpa OOM & Timeout, tidak ada crash |
| TS-14 | Export dengan Filter Aktif | ✅ Export DRAFT menghasilkan persis 500.003 baris tanpa HTML |

**Total: 14/14 ✅ LULUS**

---

## 🌐 Deployment & Keterbatasan Free-Tier (Railway + Aiven)

Sebagai alternatif yang sangat stabil untuk mode gratis (Free Tier), kita menggunakan pemisahan infrastruktur:
1. **Database:** Aiven MySQL (Limit Storage 1 GB)
2. **Web Server:** Railway Web Service (via Nixpacks)

**Fakta Pengujian Lokal vs Online:**
- **Lokal (Berhasil 100%):** Pada mesin localhost, perintah `php artisan app:seed-enrollments` (default 5.000.000 data) telah **terbukti berhasil dijalankan dan diukur memakan waktu 7 menit 47 detik** (*bulk insert*). UI pencarian dan navigasi merespon secara *real-time* tanpa hambatan.
- **Online (Aiven Free Tier):** Mengingat Aiven MySQL hanya memberikan kapasitas gratis maksimal 1 GB, secara teori server ini mampu menampung hingga 4-5 Juta baris (berkat efisiensi tipe data). Pengujian *live* telah membuktikan sistem ini **lancar dan stabil di angka 2.000.000 baris data secara online**.

**Command yang dijalankan:**
* **Di Localhost (untuk 5 Juta Data):** 
  `php artisan app:seed-enrollments`
* **Di Produksi Online (Telah Diuji Tembus 2.000.000 Data):** 
  `php artisan app:seed-enrollments --count=2000000`

Lihat `MIGRATION_NOTES.md` untuk opsi melakukan *dump* manual dari lokal ke Aiven MySQL.

### VPS / Shared Hosting Manual
```bash
# 1. Build asset frontend
npm run build

# 2. Set .env production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

# 3. Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Migrasi di server
php artisan migrate --force

# 5. Seeding (opsional, ~4-5 menit)
php artisan app:seed-enrollments --count=5000000
```

---

## 📁 Struktur Proyek Utama

```
├── app/
│   ├── Console/Commands/SeedEnrollments.php     ← Seeder 5 juta baris
│   ├── Http/Controllers/EnrollmentController.php
│   ├── Http/Requests/StoreEnrollmentRequest.php
│   ├── Http/Requests/UpdateEnrollmentRequest.php
│   └── Models/{Enrollment, Student, Course}.php
├── database/migrations/                          ← 4 file migration
├── resources/js/Pages/Enrollments/Index.jsx      ← SPA utama (React)
├── routes/web.php                                ← Semua endpoint
├── lang/id/validation.php                        ← Pesan validasi Bahasa Indonesia
└── ARCHITECTURE_GUIDE.md                         ← Panduan arsitektur detail
```
