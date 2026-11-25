<?php
// login_pelanggan.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'pelanggan') {
        header("Location: belanja_pelanggan.php");
        exit();
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit();
    } elseif ($_SESSION['role'] === 'kasir') {
        header("Location: dashboard_kasir.php");
        exit();
    }
}

$show_popup_register = (isset($_GET['registered']) && $_GET['registered'] === '1');
$uid_terdaftar_popup = strtoupper(trim($_GET['uid'] ?? ''));
$error = '';

// 1. LOGIN PELANGGAN VIA SCAN KARTU RFID / NFC (MENDUKUNG SEMUA KARTU PELANGGAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_pelanggan_login'])) {
    $uid_scan = strtoupper(trim($_POST['uid_member'] ?? ''));

    if ($uid_scan !== '') {
        $uid_esc = mysqli_real_escape_string($koneksi, $uid_scan);
        $nama_pelanggan   = 'Member (' . $uid_scan . ')';
        $id_pengguna_sesi = 0;
        $username_sesi    = 'pelanggan';

        // Cek UID kartu di tbl_pengguna (role pelanggan)
        $cek_kol_usr = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pengguna LIKE 'uid_member'");
        if ($cek_kol_usr && mysqli_num_rows($cek_kol_usr) > 0) {
            $q_usr_uid = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE UPPER(uid_member) = '$uid_esc' AND role = 'pelanggan' LIMIT 1");
            if ($q_usr_uid && mysqli_num_rows($q_usr_uid) > 0) {
                $u_row            = mysqli_fetch_assoc($q_usr_uid);
                $nama_pelanggan   = $u_row['nama_lengkap'];
                $id_pengguna_sesi = intval($u_row['id_pengguna']);
                $username_sesi    = $u_row['username'];
            }
        }

        // Jika belum ketemu, cek di tbl_pelanggan
        if ($id_pengguna_sesi === 0) {
            $cek_kol_pel = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pelanggan LIKE 'uid_member'");
            $sql_pel = ($cek_kol_pel && mysqli_num_rows($cek_kol_pel) > 0)
                ? "SELECT * FROM tbl_pelanggan WHERE UPPER(uid_member) = '$uid_esc' OR UPPER(nama_pelanggan) = '$uid_esc' OR CONCAT('MBR-', LPAD(id_pelanggan, 3, '0')) = '$uid_esc' OR CAST(id_pelanggan AS CHAR) = '$uid_esc' LIMIT 1"
                : "SELECT * FROM tbl_pelanggan WHERE UPPER(nama_pelanggan) = '$uid_esc' OR CONCAT('MBR-', LPAD(id_pelanggan, 3, '0')) = '$uid_esc' OR CAST(id_pelanggan AS CHAR) = '$uid_esc' LIMIT 1";

            $q_pel = mysqli_query($koneksi, $sql_pel);
            if ($q_pel && mysqli_num_rows($q_pel) > 0) {
                $pel              = mysqli_fetch_assoc($q_pel);
                $nama_pelanggan   = $pel['nama_pelanggan'];
                $id_pengguna_sesi = intval($pel['id_pelanggan']);
            }
        }

        $_SESSION['id_pengguna']  = $id_pengguna_sesi;
        $_SESSION['nama_lengkap'] = $nama_pelanggan;
        $_SESSION['username']     = $username_sesi;
        $_SESSION['role']         = 'pelanggan';
        $_SESSION['uid_member']   = $uid_scan;

        header("Location: belanja_pelanggan.php");
        exit();
    } else {
        $error = "Silakan tap kartu RFID/NFC atau masukkan kode kartu terlebih dahulu.";
    }
}

// 2. LOGIN PELANGGAN MANUAL (USERNAME & PASSWORD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_manual_pelanggan'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $q_usr = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE username = '$username' AND role = 'pelanggan' LIMIT 1");
        if ($q_usr && mysqli_num_rows($q_usr) === 1) {
            $user = mysqli_fetch_assoc($q_usr);
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['id_pengguna']  = $user['id_pengguna'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = 'pelanggan';

                header("Location: belanja_pelanggan.php");
                exit();
            } else {
                $error = "Password Pelanggan salah.";
            }
        } else {
            $error = "Akun Pelanggan tidak ditemukan.";
        }
    } else {
        $error = "Mohon isi username dan password Pelanggan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-700 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        <!-- HEADER PELANGGAN -->
        <div class="bg-slate-900 px-6 py-5 text-white flex items-center justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-sky-600 flex items-center justify-center shadow-lg shadow-sky-600/30 shrink-0">
                    <i class="fa-solid fa-bag-shopping text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight leading-tight">Login Pelanggan</h1>
                    <p class="text-xs text-sky-300 mt-0.5">Area Belanja Toko Kita</p>
                </div>
            </div>
            <span class="bg-sky-600/20 border border-sky-500/40 text-sky-300 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase">Pelanggan</span>
        </div>

        <div class="p-6 space-y-5 text-xs">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- PANEL SCAN KARTU PELANGGAN -->
            <div class="bg-sky-50/80 border-2 border-sky-200 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="font-bold text-sky-900 flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-wifi rotate-90 text-sky-600"></i>
                        <span>Scan Kartu Pelanggan</span>
                    </div>
                    <span class="bg-sky-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full">RFID / NFC</span>
                </div>

                <p class="text-[11px] text-slate-600">
                    Tap kartu/tag RFID atau NFC apa pun ke reader untuk langsung masuk ke <b>Halaman Belanja</b>.
                </p>

                <form id="formScanPelanggan" action="" method="POST" class="space-y-2.5">
                    <input type="hidden" name="scan_pelanggan_login" value="1">
                    <div class="flex items-center gap-2">
                        <input type="text" id="inputUidPelanggan" name="uid_member" autofocus autocomplete="off" placeholder="Tap kartu RFID/NFC atau ketik kode..." class="flex-1 px-3.5 py-2.5 bg-white border border-sky-300 rounded-xl font-mono uppercase font-bold text-slate-800 outline-none focus:ring-2 focus:ring-sky-500">
                        <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl font-bold transition shadow-sm shrink-0">
                            Masuk
                        </button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-[10px] text-slate-500 font-semibold">Tes cepat:</span>
                    <button type="button" onclick="scanCepatPelanggan('04A1B2C3D4')" class="bg-white hover:bg-sky-100 text-sky-800 border border-sky-300 px-2 py-0.5 rounded-md font-mono font-bold text-[10px] transition">
                        04A1B2C3D4
                    </button>
                    <button type="button" onclick="scanCepatPelanggan('MBR-001')" class="bg-white hover:bg-sky-100 text-sky-800 border border-sky-300 px-2 py-0.5 rounded-md font-mono font-bold text-[10px] transition">
                        MBR-001
                    </button>
                </div>
            </div>

            <div class="relative flex items-center py-1">
                <div class="flex-grow border-t border-slate-200"></div>
                <span class="mx-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Atau Login Akun Pelanggan</span>
                <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <!-- FORM LOGIN MANUAL PELANGGAN -->
            <form action="" method="POST" class="space-y-3.5">
                <input type="hidden" name="login_manual_pelanggan" value="1">
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Username Pelanggan</label>
                    <input type="text" name="username" required placeholder="Masukkan username pelanggan" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Password</label>
                    <input type="password" name="password" required placeholder="Masukkan password" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                </div>
                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white py-2.5 rounded-xl font-bold transition shadow-sm">
                    Login sebagai Pelanggan
                </button>
            </form>

            <!-- TAUTAN REGISTRASI PELANGGAN -->
            <div class="text-center pt-2 border-t border-slate-100 text-[11px] text-slate-600">
                Pelanggan baru &amp; ingin mendaftarkan kartu RFID/NFC?
                <a href="register_pelanggan.php" class="text-sky-600 hover:text-sky-800 font-bold underline">Daftar Pelanggan di sini</a>
            </div>

            <!-- PINDAH HALAMAN LOGIN ROLE LAIN -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Login Petugas / Admin?</span>
                <div class="flex items-center gap-3 font-bold">
                    <a href="login_admin.php" class="text-indigo-600 hover:underline">Login Admin</a>
                    <span>•</span>
                    <a href="login_kasir.php" class="text-emerald-600 hover:underline">Login Kasir</a>
                </div>
            </div>
        </div>
    </div>

    <!-- POP-UP REGISTER BERHASIL -->
    <?php if ($show_popup_register): ?>
    <div id="popupRegisterBerhasil" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-emerald-200 text-center p-6 space-y-4">
            <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="space-y-1">
                <h2 class="text-base font-extrabold text-slate-800">Register Berhasil!</h2>
                <p class="text-xs text-slate-600">Akun pelanggan Anda sudah terdaftar. Silakan tap kartu RFID/NFC atau login untuk mulai belanja.</p>
                <?php if ($uid_terdaftar_popup !== ''): ?>
                    <div class="pt-2">
                        <span class="text-[11px] text-slate-500">Kode / UID Kartu:</span>
                        <div class="font-mono font-extrabold text-sm text-sky-700 bg-sky-50 border border-sky-200 rounded-xl py-1.5 px-3 mt-1 inline-block">
                            <?= htmlspecialchars($uid_terdaftar_popup); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" onclick="tutupPopupRegister()" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                OK, Lanjut Scan / Login
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const inp = document.getElementById('inputUidPelanggan');
            const popup = document.getElementById('popupRegisterBerhasil');

            if (inp) {
                if (!popup) {
                    inp.focus();
                }
                inp.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        this.value = this.value.trim().toUpperCase();
                        if (this.value !== '') {
                            document.getElementById('formScanPelanggan').submit();
                        }
                    }
                });
            }
        });

        function tutupPopupRegister() {
            const popup = document.getElementById('popupRegisterBerhasil');
            if (popup) popup.classList.add('hidden');
            const inp = document.getElementById('inputUidPelanggan');
            if (inp) inp.focus();
        }

        function scanCepatPelanggan(kode) {
            const inp = document.getElementById('inputUidPelanggan');
            const frm = document.getElementById('formScanPelanggan');
            if (inp && frm) {
                inp.value = kode;
                frm.submit();
            }
        }
    </script>
</body>
</html>