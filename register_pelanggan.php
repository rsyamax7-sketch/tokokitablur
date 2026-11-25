<?php
// register_pelanggan.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung arahkan sesuai role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit();
    } elseif ($_SESSION['role'] === 'kasir') {
        header("Location: dashboard_kasir.php");
        exit();
    } else {
        header("Location: belanja_pelanggan.php");
        exit();
    }
}

// Pastikan kolom uid_member tersedia di tbl_pelanggan dan tbl_pengguna
$cek_kol_pel = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pelanggan LIKE 'uid_member'");
if ($cek_kol_pel && mysqli_num_rows($cek_kol_pel) === 0) {
    @mysqli_query($koneksi, "ALTER TABLE tbl_pelanggan ADD COLUMN uid_member VARCHAR(100) NULL DEFAULT NULL");
}

$cek_kol_usr = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pengguna LIKE 'uid_member'");
if ($cek_kol_usr && mysqli_num_rows($cek_kol_usr) === 0) {
    @mysqli_query($koneksi, "ALTER TABLE tbl_pengguna ADD COLUMN uid_member VARCHAR(100) NULL DEFAULT NULL");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar_pelanggan'])) {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $kota         = trim($_POST['kota'] ?? 'Ponorogo');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $konfirmasi   = $_POST['konfirmasi_password'] ?? '';
    $uid_member   = strtoupper(trim($_POST['uid_member'] ?? ''));

    if ($nama_lengkap === '' || $username === '' || $password === '') {
        $error = "Nama lengkap, username, dan password wajib diisi.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak cocok dengan password.";
    } elseif (strlen($password) < 4) {
        $error = "Password minimal 4 karakter.";
    } else {
        $user_esc = mysqli_real_escape_string($koneksi, $username);
        $cek_user = mysqli_query($koneksi, "SELECT id_pengguna FROM tbl_pengguna WHERE username = '$user_esc' LIMIT 1");

        if ($cek_user && mysqli_num_rows($cek_user) > 0) {
            $error = "Username <b>" . htmlspecialchars($username) . "</b> sudah digunakan, pilih username lain.";
        } else {
            // Cek jika UID kartu sudah terdaftar pada pelanggan/pengguna lain
            $uid_bentrok = false;
            if ($uid_member !== '') {
                $uid_esc = mysqli_real_escape_string($koneksi, $uid_member);
                $cek_uid_pel = mysqli_query($koneksi, "SELECT id_pelanggan FROM tbl_pelanggan WHERE UPPER(uid_member) = '$uid_esc' LIMIT 1");
                $cek_uid_usr = mysqli_query($koneksi, "SELECT id_pengguna FROM tbl_pengguna WHERE UPPER(uid_member) = '$uid_esc' LIMIT 1");

                if (($cek_uid_pel && mysqli_num_rows($cek_uid_pel) > 0) || ($cek_uid_usr && mysqli_num_rows($cek_uid_usr) > 0)) {
                    $uid_bentrok = true;
                    $error = "Kartu / UID <b>" . htmlspecialchars($uid_member) . "</b> sudah terdaftar pada akun lain.";
                }
            }

            if (!$uid_bentrok) {
                $nama_esc      = mysqli_real_escape_string($koneksi, $nama_lengkap);
                $kota_esc      = mysqli_real_escape_string($koneksi, $kota !== '' ? $kota : 'Ponorogo');
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $uid_sql       = ($uid_member !== '') ? "'" . mysqli_real_escape_string($koneksi, $uid_member) . "'" : "NULL";

                // Simpan ke tbl_pelanggan
                $sql_pel     = "INSERT INTO tbl_pelanggan (nama_pelanggan, kota, uid_member) VALUES ('$nama_esc', '$kota_esc', $uid_sql)";
                $ok_pel      = mysqli_query($koneksi, $sql_pel);
                $id_pel_baru = $ok_pel ? intval(mysqli_insert_id($koneksi)) : 0;

                // Jika UID tidak diisi manual, buatkan kode kartu otomatis berbasis ID pelanggan (misal MBR-005)
                if ($uid_member === '' && $id_pel_baru > 0) {
                    $uid_member = 'MBR-' . str_pad($id_pel_baru, 3, '0', STR_PAD_LEFT);
                }
                $uid_sql_upd = ($uid_member !== '') ? "'" . mysqli_real_escape_string($koneksi, $uid_member) . "'" : "NULL";
                if ($id_pel_baru > 0 && $uid_member !== '') {
                    @mysqli_query($koneksi, "UPDATE tbl_pelanggan SET uid_member = $uid_sql_upd WHERE id_pelanggan = $id_pel_baru");
                }

                // Simpan ke tbl_pengguna dengan role pelanggan
                $sql_usr = "INSERT INTO tbl_pengguna (nama_lengkap, username, password, role, uid_member) VALUES ('$nama_esc', '$user_esc', '$password_hash', 'pelanggan', $uid_sql_upd)";
                $ok_usr  = mysqli_query($koneksi, $sql_usr);

                if ($ok_usr) {
                    // Jangan set $_SESSION login; langsung kembali ke login.php untuk memunculkan pop-up register berhasil
                    header("Location: login.php?registered=1&uid=" . urlencode($uid_member));
                    exit();
                } else {
                    $error = "Gagal menyimpan akun pelanggan: " . htmlspecialchars(mysqli_error($koneksi));
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan &amp; Scan Kartu - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-700 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        <!-- HEADER -->
        <div class="bg-slate-900 px-6 py-5 text-white flex items-center justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-sky-600 flex items-center justify-center shadow-lg shadow-sky-600/30 shrink-0">
                    <i class="fa-solid fa-user-plus text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight leading-tight">Registrasi Pelanggan</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Daftar Akun &amp; Kaitkan Kartu RFID / NFC</p>
                </div>
            </div>
            <a href="login.php" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-xl font-semibold transition">
                Ke Halaman Login
            </a>
        </div>

        <div class="p-6 space-y-4 text-xs">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= $error; ?></span>
                </div>
            <?php endif; ?>

            <form id="formRegisterPelanggan" action="" method="POST" class="space-y-4">
                <input type="hidden" name="daftar_pelanggan" value="1">

                <!-- KOLOM SCAN KARTU RFID / NFC -->
                <div class="bg-sky-50/80 border-2 border-sky-200 rounded-2xl p-4 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-sky-900 flex items-center gap-2">
                            <i class="fa-solid fa-wifi rotate-90 text-sky-600"></i>
                            <span>Scan Kartu / Tag RFID atau NFC</span>
                        </label>
                        <button type="button" onclick="fokusScanKartu()" class="bg-white hover:bg-sky-100 text-sky-700 border border-sky-300 px-2.5 py-1 rounded-lg text-[10px] font-bold transition">
                            <i class="fa-solid fa-crosshairs mr-1"></i> Fokus Tap Kartu
                        </button>
                    </div>

                    <input type="text" id="inputUidRegister" name="uid_member" value="<?= htmlspecialchars($_POST['uid_member'] ?? ''); ?>" autocomplete="off" placeholder="Tempelkan kartu RFID/NFC ke reader (atau kosongkan untuk auto-kode)..." class="w-full px-3.5 py-2.5 bg-white border border-sky-300 rounded-xl font-mono uppercase font-bold text-slate-800 outline-none focus:ring-2 focus:ring-sky-500">
                    <p class="text-[11px] text-slate-600">
                        Setelah daftar berhasil, Anda akan kembali ke halaman <b>Login</b> dan dapat langsung <b>scan kartu</b> untuk masuk.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" required value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>" placeholder="Contoh: Budi Santoso" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Kota</label>
                        <input type="text" name="kota" value="<?= htmlspecialchars($_POST['kota'] ?? 'Ponorogo'); ?>" placeholder="Contoh: Ponorogo" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Username *</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Buat username untuk login" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Password *</label>
                        <input type="password" name="password" required placeholder="Minimal 4 karakter" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Konfirmasi Password *</label>
                        <input type="password" name="konfirmasi_password" required placeholder="Ulangi password" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                    </div>
                </div>

                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white py-2.5 rounded-xl font-bold transition shadow-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-user-check"></i> Daftar Akun Pelanggan
                </button>
            </form>

            <div class="text-center pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                Sudah punya akun atau kartu terdaftar?
                <a href="login.php" class="text-sky-600 hover:text-sky-800 font-bold underline">Login / Scan Kartu di sini</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const inpUid = document.getElementById('inputUidRegister');
            if (inpUid) {
                inpUid.focus();
                inpUid.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        this.value = this.value.trim().toUpperCase();
                    }
                });
            }
        });

        function fokusScanKartu() {
            const inpUid = document.getElementById('inputUidRegister');
            if (inpUid) {
                inpUid.focus();
                inpUid.select();
            }
        }
    </script>
</body>
</html>