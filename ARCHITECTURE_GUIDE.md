# Panduan Arsitektur & Setup Awal: Proyek SPA CRUD Akademik (Skala 5 Juta Data)

Dokumen ini berisi panduan tingkat lanjut (Senior-level) untuk merancang aplikasi Single Page Application (SPA) dengan skala 5 juta baris data menggunakan stack **Laravel + React.js (Inertia.js)**. 

Pendekatan menggunakan **Laravel Inertia.js (Breeze React)** sangat disarankan karena Inertia bertindak sebagai jembatan yang menghubungkan routing Laravel langsung ke komponen React secara mulus layaknya SPA murni, tanpa kerumitan membangun API REST/GraphQL terpisah.

---

## 1. Inisialisasi Proyek (Command Line)
Jalankan perintah berikut di terminal untuk menginstal Laravel, mengatur konfigurasi frontend React, dan menyiapkan database.

```bash
# 1. Buat proyek Laravel baru
composer create-project laravel/laravel krs-akademik
cd krs-akademik

# 2. Instal Laravel Breeze untuk scaffolding autentikasi & SPA React
composer require laravel/breeze --dev
php artisan breeze:install react

# 3. Instal dependensi Node.js
npm install

# 4. Sesuaikan koneksi database di file .env
# DB_CONNECTION=mysql
# DB_DATABASE=krs_akademik
# DB_USERNAME=root
# DB_PASSWORD=
```

---

## 2. Perancangan Skema Database (Migrasi & Indexing)
Untuk menangani 5 juta data, **Indexing** adalah nyawa dari aplikasi. Tanpa Index yang tepat, query pencarian bisa memakan waktu bermenit-menit (Full Table Scan).

Jalankan perintah pembuatan migrasi:
```bash
php artisan make:migration create_students_table
php artisan make:migration create_courses_table
php artisan make:migration create_enrollments_table
```

### Migrasi `students`
```php
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->string('nim', 12)->unique(); // Index implisit dari unique()
    $table->string('name', 100);
    $table->string('email')->unique();
    $table->timestamps();
});
```

### Migrasi `courses`
```php
Schema::create('courses', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10)->unique();
    $table->string('name', 120);
    $table->tinyInteger('credits')->unsigned();
    $table->timestamps();
});
```

### Migrasi `enrollments` (Tabel Transaksi Utama)
```php
Schema::create('enrollments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained()->onDelete('cascade');
    $table->foreignId('course_id')->constrained()->onDelete('cascade');
    $table->string('academic_year', 9); // Contoh: 2024/2025
    $table->enum('semester', ['GANJIL', 'GENAP']);
    $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'])->default('DRAFT');
    $table->timestamps();
    $table->softDeletes(); // Wajib untuk histori akademik

    // 1. Unique Constraint: Mencegah duplikasi data pendaftaran
    $table->unique(['student_id', 'course_id', 'academic_year', 'semester'], 'enrollments_unique_idx');

    // 2. Composite Index: Mempercepat "Quick Filter"
    $table->index(['academic_year', 'semester', 'status'], 'enrollments_composite_idx');
});
```

---

## 3. Strategi Seeder 5 Juta Data (Batch Insert)
Penggunaan Model Eloquent (e.g., `Enrollment::create()`) di dalam loop 5 juta kali akan menyebabkan *Out of Memory*. Solusinya adalah menggunakan **Batch Insert** murni via Query Builder `DB::table()`.

```bash
php artisan make:command SeedEnrollments
```

**Isi Command (`app/Console/Commands/SeedEnrollments.php`):**
```php
public function handle()
{
    $total = 5000000;
    $batchSize = 5000; // Angka aman untuk batas placeholder MySQL
    $inserted = 0;

    $studentIds = DB::table('students')->pluck('id')->toArray();
    $courseIds = DB::table('courses')->pluck('id')->toArray();

    $this->info("Memulai seeding {$total} data...");

    while ($inserted < $total) {
        $batch = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $batch[] = [
                'student_id' => $studentIds[array_rand($studentIds)],
                'course_id' => $courseIds[array_rand($courseIds)],
                'academic_year' => '2024/2025',
                'semester' => ['GANJIL', 'GENAP'][rand(0, 1)],
                'status' => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'][rand(0, 3)],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // insertOrIgnore mencegah error berhenti jika terjadi duplikasi acak
        DB::table('enrollments')->insertOrIgnore($batch); 
        $inserted += $batchSize;
        $this->info("Telah menyisipkan {$inserted} / {$total} baris.");
    }

    $this->info("Seeding selesai!");
}
```

---

## 4. Struktur Awal Backend: Transaksi Atomik (Create)
Transaksi database (Atomicity) menjamin bahwa jika salah satu proses insert gagal, seluruh data di-rollback (dibatalkan).

**Controller (`EnrollmentController.php`):**
```php
use Illuminate\Support\Facades\DB;
use App\Models\{Student, Course, Enrollment};
use Illuminate\Http\Request;

public function store(Request $request)
{
    try {
        DB::transaction(function () use ($request) {
            // 1. Buat atau cari Student
            $student = Student::firstOrCreate(
                ['nim' => $request->student_nim],
                ['name' => $request->student_name, 'email' => $request->student_email]
            );

            // 2. Buat atau cari Course
            $course = Course::firstOrCreate(
                ['code' => $request->course_code],
                ['name' => $request->course_name, 'credits' => $request->course_credits]
            );

            // 3. Masukkan Enrollment
            Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'academic_year' => $request->academic_year,
                'semester' => $request->semester,
                'status' => 'DRAFT',
            ]);
        });

        return redirect()->back()->with('success', 'KRS Berhasil Disimpan.');
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Gagal menyimpan: ' . $e->getMessage()]);
    }
}
```

---

## 5. Struktur Awal Frontend: State Management Tabel Besar (React)
Saat menangani tabel jutaan data, kita **wajib** menggunakan *Server-Side Pagination* dan *debounce* inputan agar server tidak terkena DDoSed oleh request yang terlalu cepat.

**Setup Hook Custom Debounce (`resources/js/hooks/useDebounce.js`):**
```javascript
import { useState, useEffect } from 'react';

export default function useDebounce(value, delay = 500) {
    const [debouncedValue, setDebouncedValue] = useState(value);
    useEffect(() => {
        const handler = setTimeout(() => setDebouncedValue(value), delay);
        return () => clearTimeout(handler);
    }, [value, delay]);
    return debouncedValue;
}
```

**Kerangka Komponen UI (`resources/js/Pages/EnrollmentIndex.jsx`):**
```javascript
import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import useDebounce from '@/hooks/useDebounce';

export default function EnrollmentIndex({ enrollments, filters }) {
    // 1. State Filter
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    
    // 2. Tunda (Debounce) search 500ms
    const debouncedSearch = useDebounce(search, 500);

    // 3. Panggil API secara reaktif (Inertia otomatis mengganti state tabel)
    useEffect(() => {
        router.get('/enrollments', {
            search: debouncedSearch,
            status: status
        }, {
            preserveState: true, 
            replace: true        
        });
    }, [debouncedSearch, status]);

    return (
        <div className="p-6 max-w-7xl mx-auto">
            {/* ... Komponen UI Pencarian & Filter ... */}
            
            <table className="w-full text-left border-collapse">
                <thead>
                    <tr><th>NIM</th><th>Nama</th><th>Mata Kuliah</th><th>Status</th></tr>
                </thead>
                <tbody>
                    {enrollments.data.map((item) => (
                        <tr key={item.id} className="border-t">
                            <td>{item.student.nim}</td>
                            <td>{item.student.name}</td>
                            <td>{item.course.name}</td>
                            <td>{item.status}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {/* Pagination Button */}
            <div className="mt-4 flex gap-2">
                {enrollments.links.map((link, idx) => (
                   <button 
                       key={idx} 
                       disabled={!link.url}
                       onClick={() => router.get(link.url, {}, {preserveState:true})}
                       className={`px-3 py-1 border ${link.active ? 'bg-blue-500 text-white' : ''}`}
                       dangerouslySetInnerHTML={{__html: link.label}}
                   />
                ))}
            </div>
        </div>
    );
}
```
