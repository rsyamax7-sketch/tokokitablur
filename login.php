<?php
// login.php
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
    } elseif ($_SESSION['role'] === 'pelanggan') {
        header("Location: belanja_pelanggan.php");
        exit();
    }
}

$show_popup_register = (isset($_GET['registered']) && $_GET['registered'] === '1');
$uid_terdaftar_popup = strtoupper(trim($_GET['uid'] ?? ''));
$error = '';

// PROSES LOGIN USERNAME & PASSWORD BIASA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_manual'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $query  = "SELECT * FROM tbl_pengguna WHERE username = '$username' LIMIT 1";
        $result = mysqli_query($koneksi, $query);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            $password_valid = password_verify($password, $user['password']) || ($password === $user['password']);

            if ($password_valid) {
                $_SESSION['id_pengguna']  = $user['id_pengguna'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: index.php");
                } elseif ($user['role'] === 'kasir') {
                    header("Location: dashboard_kasir.php");
                } elseif ($user['role'] === 'pelanggan') {
                    header("Location: belanja_pelanggan.php");
                } else {
                    header("Location: belanja_pelanggan.php");
                }
                exit();
            } else {
                $error = "Password yang Anda masukkan salah.";
            }
        } else {
            $error = "Username tidak ditemukan.";
        }
    } else {
        $error = "Mohon isi username dan password.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-slate-700 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden">
        <!-- HEADER PORTAL -->
        <div class="bg-slate-900 px-6 sm:px-8 py-6 text-white flex flex-wrap items-center justify-between gap-4 border-b border-slate-800">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-sky-500 flex items-center justify-center shadow-lg shadow-indigo-600/30 shrink-0">
                    <i class="fa-solid fa-store text-xl"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-lg tracking-tight leading-tight">Portal Login Toko Kita</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Pilih jalur login sesuai peran atau masuk dengan akun</p>
                </div>
            </div>
            <a href="register_pelanggan.php" class="text-xs bg-sky-600 hover:bg-sky-500 text-white px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-user-plus"></i> Daftar Pelanggan
            </a>
        </div>

        <div class="p-6 sm:p-8 space-y-6 text-xs">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- ARAH KE 3 HALAMAN LOGIN TERPISAH -->
            <div class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pilih Halaman Login Role</span>
                    <span class="text-[11px] text-slate-400">3 Halaman Terpisah</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <!-- LOGIN ADMIN -->
                    <a href="login_admin.php" class="group bg-indigo-50/70 hover:bg-indigo-600 border-2 border-indigo-200 hover:border-indigo-600 rounded-2xl p-4 transition flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 group-hover:bg-white text-white group-hover:text-indigo-600 flex items-center justify-center text-base transition shadow-xs">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <i class="fa-solid fa-arrow-right text-indigo-400 group-hover:text-white group-hover:translate-x-0.5 transition"></i>
                        </div>
                        <div>
                            <div class="font-extrabold text-sm text-indigo-950 group-hover:text-white transition">Login Admin</div>
                            <div class="text-[11px] text-indigo-700/80 group-hover:text-indigo-100 mt-0.5 transition">Kontrol &amp; Laporan</div>
                        </div>
                    </a>

                    <!-- LOGIN KASIR -->
                    <a href="login_kasir.php" class="group bg-emerald-50/70 hover:bg-emerald-600 border-2 border-emerald-200 hover:border-emerald-600 rounded-2xl p-4 transition flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 group-hover:bg-white text-white group-hover:text-emerald-600 flex items-center justify-center text-base transition shadow-xs">
                                <i class="fa-solid fa-cash-register"></i>
                            </div>
                            <i class="fa-solid fa-arrow-right text-emerald-400 group-hover:text-white group-hover:translate-x-0.5 transition"></i>
                        </div>
                        <div>
                            <div class="font-extrabold text-sm text-emerald-950 group-hover:text-white transition">Login Kasir</div>
                            <div class="text-[11px] text-emerald-700/80 group-hover:text-emerald-100 mt-0.5 transition">Kasir &amp; Keranjang</div>
                        </div>
                    </a>

                    <!-- LOGIN PELANGGAN -->
                    <a href="login_pelanggan.php" class="group bg-sky-50/70 hover:bg-sky-600 border-2 border-sky-200 hover:border-sky-600 rounded-2xl p-4 transition flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-sky-600 group-hover:bg-white text-white group-hover:text-sky-600 flex items-center justify-center text-base transition shadow-xs">
                                <i class="fa-solid fa-bag-shopping"></i>
                            </div>
                            <i class="fa-solid fa-arrow-right text-sky-400 group-hover:text-white group-hover:translate-x-0.5 transition"></i>
                        </div>
                        <div>
                            <div class="font-extrabold text-sm text-sky-950 group-hover:text-white transition">Login Pelanggan</div>
                            <div class="text-[11px] text-sky-700/80 group-hover:text-sky-100 mt-0.5 transition">Belanja &amp; QRIS</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- FORM LOGIN USERNAME & PASSWORD CEPAT -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-3.5">
                <div class="font-bold text-slate-800 flex items-center gap-2 text-xs">
                    <i class="fa-solid fa-key text-indigo-600"></i>
                    <span>Login Akun Langsung</span>
                </div>
                <form action="" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <input type="hidden" name="login_manual" value="1">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1 text-[11px]">Username</label>
                        <input type="text" name="username" required placeholder="Masukkan username" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1 text-[11px]">Password</label>
                        <input type="password" name="password" required placeholder="Masukkan password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 px-4 rounded-xl font-bold transition shadow-xs">
                        Login Akun
                    </button>
                </form>
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
                <p class="text-xs text-slate-600">Akun pelanggan Anda sudah terdaftar. Silakan masuk ke halaman Login Pelanggan atau login akun.</p>
                <?php if ($uid_terdaftar_popup !== ''): ?>
                    <div class="pt-2">
                        <span class="text-[11px] text-slate-500">Kode / UID Kartu:</span>
                        <div class="font-mono font-extrabold text-sm text-sky-700 bg-sky-50 border border-sky-200 rounded-xl py-1.5 px-3 mt-1 inline-block">
                            <?= htmlspecialchars($uid_terdaftar_popup); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <a href="login_pelanggan.php" class="flex-1 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                    Login Pelanggan
                </a>
                <button type="button" onclick="tutupPopupRegister()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function tutupPopupRegister() {
            const popup = document.getElementById('popupRegisterBerhasil');
            if (popup) popup.classList.add('hidden');
        }
    </script>
</body>
</html>