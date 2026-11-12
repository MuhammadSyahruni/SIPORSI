# SIPORSI — Sistem Portofolio Digital Siswa SMK

## Stack
- PHP Native Modular (backend utama)
- MySQL melalui PDO
- HTML, CSS, JavaScript
- Bootstrap 5
- Python sebagai layanan tambahan opsional pada tahap lanjutan

## Menjalankan di XAMPP
1. Ekstrak folder `siporsi` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dari XAMPP.
3. Buka `http://localhost/siporsi/public/`.
4. Database belum dibuat pada Tahap 1. Skema database disiapkan pada Tahap 2.

## Catatan konfigurasi
Periksa `app/config/app.php` jika nama folder proyek berubah.
Periksa `app/config/database.php` saat database sudah dibuat.

## Keamanan
- Gunakan PDO prepared statements untuk query dinamis.
- Gunakan fungsi `e()` untuk output HTML.
- Simpan file privat di luar folder `public`.
- Aktifkan HTTPS dan cookie Secure saat deployment.
- Jangan gunakan konfigurasi akun default XAMPP untuk server produksi.
