# Sistem Lelang Service Katalog

## Identitas

Nama: Rafly Zulfikar AlKautsar

NIM: 102022400192

Kelas: SI-48-08

---

## Fitur Utama

* **RESTful API Standar Kontrak**: Menyediakan 4 *endpoint* utama dengan format *response wrapper* JSON yang konsisten.
* **Header Authentication**: Semua *endpoint* dilindungi menggunakan API Key khusus (`X-IAE-KEY`).
* **Interactive API Documentation**: Dilengkapi dengan Swagger UI (OpenAPI 3.0) menggunakan PHP Attributes terbaru.
* **GraphQL Implementation**: Mendukung kueri GraphQL yang dinamis beserta antarmuka GraphQL Playground untuk pengujian.
* **Dockerized Environment**: Berjalan secara terisolasi menggunakan Docker dan SQLite untuk kemudahan pengujian.

---

## Library & Teknologi

| Komponen | Teknologi / Library |
| :--- | :--- |
| **Framework** | Laravel 12 (PHP 8.2) |
| **Database** | SQLite (Bawaan) |
| **API Documentation** | `darkaonline/l5-swagger` |
| **GraphQL Engine** | `nuwave/lighthouse` |
| **GraphQL UI** | `mll-lab/laravel-graphiql` |

---

## Syarat Sistem

* **Docker Engine / Docker Desktop** (Berjalan di latar belakang)
* **Terminal / Windows PowerShell**
* **Git** (Opsional, untuk proses *clone*)

## Instalasi Lokal

**1. Clone repository dan masuk ke direktori proyek:**

```
git clone https://github.com/IAE-2026-48-08/102022400192_Lelang-Katalog-Service.git
cd TUBES-IAE
```

**2. Build dan jalankan container Docker:**

```
docker compose up --build -d
```

**3. Install dependensi PHP via Composer:**

```
docker compose run app composer install
```

**4. Generate Application Key:**

```
docker compose run app php artisan key:generate
```

**5. Siapkan Database SQLite:**

```
# Khusus pengguna Windows PowerShell:
New-Item database/database.sqlite -ItemType File
```

**6. Jalankan Migrasi Database:**

```
docker compose run app php artisan migrate
```

---

# Dokumentasi & Penggunaan API

**Autentikasi**

Seluruh endpoint REST maupun GraphQL dilindungi. Anda wajib menyertakan header berikut pada setiap request:

**- Key:** `X-IAE-KEY`

**- Value:** `102022400192`

**Swagger UI (REST API)**

Akses antarmuka dokumentasi Swagger untuk menguji endpoint REST secara langsung.

- **URL:** `http://localhost:8000/api/documentation`

**Endpoint REST yang tersedia:**

1. `GET /api/v1/items` (Mengambil semua daftar barang lelang)
2. `GET /api/v1/items/{id}` (Mengambil detail spesifik 1 barang)
3. `POST /api/v1/items/filter` (Memfilter daftar barang berdasarkan kriteria tertentu)
4. `POST /api/v1/items` (Menambahkan item baru ke katalog lelang)

**GraphQL Playground**

Akses antarmuka Playground untuk melakukan kueri data penawaran secara dinamis.

- **URL:** `http://localhost:8000/graphiql`
  
**Cara Pengujian:**

1. Klik tombol **HTTP HEADERS** di pojok kiri bawah Playground.
2. Masukkan header keamanan:

```
{
  "X-IAE-KEY": "102022400192"
}
```

3. Gunakan kueri berikut di panel kiri untuk mengambil data katalog barang lelang:

```
{
  items {
    id
    name
    description
    starting_price
    current_highest_bid
    auction_status
    auction_deadline
  }
}
```

4. Gunakan kueri berikut di panel kiri untuk mengambil data katalog barang spesifik:

```
{
  item(id: 1) {
    id
    name
    description
    starting_price
    auction_status
  }
}
```
