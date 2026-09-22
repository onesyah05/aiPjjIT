<p align="center">
  <img src="public/images/sibermu-logo.png" width="140" alt="Logo Universitas Siber Muhammadiyah">
</p>

<h1 align="center">PJJ AI</h1>

<p align="center">
  Asisten belajar berbasis komunitas untuk mahasiswa PJJ Informatika Universitas Siber Muhammadiyah.
</p>

## Tentang proyek

PJJ AI membantu anggota komunitas mencari jawaban dari materi kuliah yang sudah ditinjau. Pengguna masuk dengan akun Discord, membuat percakapan, mengelola knowledge umum atau per mata kuliah, serta menyumbangkan credential Gemini untuk dipakai bersama sesuai pengaturan kontribusi.

## Fitur

- Login Discord OAuth dengan verifikasi keanggotaan server dan pemetaan role admin, reviewer, atau mahasiswa.
- Percakapan dengan respons Gemini yang dikirim melalui Server-Sent Events.
- Jawaban Markdown dengan sumber knowledge yang dapat dibuka.
- Knowledge umum dan knowledge mata kuliah dari teks langsung atau berkas `.md`.
- Penyuntingan, riwayat versi, moderasi, dan pemrosesan embedding knowledge.
- Retrieval berbasis vector melalui Qdrant dengan fallback pencarian database.
- Kontribusi credential Gemini yang disimpan terenkripsi, pooling credential, pencatatan penggunaan, dan pemeriksaan kesehatan.
- Pengelolaan mata kuliah, pengguna, credential, review knowledge, dan audit log untuk admin.
- Dashboard aktivitas, pencarian percakapan, feedback jawaban, badge, dan leaderboard kontribusi.

## Teknologi

- PHP 8.3 dan Laravel 13
- React 18, Inertia.js 2, dan Tailwind CSS
- Gemini API untuk chat dan embedding
- Qdrant untuk vector retrieval
- SQLite sebagai konfigurasi awal, dengan dukungan database Laravel lainnya
- PHPUnit untuk pengujian

## Persyaratan

- PHP 8.3 atau lebih baru
- Composer 2
- Node.js 22 atau lebih baru dan npm
- Aplikasi Discord dengan OAuth2
- API key Gemini yang ditambahkan melalui menu Kontribusi
- Qdrant jika vector retrieval akan diaktifkan

## Instalasi

Clone repository dan pasang dependency:

```bash
git clone https://github.com/onesyah05/aiPjjIT.git
cd aiPjjIT
composer install
npm install
```

Salin konfigurasi lingkungan:

```bash
cp .env.example .env
php artisan key:generate
```

Untuk Windows PowerShell, gunakan perintah berikut sebagai pengganti `cp`:

```powershell
Copy-Item .env.example .env
```

Konfigurasi awal menggunakan SQLite. Buat file database jika belum tersedia:

```bash
touch database/database.sqlite
```

Untuk Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File -Force
```

Jalankan migration dan build frontend:

```bash
php artisan migrate
npm run build
```

## Konfigurasi Discord

Isi bagian berikut di `.env`:

```dotenv
APP_URL=http://localhost:8000

DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
DISCORD_GUILD_ID=
DISCORD_MEMBERSHIP_RECHECK_HOURS=24
DISCORD_ADMIN_ROLE_IDS=
DISCORD_REVIEWER_ROLE_IDS=
```

Tambahkan URI berikut ke daftar OAuth2 Redirects pada Discord Developer Portal:

```text
http://localhost:8000/auth/discord/callback
```

`DISCORD_GUILD_ID` adalah ID server Discord. `DISCORD_ADMIN_ROLE_IDS` dan `DISCORD_REVIEWER_ROLE_IDS` menerima satu atau beberapa role ID yang dipisahkan dengan koma. Role pengguna diperbarui kembali saat login atau ketika pemeriksaan keanggotaan dijalankan.

## Konfigurasi Gemini

Pengaturan model tersedia di `.env`:

```dotenv
AI_DEFAULT_PROVIDER=gemini
AI_DEFAULT_MODEL=gemini-2.5-flash
AI_EMBEDDING_MODEL=gemini-embedding-001
AI_EMBEDDING_DIMENSIONS=768
AI_REQUEST_TIMEOUT=60
AI_MAX_CREDENTIAL_ATTEMPTS=3
```

Setelah login, buka menu **Kontribusi** untuk menambahkan API key Gemini. Credential disimpan menggunakan encrypted cast Laravel dan tidak ditampilkan kembali dalam bentuk utuh.

## Qdrant dan embedding

Qdrant bersifat opsional. Aktifkan melalui `.env`:

```dotenv
QDRANT_ENABLED=true
QDRANT_URL=http://localhost:6333
QDRANT_API_KEY=
QDRANT_COLLECTION=knowledge
QDRANT_TIMEOUT=10
QDRANT_SCORE_THRESHOLD=0.35
```

Pastikan queue worker berjalan agar knowledge yang disetujui dapat diproses:

```bash
php artisan queue:work
```

Untuk memasukkan ulang seluruh knowledge aktif yang sudah disetujui ke antrean Qdrant:

```bash
php artisan knowledge:reindex
```

## Menjalankan aplikasi

Jalankan seluruh proses pengembangan melalui Composer:

```bash
composer run dev
```

Kemudian buka:

```text
http://localhost:8000/login
```

Dashboard admin tersedia di `/admin` untuk pengguna yang memiliki role Discord sesuai `DISCORD_ADMIN_ROLE_IDS`.

## Pengujian

```bash
php artisan test --compact
```

Build production frontend dan SSR:

```bash
npm run build
```

## Keamanan

- Jangan commit `.env`, database lokal, token Discord, atau API key Gemini.
- Gunakan `APP_DEBUG=false` pada production.
- Pastikan queue worker dan scheduler dijalankan oleh process manager pada production.
- Samakan `APP_URL` dan `DISCORD_REDIRECT_URI` dengan domain deployment.

## Lisensi

Proyek ini menggunakan lisensi MIT.
