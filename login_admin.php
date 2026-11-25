<?php
// login_admin.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit();
    } elseif ($_SESSION['role'] === 'kasir') {
        header("Location: dashboard_kasir.php");
        exit();
    } elseif ($_SESSION['role'] === 'pelanggan') {
        header("Location: belanja_pelanggan.php");
        exit();
    }
}

$error = '';

// Daftar UID/kode kartu default khusus Admin
$admin_card_map = [
    'ADMIN-001' => ['username' => 'admin', 'nama' => 'Administrator'],
    'ADM-001'   => ['username' => 'admin', 'nama' => 'Administrator'],
];

// 1. LOGIN ADMIN VIA SCAN KARTU RFID / NFC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_admin_login'])) {
    $uid_scan = strtoupper(trim($_POST['uid_member'] ?? ''));

    if ($uid_scan !== '') {
        $uid_esc   = mysqli_real_escape_string($koneksi, $uid_scan);
        $valid_adm = false;
        $id_usr    = 0;
        $nama_usr  = 'Administrator';
        $user_name = 'admin';

        // Cek kolom uid_member di tbl_pengguna khusus role admin
        $cek_kol = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pengguna LIKE 'uid_member'");
        if ($cek_kol && mysqli_num_rows($cek_kol) > 0) {
            $q_uid = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE UPPER(uid_member) = '$uid_esc' AND role = 'admin' LIMIT 1");
            if ($q_uid && mysqli_num_rows($q_uid) > 0) {
                $row       = mysqli_fetch_assoc($q_uid);
                $valid_adm = true;
                $id_usr    = intval($row['id_pengguna']);
                $nama_usr  = $row['nama_lengkap'];
                $user_name = $row['username'];
            }
        }

        // Cek pemetaan kartu default admin
        if (!$valid_adm && isset($admin_card_map[$uid_scan])) {
            $valid_adm = true;
            $user_name = $admin_card_map[$uid_scan]['username'];
            $nama_usr  = $admin_card_map[$uid_scan]['nama'];

            $u_esc = mysqli_real_escape_string($koneksi, $user_name);
            $q_db  = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE username = '$u_esc' AND role = 'admin' LIMIT 1");
            if ($q_db && mysqli_num_rows($q_db) > 0) {
                $row       = mysqli_fetch_assoc($q_db);
                $id_usr    = intval($row['id_pengguna']);
                $nama_usr  = $row['nama_lengkap'];
                $user_name = $row['username'];
            }
        }

        if ($valid_adm) {
            $_SESSION['id_pengguna']  = $id_usr;
            $_SESSION['nama_lengkap'] = $nama_usr;
            $_SESSION['username']     = $user_name;
            $_SESSION['role']         = 'admin';
            $_SESSION['uid_member']   = $uid_scan;

            header("Location: index.php");
            exit();
        } else {
            $error = "Kartu (" . htmlspecialchars($uid_scan) . ") tidak terdaftar sebagai akun Admin.";
        }
    } else {
        $error = "Silakan tap kartu Admin atau masukkan kode kartu terlebih dahulu.";
    }
}

// 2. LOGIN ADMIN MANUAL (USERNAME & PASSWORD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_manual_admin'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $q_usr = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE username = '$username' AND role = 'admin' LIMIT 1");
        if ($q_usr && mysqli_num_rows($q_usr) === 1) {
            $user = mysqli_fetch_assoc($q_usr);
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['id_pengguna']  = $user['id_pengguna'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = 'admin';

                header("Location: index.php");
                exit();
            } else {
                $error = "Password Admin salah.";
            }
        } else {
            $error = "Akun Admin tidak ditemukan.";
        }
    } else {
        $error = "Mohon isi username dan password Admin.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-700 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        <!-- HEADER ADMIN -->
        <div class="bg-slate-900 px-6 py-5 text-white flex items-center justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30 shrink-0">
                    <i class="fa-solid fa-user-shield text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight leading-tight">Login Admin</h1>
                    <p class="text-xs text-indigo-300 mt-0.5">Panel Kontrol Toko Kita</p>
                </div>
            </div>
            <span class="bg-indigo-600/20 border border-indigo-500/40 text-indigo-300 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase">Admin</span>
        </div>

        <div class="p-6 space-y-5 text-xs">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- PANEL SCAN KARTU ADMIN -->
            <div class="bg-indigo-50/80 border-2 border-indigo-200 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="font-bold text-indigo-950 flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-wifi rotate-90 text-indigo-600"></i>
                        <span>Scan Kartu Admin</span>
                    </div>
                    <span class="bg-indigo-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full">RFID / NFC</span>
                </div>

                <p class="text-[11px] text-slate-600">
                    Tempelkan kartu RFID/NFC Admin ke reader untuk langsung masuk ke <b>Dashboard Admin</b>.
                </p>

                <form id="formScanAdmin" action="" method="POST" class="space-y-2.5">
                    <input type="hidden" name="scan_admin_login" value="1">
                    <div class="flex items-center gap-2">
                        <input type="text" id="inputUidAdmin" name="uid_member" autofocus autocomplete="off" placeholder="Tap kartu Admin (contoh: ADMIN-001)..." class="flex-1 px-3.5 py-2.5 bg-white border border-indigo-300 rounded-xl font-mono uppercase font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-600">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl font-bold transition shadow-sm shrink-0">
                            Masuk
                        </button>
                    </div>
                </form>

                <div class="flex items-center gap-1.5 pt-1">
                    <span class="text-[10px] text-slate-500 font-semibold">Tes cepat:</span>
                    <button type="button" onclick="scanCepatAdmin('ADMIN-001')" class="bg-white hover:bg-indigo-100 text-indigo-800 border border-indigo-300 px-2 py-0.5 rounded-md font-mono font-bold text-[10px] transition">
                        ADMIN-001
                    </button>
                </div>
            </div>

            <div class="relative flex items-center py-1">
                <div class="flex-grow border-t border-slate-200"></div>
                <span class="mx-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Atau Login Akun Admin</span>
                <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <!-- FORM LOGIN MANUAL ADMIN -->
            <form action="" method="POST" class="space-y-3.5">
                <input type="hidden" name="login_manual_admin" value="1">
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Username Admin</label>
                    <input type="text" name="username" required placeholder="Masukkan username admin" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Password</label>
                    <input type="password" name="password" required placeholder="Masukkan password" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-bold transition shadow-sm">
                    Login sebagai Admin
                </button>
            </form>

            <!-- PINDAH HALAMAN LOGIN ROLE LAIN -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Bukan Admin?</span>
                <div class="flex items-center gap-3 font-bold">
                    <a href="login_kasir.php" class="text-emerald-600 hover:underline">Login Kasir</a>
                    <span>•</span>
                    <a href="login_pelanggan.php" class="text-sky-600 hover:underline">Login Pelanggan</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const inp = document.getElementById('inputUidAdmin');
            if (inp) {
                inp.focus();
                inp.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        this.value = this.value.trim().toUpperCase();
                        if (this.value !== '') {
                            document.getElementById('formScanAdmin').submit();
                        }
                    }
                });
            }
        });

        function scanCepatAdmin(kode) {
            const inp = document.getElementById('inputUidAdmin');
            const frm = document.getElementById('formScanAdmin');
            if (inp && frm) {
                inp.value = kode;
                frm.submit();
            }
        }
    </script>
</body>
</html>