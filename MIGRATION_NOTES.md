# Catatan Migrasi Database (Aiven MySQL)

Karena Aiven MySQL Free Tier memiliki limit penyimpanan **1GB**, Anda tidak bisa mengunggah atau melakukan seeding seluruh 5 juta baris data (yang memakan sekitar 2GB+ dengan *index*). 

Jika Anda ingin menghindari *seeding* ulang dari awal dan memilih untuk memindahkan sebagian data (misalnya 750.000 baris pertama) dari localhost Anda yang sudah ada ke Aiven, berikut adalah panduannya:

## 1. Export Data Lokal Menggunakan LIMIT
Buka terminal laptop Anda dan jalankan perintah `mysqldump` ini untuk mengambil struktur tabel dan hanya 750.000 data:

```bash
mysqldump -u root -p database_name --tables students courses > 1_master_data.sql
mysqldump -u root -p database_name --tables enrollments --where="1 LIMIT 750000" > 2_enrollments.sql
```
*(Ubah `database_name` dengan nama database lokal Anda)*

## 2. Import ke Aiven MySQL
Setelah Anda membuat layanan MySQL di Aiven, catat **Service URI** atau jalankan perintah CLI mysql bawaan untuk mengimpor data yang sudah Anda *dump* tadi.

```bash
mysql -u [aiven_user] -p -h [aiven_host] -P [aiven_port] [aiven_database] < 1_master_data.sql
mysql -u [aiven_user] -p -h [aiven_host] -P [aiven_port] [aiven_database] < 2_enrollments.sql
```

## 3. Catatan Penting
- Memindahkan data 750.000 baris melalui internet (koneksi rumah/lokal ke Aiven) akan membutuhkan waktu *upload* beberapa menit.
- Aiven secara otomatis akan memonitor ukuran *disk* Anda. Jika mencapai 99%, koneksi tulis (*write*) biasanya akan dibekukan (*read-only*) untuk melindungi server mereka. Jadi angka 750.000 (sekitar 350-500 MB) adalah batas aman (*sweet spot*) yang disarankan.
