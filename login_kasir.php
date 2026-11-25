<?php
// login_kasir.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login sebagai kasir, arahkan ke dashboard sesuai slot kasirnya
if (isset($_SESSION['role']) && $_SESSION['role'] === 'kasir') {
    $tujuan_sesi = (($_SESSION['kasir_slot'] ?? 1) === 2) ? 'dashboard_kasir2.php' : 'dashboard_kasir.php';
    header("Location: " . $tujuan_sesi);
    exit();
}

$error = '';

// Pemetaan kartu default Kasir 1 & Kasir 2
$kasir_card_map = [
    'KASIR-001' => ['slot' => 1, 'username' => 'kasir1', 'nama' => 'Kasir 1'],
    'KSR-001'   => ['slot' => 1, 'username' => 'kasir1', 'nama' => 'Kasir 1'],
    'KASIR-002' => ['slot' => 2, 'username' => 'kasir2', 'nama' => 'Kasir 2'],
    'KSR-002'   => ['slot' => 2, 'username' => 'kasir2', 'nama' => 'Kasir 2'],
];

// Ambil maksimal 2 akun kasir dari tbl_pengguna
$akun_kasir_list = [];
$q_ksr = mysqli_query($koneksi, "SELECT id_pengguna, nama_lengkap, username FROM tbl_pengguna WHERE role = 'kasir' ORDER BY id_pengguna ASC LIMIT 2");
if ($q_ksr) {
    while ($r = mysqli_fetch_assoc($q_ksr)) {
        $akun_kasir_list[] = $r;
    }
}

$ksr1 = [
    'id_pengguna'  => isset($akun_kasir_list[0]) ? intval($akun_kasir_list[0]['id_pengguna']) : 1,
    'nama_lengkap' => isset($akun_kasir_list[0]) && !empty($akun_kasir_list[0]['nama_lengkap']) ? $akun_kasir_list[0]['nama_lengkap'] : 'Kasir 1',
    'username'     => isset($akun_kasir_list[0]) ? $akun_kasir_list[0]['username'] : 'kasir1',
];

$ksr2 = [
    'id_pengguna'  => isset($akun_kasir_list[1]) ? intval($akun_kasir_list[1]['id_pengguna']) : ($ksr1['id_pengguna'] + 1),
    'nama_lengkap' => isset($akun_kasir_list[1]) && !empty($akun_kasir_list[1]['nama_lengkap']) ? $akun_kasir_list[1]['nama_lengkap'] : 'Kasir 2',
    'username'     => isset($akun_kasir_list[1]) ? $akun_kasir_list[1]['username'] : 'kasir2',
];

// Cek kolom pada tbl_transaksi untuk menghitung total transaksi berbeda per kasir
$kol_id_pengguna = false;
$kol_kasir       = false;
$q_cek_kol = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_transaksi");
if ($q_cek_kol) {
    while ($col = mysqli_fetch_assoc($q_cek_kol)) {
        if ($col['Field'] === 'id_pengguna') $kol_id_pengguna = true;
        if ($col['Field'] === 'kasir') $kol_kasir = true;
    }
}

$cond_k1 = "1=0";
$cond_k2 = "1=0";

if ($kol_id_pengguna && $kol_kasir) {
    $cond_k1 = "(t.id_pengguna = {$ksr1['id_pengguna']} OR LOWER(COALESCE(t.kasir, '')) IN ('kasir 1', 'kasir1', '1'))";
    $cond_k2 = "(t.id_pengguna = {$ksr2['id_pengguna']} OR LOWER(COALESCE(t.kasir, '')) IN ('kasir 2', 'kasir2', '2'))";
} elseif ($kol_id_pengguna) {
    $cond_k1 = "(t.id_pengguna = {$ksr1['id_pengguna']})";
    $cond_k2 = "(t.id_pengguna = {$ksr2['id_pengguna']})";
} elseif ($kol_kasir) {
    $cond_k1 = "(LOWER(COALESCE(t.kasir, '')) IN ('kasir 1', 'kasir1', '1'))";
    $cond_k2 = "(LOWER(COALESCE(t.kasir, '')) IN ('kasir 2', 'kasir2', '2'))";
}

$s1 = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(t.id_transaksi) AS jml, COALESCE(SUM(t.total_belanja),0) AS omzet FROM tbl_transaksi t WHERE $cond_k1"));
$s2 = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(t.id_transaksi) AS jml, COALESCE(SUM(t.total_belanja),0) AS omzet FROM tbl_transaksi t WHERE $cond_k2"));

$stats_ksr = [
    1 => ['trx' => intval($s1['jml'] ?? 0), 'omzet' => floatval($s1['omzet'] ?? 0)],
    2 => ['trx' => intval($s2['jml'] ?? 0), 'omzet' => floatval($s2['omzet'] ?? 0)],
];

$pilihan_slot = (isset($_GET['pilih']) && $_GET['pilih'] === '2') ? 2 : 1;

function redirectKeDashboardKasir(int $slot): void {
    $tujuan = ($slot === 2) ? 'dashboard_kasir2.php' : 'dashboard_kasir.php';
    header("Location: " . $tujuan);
    exit();
}

// 1. PILIH LANGSUNG KASIR 1 (dashboard_kasir.php) ATAU KASIR 2 (dashboard_kasir2.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilih_kasir_masuk'])) {
    $slot       = (($_POST['slot_kasir'] ?? '1') === '2') ? 2 : 1;
    $target_ksr = ($slot === 2) ? $ksr2 : $ksr1;

    $_SESSION['id_pengguna']  = $target_ksr['id_pengguna'];
    $_SESSION['nama_lengkap'] = $target_ksr['nama_lengkap'];
    $_SESSION['username']     = $target_ksr['username'];
    $_SESSION['role']         = 'kasir';
    $_SESSION['kasir_slot']   = $slot;

    redirectKeDashboardKasir($slot);
}

// 2. LOGIN KASIR VIA SCAN KARTU RFID / NFC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_kasir_login'])) {
    $uid_scan = strtoupper(trim($_POST['uid_member'] ?? ''));

    if ($uid_scan !== '') {
        $uid_esc   = mysqli_real_escape_string($koneksi, $uid_scan);
        $valid_ksr = false;
        $slot_ksr  = 1;
        $id_usr    = $ksr1['id_pengguna'];
        $nama_usr  = $ksr1['nama_lengkap'];
        $user_name = $ksr1['username'];

        $cek_kol = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_pengguna LIKE 'uid_member'");
        if ($cek_kol && mysqli_num_rows($cek_kol) > 0) {
            $q_uid = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE UPPER(uid_member) = '$uid_esc' AND role = 'kasir' LIMIT 1");
            if ($q_uid && mysqli_num_rows($q_uid) > 0) {
                $row       = mysqli_fetch_assoc($q_uid);
                $valid_ksr = true;
                $id_usr    = intval($row['id_pengguna']);
                $nama_usr  = $row['nama_lengkap'];
                $user_name = $row['username'];
                $slot_ksr  = ($id_usr === $ksr2['id_pengguna'] || stripos($user_name, '2') !== false || stripos($nama_usr, '2') !== false) ? 2 : 1;
            }
        }

        if (!$valid_ksr && isset($kasir_card_map[$uid_scan])) {
            $cfg       = $kasir_card_map[$uid_scan];
            $valid_ksr = true;
            $slot_ksr  = $cfg['slot'];
            $target    = ($slot_ksr === 2) ? $ksr2 : $ksr1;
            $id_usr    = $target['id_pengguna'];
            $nama_usr  = $target['nama_lengkap'];
            $user_name = $target['username'];

            $u_esc = mysqli_real_escape_string($koneksi, $user_name);
            $q_db  = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE username = '$u_esc' AND role = 'kasir' LIMIT 1");
            if ($q_db && mysqli_num_rows($q_db) > 0) {
                $row       = mysqli_fetch_assoc($q_db);
                $id_usr    = intval($row['id_pengguna']);
                $nama_usr  = $row['nama_lengkap'];
                $user_name = $row['username'];
            }
        }

        if ($valid_ksr) {
            $_SESSION['id_pengguna']  = $id_usr;
            $_SESSION['nama_lengkap'] = $nama_usr;
            $_SESSION['username']     = $user_name;
            $_SESSION['role']         = 'kasir';
            $_SESSION['kasir_slot']   = $slot_ksr;
            $_SESSION['uid_member']   = $uid_scan;

            redirectKeDashboardKasir($slot_ksr);
        } else {
            $error = "Kartu (" . htmlspecialchars($uid_scan) . ") tidak dikenali sebagai Kasir 1 atau Kasir 2.";
        }
    } else {
        $error = "Silakan tap kartu Kasir 1 / Kasir 2 atau masukkan kode kartu terlebih dahulu.";
    }
}

// 3. LOGIN KASIR MANUAL (USERNAME & PASSWORD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_manual_kasir'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $q_usr = mysqli_query($koneksi, "SELECT * FROM tbl_pengguna WHERE username = '$username' AND role = 'kasir' LIMIT 1");
        if ($q_usr && mysqli_num_rows($q_usr) === 1) {
            $user = mysqli_fetch_assoc($q_usr);
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $id_u     = intval($user['id_pengguna']);
                $slot_ksr = ($id_u === $ksr2['id_pengguna'] || stripos($user['username'], '2') !== false || stripos($user['nama_lengkap'], '2') !== false) ? 2 : 1;

                $_SESSION['id_pengguna']  = $id_u;
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = 'kasir';
                $_SESSION['kasir_slot']   = $slot_ksr;

                redirectKeDashboardKasir($slot_ksr);
            } else {
                $error = "Password Kasir salah.";
            }
        } else {
            $error = "Akun Kasir tidak ditemukan.";
        }
    } else {
        $error = "Mohon isi username dan password Kasir.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir 1 &amp; Kasir 2 - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-700 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        <!-- HEADER -->
        <div class="bg-slate-900 px-6 py-5 text-white flex items-center justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-600/30 shrink-0">
                    <i class="fa-solid fa-cash-register text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight leading-tight">Login Kasir (Kasir 1 &amp; Kasir 2)</h1>
                    <p class="text-xs text-emerald-300 mt-0.5">Kasir 1 ke Dashboard Kasir • Kasir 2 ke Dashboard Kasir 2</p>
                </div>
            </div>
            <span class="bg-emerald-600/20 border border-emerald-500/40 text-emerald-300 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase">2 Kasir</span>
        </div>

        <div class="p-6 space-y-5 text-xs">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- PILIH KASIR 1 ATAU KASIR 2 -->
            <div class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pilih Kasir untuk Masuk Dashboard</span>
                    <span class="text-[11px] text-slate-400">Terpisah Otomatis</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- KASIR 1 -> dashboard_kasir.php -->
                    <form action="" method="POST" class="bg-emerald-50/70 border-2 border-emerald-200 rounded-2xl p-4 flex flex-col justify-between gap-3">
                        <input type="hidden" name="pilih_kasir_masuk" value="1">
                        <input type="hidden" name="slot_kasir" value="1">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="bg-emerald-600 text-white text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full">Kasir 1</span>
                                <span class="font-mono text-[10px] text-emerald-800 font-bold">KASIR-001</span>
                            </div>
                            <div class="font-extrabold text-sm text-slate-800 mt-2"><?= htmlspecialchars($ksr1['nama_lengkap']); ?></div>
                            <div class="text-xs font-bold text-emerald-700 mt-1">
                                <?= number_format($stats_ksr[1]['trx'], 0, ',', '.'); ?> Transaksi
                                <span class="text-slate-500 font-medium">• Rp <?= number_format($stats_ksr[1]['omzet'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded-xl font-bold transition shadow-xs flex items-center justify-center gap-1.5">
                            <span>Masuk Kasir 1</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </form>

                    <!-- KASIR 2 -> dashboard_kasir2.php -->
                    <form action="" method="POST" class="bg-sky-50/70 border-2 border-sky-200 rounded-2xl p-4 flex flex-col justify-between gap-3">
                        <input type="hidden" name="pilih_kasir_masuk" value="1">
                        <input type="hidden" name="slot_kasir" value="2">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="bg-sky-600 text-white text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full">Kasir 2</span>
                                <span class="font-mono text-[10px] text-sky-800 font-bold">KASIR-002</span>
                            </div>
                            <div class="font-extrabold text-sm text-slate-800 mt-2"><?= htmlspecialchars($ksr2['nama_lengkap']); ?></div>
                            <div class="text-xs font-bold text-sky-700 mt-1">
                                <?= number_format($stats_ksr[2]['trx'], 0, ',', '.'); ?> Transaksi
                                <span class="text-slate-500 font-medium">• Rp <?= number_format($stats_ksr[2]['omzet'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white py-2 rounded-xl font-bold transition shadow-xs flex items-center justify-center gap-1.5">
                            <span>Masuk Kasir 2</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- SCAN KARTU KASIR 1 / KASIR 2 -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800 flex items-center gap-2 text-xs">
                        <i class="fa-solid fa-wifi rotate-90 text-emerald-600"></i>
                        <span>Scan Kartu Kasir 1 / Kasir 2</span>
                    </span>
                    <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full">RFID / NFC</span>
                </div>
                <form id="formScanKasir" action="" method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="scan_kasir_login" value="1">
                    <input type="text" id="inputUidKasir" name="uid_member" autocomplete="off" placeholder="Tap kartu KASIR-001 atau KASIR-002..." class="flex-1 px-3.5 py-2 bg-white border border-slate-300 rounded-xl font-mono uppercase font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-600">
                    <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-xl font-bold transition shrink-0">
                        Scan Masuk
                    </button>
                </form>
            </div>

            <!-- LOGIN MANUAL USERNAME & PASSWORD -->
            <form action="" method="POST" class="space-y-3 pt-1 border-t border-slate-100">
                <input type="hidden" name="login_manual_kasir" value="1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Username Kasir</label>
                        <input type="text" id="inputUsernameKasir" name="username" value="<?= htmlspecialchars($pilihan_slot === 2 ? $ksr2['username'] : $ksr1['username']); ?>" required placeholder="kasir1 atau kasir2" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-emerald-600 text-slate-800">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Password</label>
                        <input type="password" name="password" required placeholder="Masukkan password" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-emerald-600 text-slate-800">
                    </div>
                </div>
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white py-2.5 rounded-xl font-bold transition shadow-xs">
                    Login Akun ke Dashboard Kasir
                </button>
            </form>

            <!-- PINDAH HALAMAN LOGIN ROLE LAIN -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <a href="login.php" class="text-slate-600 hover:underline font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Portal Login</a>
                <div class="flex items-center gap-3 font-bold">
                    <a href="login_admin.php" class="text-indigo-600 hover:underline">Login Admin</a>
                    <span>•</span>
                    <a href="login_pelanggan.php" class="text-sky-600 hover:underline">Login Pelanggan</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const inp = document.getElementById('inputUidKasir');
            if (inp) {
                inp.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        this.value = this.value.trim().toUpperCase();
                        if (this.value !== '') {
                            document.getElementById('formScanKasir').submit();
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>