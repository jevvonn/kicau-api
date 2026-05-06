# Kicau Api - Kanca Clone API

REST API backend untuk **Kicau**, proyek akhir mata kuliah **Teknologi Integrasi Sistem**.

> Terinspirasi dari aplikasi **Kanca** — pemenang **BI OJK Hackathon 2025** yang dikembangkan oleh tim dari Unversitas Binus.

---

## Tim Pengembang

| Nama             |
| ---------------- |
| Jevon Mozart     |
| A. Muflih Azhari |
| Raisya Aqilla    |
| Nayla Shafa      |

---

## Dokumentasi API

Akses dokumentasi lengkap endpoint API di Postman:

**[Kanca Clone API — Postman Docs](https://www.postman.com/jevvonn-team/workspace/kanca-clone-projek-akhir-tis)**

---

## Instalasi Lokal

### Prasyarat

- PHP >= 8.2
- Composer
- MySQL / MariaDB
- Git

### Langkah-langkah

**1. Clone repository**

```bash
git clone https://github.com/jevvonn/kanca-clone-api.git
cd kanca-clone-api
```

**2. Install dependencies**

```bash
composer install
```

**3. Salin file environment**

```bash
cp .env.example .env
```

**4. Generate application key**

```bash
php artisan key:generate
```

**5. Konfigurasi database**

Buka file `.env` dan sesuaikan konfigurasi database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanca_clone
DB_USERNAME=root
DB_PASSWORD=
```

**6. Jalankan migrasi database**

```bash
php artisan migrate
```

**7. Buat symbolic link storage**

```bash
php artisan storage:link
```

**8. Jalankan server lokal**

```bash
php artisan serve
```

API dapat diakses di `http://127.0.0.1:8000`

---

## Autentikasi

API menggunakan **Laravel Sanctum** dengan Bearer Token. Sertakan header berikut pada setiap request yang membutuhkan autentikasi:

```
Authorization: Bearer <token>
Accept: application/json
```
