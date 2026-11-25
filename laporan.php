<?php
// laporan.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Arahkan sesuai role jika bukan admin
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'kasir') {
        header("Location: dashboard_kasir.php");
        exit();
    } elseif ($_SESSION['role'] === 'pelanggan') {
        header("Location: belanja_pelanggan.php");
        exit();
    }
}

$_SESSION['role'] = 'admin';
$nama_user = $_SESSION['nama_lengkap'] ?? 'Administrator';

// Filter tanggal & pencarian
$tgl_awal  = isset($_GET['tgl_awal']) && $_GET['tgl_awal'] !== '' ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) && $_GET['tgl_akhir'] !== '' ? $_GET['tgl_akhir'] : date('Y-m-d');
$cari      = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$semua_tgl = isset($_GET['semua']) && $_GET['semua'] === '1';

// Validasi format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal)) {
    $tgl_awal = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) {
    $tgl_akhir = date('Y-m-d');
}

$where_clauses = [];
if (!$semua_tgl) {
    $tgl_awal_esc  = mysqli_real_escape_string($koneksi, $tgl_awal);
    $tgl_akhir_esc = mysqli_real_escape_string($koneksi, $tgl_akhir);
    $where_clauses[] = "DATE(t.tanggal) BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc'";
}

if ($cari !== '') {
    $cari_esc = mysqli_real_escape_string($koneksi, $cari);
    $cari_clean = preg_replace('/[^0-9]/', '', $cari);
    $cond_cari = "(p.nama_pelanggan LIKE '%$cari_esc%' OR p.kota LIKE '%$cari_esc%' OR CONCAT('TRX', LPAD(t.id_transaksi, 6, '0')) LIKE '%$cari_esc%' OR CONCAT('#TRX-', LPAD(t.id_transaksi, 3, '0')) LIKE '%$cari_esc%'";
    if ($cari_clean !== '') {
        $cond_cari .= " OR t.id_transaksi = " . intval($cari_clean);
    }
    $cond_cari .= ")";
    $where_clauses[] = $cond_cari;
}

$where_sql = !empty($where_clauses) ? ("WHERE " . implode(" AND ", $where_clauses)) : "";

// Ambil daftar transaksi
$query_trx = "SELECT t.*, p.nama_pelanggan, p.kota,
                     COALESCE((SELECT SUM(d.jumlah) FROM tbl_detail_transaksi d WHERE d.id_transaksi = t.id_transaksi), 0) AS total_qty
              FROM tbl_transaksi t
              LEFT JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
              $where_sql
              ORDER BY t.tanggal DESC, t.id_transaksi DESC";
$result_trx = mysqli_query($koneksi, $query_trx);

$daftar_transaksi = [];
$total_omzet      = 0;
$total_transaksi  = 0;
$total_item_terjual = 0;

if ($result_trx) {
    while ($row = mysqli_fetch_assoc($result_trx)) {
        $daftar_transaksi[] = $row;
        $total_omzet        += floatval($row['total_belanja']);
        $total_transaksi    += 1;
        $total_item_terjual += intval($row['total_qty']);
    }
}

$rata_rata_trx = ($total_transaksi > 0) ? round($total_omzet / $total_transaksi) : 0;

// Ekspor CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'laporan_penjualan_' . ($semua_tgl ? 'semua' : ($tgl_awal . '_sd_' . $tgl_akhir)) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['No. Nota', 'Tanggal', 'Pelanggan', 'Kota', 'Total Item', 'Total Belanja (Rp)']);

    foreach ($daftar_transaksi as $trx) {
        $no_nota = 'TRX' . str_pad(intval($trx['id_transaksi']), 6, '0', STR_PAD_LEFT);
        fputcsv($out, [
            $no_nota,
            $trx['tanggal'],
            $trx['nama_pelanggan'] ?? 'Umum',
            $trx['kota'] ?? '-',
            intval($trx['total_qty']),
            floatval($trx['total_belanja'])
        ]);
    }
    fclose($out);
    exit();
}

// Ambil rincian item untuk setiap transaksi yang tampil
$detail_per_trx = [];
if (!empty($daftar_transaksi)) {
    $ids = array_map(function($t) { return intval($t['id_transaksi']); }, $daftar_transaksi);
    $ids_in = implode(',', $ids);
    $q_det = mysqli_query($koneksi, "SELECT * FROM tbl_detail_transaksi WHERE id_transaksi IN ($ids_in) ORDER BY id_detail ASC");
    if ($q_det) {
        while ($d = mysqli_fetch_assoc($q_det)) {
            $detail_per_trx[intval($d['id_transaksi'])][] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Admin Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; color: #000000 !important; overflow: visible !important; height: auto !important; }
            main { overflow: visible !important; padding: 0 !important; }
            .print-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700 flex h-screen overflow-hidden">

    <!-- SIDEBAR ADMIN -->
    <aside class="no-print w-64 bg-slate-900 text-slate-300 flex flex-col justify-between p-6 shrink-0 border-r border-slate-800">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-indigo-600 text-white font-bold rounded-xl flex items-center justify-center w-10 h-10 shadow-lg shadow-indigo-600/20">
                    <i class="fa-solid fa-store text-base"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-base tracking-tight leading-tight">Toko Kita</h2>
                    <span class="text-[11px] text-indigo-400 font-medium tracking-wide uppercase">Panel Admin</span>
                </div>
            </div>

            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Menu Utama</div>
            <nav class="space-y-1 text-sm font-medium">
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-chart-line text-xs text-slate-400"></i>
                    <span>Dashboard</span>
                </a>
                <a href="data_produk.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-box text-xs text-slate-400"></i>
                    <span>Data Produk</span>
                </a>
                <a href="data_pelanggan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-users text-xs text-slate-400"></i>
                    <span>Data Pelanggan</span>
                </a>
                <a href="transaksi.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-receipt text-xs text-slate-400"></i>
                    <span>Transaksi</span>
                </a>
                <a href="laporan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
                    <i class="fa-solid fa-file-invoice-dollar text-xs"></i>
                    <span>Laporan</span>
                </a>
            </nav>
        </div>

        <div class="border-t border-slate-800/80 pt-4 space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-800 text-indigo-400 font-bold flex items-center justify-center text-xs border border-slate-700">AD</div>
                <div class="text-xs leading-tight">
                    <p class="font-semibold text-slate-200"><?= htmlspecialchars($nama_user); ?></p>
                    <p class="text-indigo-400 text-[11px] mt-0.5 capitalize">Role: admin</p>
                </div>
            </div>
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')" class="w-full bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition border border-red-500/20">
                <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Logout
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="no-print bg-white border-b border-slate-200/80 px-8 py-4 flex flex-wrap items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight">Laporan Penjualan &amp; Transaksi</h1>
                <p class="text-xs text-slate-400 mt-0.5">Rekap pendapatan, jumlah transaksi, rincian item terjual, cetak &amp; ekspor CSV.</p>
            </div>
            <div class="flex items-center gap-2.5">
                <?php
                $query_export = http_build_query([
                    'tgl_awal'  => $tgl_awal,
                    'tgl_akhir' => $tgl_akhir,
                    'cari'      => $cari,
                    'semua'     => $semua_tgl ? '1' : '0',
                    'export'    => 'csv'
                ]);
                ?>
                <a href="laporan.php?<?= $query_export; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-sm">
                    <i class="fa-solid fa-file-csv text-xs"></i> Export CSV
                </a>
                <button type="button" onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-sm">
                    <i class="fa-solid fa-print text-xs"></i> Cetak Laporan
                </button>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-8 space-y-6">

            <!-- JUDUL SAAT DICETAK -->
            <div class="hidden print:block border-b border-slate-300 pb-3 mb-4">
                <h2 class="text-lg font-bold text-slate-900">LAPORAN PENJUALAN - TOKO KITA</h2>
                <p class="text-xs text-slate-600">
                    Periode: <?= $semua_tgl ? 'Semua Tanggal' : (htmlspecialchars($tgl_awal) . ' s/d ' . htmlspecialchars($tgl_akhir)); ?>
                    <?php if ($cari !== ''): ?> | Filter: <?= htmlspecialchars($cari); ?><?php endif; ?>
                </p>
            </div>

            <!-- FILTER LAPORAN -->
            <form action="" method="GET" class="no-print bg-white rounded-2xl border border-slate-200/70 p-5 shadow-2xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end text-xs">
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Tanggal Awal</label>
                    <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Tanggal Akhir</label>
                    <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Cari Pelanggan / No. Nota</label>
                    <input type="text" name="cari" value="<?= htmlspecialchars($cari); ?>" placeholder="Nama, kota, atau TRX..." class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl font-semibold transition shadow-sm flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-[10px]"></i> Terapkan
                    </button>
                    <a href="laporan.php?semua=1" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-2 rounded-xl font-semibold transition" title="Tampilkan Semua Tanggal">
                        Semua
                    </a>
                </div>
                <div class="flex justify-end">
                    <a href="laporan.php" class="text-slate-500 hover:text-slate-800 font-semibold py-2 px-2 transition">
                        <i class="fa-solid fa-rotate-left mr-1"></i> Reset Bulan Ini
                    </a>
                </div>
            </form>

            <!-- KARTU RINGKASAN -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="print-card bg-white rounded-2xl border border-slate-200/70 p-5 shadow-2xs">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Pendapatan</div>
                    <div class="text-xl font-extrabold text-indigo-600 mt-1.5">Rp <?= number_format($total_omzet, 0, ',', '.'); ?></div>
                    <div class="text-[11px] text-slate-400 mt-1">
                        <?= $semua_tgl ? 'Seluruh periode' : (htmlspecialchars($tgl_awal) . ' s/d ' . htmlspecialchars($tgl_akhir)); ?>
                    </div>
                </div>

                <div class="print-card bg-white rounded-2xl border border-slate-200/70 p-5 shadow-2xs">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Jumlah Transaksi</div>
                    <div class="text-xl font-extrabold text-slate-800 mt-1.5"><?= number_format($total_transaksi, 0, ',', '.'); ?> <span class="text-xs font-semibold text-slate-500">Nota</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Transaksi tercatat</div>
                </div>

                <div class="print-card bg-white rounded-2xl border border-slate-200/70 p-5 shadow-2xs">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Item Terjual</div>
                    <div class="text-xl font-extrabold text-emerald-600 mt-1.5"><?= number_format($total_item_terjual, 0, ',', '.'); ?> <span class="text-xs font-semibold text-slate-500">Pcs</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Akumulasi kuantitas produk</div>
                </div>

                <div class="print-card bg-white rounded-2xl border border-slate-200/70 p-5 shadow-2xs">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Rata-Rata / Transaksi</div>
                    <div class="text-xl font-extrabold text-slate-800 mt-1.5">Rp <?= number_format($rata_rata_trx, 0, ',', '.'); ?></div>
                    <div class="text-[11px] text-slate-400 mt-1">Nilai rata-rata per nota</div>
                </div>
            </div>

            <!-- TABEL LAPORAN TRANSAKSI -->
            <div class="print-card bg-white rounded-2xl border border-slate-200/70 shadow-2xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Rincian Transaksi Penjualan</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Menampilkan <?= count($daftar_transaksi); ?> transaksi sesuai filter.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-6">No. Nota</th>
                                <th class="py-3.5 px-6">Tanggal</th>
                                <th class="py-3.5 px-6">Pelanggan</th>
                                <th class="py-3.5 px-6">Item Dibeli</th>
                                <th class="py-3.5 px-6 text-center">Qty</th>
                                <th class="py-3.5 px-6 text-right">Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                            <?php if (!empty($daftar_transaksi)): ?>
                                <?php foreach ($daftar_transaksi as $trx): ?>
                                    <?php
                                    $id_t = intval($trx['id_transaksi']);
                                    $no_nota = 'TRX' . str_pad($id_t, 6, '0', STR_PAD_LEFT);
                                    $items = $detail_per_trx[$id_t] ?? [];
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition align-top">
                                        <td class="py-4 px-6 font-mono font-bold text-indigo-600"><?= htmlspecialchars($no_nota); ?></td>
                                        <td class="py-4 px-6 whitespace-nowrap"><?= htmlspecialchars($trx['tanggal']); ?></td>
                                        <td class="py-4 px-6">
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($trx['nama_pelanggan'] ?? 'Pelanggan Umum'); ?></div>
                                            <div class="text-[11px] text-slate-400"><?= htmlspecialchars($trx['kota'] ?? '-'); ?></div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php if (!empty($items)): ?>
                                                <ul class="space-y-1">
                                                    <?php foreach ($items as $it): ?>
                                                        <li class="text-slate-700">
                                                            <span class="font-semibold"><?= htmlspecialchars($it['nama_produk']); ?></span>
                                                            <span class="text-slate-400"> (<?= intval($it['jumlah']); ?> x Rp <?= number_format(floatval($it['harga']), 0, ',', '.'); ?>)</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">Tidak ada rincian item</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-center font-bold text-slate-800"><?= intval($trx['total_qty']); ?></td>
                                        <td class="py-4 px-6 text-right font-extrabold text-emerald-600 whitespace-nowrap">
                                            Rp <?= number_format(floatval($trx['total_belanja']), 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-slate-400">
                                        Tidak ada data transaksi pada periode/filter yang dipilih.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($daftar_transaksi)): ?>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-bold text-slate-800">
                                <td colspan="4" class="py-3.5 px-6 text-right">TOTAL KESELURUHAN</td>
                                <td class="py-3.5 px-6 text-center text-emerald-700"><?= number_format($total_item_terjual, 0, ',', '.'); ?></td>
                                <td class="py-3.5 px-6 text-right text-indigo-700 text-sm">Rp <?= number_format($total_omzet, 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>