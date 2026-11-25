<?php
// data_produk_kasir2.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keranjang & struk khusus Kasir 2 agar tidak tercampur dengan Kasir 1
$cart_key  = 'keranjang_kasir2';
$struk_key = 'last_struk_bayar_kasir2';

if (!isset($_SESSION[$cart_key])) {
    $_SESSION[$cart_key] = [];
}

// Pastikan kolom id_pengguna & kasir tersedia di tbl_transaksi
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

// Ambil akun Kasir 2 dari tbl_pengguna jika ada
$id_kasir2_db   = 2;
$nama_kasir2_db = 'Kasir 2';
$q_ksr = mysqli_query($koneksi, "SELECT id_pengguna, nama_lengkap, username FROM tbl_pengguna WHERE role = 'kasir' ORDER BY id_pengguna ASC LIMIT 2");
if ($q_ksr) {
    $list_ksr = [];
    while ($r = mysqli_fetch_assoc($q_ksr)) {
        $list_ksr[] = $r;
    }
    if (isset($list_ksr[1])) {
        $id_kasir2_db   = intval($list_ksr[1]['id_pengguna']);
        $nama_kasir2_db = !empty($list_ksr[1]['nama_lengkap']) ? $list_ksr[1]['nama_lengkap'] : 'Kasir 2';
    }
}

$_SESSION['role']         = 'kasir';
$_SESSION['kasir_slot']   = 2;
$_SESSION['id_pengguna']  = $id_kasir2_db;
$_SESSION['nama_lengkap'] = $nama_kasir2_db;

$role_login = 'kasir';
$nama_user  = $_SESSION['nama_lengkap'];
$id_kasir2  = intval($_SESSION['id_pengguna']);

// PENGATURAN PAJAK DEFAULT (PPN 11%)
$tarif_pajak_default = 11;

// DAFTAR VOUCHER DISKON TERSEDIA
$daftar_voucher = [
    'HEMAT10'   => ['tipe' => 'persen',  'nilai' => 10,     'max_potongan' => 500000,  'label' => 'Diskon 10% (maks. Rp 500.000)'],
    'POTONG50K' => ['tipe' => 'nominal', 'nilai' => 50000,  'max_potongan' => 50000,   'label' => 'Potongan Rp 50.000'],
    'KASIR100K' => ['tipe' => 'nominal', 'nilai' => 100000, 'max_potongan' => 100000,  'label' => 'Potongan Rp 100.000'],
    'SUPER20'   => ['tipe' => 'persen',  'nilai' => 20,     'max_potongan' => 1000000, 'label' => 'Diskon 20% (maks. Rp 1.000.000)'],
];

function hitungPotonganVoucher($kode_input, $subtotal_belanja, $daftar_voucher) {
    $kode = strtoupper(trim((string)$kode_input));
    if ($kode === '' || !isset($daftar_voucher[$kode]) || $subtotal_belanja <= 0) {
        return [
            'kode'        => '',
            'potongan'    => 0,
            'grand_total' => max(0, $subtotal_belanja),
            'valid'       => false
        ];
    }

    $v = $daftar_voucher[$kode];
    if ($v['tipe'] === 'persen') {
        $potongan = round(($subtotal_belanja * $v['nilai']) / 100);
        if (!empty($v['max_potongan']) && $potongan > $v['max_potongan']) {
            $potongan = $v['max_potongan'];
        }
    } else {
        $potongan = $v['nilai'];
    }

    if ($potongan > $subtotal_belanja) {
        $potongan = $subtotal_belanja;
    }

    return [
        'kode'        => $kode,
        'potongan'    => $potongan,
        'grand_total' => max(0, $subtotal_belanja - $potongan),
        'valid'       => true
    ];
}

// =========================================================================
// PROSES AUTO-SCAN BARCODE: SEMUA KODE BARCODE MASUK KE 1 BARANG (LAPTOP ROG)
// =========================================================================
if (isset($_POST['scan_barcode'])) {
    $barcode = trim($_POST['scan_barcode']);

    if (!empty($barcode)) {
        $q_prod = mysqli_query($koneksi, "SELECT * FROM tbl_produk WHERE nama_produk LIKE '%Laptop ROG%' ORDER BY id_produk ASC LIMIT 1");
        if (!$q_prod || mysqli_num_rows($q_prod) === 0) {
            $q_prod = mysqli_query($koneksi, "SELECT * FROM tbl_produk ORDER BY id_produk ASC LIMIT 1");
        }
        $prod = mysqli_fetch_assoc($q_prod);

        if ($prod) {
            $qty_di_cart = 0;
            foreach ($_SESSION[$cart_key] as $item) {
                if ($item['id_produk'] == $prod['id_produk']) {
                    $qty_di_cart = $item['qty'];
                    break;
                }
            }

            if ($prod['stok'] <= 0 || ($qty_di_cart + 1) > $prod['stok']) {
                header("Location: data_produk_kasir2.php?status=stok_kurang&item=" . urlencode($prod['nama_produk']));
                exit();
            }

            $found = false;
            foreach ($_SESSION[$cart_key] as &$item) {
                if ($item['id_produk'] == $prod['id_produk']) {
                    $item['qty'] += 1;
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION[$cart_key][] = [
                    'id_produk'   => $prod['id_produk'],
                    'nama_produk' => $prod['nama_produk'],
                    'harga'       => $prod['harga'],
                    'qty'         => 1
                ];
            }
            header("Location: data_produk_kasir2.php?status=sukses_scan&item=" . urlencode($prod['nama_produk']) . "&kode=" . urlencode($barcode));
            exit();
        } else {
            header("Location: data_produk_kasir2.php?status=barcode_tidak_ditemukan&kode=" . urlencode($barcode));
            exit();
        }
    }
}

// PROSES TAMBAH KE KERANJANG MANUAL (MODAL)
if (isset($_POST['tambah_keranjang'])) {
    $id_produk = intval($_POST['id_produk']);
    $qty_beli  = intval($_POST['qty']);

    $q_prod = mysqli_query($koneksi, "SELECT * FROM tbl_produk WHERE id_produk = $id_produk");
    $prod   = mysqli_fetch_assoc($q_prod);

    if ($prod && $qty_beli > 0) {
        if ($qty_beli <= $prod['stok']) {
            $found = false;
            foreach ($_SESSION[$cart_key] as &$item) {
                if ($item['id_produk'] == $id_produk) {
                    $item['qty'] += $qty_beli;
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION[$cart_key][] = [
                    'id_produk'   => $prod['id_produk'],
                    'nama_produk' => $prod['nama_produk'],
                    'harga'       => $prod['harga'],
                    'qty'         => $qty_beli
                ];
            }
            header("Location: data_produk_kasir2.php?status=sukses_add_cart");
            exit();
        } else {
            header("Location: data_produk_kasir2.php?status=stok_kurang&item=" . urlencode($prod['nama_produk']));
            exit();
        }
    }
}

// PROSES HAPUS ITEM DARI KERANJANG
if (isset($_GET['hapus_cart'])) {
    $index = intval($_GET['hapus_cart']);
    if (isset($_SESSION[$cart_key][$index])) {
        unset($_SESSION[$cart_key][$index]);
        $_SESSION[$cart_key] = array_values($_SESSION[$cart_key]);
    }
    header("Location: data_produk_kasir2.php");
    exit();
}

// PROSES EDIT PRODUK KATALOG (TERMASUK BARCODE & UPLOAD GAMBAR)
if (isset($_POST['edit_produk'])) {
    $id_produk    = intval($_POST['id_produk']);
    $kode_barcode = mysqli_real_escape_string($koneksi, trim($_POST['kode_barcode']));
    $id_kategori  = intval($_POST['id_kategori']);
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga        = floatval($_POST['harga']);
    $stok         = intval($_POST['stok']);

    if ($id_produk > 0 && !empty($nama_produk)) {
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp    = $_FILES['gambar']['tmp_name'];
            $file_name   = $_FILES['gambar']['name'];
            $file_ext    = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed_ext)) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                $nama_file_gambar = 'prod_' . time() . '.' . $file_ext;
                if (move_uploaded_file($file_tmp, 'uploads/' . $nama_file_gambar)) {
                    mysqli_query($koneksi, "UPDATE tbl_produk SET kode_barcode = '$kode_barcode', id_kategori = '$id_kategori', nama_produk = '$nama_produk', harga = '$harga', stok = '$stok', gambar = '$nama_file_gambar' WHERE id_produk = $id_produk");
                }
            }
        } else {
            mysqli_query($koneksi, "UPDATE tbl_produk SET kode_barcode = '$kode_barcode', id_kategori = '$id_kategori', nama_produk = '$nama_produk', harga = '$harga', stok = '$stok' WHERE id_produk = $id_produk");
        }
        header("Location: data_produk_kasir2.php?status=sukses_edit");
        exit();
    }
}

// PROSES HAPUS PRODUK KATALOG
if (isset($_GET['hapus'])) {
    $id_produk = intval($_GET['hapus']);
    if ($id_produk > 0) {
        try {
            $q_img    = mysqli_query($koneksi, "SELECT gambar FROM tbl_produk WHERE id_produk = $id_produk");
            $img_data = ($q_img) ? mysqli_fetch_assoc($q_img) : null;

            if (mysqli_query($koneksi, "DELETE FROM tbl_produk WHERE id_produk = $id_produk")) {
                if (!empty($img_data['gambar']) && file_exists('uploads/' . $img_data['gambar'])) {
                    @unlink('uploads/' . $img_data['gambar']);
                }
                header("Location: data_produk_kasir2.php?status=sukses_hapus");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            header("Location: data_produk_kasir2.php?status=gagal_hapus&msg=" . urlencode("Produk masih dipakai di riwayat transaksi (tbl_detail_transaksi)."));
            exit();
        }
    }
}

// PROSES FINAL CHECKOUT KASIR 2 (TUNAI & QRIS + VOUCHER DISKON + PAJAK)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_checkout_all'])) {
    $id_pelanggan      = intval($_POST['id_pelanggan']);
    $tanggal           = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $metode_pembayaran = (isset($_POST['metode_pembayaran']) && $_POST['metode_pembayaran'] === 'QRIS') ? 'QRIS' : 'TUNAI';
    $uang_bayar        = isset($_POST['uang_tunai']) ? floatval($_POST['uang_tunai']) : 0;
    $kode_voucher_raw  = isset($_POST['kode_voucher']) ? trim($_POST['kode_voucher']) : '';
    $pakai_pajak       = isset($_POST['pakai_pajak']) && $_POST['pakai_pajak'] === '1';
    $persen_pajak      = $pakai_pajak ? (isset($_POST['persen_pajak']) ? max(0, floatval($_POST['persen_pajak'])) : $tarif_pajak_default) : 0;

    if ($id_pelanggan > 0 && !empty($_SESSION[$cart_key])) {
        $subtotal_belanja = 0;
        foreach ($_SESSION[$cart_key] as $item) {
            $subtotal_belanja += ($item['harga'] * $item['qty']);
        }

        $hasil_voucher         = hitungPotonganVoucher($kode_voucher_raw, $subtotal_belanja, $daftar_voucher);
        $diskon_nominal        = $hasil_voucher['potongan'];
        $kode_voucher_terpakai = $hasil_voucher['valid'] ? $hasil_voucher['kode'] : '';
        $setelah_diskon        = $hasil_voucher['grand_total'];

        $nominal_pajak = $pakai_pajak ? round(($setelah_diskon * $persen_pajak) / 100) : 0;
        $grand_total   = max(0, $setelah_diskon + $nominal_pajak);

        if ($metode_pembayaran === 'QRIS') {
            $uang_bayar = $grand_total;
            $kembalian  = 0;
        } else {
            if ($uang_bayar < $grand_total) {
                $uang_bayar = $grand_total;
            }
            $kembalian = $uang_bayar - $grand_total;
        }

        $cols = ['tanggal', 'id_pelanggan', 'total_belanja'];
        $vals = ["'$tanggal'", "'$id_pelanggan'", "'$grand_total'"];
        if ($kol_id_pengguna) {
            $cols[] = 'id_pengguna';
            $vals[] = "'$id_kasir2'";
        }
        if ($kol_kasir) {
            $cols[] = 'kasir';
            $vals[] = "'Kasir 2'";
        }

        $query_transaksi = "INSERT INTO tbl_transaksi (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";

        if (mysqli_query($koneksi, $query_transaksi)) {
            $id_transaksi_baru = mysqli_insert_id($koneksi);

            foreach ($_SESSION[$cart_key] as $item) {
                $pid   = $item['id_produk'];
                $pname = mysqli_real_escape_string($koneksi, $item['nama_produk']);
                $pqty  = $item['qty'];
                $prc   = $item['harga'];

                mysqli_query($koneksi, "INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES ('$id_transaksi_baru', '$pid', '$pname', '$pqty', '$prc')");
                mysqli_query($koneksi, "UPDATE tbl_produk SET stok = stok - $pqty WHERE id_produk = $pid");
            }

            $_SESSION[$struk_key] = [
                'trx_id'       => $id_transaksi_baru,
                'kasir_label'  => 'Kasir 2',
                'metode'       => $metode_pembayaran,
                'subtotal'     => $subtotal_belanja,
                'kode_voucher' => $kode_voucher_terpakai,
                'diskon'       => $diskon_nominal,
                'pakai_pajak'  => $pakai_pajak,
                'persen_pajak' => $persen_pajak,
                'pajak'        => $nominal_pajak,
                'tunai'        => $uang_bayar,
                'kembalian'    => $kembalian
            ];

            unset($_SESSION[$cart_key]);

            header("Location: data_produk_kasir2.php?status=sukses_checkout&trx_id=" . $id_transaksi_baru);
            exit();
        }
    }
}

$keyword = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, $_GET['cari']) : '';

$query_kategori = "SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC";
$result_kategori = mysqli_query($koneksi, $query_kategori);
$data_kategori_list = [];
while ($kat = mysqli_fetch_assoc($result_kategori)) {
    $data_kategori_list[] = $kat;
}

$data_pelanggan = mysqli_query($koneksi, "SELECT * FROM tbl_pelanggan ORDER BY nama_pelanggan ASC");

$query_produk = "SELECT p.*, k.nama_kategori 
                 FROM tbl_produk p 
                 LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori";
if (!empty($keyword)) {
    $query_produk .= " WHERE p.nama_produk LIKE '%$keyword%' OR p.kode_barcode LIKE '%$keyword%' OR k.nama_kategori LIKE '%$keyword%'";
}
$query_produk .= " ORDER BY p.id_produk DESC";
$result_produk = mysqli_query($koneksi, $query_produk);

$show_struk = false;
$data_struk = [];
$detail_struk_items = [];
$struk_kasir_label  = 'Kasir 2';
$struk_metode       = 'TUNAI';
$struk_subtotal     = 0;
$struk_kode_voucher = '';
$struk_diskon       = 0;
$struk_pakai_pajak  = true;
$struk_persen_pajak = $tarif_pajak_default;
$struk_pajak        = 0;
$struk_tunai        = 0;
$struk_kembalian    = 0;

if (isset($_GET['status']) && $_GET['status'] === 'sukses_checkout' && isset($_GET['trx_id'])) {
    $id_trx_baru = intval($_GET['trx_id']);
    $q_struk = mysqli_query($koneksi, "SELECT t.*, p.nama_pelanggan, p.kota FROM tbl_transaksi t JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan WHERE t.id_transaksi = $id_trx_baru");
    if ($q_struk && mysqli_num_rows($q_struk) > 0) {
        $data_struk = mysqli_fetch_assoc($q_struk);
        $q_det = mysqli_query($koneksi, "SELECT * FROM tbl_detail_transaksi WHERE id_transaksi = $id_trx_baru");
        while ($d_item = mysqli_fetch_assoc($q_det)) {
            $detail_struk_items[] = $d_item;
        }
        if (isset($_SESSION[$struk_key]) && $_SESSION[$struk_key]['trx_id'] == $id_trx_baru) {
            $struk_kasir_label  = $_SESSION[$struk_key]['kasir_label'] ?? 'Kasir 2';
            $struk_metode       = $_SESSION[$struk_key]['metode'] ?? 'TUNAI';
            $struk_subtotal     = $_SESSION[$struk_key]['subtotal'] ?? $data_struk['total_belanja'];
            $struk_kode_voucher = $_SESSION[$struk_key]['kode_voucher'] ?? '';
            $struk_diskon       = $_SESSION[$struk_key]['diskon'] ?? 0;
            $struk_pakai_pajak  = $_SESSION[$struk_key]['pakai_pajak'] ?? false;
            $struk_persen_pajak = $_SESSION[$struk_key]['persen_pajak'] ?? $tarif_pajak_default;
            $struk_pajak        = $_SESSION[$struk_key]['pajak'] ?? 0;
            $struk_tunai        = $_SESSION[$struk_key]['tunai'];
            $struk_kembalian    = $_SESSION[$struk_key]['kembalian'];
        } else {
            $struk_subtotal     = $data_struk['total_belanja'];
            $struk_tunai        = $data_struk['total_belanja'];
        }
        $show_struk = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir 2 &amp; Keranjang Barcode - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        .struk-paper {
            width: 80mm;
            max-width: 100%;
            box-sizing: border-box;
            font-family: 'Courier New', Courier, monospace;
            color: #000000;
            background: #ffffff;
            line-height: 1.35;
        }

        .struk-dash {
            border-top: 1px dashed #000000;
            margin: 6px 0;
            width: 100%;
        }

        @media print {
            @page {
                size: 80mm 297mm;
                margin: 0mm;
            }
            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                overflow: hidden !important;
            }
            body * {
                visibility: hidden !important;
            }
            #printArea, #printArea * {
                visibility: visible !important;
            }
            #printArea {
                position: fixed !important;
                left: 4mm !important;
                top: 0 !important;
                width: 72mm !important;
                max-width: 72mm !important;
                height: auto !important;
                min-height: 0 !important;
                padding: 3mm 2mm 4mm 2mm !important;
                margin: 0 !important;
                font-size: 9.5px !important;
                line-height: 1.35 !important;
                color: #000000 !important;
                background: #ffffff !important;
                box-shadow: none !important;
                border: none !important;
                z-index: 99999 !important;
            }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700 flex h-screen overflow-hidden">

    <!-- SIDEBAR KASIR 2 (SKY) -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between p-6 shrink-0 border-r border-slate-800">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-sky-600 text-white font-bold rounded-xl flex items-center justify-center w-10 h-10 shadow-lg shadow-sky-500/20">
                    <i class="fa-solid fa-cash-register text-base"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-base tracking-tight leading-tight">Toko Kita</h2>
                    <span class="text-[11px] text-sky-400 font-medium tracking-wide uppercase">Panel Kasir 2</span>
                </div>
            </div>

            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Menu Kasir 2</div>
            <nav class="space-y-1 text-sm font-medium">
                <a href="dashboard_kasir2.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white transition">
                    <i class="fa-solid fa-chart-pie text-xs text-slate-400"></i>
                    <span>Dashboard Kasir 2</span>
                </a>
                <a href="data_produk_kasir2.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-sky-600 text-white shadow-md shadow-sky-600/20">
                    <i class="fa-solid fa-cart-shopping text-xs"></i>
                    <span>Kasir 2 &amp; Keranjang</span>
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
                <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Logout
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200/80 px-8 py-4 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight">Katalog Produk &amp; Keranjang Kasir 2</h1>
                <p class="text-xs text-slate-400 mt-0.5">Transaksi tercatat khusus untuk <b>Kasir 2</b>. Scan barcode apa pun untuk otomatis masuk ke keranjang Laptop ROG.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="bukaModalKeranjang()" class="relative bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 shadow-sm transition">
                    <i class="fa-solid fa-cart-shopping text-[10px]"></i> Keranjang Kasir 2
                    <?php if (count($_SESSION[$cart_key]) > 0): ?>
                        <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white font-bold rounded-full w-5 h-5 flex items-center justify-center text-[10px]">
                            <?= count($_SESSION[$cart_key]); ?>
                        </span>
                    <?php endif; ?>
                </button>
                <a href="tambah_produk_kasir.php" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-sm">
                    <i class="fa-solid fa-plus text-[10px]"></i> Tambah Produk
                </a>
                <a href="dashboard_kasir2.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> Dashboard Kasir 2
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-8 space-y-6">

            <!-- KOTAK AUTO-SCAN BARCODE -->
            <div class="bg-gradient-to-r from-sky-600 to-indigo-600 rounded-2xl p-5 text-white shadow-md flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-barcode"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-sm tracking-tight">Scanner Barcode Otomatis (Kasir 2)</h2>
                        <p class="text-xs text-sky-100 mt-0.5">Scan barcode apa pun lalu tekan Enter</p>
                    </div>
                </div>
                <form id="formScanBarcode" action="" method="POST" class="w-full md:w-96">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sky-700">
                            <i class="fa-solid fa-qrcode text-sm"></i>
                        </span>
                        <input type="text" id="inputScanBarcode" name="scan_barcode" autofocus autocomplete="off" placeholder="Scan barcode apa pun di sini..." class="w-full pl-10 pr-4 py-2.5 bg-white text-slate-800 font-mono font-bold rounded-xl text-xs shadow-inner outline-none focus:ring-4 focus:ring-sky-300">
                    </div>
                </form>
            </div>

            <!-- NOTIFIKASI STATUS -->
            <?php if (isset($_GET['status'])): ?>
                <?php $is_error = in_array($_GET['status'], ['stok_kurang', 'barcode_tidak_ditemukan', 'gagal_hapus']); ?>
                <div class="<?= $is_error ? 'bg-red-50 border-red-200 text-red-800' : 'bg-sky-50 border-sky-200/70 text-sky-900'; ?> border px-4 py-3 rounded-2xl text-xs flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid <?= $is_error ? 'fa-circle-exclamation text-red-600' : 'fa-circle-check text-sky-600'; ?> text-sm"></i>
                        <span>
                            <?php if ($_GET['status'] === 'sukses_scan') echo 'Scan Kode <b>' . htmlspecialchars($_GET['kode'] ?? '') . '</b> Berhasil: <b>' . htmlspecialchars($_GET['item'] ?? 'Laptop ROG') . '</b> (+1 Pcs) masuk ke keranjang Kasir 2!'; ?>
                            <?php if ($_GET['status'] === 'barcode_tidak_ditemukan') echo 'Gagal: Produk target tidak ditemukan di database!'; ?>
                            <?php if ($_GET['status'] === 'sukses_add_cart') echo 'Produk berhasil dimasukkan ke keranjang Kasir 2!'; ?>
                            <?php if ($_GET['status'] === 'sukses_checkout') echo 'Checkout Kasir 2 berhasil diproses dan struk siap dicetak!'; ?>
                            <?php if ($_GET['status'] === 'stok_kurang') echo 'Gagal: Stok <b>' . htmlspecialchars($_GET['item'] ?? 'produk') . '</b> tidak mencukupi atau habis!'; ?>
                            <?php if ($_GET['status'] === 'sukses_edit') echo 'Data produk berhasil diperbarui.'; ?>
                            <?php if ($_GET['status'] === 'sukses_tambah') echo 'Produk baru berhasil ditambahkan.'; ?>
                            <?php if ($_GET['status'] === 'sukses_hapus') echo 'Produk berhasil dihapus.'; ?>
                            <?php if ($_GET['status'] === 'gagal_hapus') echo htmlspecialchars($_GET['msg'] ?? 'Gagal menghapus produk.'); ?>
                        </span>
                    </div>
                    <a href="data_produk_kasir2.php" class="<?= $is_error ? 'text-red-600 hover:text-red-900' : 'text-sky-600 hover:text-sky-900'; ?>"><i class="fa-solid fa-xmark text-sm"></i></a>
                </div>
            <?php endif; ?>

            <!-- TABEL PRODUK -->
            <div class="bg-white rounded-2xl border border-slate-200/70 shadow-2xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Daftar Produk &amp; Aksi Kasir 2</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Pilih produk manual atau gunakan scanner barcode di atas.</p>
                    </div>

                    <form action="" method="GET" class="flex items-center gap-2 w-full md:w-auto">
                        <div class="relative w-full md:w-64">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" name="cari" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama / barcode..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none text-slate-800">
                        </div>
                        <?php if (!empty($keyword)): ?>
                            <a href="data_produk_kasir2.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-xl text-xs font-semibold transition" title="Reset">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-6">Barcode</th>
                                <th class="py-3.5 px-6">Nama Produk</th>
                                <th class="py-3.5 px-6">Kategori</th>
                                <th class="py-3.5 px-6 text-right">Harga Satuan</th>
                                <th class="py-3.5 px-6 text-center">Stok</th>
                                <th class="py-3.5 px-6 text-center">Kasir / Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                            <?php if (mysqli_num_rows($result_produk) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result_produk)): ?>
                                    <?php $kode_bc = !empty($row['kode_barcode']) ? $row['kode_barcode'] : 'PROD' . $row['id_produk']; ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-3 px-6">
                                            <svg class="barcode-svg h-8" jsbarcode-value="<?= htmlspecialchars($kode_bc); ?>" jsbarcode-width="1.2" jsbarcode-height="25" jsbarcode-fontSize="10" jsbarcode-margin="0"></svg>
                                        </td>
                                        <td class="py-4 px-6 text-slate-800 font-semibold flex items-center gap-2.5">
                                            <?php if (!empty($row['gambar']) && file_exists('uploads/' . $row['gambar'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($row['gambar']); ?>" alt="Foto" class="w-10 h-10 rounded-lg object-cover border border-slate-200 shrink-0 shadow-xs">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center text-xs shrink-0 font-bold border border-slate-200">
                                                    <i class="fa-solid fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($row['nama_produk']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md border border-slate-200/60 text-[11px] font-semibold">
                                                <?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-right font-bold text-sky-600">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                                        <td class="py-4 px-6 text-center">
                                            <span class="<?= $row['stok'] <= 5 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'; ?> px-3 py-1 rounded-full border text-[11px] font-bold inline-block">
                                                <?= $row['stok']; ?> Pcs
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-center space-x-1">
                                            <?php if ($row['stok'] > 0): ?>
                                                <button onclick="bukaModalAddToCart(<?= $row['id_produk']; ?>, '<?= htmlspecialchars($row['nama_produk'], ENT_QUOTES); ?>', <?= $row['stok']; ?>)" class="bg-sky-600 hover:bg-sky-700 text-white px-2.5 py-1.5 rounded-lg transition font-semibold" title="Tambah ke Keranjang">
                                                    <i class="fa-solid fa-cart-plus"></i> + Keranjang
                                                </button>
                                            <?php else: ?>
                                                <span class="text-red-500 font-bold text-[10px]">Stok Habis</span>
                                            <?php endif; ?>
                                            <button onclick="bukaModalEdit(<?= $row['id_produk']; ?>, '<?= htmlspecialchars($kode_bc, ENT_QUOTES); ?>', '<?= $row['id_kategori']; ?>', '<?= htmlspecialchars($row['nama_produk'], ENT_QUOTES); ?>', <?= $row['harga']; ?>, <?= $row['stok']; ?>)" class="bg-amber-50 hover:bg-amber-100 text-amber-700 px-2.5 py-1.5 rounded-lg border border-amber-200 transition font-semibold" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="data_produk_kasir2.php?hapus=<?= intval($row['id_produk']); ?>" onclick="return confirm('Yakin ingin menghapus produk ini?')" class="bg-red-50 hover:bg-red-100 text-red-600 px-2.5 py-1.5 rounded-lg border border-red-200 transition font-semibold inline-block" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400">Produk tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL TAMBAH KE KERANJANG -->
    <div id="modalAddToCart" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Masukkan ke Keranjang Kasir 2</h3>
                <button onclick="tutupModalAddToCart()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <form action="" method="POST" class="p-6 space-y-4 text-xs">
                <input type="hidden" id="cartIdProd" name="id_produk">
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Nama Produk</label>
                    <input type="text" id="cartNamaProd" readonly class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-300 rounded-xl text-slate-800 font-semibold outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Jumlah Beli (Qty)</label>
                    <input type="number" id="cartQty" name="qty" min="1" value="1" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800">
                    <p class="text-[10px] text-slate-400 mt-1">Stok tersedia: <span id="cartMaxStok" class="font-bold text-slate-600"></span></p>
                </div>

                <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="tutupModalAddToCart()" class="px-4 py-2 font-semibold text-slate-500 hover:bg-slate-100 rounded-xl transition">Batal</button>
                    <button type="submit" name="tambah_keranjang" class="px-5 py-2 font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-xl transition shadow-sm">+ Masukkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL KERANJANG & CHECKOUT -->
    <div id="modalKeranjang" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden border border-slate-100 flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-cart-shopping text-xs"></i>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm">Keranjang Belanja Kasir 2</h3>
                </div>
                <button onclick="tutupModalKeranjang()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                <?php if (!empty($_SESSION[$cart_key])): ?>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                                    <th class="py-2.5 px-4">No</th>
                                    <th class="py-2.5 px-4">Nama Produk</th>
                                    <th class="py-2.5 px-4 text-right">Harga</th>
                                    <th class="py-2.5 px-4 text-center">Qty</th>
                                    <th class="py-2.5 px-4 text-right">Subtotal</th>
                                    <th class="py-2.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php
                                $no = 1;
                                $subtotal_belanja = 0;
                                foreach ($_SESSION[$cart_key] as $idx => $item):
                                    $subtotal = $item['harga'] * $item['qty'];
                                    $subtotal_belanja += $subtotal;
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-4 font-bold"><?= $no++; ?></td>
                                        <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($item['nama_produk']); ?></td>
                                        <td class="py-3 px-4 text-right">Rp <?= number_format($item['harga'], 0, ',', '.'); ?></td>
                                        <td class="py-3 px-4 text-center font-bold text-sky-600"><?= $item['qty']; ?></td>
                                        <td class="py-3 px-4 text-right font-bold text-slate-800">Rp <?= number_format($subtotal, 0, ',', '.'); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="data_produk_kasir2.php?hapus_cart=<?= $idx; ?>" class="text-red-500 hover:text-red-700 bg-red-50 p-1.5 rounded-lg border border-red-200 inline-block" title="Hapus Item">
                                                <i class="fa-solid fa-trash text-[11px]"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="" method="POST" onsubmit="return validasiCheckoutKeranjang()" class="space-y-4 pt-2 border-t border-slate-100">
                        <input type="hidden" name="proses_checkout_all" value="1">
                        <input type="hidden" id="valSubtotalBelanja" value="<?= $subtotal_belanja; ?>">
                        <input type="hidden" id="valGrandTotal" value="<?= $subtotal_belanja; ?>">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Pilih Pelanggan</label>
                                <select name="id_pelanggan" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white outline-none text-slate-800">
                                    <option value="" disabled selected>-- Pilih Pelanggan --</option>
                                    <?php while ($cust = mysqli_fetch_assoc($data_pelanggan)): ?>
                                        <option value="<?= $cust['id_pelanggan']; ?>"><?= htmlspecialchars($cust['nama_pelanggan']); ?> (<?= htmlspecialchars($cust['kota']); ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Tanggal Transaksi</label>
                                <input type="date" name="tanggal" required value="<?= date('Y-m-d'); ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Metode Pembayaran</label>
                                <select id="metodePembayaran" name="metode_pembayaran" onchange="toggleMetodePembayaran()" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white outline-none text-slate-800 font-semibold">
                                    <option value="TUNAI" selected>Tunai (Cash)</option>
                                    <option value="QRIS">QRIS</option>
                                </select>
                            </div>
                        </div>

                        <!-- KOLOM VOUCHER DISKON -->
                        <div class="bg-amber-50/70 border border-amber-200/80 rounded-xl p-4 space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <label class="font-bold text-slate-700 flex items-center gap-1.5">
                                    <i class="fa-solid fa-ticket text-amber-600"></i> Kode Voucher Diskon
                                </label>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($daftar_voucher as $kode_v => $info_v): ?>
                                        <button type="button" onclick="pakaiKodeVoucher('<?= $kode_v; ?>')" class="bg-white hover:bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded-md font-mono font-bold text-[10px] transition">
                                            <?= $kode_v; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="text" id="inputKodeVoucher" name="kode_voucher" oninput="hitungKalkulasiCheckout()" placeholder="Ketik kode voucher (contoh: HEMAT10 / POTONG50K)..." class="flex-1 px-3.5 py-2 bg-white border border-slate-300 rounded-xl font-mono uppercase font-bold text-slate-800 outline-none focus:border-amber-500">
                                <button type="button" onclick="resetVoucherDiskon()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded-xl font-semibold transition">
                                    Hapus
                                </button>
                            </div>
                            <div id="statusVoucherText" class="text-[11px] text-slate-500">
                                Klik salah satu kode di atas atau ketik manual untuk menerapkan potongan harga.
                            </div>
                        </div>

                        <!-- PENGATURAN PAJAK (PPN) -->
                        <div class="bg-blue-50/60 border border-blue-200/80 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <label class="flex items-center gap-2.5 font-bold text-slate-700 cursor-pointer select-none">
                                <input type="checkbox" id="checkboxPakaiPajak" name="pakai_pajak" value="1" checked onchange="hitungKalkulasiCheckout()" class="w-4 h-4 accent-blue-600 rounded">
                                <span><i class="fa-solid fa-percent text-blue-600 mr-1"></i> Kenakan Pajak Transaksi</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-600 font-semibold">Tarif Pajak (%):</span>
                                <input type="number" id="inputPersenPajak" name="persen_pajak" min="0" max="100" step="0.5" value="<?= $tarif_pajak_default; ?>" oninput="hitungKalkulasiCheckout()" class="w-20 px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg font-bold text-blue-700 text-right outline-none">
                                <span class="font-bold text-slate-600">%</span>
                            </div>
                        </div>

                        <!-- PANEL QRIS BESAR & JELAS -->
                        <div id="boxQrisPembayaran" class="hidden bg-emerald-50/80 border-2 border-emerald-200 rounded-2xl p-5 flex flex-col items-center text-center gap-3">
                            <div class="w-full flex items-center justify-between border-b border-emerald-200/80 pb-2 text-left">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Pembayaran Scan QRIS</div>
                                    <div class="font-bold text-slate-800 text-sm">Toko Zeavara, Mainan</div>
                                    <div class="text-[11px] font-mono text-slate-600">NMID : ID1026499001851</div>
                                </div>
                                <button type="button" onclick="bukaModalQrisBesar()" class="bg-white hover:bg-emerald-100 text-emerald-700 border border-emerald-300 px-3 py-1.5 rounded-xl text-[11px] font-bold flex items-center gap-1.5 shadow-2xs transition shrink-0">
                                    <i class="fa-solid fa-up-right-and-down-left-from-center"></i> Layar Penuh
                                </button>
                            </div>

                            <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm cursor-pointer" onclick="bukaModalQrisBesar()" title="Klik untuk memperbesar QRIS">
                                <img src="qris.jpeg" alt="QRIS Toko Zeavara" class="w-56 sm:w-64 h-auto rounded-xl object-contain mx-auto">
                            </div>

                            <div class="space-y-1">
                                <div id="labelTagihanQris" class="text-sm font-extrabold text-emerald-800">
                                    Tagihan: Rp <?= number_format($subtotal_belanja, 0, ',', '.'); ?>
                                </div>
                                <p class="text-[11px] text-slate-600">Arahkan kamera m-banking / e-wallet ke kode QR di atas, lalu klik <b>Proses Checkout Sekaligus</b>.</p>
                            </div>
                        </div>

                        <!-- RINGKASAN SUBTOTAL, DISKON, PAJAK & TOTAL AKHIR -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div>
                                <label class="block font-bold text-slate-500 mb-1">Subtotal</label>
                                <input type="text" id="displaySubtotal" readonly value="Rp <?= number_format($subtotal_belanja, 0, ',', '.'); ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg font-semibold text-slate-700 outline-none text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-amber-700 mb-1">Diskon Voucher</label>
                                <input type="text" id="displayDiskonVoucher" readonly value="- Rp 0" class="w-full px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg font-bold text-amber-800 outline-none text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-blue-700 mb-1" id="labelRingkasanPajak">Pajak (<?= $tarif_pajak_default; ?>%)</label>
                                <input type="text" id="displayNominalPajak" readonly value="+ Rp 0" class="w-full px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg font-bold text-blue-800 outline-none text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-sky-700 mb-1">Total Bayar (Nett)</label>
                                <input type="text" id="displayGrandTotal" readonly value="Rp <?= number_format($subtotal_belanja, 0, ',', '.'); ?>" class="w-full px-3 py-2 bg-white border border-sky-300 rounded-lg font-bold text-sky-700 outline-none text-sm">
                            </div>
                        </div>

                        <!-- PEMBAYARAN & KEMBALIAN -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Uang Tunai / Bayar (Rp)</label>
                                <input type="number" id="uangTunaiCart" name="uang_tunai" min="0" oninput="hitungKembalianCart()" required placeholder="Nominal uang" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg font-bold text-sky-700 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Uang Kembalian</label>
                                <input type="text" id="uangKembalianCart" readonly value="Rp 0" class="w-full px-3 py-2 bg-sky-50 border border-sky-200 rounded-lg font-bold text-sky-800 outline-none text-sm">
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" onclick="tutupModalKeranjang()" class="px-4 py-2 font-semibold text-slate-500 hover:bg-slate-100 rounded-xl transition">Tutup</button>
                            <button type="submit" class="px-6 py-2.5 font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-xl transition shadow-sm">Proses Checkout Kasir 2</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-12 text-slate-400 space-y-2">
                        <i class="fa-solid fa-cart-shopping text-3xl text-slate-300"></i>
                        <p class="font-semibold text-slate-600">Keranjang belanja Kasir 2 masih kosong.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL QRIS UKURAN PENUH (MUDAH DI-SCAN) -->
    <div id="modalQrisBesar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 p-6 text-center space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 text-left">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Scan QRIS Pembayaran</div>
                    <div class="font-bold text-slate-800 text-sm">Toko Zeavara, Mainan</div>
                    <div class="text-[11px] font-mono text-slate-500">NMID : ID1026499001851</div>
                </div>
                <button type="button" onclick="tutupModalQrisBesar()" class="text-slate-400 hover:text-slate-700 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex justify-center">
                <img src="qris.jpg" alt="QRIS Toko Zeavara Besar" class="w-72 sm:w-80 h-auto rounded-xl object-contain bg-white p-2 border border-slate-200 shadow-xs">
            </div>

            <div id="labelTagihanQrisModal" class="text-base font-extrabold text-emerald-700">
                Total Tagihan: Rp <?= number_format($subtotal_belanja ?? 0, 0, ',', '.'); ?>
            </div>

            <button type="button" onclick="tutupModalQrisBesar()" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                Kembali ke Keranjang
            </button>
        </div>
    </div>

    <!-- MODAL EDIT PRODUK -->
    <div id="modalEdit" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Edit Data Produk</h3>
                <button onclick="tutupModalEdit()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
                <input type="hidden" id="editId" name="id_produk">
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Kode Barcode</label>
                    <input type="text" id="editBarcode" name="kode_barcode" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Nama Produk</label>
                    <input type="text" id="editNama" name="nama_produk" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800">
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Kategori Produk</label>
                    <select id="editKategori" name="id_kategori" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800 bg-white">
                        <option value="" disabled>Pilih Kategori</option>
                        <?php foreach ($data_kategori_list as $kat): ?>
                            <option value="<?= $kat['id_kategori']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Harga Satuan (Rp)</label>
                        <input type="number" id="editHarga" name="harga" min="0" step="500" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Stok (Pcs)</label>
                        <input type="number" id="editStok" name="stok" min="0" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl outline-none text-slate-800">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Ganti Foto / Gambar (Opsional)</label>
                    <input type="file" name="gambar" accept="image/png, image/jpeg, image/jpg, image/webp" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl outline-none text-slate-800 bg-slate-50">
                </div>

                <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="tutupModalEdit()" class="px-4 py-2 font-semibold text-slate-500 hover:bg-slate-100 transition">Batal</button>
                    <button type="submit" name="edit_produk" class="px-5 py-2 font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-xl transition shadow-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL STRUK PEMBAYARAN NOTA (80 x 297 MM) -->
    <?php if ($show_struk): ?>
    <?php $no_trx_fmt = 'TRX' . str_pad($data_struk['id_transaksi'], 6, '0', STR_PAD_LEFT); ?>
    <div id="modalStruk" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col items-center my-auto">
            <div id="printArea" class="struk-paper p-3 text-[10px]">
                <div class="text-center w-full">
                    <div class="font-bold text-[13px] tracking-wider uppercase">TOKO KITA</div>
                    <div class="text-[8.5px]">Jl. Merdeka No. 24, Ponorogo</div>
                    <div class="text-[8.5px]">Telp: 0812-3456-7890</div>
                </div>

                <div class="struk-dash"></div>

                <div class="space-y-0.5 text-[8.5px] w-full">
                    <div class="flex justify-between">
                        <span>No. Nota</span>
                        <span class="font-bold"><?= $no_trx_fmt; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tanggal</span>
                        <span><?= htmlspecialchars($data_struk['tanggal']); ?> <?= date('H:i'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kasir</span>
                        <span class="font-bold"><?= htmlspecialchars($struk_kasir_label); ?> (<?= htmlspecialchars($nama_user); ?>)</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pelanggan</span>
                        <span><?= htmlspecialchars($data_struk['nama_pelanggan']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pembayaran</span>
                        <span class="font-bold"><?= htmlspecialchars($struk_metode); ?></span>
                    </div>
                    <?php if (!empty($struk_kode_voucher)): ?>
                    <div class="flex justify-between">
                        <span>Voucher</span>
                        <span class="font-bold"><?= htmlspecialchars($struk_kode_voucher); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="struk-dash"></div>

                <div class="space-y-1 w-full">
                    <?php $total_qty_item = 0; ?>
                    <?php foreach ($detail_struk_items as $d_st): ?>
                        <?php
                            $sub_st = $d_st['harga'] * $d_st['jumlah'];
                            $total_qty_item += $d_st['jumlah'];
                        ?>
                        <div class="w-full">
                            <div class="font-bold break-words"><?= htmlspecialchars($d_st['nama_produk']); ?></div>
                            <div class="flex justify-between text-[8.5px]">
                                <span><?= $d_st['jumlah']; ?> x Rp<?= number_format($d_st['harga'], 0, ',', '.'); ?></span>
                                <span>Rp<?= number_format($sub_st, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="struk-dash"></div>

                <div class="space-y-0.5 text-[9px] w-full">
                    <div class="flex justify-between text-[8.5px]">
                        <span>Total Item</span>
                        <span><?= $total_qty_item; ?> pcs</span>
                    </div>
                    <?php if ($struk_diskon > 0 || $struk_pakai_pajak): ?>
                    <div class="flex justify-between text-[8.5px]">
                        <span>Subtotal</span>
                        <span>Rp<?= number_format($struk_subtotal, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($struk_diskon > 0): ?>
                    <div class="flex justify-between text-[8.5px]">
                        <span>Diskon<?= !empty($struk_kode_voucher) ? ' (' . htmlspecialchars($struk_kode_voucher) . ')' : ''; ?></span>
                        <span>-Rp<?= number_format($struk_diskon, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($struk_pakai_pajak): ?>
                    <div class="flex justify-between text-[8.5px]">
                        <span>Pajak (<?= htmlspecialchars($struk_persen_pajak); ?>%)</span>
                        <span>+Rp<?= number_format($struk_pajak, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between font-bold text-[11px] pt-0.5">
                        <span>TOTAL</span>
                        <span>Rp<?= number_format($data_struk['total_belanja'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between text-[8.5px]">
                        <span><?= $struk_metode === 'QRIS' ? 'Bayar (QRIS)' : 'Tunai'; ?></span>
                        <span>Rp<?= number_format($struk_tunai, 0, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between text-[8.5px]">
                        <span>Kembali</span>
                        <span>Rp<?= number_format($struk_kembalian, 0, ',', '.'); ?></span>
                    </div>
                </div>

                <div class="struk-dash"></div>

                <div class="text-center pt-1 w-full">
                    <svg class="struk-barcode mx-auto" jsbarcode-value="<?= $no_trx_fmt; ?>" jsbarcode-width="1.1" jsbarcode-height="22" jsbarcode-fontSize="8" jsbarcode-margin="0"></svg>
                    <div class="text-[8.5px] font-bold mt-1">TERIMA KASIH ATAS KUNJUNGAN ANDA</div>
                    <div class="text-[8px]">Barang yang sudah dibeli tidak dapat ditukar</div>
                </div>
            </div>

            <div class="w-full px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between font-sans text-xs gap-2">
                <button onclick="cetakStrukPresisi()" class="bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg font-semibold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-print text-[10px]"></i> Cetak Struk (80x297)
                </button>
                <a href="data_produk_kasir2.php" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-1.5 rounded-lg font-semibold transition">
                    Selesai
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const DATA_VOUCHER = <?= json_encode($daftar_voucher); ?>;

        document.addEventListener("DOMContentLoaded", function() {
            JsBarcode(".barcode-svg").init();
            JsBarcode(".struk-barcode").init();
            hitungKalkulasiCheckout();
            toggleMetodePembayaran();
            fokusScanner();
        });

        function fokusScanner() {
            const modalCart = document.getElementById('modalKeranjang');
            const modalAdd  = document.getElementById('modalAddToCart');
            const modalEdit = document.getElementById('modalEdit');
            const modalStrk = document.getElementById('modalStruk');
            const modalQris = document.getElementById('modalQrisBesar');

            const adaModalTerbuka =
                (modalCart && !modalCart.classList.contains('hidden')) ||
                (modalAdd && !modalAdd.classList.contains('hidden')) ||
                (modalEdit && !modalEdit.classList.contains('hidden')) ||
                (modalStrk && !modalStrk.classList.contains('hidden')) ||
                (modalQris && !modalQris.classList.contains('hidden'));

            if (!adaModalTerbuka) {
                const scanInput = document.getElementById('inputScanBarcode');
                if (scanInput && document.activeElement !== scanInput) {
                    scanInput.focus();
                }
            }
        }

        document.addEventListener("click", function(e) {
            if (!e.target.closest("input, select, textarea, button, a, form")) {
                fokusScanner();
            }
        });

        function cetakStrukPresisi() {
            JsBarcode(".struk-barcode").init();
            window.print();
        }

        function bukaModalQrisBesar() {
            const m = document.getElementById('modalQrisBesar');
            if (m) m.classList.remove('hidden');
        }

        function tutupModalQrisBesar() {
            const m = document.getElementById('modalQrisBesar');
            if (m) m.classList.add('hidden');
        }

        function pakaiKodeVoucher(kode) {
            const inputV = document.getElementById('inputKodeVoucher');
            if (inputV) {
                inputV.value = kode;
                hitungKalkulasiCheckout();
            }
        }

        function resetVoucherDiskon() {
            const inputV = document.getElementById('inputKodeVoucher');
            if (inputV) {
                inputV.value = '';
                hitungKalkulasiCheckout();
            }
        }

        function hitungKalkulasiCheckout() {
            const subtotalEl     = document.getElementById('valSubtotalBelanja');
            const grandEl        = document.getElementById('valGrandTotal');
            const displaySubEl   = document.getElementById('displaySubtotal');
            const displayDisEl   = document.getElementById('displayDiskonVoucher');
            const displayPajakEl = document.getElementById('displayNominalPajak');
            const labelPajakEl   = document.getElementById('labelRingkasanPajak');
            const displayGrdEl   = document.getElementById('displayGrandTotal');
            const inputV         = document.getElementById('inputKodeVoucher');
            const statusV        = document.getElementById('statusVoucherText');
            const chkPajak       = document.getElementById('checkboxPakaiPajak');
            const inpPersenPajak = document.getElementById('inputPersenPajak');
            const labelQris      = document.getElementById('labelTagihanQris');
            const labelQrisModal = document.getElementById('labelTagihanQrisModal');

            if (!subtotalEl || !grandEl) return;

            const subtotal = parseFloat(subtotalEl.value) || 0;
            const kodeRaw  = (inputV ? inputV.value : '').trim().toUpperCase();

            let potongan = 0;

            if (kodeRaw !== '' && DATA_VOUCHER[kodeRaw]) {
                const v = DATA_VOUCHER[kodeRaw];
                if (v.tipe === 'persen') {
                    potongan = Math.round((subtotal * v.nilai) / 100);
                    if (v.max_potongan && potongan > v.max_potongan) {
                        potongan = v.max_potongan;
                    }
                } else {
                    potongan = v.nilai;
                }
                if (potongan > subtotal) potongan = subtotal;

                if (statusV) {
                    statusV.innerHTML = '<span class="text-emerald-700 font-semibold"><i class="fa-solid fa-check-circle"></i> Voucher <b>' + kodeRaw + '</b> aktif: ' + v.label + ' (-Rp ' + potongan.toLocaleString('id-ID') + ')</span>';
                }
            } else if (kodeRaw !== '') {
                if (statusV) {
                    statusV.innerHTML = '<span class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> Kode voucher <b>' + kodeRaw + '</b> tidak valid.</span>';
                }
            } else {
                if (statusV) {
                    statusV.innerHTML = 'Klik salah satu kode di atas atau ketik manual untuk menerapkan potongan harga.';
                }
            }

            const setelahDiskon = Math.max(0, subtotal - potongan);
            const pakaiPajak    = chkPajak ? chkPajak.checked : false;
            const persenPajak   = pakaiPajak && inpPersenPajak ? Math.max(0, parseFloat(inpPersenPajak.value) || 0) : 0;

            if (inpPersenPajak) {
                inpPersenPajak.disabled = !pakaiPajak;
                inpPersenPajak.classList.toggle('bg-slate-100', !pakaiPajak);
                inpPersenPajak.classList.toggle('bg-white', pakaiPajak);
            }

            const nominalPajak = pakaiPajak ? Math.round((setelahDiskon * persenPajak) / 100) : 0;
            const grandTotal   = Math.max(0, setelahDiskon + nominalPajak);

            grandEl.value = grandTotal;
            if (displaySubEl) displaySubEl.value = 'Rp ' + subtotal.toLocaleString('id-ID');
            if (displayDisEl) displayDisEl.value = '- Rp ' + potongan.toLocaleString('id-ID');
            if (labelPajakEl) labelPajakEl.innerText = pakaiPajak ? ('Pajak (' + persenPajak + '%)') : 'Pajak (Nonaktif)';
            if (displayPajakEl) displayPajakEl.value = pakaiPajak ? ('+ Rp ' + nominalPajak.toLocaleString('id-ID')) : 'Rp 0';
            if (displayGrdEl) displayGrdEl.value = 'Rp ' + grandTotal.toLocaleString('id-ID');
            if (labelQris) labelQris.innerHTML = 'Tagihan: Rp ' + grandTotal.toLocaleString('id-ID');
            if (labelQrisModal) labelQrisModal.innerHTML = 'Total Tagihan: Rp ' + grandTotal.toLocaleString('id-ID');

            toggleMetodePembayaran();
        }

        function toggleMetodePembayaran() {
            const metodeEl   = document.getElementById('metodePembayaran');
            const boxQris     = document.getElementById('boxQrisPembayaran');
            const bayarEl     = document.getElementById('uangTunaiCart');
            const kembaliEl   = document.getElementById('uangKembalianCart');
            const grandTotal  = parseFloat(document.getElementById('valGrandTotal')?.value) || 0;

            if (!metodeEl || !bayarEl || !kembaliEl) return;

            if (metodeEl.value === 'QRIS') {
                if (boxQris) boxQris.classList.remove('hidden');
                bayarEl.value = grandTotal;
                bayarEl.readOnly = true;
                bayarEl.classList.add('bg-slate-100');
                bayarEl.classList.remove('bg-white');
                kembaliEl.value = 'Rp 0';
                kembaliEl.classList.remove('text-red-600', 'bg-red-50');
                kembaliEl.classList.add('text-sky-800', 'bg-sky-50');
            } else {
                if (boxQris) boxQris.classList.add('hidden');
                bayarEl.readOnly = false;
                bayarEl.classList.remove('bg-slate-100');
                bayarEl.classList.add('bg-white');
                hitungKembalianCart();
            }
        }

        function bukaModalAddToCart(id, nama, stok) {
            document.getElementById('cartIdProd').value = id;
            document.getElementById('cartNamaProd').value = nama;
            document.getElementById('cartMaxStok').innerText = stok + ' Pcs';
            document.getElementById('cartQty').max = stok;
            document.getElementById('modalAddToCart').classList.remove('hidden');
        }
        function tutupModalAddToCart() {
            document.getElementById('modalAddToCart').classList.add('hidden');
            fokusScanner();
        }

        function bukaModalKeranjang() {
            document.getElementById('modalKeranjang').classList.remove('hidden');
            hitungKalkulasiCheckout();
            toggleMetodePembayaran();
        }
        function tutupModalKeranjang() {
            document.getElementById('modalKeranjang').classList.add('hidden');
            fokusScanner();
        }

        function bukaModalEdit(id, barcode, id_kat, nama, harga, stok) {
            document.getElementById('editId').value = id;
            document.getElementById('editBarcode').value = barcode;
            document.getElementById('editKategori').value = id_kat;
            document.getElementById('editNama').value = nama;
            document.getElementById('editHarga').value = harga;
            document.getElementById('editStok').value = stok;
            document.getElementById('modalEdit').classList.remove('hidden');
        }
        function tutupModalEdit() {
            document.getElementById('modalEdit').classList.add('hidden');
            fokusScanner();
        }

        function hitungKembalianCart() {
            const metode = document.getElementById('metodePembayaran')?.value || 'TUNAI';
            if (metode === 'QRIS') {
                document.getElementById('uangKembalianCart').value = 'Rp 0';
                return;
            }

            const grandTotal = parseFloat(document.getElementById('valGrandTotal').value) || 0;
            const uangTunai  = parseFloat(document.getElementById('uangTunaiCart').value) || 0;
            const kembalian  = uangTunai - grandTotal;

            if (kembalian >= 0) {
                document.getElementById('uangKembalianCart').value = 'Rp ' + kembalian.toLocaleString('id-ID');
                document.getElementById('uangKembalianCart').classList.remove('text-red-600', 'bg-red-50');
                document.getElementById('uangKembalianCart').classList.add('text-sky-800', 'bg-sky-50');
            } else {
                document.getElementById('uangKembalianCart').value = 'Uang Kurang!';
                document.getElementById('uangKembalianCart').classList.remove('text-sky-800', 'bg-sky-50');
                document.getElementById('uangKembalianCart').classList.add('text-red-600', 'bg-red-50');
            }
        }

        function validasiCheckoutKeranjang() {
            const metode = document.getElementById('metodePembayaran')?.value || 'TUNAI';
            if (metode === 'QRIS') {
                return true;
            }

            const grandTotal = parseFloat(document.getElementById('valGrandTotal').value) || 0;
            const uangTunai  = parseFloat(document.getElementById('uangTunaiCart').value) || 0;
            if (uangTunai < grandTotal) {
                alert('Jumlah uang tunai kurang dari total belanja!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>