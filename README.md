# 📦 Internal Procurement System API

Sistem backend procurement tingkat tinggi yang dirancang untuk skala besar (Large Scale), performa tinggi, dan integritas data yang ketat. Dibangun dengan **Laravel 12** dan **PostgreSQL 14**.

---

## 🚀 Fitur Utama & Engineering Advanced

### 1. High Performance Big Data
- **5 Juta Record Seeding**: Seeder yang dioptimalkan dengan *transactional batch inserts* dan *chunking* untuk memproses 5 juta data request berserta items dan history status tanpa membebani memori.
- **Database Partitioning**: Implementasi *Range Partitioning* pada tabel `requests` berdasarkan tahun untuk menjaga kecepatan query seiring bertambahnya data.
- **Chunked CSV Export**: Export data jutaan baris menggunakan *PHP Stream* dan *Eloquent Chunking* untuk menghindari error *Out of Memory (OOM)*.

### 2. Concurrency & Data Integrity
- **Double Approval Prevention**: Menggunakan **Pessimistic Locking** (`lockForUpdate`) untuk memastikan tidak ada dua orang yang bisa memproses satu request di detik yang sama.
- **Parallel Modification (Optimistic Locking)**: Setiap update status harus menyertakan `last_updated_at`. Jika data di database sudah berubah oleh user lain (Conflict), sistem akan menolak dengan **HTTP 409 Conflict**.
- **Atomic Stock Update**: Mencegah *Race Condition* stok dengan melakukan pengurangan stok langsung di level database menggunakan SQL Atomic Increment/Decrement. Stok tidak pernah dihitung di PHP untuk mencegah data basi.

### 3. Scalability & Analytics
- **Read/Write Replica Support**: Mendukung konfigurasi database host terpisah untuk operasi baca dan tulis.
- **Redis Caching**: Caching data master dan laporan dashboard (misal: Top Departments) selama 1 jam untuk meningkatkan responsivitas API.
- **Reporting Analytics**: 
  - `Monthly Category Leaderboard`: Tren kategori barang terpopuler per bulan.
  - `Lead Time Metric`: Rata-rata waktu pemrosesan dari submission hingga completion.
- **Cold Storage (Archiving)**: Fitur pengarsipan otomatis untuk memindahkan data permohonan yang sudah selesai/ditolak lebih dari 2 tahun ke tabel cold storage.

---

## 🛠️ Stack Teknologi
- **Core**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 14 (Partitioning, Row-Level Locking)
- **Cache & Queue**: Redis
- **Docs**: L5-Swagger (OpenAPI 3.0)
- **Security**: Laravel Sanctum

---

## 📖 Cara Penggunaan & API

### Setup Project
1. Clone repository dan install dependensi: `composer install`
2. Atur `.env` (isi DB_READ_HOST dan DB_WRITE_HOST jika ada replica).
3. Jalankan migrasi & data master: `php artisan migrate --seed`
4. **Opsional (Large Scale Testing)**: Jalankan seeder 5 juta record:
   `php artisan db:seed --class=LargeScaleRequestSeeder`

### Dokumentasi API
Akses Swagger UI untuk melihat daftar endpoint lengkap dan mencobanya langsung:
`http://localhost:8000/api/documentation`

### Endpoint Workflow Utama:
- `POST /api/requests/{id}/approve` (Manager Only)
- `POST /api/requests/{id}/check-stock` ( Gudang/Purchasing)
- `POST /api/requests/{id}/procure` (Generate PO)
- `GET /api/requests/export` (Export CSV 5M records)

---

## 📊 Analytics Query Examples
Laporan statistik dapat diakses melalui:
- `/api/reports/monthly-categories`
- `/api/reports/lead-time`
