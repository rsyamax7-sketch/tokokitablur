<?php
// laporan_penjualan.php (Panel Admin)
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan hanya admin yang bisa mengakses laporan penuh
$role_login = $_SESSION['role'] ?? 'admin';
if ($role_login !== 'admin') {
    header("Location: dashboard_kasir.php");
    exit();
}

$nama_user = $_SESSION['nama_lengkap'] ?? 'Administrator';

// Ambil data rekapitulasi transaksi dari database beserta jumlah item terjual
$query_laporan = "SELECT t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan, p.kota,
                         COALESCE((SELECT SUM(d.jumlah) FROM tbl_detail_transaksi d WHERE d.id_transaksi = t.id_transaksi), 0) AS total_qty
                  FROM tbl_transaksi t 
                  JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan 
                  ORDER BY t.id_transaksi DESC";
$result_laporan = mysqli_query($koneksi, $query_laporan);

$daftar_laporan = [];
$total_pendapatan = 0;
$total_trx = 0;
$total_item = 0;

if ($result_laporan) {
    while ($row = mysqli_fetch_assoc($result_laporan)) {
        $daftar_laporan[] = $row;
        $total_pendapatan += floatval($row['total_belanja']);
        $total_trx += 1;
        $total_item += intval($row['total_qty']);
    }
}

$rata_rata_trx = ($total_trx > 0) ? round($total_pendapatan / $total_trx) : 0;

// Ambil rincian item untuk setiap transaksi
$detail_per_trx = [];
if (!empty($daftar_laporan)) {
    $ids = array_map(function($t) { return intval($t['id_transaksi']); }, $daftar_laporan);
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
    <title>Laporan Penjualan - Panel Admin Toko Kita</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        @media print {
            body { background: #ffffff !important; color: #000000 !important; overflow: visible !important; height: auto !important; }
            .flex.h-screen.overflow-hidden { display: block !important; height: auto !important; overflow: visible !important; }
            .flex-1.flex.flex-col.overflow-hidden { overflow: visible !important; }
            main { overflow: visible !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .print-table, .print-table th, .print-table td {
                border: 1px solid #334155 !important;
                border-collapse: collapse !important;
            }
            .print-table th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-table tfoot tr {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700 flex h-screen overflow-hidden">

    <!-- SIDEBAR ADMIN (INDIGO) -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between p-6 shrink-0 border-r border-slate-800 no-print">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-indigo-600 text-white font-bold rounded-xl flex items-center justify-center w-10 h-10 shadow-lg shadow-indigo-500/20">
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
                    <i class="fa-solid fa-chart-pie text-xs text-slate-400"></i>
                    <span>Dashboard</span>
                </a>
                <a href="laporan_penjualan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
                    <i class="fa-solid fa-receipt text-xs"></i>
                    <span>Laporan Penjualan</span>
                </a>
                <a href="data_produk.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-box-archive text-xs text-slate-400"></i>
                    <span>Data Produk &amp; Kasir</span>
                </a>
                <a href="data_pelanggan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-users text-xs text-slate-400"></i>
                    <span>Data Pelanggan</span>
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
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar?')" class="w-full bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition border border-red-500/20">
                <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Logout
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200/80 px-8 py-4 flex items-center justify-between shrink-0 no-print">
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight">Laporan Penjualan Keseluruhan</h1>
                <p class="text-xs text-slate-400 mt-0.5">Rekapitulasi total penjualan, jumlah transaksi, item terjual, dan rincian nota.</p>
            </div>
            <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-sm">
                <i class="fa-solid fa-print text-[10px]"></i> Cetak Laporan
            </button>
        </header>

        <main class="flex-1 overflow-y-auto p-8 space-y-6" id="printArea">

            <!-- JUDUL & TABEL RINGKASAN KHUSUS CETAK -->
            <div class="hidden print:block space-y-4">
                <div class="text-center border-b-2 border-slate-800 pb-3">
                    <h2 class="text-lg font-extrabold text-slate-900 uppercase tracking-wide">Laporan Penjualan Toko Kita</h2>
                    <p class="text-xs text-slate-600 mt-0.5">Dicetak oleh: <?= htmlspecialchars($nama_user); ?> • Tanggal Cetak: <?= date('d/m/Y H:i'); ?></p>
                </div>

                <!-- TABEL RINGKASAN TOTAL PENJUALAN (CETAK) -->
                <table class="print-table w-full text-xs text-left border-collapse">
                    <thead>
                        <tr>
                            <th colspan="4" class="py-2 px-3 text-center uppercase tracking-wider font-bold">Ringkasan Total Penjualan</th>
                        </tr>
                        <tr>
                            <th class="py-2 px-3">Total Penjualan</th>
                            <th class="py-2 px-3">Total Transaksi</th>
                            <th class="py-2 px-3">Total Item Terjual</th>
                            <th class="py-2 px-3">Rata-Rata / Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="font-bold text-slate-900">
                            <td class="py-2.5 px-3">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></td>
                            <td class="py-2.5 px-3"><?= number_format($total_trx, 0, ',', '.'); ?> Nota</td>
                            <td class="py-2.5 px-3"><?= number_format($total_item, 0, ',', '.'); ?> Pcs</td>
                            <td class="py-2.5 px-3">Rp <?= number_format($rata_rata_trx, 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- KARTU STATISTIK RINGKASAN (TAMPILAN LAYAR) -->
            <div class="no-print grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Penjualan</p>
                        <h3 class="text-xl font-extrabold text-indigo-600 mt-1">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Akumulasi seluruh transaksi</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base font-bold shrink-0">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
                        <h3 class="text-xl font-extrabold text-slate-800 mt-1"><?= number_format($total_trx, 0, ',', '.'); ?> <span class="text-xs font-semibold text-slate-500">Nota</span></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Transaksi tercatat</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base font-bold shrink-0">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Item Terjual</p>
                        <h3 class="text-xl font-extrabold text-emerald-600 mt-1"><?= number_format($total_item, 0, ',', '.'); ?> <span class="text-xs font-semibold text-slate-500">Pcs</span></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Jumlah unit produk keluar</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-base font-bold shrink-0">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-2xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rata-Rata / Transaksi</p>
                        <h3 class="text-xl font-extrabold text-slate-800 mt-1">Rp <?= number_format($rata_rata_trx, 0, ',', '.'); ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Nilai rata-rata per nota</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-base font-bold shrink-0">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <!-- TABEL LAPORAN TRANSAKSI -->
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-2xs overflow-hidden print:rounded-none print:border-0 print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between print:px-0 print:py-2 print:border-0">
                    <h3 class="font-bold text-slate-800 text-sm">Tabel Rincian Transaksi Penjualan</h3>
                    <span class="text-[11px] text-slate-400 font-medium print:text-slate-700"><?= count($daftar_laporan); ?> transaksi</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="print-table w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-4 text-center">No</th>
                                <th class="py-3.5 px-4">ID Transaksi</th>
                                <th class="py-3.5 px-4">Tanggal</th>
                                <th class="py-3.5 px-4">Pelanggan &amp; Kota</th>
                                <th class="py-3.5 px-4">Rincian Item</th>
                                <th class="py-3.5 px-4 text-center">Qty</th>
                                <th class="py-3.5 px-4 text-right">Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                            <?php if (!empty($daftar_laporan)): ?>
                                <?php $no = 1; ?>
                                <?php foreach ($daftar_laporan as $row): ?>
                                    <?php
                                    $id_t  = intval($row['id_transaksi']);
                                    $items = $detail_per_trx[$id_t] ?? [];
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition align-top">
                                        <td class="py-3.5 px-4 text-center font-semibold text-slate-700"><?= $no++; ?></td>
                                        <td class="py-3.5 px-4 font-bold text-slate-800 whitespace-nowrap">#TRX-<?= str_pad($id_t, 3, '0', STR_PAD_LEFT); ?></td>
                                        <td class="py-3.5 px-4 whitespace-nowrap"><?= htmlspecialchars($row['tanggal']); ?></td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-semibold text-slate-800"><?= htmlspecialchars($row['nama_pelanggan']); ?></div>
                                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($row['kota']); ?></div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if (!empty($items)): ?>
                                                <ul class="space-y-0.5 text-[11px] text-slate-700">
                                                    <?php foreach ($items as $it): ?>
                                                        <li>
                                                            <span class="font-semibold"><?= htmlspecialchars($it['nama_produk']); ?></span>
                                                            <span class="text-slate-500">(<?= intval($it['jumlah']); ?> x Rp <?= number_format(floatval($it['harga']), 0, ',', '.'); ?>)</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic text-[11px]">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-bold text-slate-800"><?= intval($row['total_qty']); ?></td>
                                        <td class="py-3.5 px-4 text-right font-bold text-indigo-600 print:text-slate-900 whitespace-nowrap">Rp <?= number_format(floatval($row['total_belanja']), 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400">Belum ada data transaksi penjualan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($daftar_laporan)): ?>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-extrabold text-slate-900">
                                <td colspan="5" class="py-3.5 px-4 text-right">TOTAL PENJUALAN KESELURUHAN (<?= number_format($total_trx, 0, ',', '.'); ?> TRANSAKSI)</td>
                                <td class="py-3.5 px-4 text-center text-emerald-700 print:text-slate-900"><?= number_format($total_item, 0, ',', '.'); ?> Pcs</td>
                                <td class="py-3.5 px-4 text-right text-indigo-700 print:text-slate-900 text-sm whitespace-nowrap">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></td>
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