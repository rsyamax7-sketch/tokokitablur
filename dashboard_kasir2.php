<?php
// dashboard_kasir2.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan kolom id_pengguna & kasir tersedia di tbl_transaksi agar transaksi tidak tercampur ke Kasir 1
$kol_id_pengguna = false;
$kol_kasir       = false;
$q_cek_kol = mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_transaksi");
if ($q_cek_kol) {
    while ($col = mysqli_fetch_assoc($q_cek_kol)) {
        if ($col['Field'] === 'id_pengguna') $kol_id_pengguna = true;
        if ($col['Field'] === 'kasir') $kol_kasir = true;
    }
}

if (!$kol_id_pengguna) {
    if (mysqli_query($koneksi, "ALTER TABLE tbl_transaksi ADD COLUMN id_pengguna INT NULL DEFAULT NULL")) {
        $kol_id_pengguna = true;
    }
}
if (!$kol_kasir) {
    if (mysqli_query($koneksi, "ALTER TABLE tbl_transaksi ADD COLUMN kasir VARCHAR(50) NULL DEFAULT NULL")) {
        $kol_kasir = true;
    }
}

// Ambil akun Kasir 2 dari tbl_pengguna (jika ada)
$akun_kasir = [];
$q_ksr = mysqli_query($koneksi, "SELECT id_pengguna, nama_lengkap, username FROM tbl_pengguna WHERE role = 'kasir' ORDER BY id_pengguna ASC LIMIT 2");
if ($q_ksr) {
    while ($r = mysqli_fetch_assoc($q_ksr)) {
        $akun_kasir[] = $r;
    }
}

$kasir_2 = [
    'id_pengguna'  => isset($akun_kasir[1]) ? intval($akun_kasir[1]['id_pengguna']) : 2,
    'nama_lengkap' => isset($akun_kasir[1]) && !empty($akun_kasir[1]['nama_lengkap']) ? $akun_kasir[1]['nama_lengkap'] : 'Kasir 2',
    'username'     => isset($akun_kasir[1]) ? $akun_kasir[1]['username'] : 'kasir2',
];

// Tetapkan sesi aktif ke Kasir 2
$_SESSION['id_pengguna']  = $kasir_2['id_pengguna'];
$_SESSION['nama_lengkap'] = $kasir_2['nama_lengkap'];
$_SESSION['username']     = $kasir_2['username'];
$_SESSION['role']         = 'kasir';
$_SESSION['kasir_slot']   = 2;

$nama_user = $_SESSION['nama_lengkap'];
$id_kasir2 = intval($_SESSION['id_pengguna']);

$pesan_sukses = '';
$pesan_error  = '';

// Proses tambah transaksi baru khusus KASIR 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_transaksi_kasir2'])) {
    $id_pelanggan  = intval($_POST['id_pelanggan'] ?? 0);
    $total_belanja = floatval($_POST['total_belanja'] ?? 0);
    $tanggal       = trim($_POST['tanggal'] ?? date('Y-m-d H:i:s'));
    if (strlen($tanggal) === 16) {
        $tanggal .= ':00';
    }
    $tanggal_esc = mysqli_real_escape_string($koneksi, $tanggal);

    if ($id_pelanggan > 0 && $total_belanja > 0) {
        $cols = ['id_pelanggan', 'tanggal', 'total_belanja'];
        $vals = [$id_pelanggan, "'" . $tanggal_esc . "'", $total_belanja];

        if ($kol_id_pengguna) {
            $cols[] = 'id_pengguna';
            $vals[] = $id_kasir2;
        }
        if ($kol_kasir) {
            $cols[] = 'kasir';
            $vals[] = "'Kasir 2'";
        }

        $sql_ins = "INSERT INTO tbl_transaksi (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        if (mysqli_query($koneksi, $sql_ins)) {
            $id_baru = mysqli_insert_id($koneksi);
            header("Location: dashboard_kasir2.php?saved=1&id=" . $id_baru);
            exit();
        } else {
            $pesan_error = "Gagal menyimpan transaksi Kasir 2: " . mysqli_error($koneksi);
        }
    } else {
        $pesan_error = "Pilih pelanggan dan masukkan total belanja yang valid.";
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $id_trx_baru  = intval($_GET['id'] ?? 0);
    $pesan_sukses = "Transaksi" . ($id_trx_baru > 0 ? " #TRX-" . str_pad($id_trx_baru, 3, '0', STR_PAD_LEFT) : "") . " berhasil dicatat khusus untuk Kasir 2.";
}

// Kondisi filter khusus Kasir 2 saja
$cond_kasir2 = "1=0";
if ($kol_id_pengguna && $kol_kasir) {
    $cond_kasir2 = "(t.id_pengguna = $id_kasir2 OR LOWER(COALESCE(t.kasir, '')) IN ('kasir 2', 'kasir2', '2'))";
} elseif ($kol_id_pengguna) {
    $cond_kasir2 = "(t.id_pengguna = $id_kasir2)";
} elseif ($kol_kasir) {
    $cond_kasir2 = "(LOWER(COALESCE(t.kasir, '')) IN ('kasir 2', 'kasir2', '2'))";
}

// Rekap transaksi & penjualan khusus Kasir 2
$s_k2 = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(t.id_transaksi) AS jml_trx, COALESCE(SUM(t.total_belanja), 0) AS total_omzet
    FROM tbl_transaksi t
    WHERE $cond_kasir2
"));
$trx_kasir2   = intval($s_k2['jml_trx'] ?? 0);
$omzet_kasir2 = floatval($s_k2['total_omzet'] ?? 0);

$q_total_produk = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM tbl_produk WHERE stok > 0");
$total_produk_tersedia = intval(mysqli_fetch_assoc($q_total_produk)['total'] ?? 0);

// Daftar pelanggan untuk input transaksi Kasir 2
$daftar_pelanggan = [];
$q_pel = mysqli_query($koneksi, "SELECT id_pelanggan, nama_pelanggan, kota FROM tbl_pelanggan ORDER BY nama_pelanggan ASC");
if ($q_pel) {
    while ($p = mysqli_fetch_assoc($q_pel)) {
        $daftar_pelanggan[] = $p;
    }
}

// Daftar transaksi milik Kasir 2 saja
$q_trx_k2 = mysqli_query($koneksi, "
    SELECT t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan, p.kota
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE $cond_kasir2
    ORDER BY t.id_transaksi DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir 2 - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700 flex h-screen overflow-hidden">

    <!-- SIDEBAR KASIR 2 -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between p-6 shrink-0 border-r border-slate-800">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-sky-600 text-white font-bold rounded-xl flex items-center justify-center w-10 h-10 shadow-lg shadow-sky-600/20">
                    <i class="fa-solid fa-cash-register text-base"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-base tracking-tight leading-tight">Toko Kita</h2>
                    <span class="text-[11px] text-sky-400 font-medium tracking-wide uppercase">Panel Kasir 2</span>
                </div>
            </div>

            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Menu Kasir 2</div>
            <nav class="space-y-1 text-sm font-medium">
                <a href="dashboard_kasir2.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-sky-600 text-white shadow-md shadow-sky-600/20">
                    <i class="fa-solid fa-gauge-high text-xs"></i>
                    <span>Dashboard Kasir 2</span>
                </a>
                <a href="data_produk_kasir2.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-cart-shopping text-xs text-slate-400"></i>
                    <span>Kasir &amp; Keranjang</span>
                </a>
            </nav>
        </div>

        <div class="border-t border-slate-800/80 pt-4 space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-800 text-sky-400 font-bold flex items-center justify-center text-xs border border-slate-700">K2</div>
                <div class="text-xs leading-tight">
                    <p class="font-semibold text-slate-200"><?= htmlspecialchars($nama_user); ?></p>
                    <p class="text-sky-400 text-[11px] mt-0.5">Kasir 2 (ID: <?= $id_kasir2; ?>)</p>
                </div>
            </div>
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')" class="w-full bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition border border-red-500/20">
                <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Logout / Keluar
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200/80 px-8 py-4 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight">Dashboard Kasir 2</h1>
                <p class="text-xs text-slate-400 mt-0.5">Sistem Kasir Toko Kita — Tanggal: <?= date('d M Y'); ?> • Khusus Kasir 2</p>
            </div>
            <a href="login_kasir.php" class="bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition">
                Ganti Kasir
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-8 space-y-6">

            <?php if (!empty($pesan_sukses)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <span><?= htmlspecialchars($pesan_sukses); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-exclamation text-red-600"></i>
                    <span><?= htmlspecialchars($pesan_error); ?></span>
                </div>
            <?php endif; ?>

            <!-- STATISTIK KHUSUS KASIR 2 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Penjualan Kasir 2</p>
                        <h3 class="text-xl font-bold text-sky-600 mt-1">Rp <?= number_format($omzet_kasir2, 0, ',', '.'); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Transaksi Kasir 2</p>
                        <h3 class="text-xl font-bold text-slate-800 mt-1"><?= number_format($trx_kasir2, 0, ',', '.'); ?> <span class="text-xs font-normal text-slate-400">struk</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Produk Siap Jual</p>
                        <h3 class="text-xl font-bold text-slate-800 mt-1"><?= number_format($total_produk_tersedia, 0, ',', '.'); ?> <span class="text-xs font-normal text-slate-400">item</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>
            </div>

            <!-- FORM INPUT TRANSAKSI KHUSUS KASIR 2 -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 text-sm">Catat Transaksi Pelanggan di Kasir 2</h3>
                    <span class="text-[11px] bg-sky-50 text-sky-700 border border-sky-200 px-2.5 py-1 rounded-lg font-bold">Khusus Kasir 2</span>
                </div>

                <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end text-xs">
                    <input type="hidden" name="simpan_transaksi_kasir2" value="1">

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Pelanggan</label>
                        <select name="id_pelanggan" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white text-slate-800 outline-none focus:ring-2 focus:ring-sky-600">
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($daftar_pelanggan as $pel): ?>
                                <option value="<?= intval($pel['id_pelanggan']); ?>">
                                    <?= htmlspecialchars($pel['nama_pelanggan']); ?><?= !empty($pel['kota']) ? ' (' . htmlspecialchars($pel['kota']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Total Belanja (Rp)</label>
                        <input type="number" name="total_belanja" min="1" step="any" required placeholder="Contoh: 50000" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white text-slate-800 outline-none focus:ring-2 focus:ring-sky-600">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Tanggal Transaksi</label>
                        <input type="datetime-local" name="tanggal" value="<?= date('Y-m-d\TH:i'); ?>" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white text-slate-800 outline-none focus:ring-2 focus:ring-sky-600">
                    </div>

                    <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white py-2.5 px-4 rounded-xl font-bold transition shadow-sm">
                        Simpan Transaksi Kasir 2
                    </button>
                </form>
            </div>

            <!-- TABEL RIWAYAT TRANSAKSI KHUSUS KASIR 2 -->
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-2xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 text-sm">Riwayat Transaksi Kasir 2</h3>
                    <span class="text-[11px] text-slate-400 font-medium"><?= number_format($trx_kasir2, 0, ',', '.'); ?> transaksi</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-6">ID Transaksi</th>
                                <th class="py-3.5 px-6">Tanggal</th>
                                <th class="py-3.5 px-6">Pelanggan</th>
                                <th class="py-3.5 px-6">Kasir</th>
                                <th class="py-3.5 px-6 text-right">Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                            <?php if ($q_trx_k2 && mysqli_num_rows($q_trx_k2) > 0): ?>
                                <?php while ($t = mysqli_fetch_assoc($q_trx_k2)): ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-3.5 px-6 font-bold text-slate-800">#TRX-<?= str_pad((int)$t['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td class="py-3.5 px-6"><?= htmlspecialchars($t['tanggal']); ?></td>
                                        <td class="py-3.5 px-6"><?= htmlspecialchars($t['nama_pelanggan'] ?: 'Pelanggan Umum'); ?><?= !empty($t['kota']) ? ' • ' . htmlspecialchars($t['kota']) : ''; ?></td>
                                        <td class="py-3.5 px-6">
                                            <span class="bg-sky-100 text-sky-800 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold">Kasir 2</span>
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-bold text-sky-600">Rp <?= number_format((float)$t['total_belanja'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400">Belum ada transaksi yang tercatat di Kasir 2.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

</body>
</html>