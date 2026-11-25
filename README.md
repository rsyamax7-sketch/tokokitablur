# 📚 Pembelajaran Database MySQL/MariaDB & Sistem POS Toko Kita

Repository ini berisi materi dan praktik pembelajaran database menggunakan **MySQL/MariaDB**, mulai dari penguasaan konsep SQL tingkat dasar hingga lanjutan, serta implementasinya dalam membangun aplikasi web kasir full-stack (**Toko Kita**) berbasis Role-Based Access Control (RBAC), multi-kasir (**Kasir 1 & Kasir 2**), dan integrasi kasir/keranjang.

---

## 🎯 Tujuan Pembelajaran & Proyek

- Memahami dan mengimplementasikan konsep Database View, JOIN, Subquery, Stored Procedure, Stored Function, serta Database Trigger.
- Menerapkan **Role-Based Access Control (RBAC)** untuk memisahkan hak akses antara panel **Admin** (biru/indigo), **Kasir** (hijau emerald / biru sky), dan **Pelanggan**.
- Mengelola data inventaris produk lengkap dengan fitur **unggah foto/gambar produk (*file upload*)** serta barcode.
- Mendukung **2 kasir terpisah (Kasir 1 & Kasir 2)** agar total transaksi, riwayat transaksi, dan keranjang masing-masing kasir tidak tercampur.
- Membangun alur transaksi *multi-item* berbasis modal keranjang belanja, *auto-scan barcode*, kode voucher diskon, kalkulasi pajak (PPN), metode pembayaran Tunai & QRIS, kalkulasi uang tunai/kembalian otomatis, hingga pencetakan struk.

---

## 📖 Daftar Materi Database

| Bab | Materi | Keterangan |
|---|---|---|
| 1 | Database View | Membuat tabel virtual untuk menyederhanakan query |
| 2 | JOIN | Menggabungkan data relasional antar tabel |
| 3 | Subquery | Query bersarang di dalam query utama |
| 4 | Stored Procedure | Membuat instruksi prosedur SQL yang dapat dipanggil kembali |
| 5 | Stored Function | Membuat fungsi khusus SQL dengan pengembalian nilai |
| 6 | Database Trigger | Menjalankan aksi query otomatis saat ada manipulasi data (INSERT/UPDATE/DELETE) |

---

## 🕒 Riwayat Perkembangan Proyek (Dari Awal hingga Terbaru)

### 1. Tahap Awal: Fondasi Database & Aplikasi POS Dasar
- Merancang relasi tabel utama (`tbl_produk`, `tbl_kategori`, `tbl_pelanggan`, `tbl_transaksi`, `tbl_detail_transaksi`) dan koneksi PHP-MySQLi (`koneksi.php`).
- Membangun panel **Admin** dan panel **Kasir** dasar untuk mengelola produk, pelanggan, transaksi, serta laporan penjualan.

### 2. Tahap Pengembangan Fitur Kasir, Inventaris & Pembayaran
- Menambahkan fitur *file upload* foto produk serta tampilan barcode produk.
- Mengembangkan alur keranjang belanja kasir, *auto-scan barcode*, kode voucher diskon (`HEMAT10`, `POTONG50K`, `KASIR100K`, `SUPER20`), pengaturan tarif pajak (PPN), pembayaran **Tunai** dan **QRIS**, serta cetak struk.

### 3. Tahap Terbaru: Portal Login, Pemisahan Kasir 1 & Kasir 2, dan Filter Laporan
- **Perapihan Portal Login (`login.php`):** Menghilangkan kolom *Scan Kartu Instan* pada portal utama sehingga fokus mengarahkan ke halaman login peran (`login_admin.php`, `login_kasir.php`, `login_pelanggan.php`) serta login akun langsung.
- **Login Kasir 1 & Kasir 2 (`login_kasir.php`):** Mendukung pemilihan **Kasir 1** (mengarah ke `dashboard_kasir.php`) maupun **Kasir 2** (mengarah ke `dashboard_kasir2.php`) melalui tombol pilih kasir, scan kartu (`KASIR-001` / `KASIR-002`), atau login username/password, sambil menampilkan total transaksi masing-masing kasir.
- **Dashboard & Katalog Khusus Kasir 2 (`dashboard_kasir2.php` & `data_produk_kasir2.php`):** Memisahkan sesi, keranjang (`keranjang_kasir2`), struk (`last_struk_bayar_kasir2`), dan pencatatan transaksi (`id_pengguna` & `kasir = 'Kasir 2'`) agar transaksi Kasir 2 tidak tercampur ke Kasir 1.
- **Filter Kasir di Laporan (`laporan_penjualan.php`):** Menambahkan filter **Semua Kasir**, **Kasir 1**, dan **Kasir 2** beserta rekapitulasi total transaksi, item terjual, dan pendapatan.

---

## 🛠️ Teknologi & Environment

- **Database Management:** MySQL, MariaDB, phpMyAdmin, XAMPP
- **Backend & Scripting:** PHP Native (Session handling, File Upload, MySQLi)
- **Frontend & UI/UX:** HTML5, JavaScript (DOM, Modal Handler, JsBarcode), Tailwind CSS, Font Awesome 6
- **Hardware Integration:** POS Receipt / Thermal Printer

---

## 💻 Contoh Query SQL & Fitur Sistem

### 1. DDL & Penambahan Kolom Gambar serta Penanda Kasir
```sql
ALTER TABLE tbl_produk ADD COLUMN gambar VARCHAR(255) DEFAULT NULL;
ALTER TABLE tbl_transaksi ADD COLUMN id_pengguna INT NULL DEFAULT NULL;
ALTER TABLE tbl_transaksi ADD COLUMN kasir VARCHAR(50) NULL DEFAULT NULL;