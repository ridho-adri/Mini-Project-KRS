# Proyek CRUD Akademik (KRS)

Proyek ini adalah implementasi dari sistem Single Page Application (SPA) CRUD Akademik (Kartu Rencana Studi) yang dirancang untuk menangani skala data besar (hingga 5 juta baris enrollments).

## Teknologi Utama
- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** React 18, dikelola oleh Inertia.js (Breeze Starter Kit)
- **Database:** MySQL
- **Styling:** Tailwind CSS

**Kenapa Inertia.js dipakai?**
Inertia.js memungkinkan pembuatan Single Page Application yang modern dan interaktif menggunakan React *tanpa* kerumitan membangun API REST/GraphQL terpisah. Routing, autentikasi, dan controller tetap ditangani sepenuhnya oleh Laravel, sementara frontend murni menangani tampilan state berbasis React, sehingga mempercepat proses development (monolith modern).

## Persyaratan Sistem
- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL (bisa menampung 5 juta baris)

## Cara Setup Lokal

1. **Clone & Install Dependensi:**
   ```bash
   git clone <URL_REPO>
   cd krs-akademik
   composer install
   npm install
   ```

2. **Konfigurasi Lingkungan (`.env`):**
   Copy file contoh `.env` dan generate application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Ubah koneksi database wajib di `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=krs_akademik
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   *(Pastikan database `krs_akademik` sudah dibuat secara manual di server MySQL lokal Anda terlebih dahulu).*

3. **Jalankan Migrasi:**
   ```bash
   php artisan migrate
   ```

4. **Jalankan Aplikasi:**
   Jalankan server pengembangan Laravel dan watcher Vite secara bersamaan (di terminal terpisah):
   ```bash
   php artisan serve
   ```
   ```bash
   npm run dev
   ```
   Aplikasi dapat diakses di `http://localhost:8000` tanpa perlu login — semua fitur bersifat publik.

## Cara Menjalankan Seeder 5 Juta Data
Untuk membuktikan bahwa sistem dapat menangani volume data tinggi, sebuah command khusus disiapkan.
Jalankan perintah ini di terminal:
```bash
php artisan app:seed-enrollments --count=5000000
```
> **Perkiraan Waktu:** Proses seeding akan berjalan secara sekuensial dan bulk insert per 5.000 baris. Di komputer lokal rata-rata, ini memakan waktu sekitar **4-5 menit** untuk 5.000.000 baris.

Setelah selesai, command akan otomatis menjalankan dan mencetak hasil dari query: `SELECT COUNT(*) FROM enrollments`. Anda juga bisa membuktikannya langsung di database client pilihan Anda.

## Strategi Performa Database

Mengingat tabel `enrollments` memiliki lebih dari 5 juta entri, sejumlah optimisasi telah dilakukan:

1. **Indeks Database:**
   - **Composite Index** (`academic_year`, `semester`, `status`): Dibuat khusus pada kolom-kolom yang paling sering dikombinasikan saat *quick filtering* dan sorting agar database engine (InnoDB) tidak melakukan *full table scan*.
   - **Foreign Key Index:** Secara otomatis terindeks oleh MySQL (`student_id`, `course_id`) untuk mempercepat JOIN query.
   - **Unique Index:** Kombinasi 4 kolom (`student_id`, `course_id`, `academic_year`, `semester`) untuk menjamin tidak ada duplikasi input, yang juga bertindak sebagai index sekunder.
2. **Strategi Bulk Insert:** 
   Seeder custom `SeedEnrollments` tidak memanggil metode `Enrollment::create()` satu per satu (akan sangat lambat akibat N+1 koneksi/object overhead). Sebaliknya, menggunakan array multidimensi yang dimasukkan ke `DB::table('enrollments')->insert()` sekaligus setiap 5.000 entri per batch.
3. **Strategi Streaming Export:**
   Export file CSV berpotensi menyebabkan PHP *Out Of Memory* jika memuat 5 juta Eloquent Collection sekaligus. Fitur Export diimplementasikan menggunakan **StreamedResponse** dan teknik *Chunking* (`chunkById(5000)`). PHP mencetak baris langsung ke output I/O secara sekuensial sehingga pemakaian RAM tetap di level konstan (< 10 MB) tanpa mempedulikan jumlah baris.

## Keputusan Desain: Soft Delete
Sistem ini menggunakan fitur Eloquent `SoftDeletes` pada tabel `enrollments`. Saat sebuah entri KRS "dihapus" oleh pengguna:
- Kolom `deleted_at` diisi dengan *timestamp* penghapusan, **bukan** baris dihapus secara fisik.
- **Alasan:** Data histori akademik bersifat krusial. Penghapusan permanen (*hard delete*) bisa merusak referensi audit trail (kapan mahasiswa mendaftar, kapan status berubah). Dengan *soft delete*, administrator masih bisa memulihkan data via query langsung jika terjadi kesalahan input.
- Data yang telah di-*soft-delete* secara otomatis diabaikan oleh Eloquent pada semua operasi: Listing, Export, Search, dan Filter.
- *Hard delete* tidak diekspos melalui UI publik demi keamanan integritas data akademik.

## Fitur Sorting

### Sort Kolom Tunggal
Klik header kolom di tabel (ID, Tahun Ajaran, Semester, Status) untuk mengurutkan naik/turun.

### Sort Multi-Kolom (Advanced Order)
**Ctrl + Klik** pada beberapa header kolom untuk menambahkan kolom ke urutan sort secara kumulatif.
- Contoh: Ctrl+Klik **Status** → Ctrl+Klik **Tahun Ajaran** akan menghasilkan `ORDER BY status ASC, academic_year ASC`.
- Angka kecil di samping indikator ↑/↓ menunjukkan **prioritas urutan sort** (1 = prioritas pertama).
- Klik header tanpa Ctrl akan mereset ke sort kolom tunggal.
- Backend menerima parameter `sort_orders` berformat JSON: `[{"col":"status","dir":"asc"},{"col":"academic_year","dir":"desc"}]`.

## Penjelasan Logika Advanced Filter (AND / OR)
Fitur filter mahir mendukung pencarian lintas-kolom dinamis (seperti "Nama Mahasiswa berisi Budi" **ATAU** "Kode MK sama dengan CS101").

- Filter array dari frontend akan dikirim sebagai format JSON: `[{field, operator, value}]` disertai `filter_logic` (AND/OR).
- Backend secara dinamis membangun query menggunakan strategi `whereIn` dua-tahap: query ke tabel dimensi (`students`/`courses`) dahulu untuk mendapat ID, kemudian `whereIn('student_id', ...)` / `whereIn('course_id', ...)` ke tabel `enrollments`.
- Strategi ini memungkinkan penggunaan index B-Tree secara optimal pada 5 juta baris data.

## URL Aplikasi (Production)
*(Untuk diisi setelah deploy)*: `https://[URL_PRODUKSI]`
