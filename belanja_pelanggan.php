<?php
// belanja_pelanggan.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pengguna sudah login sebagai pelanggan
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pelanggan') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit();
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'kasir') {
        header("Location: dashboard_kasir.php");
        exit();
    }
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['keranjang_pelanggan'])) {
    $_SESSION['keranjang_pelanggan'] = [];
}

$nama_pelanggan_login = $_SESSION['nama_lengkap'] ?? 'Pelanggan';
$username_login       = $_SESSION['username'] ?? 'pelanggan';

// Cari id_pelanggan yang cocok di tbl_pelanggan, atau buat otomatis jika belum ada
$id_pelanggan_aktif = 0;
$nama_esc = mysqli_real_escape_string($koneksi, $nama_pelanggan_login);
$q_cust = mysqli_query($koneksi, "SELECT id_pelanggan, nama_pelanggan, kota FROM tbl_pelanggan WHERE nama_pelanggan = '$nama_esc' LIMIT 1");
if ($q_cust && mysqli_num_rows($q_cust) > 0) {
    $cust_row = mysqli_fetch_assoc($q_cust);
    $id_pelanggan_aktif = intval($cust_row['id_pelanggan']);
} else {
    $q_any_cust = mysqli_query($koneksi, "SELECT id_pelanggan FROM tbl_pelanggan ORDER BY id_pelanggan ASC LIMIT 1");
    if ($q_any_cust && mysqli_num_rows($q_any_cust) > 0) {
        $any_row = mysqli_fetch_assoc($q_any_cust);
        $id_pelanggan_aktif = intval($any_row['id_pelanggan']);
    } else {
        mysqli_query($koneksi, "INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES ('$nama_esc', 'Ponorogo')");
        $id_pelanggan_aktif = intval(mysqli_insert_id($koneksi));
    }
}

// PROSES TAMBAH KE KERANJANG PELANGGAN
if (isset($_POST['tambah_keranjang'])) {
    $id_produk = intval($_POST['id_produk']);
    $qty_beli  = intval($_POST['qty']);

    $q_prod = mysqli_query($koneksi, "SELECT * FROM tbl_produk WHERE id_produk = $id_produk");
    $prod   = mysqli_fetch_assoc($q_prod);

    if ($prod && $qty_beli > 0) {
        $qty_sekarang = 0;
        foreach ($_SESSION['keranjang_pelanggan'] as $item) {
            if ($item['id_produk'] == $id_produk) {
                $qty_sekarang = intval($item['qty']);
                break;
            }
        }

        if (($qty_sekarang + $qty_beli) <= intval($prod['stok'])) {
            $found = false;
            foreach ($_SESSION['keranjang_pelanggan'] as &$item) {
                if ($item['id_produk'] == $id_produk) {
                    $item['qty'] += $qty_beli;
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION['keranjang_pelanggan'][] = [
                    'id_produk'   => intval($prod['id_produk']),
                    'nama_produk' => $prod['nama_produk'],
                    'harga'       => floatval($prod['harga']),
                    'qty'         => $qty_beli,
                    'gambar'      => $prod['gambar'] ?? ''
                ];
            }

            header("Location: belanja_pelanggan.php?status=sukses_add");
            exit();
        } else {
            header("Location: belanja_pelanggan.php?status=stok_kurang&item=" . urlencode($prod['nama_produk']));
            exit();
        }
    }
}

// PROSES UBAH QTY ITEM DI KERANJANG
if (isset($_POST['update_qty'])) {
    $index    = intval($_POST['index']);
    $qty_baru = intval($_POST['qty_baru']);

    if (isset($_SESSION['keranjang_pelanggan'][$index])) {
        if ($qty_baru <= 0) {
            unset($_SESSION['keranjang_pelanggan'][$index]);
            $_SESSION['keranjang_pelanggan'] = array_values($_SESSION['keranjang_pelanggan']);
            header("Location: belanja_pelanggan.php?status=item_dihapus");
            exit();
        }

        $pid    = intval($_SESSION['keranjang_pelanggan'][$index]['id_produk']);
        $q_prod = mysqli_query($koneksi, "SELECT stok, nama_produk FROM tbl_produk WHERE id_produk = $pid");
        $prod   = mysqli_fetch_assoc($q_prod);

        if ($prod && $qty_baru <= intval($prod['stok'])) {
            $_SESSION['keranjang_pelanggan'][$index]['qty'] = $qty_baru;
            header("Location: belanja_pelanggan.php?status=qty_diubah");
            exit();
        } else {
            header("Location: belanja_pelanggan.php?status=stok_kurang&item=" . urlencode($prod['nama_produk'] ?? 'produk'));
            exit();
        }
    }
}

// PROSES HAPUS ITEM DARI KERANJANG
if (isset($_GET['hapus_cart'])) {
    $index = intval($_GET['hapus_cart']);
    if (isset($_SESSION['keranjang_pelanggan'][$index])) {
        unset($_SESSION['keranjang_pelanggan'][$index]);
        $_SESSION['keranjang_pelanggan'] = array_values($_SESSION['keranjang_pelanggan']);
    }
    header("Location: belanja_pelanggan.php?status=item_dihapus");
    exit();
}

// PROSES KOSONGKAN KERANJANG
if (isset($_GET['kosongkan_cart'])) {
    $_SESSION['keranjang_pelanggan'] = [];
    header("Location: belanja_pelanggan.php?status=cart_kosong");
    exit();
}

// PROSES CHECKOUT BELANJA PELANGGAN (KHUSUS QRIS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_belanja'])) {
    $tanggal           = !empty($_POST['tanggal']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal']) : date('Y-m-d');
    $metode_pembayaran = 'QRIS';

    if ($id_pelanggan_aktif > 0 && !empty($_SESSION['keranjang_pelanggan'])) {
        $stok_aman = true;
        $total_belanja = 0;

        foreach ($_SESSION['keranjang_pelanggan'] as $item) {
            $pid  = intval($item['id_produk']);
            $pqty = intval($item['qty']);
            $q_cek = mysqli_query($koneksi, "SELECT stok, harga, nama_produk FROM tbl_produk WHERE id_produk = $pid");
            $p_cek = mysqli_fetch_assoc($q_cek);

            if (!$p_cek || $pqty <= 0 || $pqty > intval($p_cek['stok'])) {
                $stok_aman = false;
                header("Location: belanja_pelanggan.php?status=stok_kurang&item=" . urlencode($p_cek['nama_produk'] ?? $item['nama_produk']));
                exit();
            }

            $total_belanja += (floatval($p_cek['harga']) * $pqty);
        }

        if ($stok_aman && $total_belanja > 0) {
            $query_trx = "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES ('$tanggal', '$id_pelanggan_aktif', '$total_belanja')";
            if (mysqli_query($koneksi, $query_trx)) {
                $id_trx_baru = intval(mysqli_insert_id($koneksi));

                foreach ($_SESSION['keranjang_pelanggan'] as $item) {
                    $pid  = intval($item['id_produk']);
                    $pqty = intval($item['qty']);
                    $q_pr = mysqli_query($koneksi, "SELECT nama_produk, harga FROM tbl_produk WHERE id_produk = $pid");
                    $pr   = mysqli_fetch_assoc($q_pr);

                    $pname = mysqli_real_escape_string($koneksi, $pr['nama_produk'] ?? $item['nama_produk']);
                    $prc   = floatval($pr['harga'] ?? $item['harga']);

                    mysqli_query($koneksi, "INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES ('$id_trx_baru', '$pid', '$pname', '$pqty', '$prc')");
                    mysqli_query($koneksi, "UPDATE tbl_produk SET stok = stok - $pqty WHERE id_produk = $pid");
                }

                $_SESSION['last_nota_pelanggan'] = [
                    'trx_id' => $id_trx_baru,
                    'metode' => $metode_pembayaran
                ];

                $_SESSION['keranjang_pelanggan'] = [];
                header("Location: belanja_pelanggan.php?status=sukses_belanja&trx_id=" . $id_trx_baru);
                exit();
            }
        }
    }
}

// Filter pencarian & kategori
$keyword     = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, trim($_GET['cari'])) : '';
$kategori_id = isset($_GET['kategori']) ? intval($_GET['kategori']) : 0;

$query_kategori = "SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC";
$result_kategori = mysqli_query($koneksi, $query_kategori);

$query_produk = "SELECT p.*, k.nama_kategori
                 FROM tbl_produk p
                 LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
                 WHERE 1=1";
if ($kategori_id > 0) {
    $query_produk .= " AND p.id_kategori = $kategori_id";
}
if (!empty($keyword)) {
    $query_produk .= " AND (p.nama_produk LIKE '%$keyword%' OR k.nama_kategori LIKE '%$keyword%')";
}
$query_produk .= " ORDER BY p.id_produk DESC";
$result_produk = mysqli_query($koneksi, $query_produk);

// Hitung total item di keranjang
$total_qty_keranjang = 0;
$subtotal_keranjang  = 0;
foreach ($_SESSION['keranjang_pelanggan'] as $item) {
    $total_qty_keranjang += intval($item['qty']);
    $subtotal_keranjang  += (floatval($item['harga']) * intval($item['qty']));
}

// Data nota belanja jika baru checkout
$show_nota = false;
$data_nota = [];
$detail_nota = [];
$nota_metode = 'QRIS';

if (isset($_GET['status']) && $_GET['status'] === 'sukses_belanja' && isset($_GET['trx_id'])) {
    $id_trx_nota = intval($_GET['trx_id']);
    $q_nota = mysqli_query($koneksi, "SELECT t.*, p.nama_pelanggan, p.kota FROM tbl_transaksi t JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan WHERE t.id_transaksi = $id_trx_nota");
    if ($q_nota && mysqli_num_rows($q_nota) > 0) {
        $data_nota = mysqli_fetch_assoc($q_nota);
        $q_det = mysqli_query($koneksi, "SELECT * FROM tbl_detail_transaksi WHERE id_transaksi = $id_trx_nota");
        while ($row_det = mysqli_fetch_assoc($q_det)) {
            $detail_nota[] = $row_det;
        }
        if (isset($_SESSION['last_nota_pelanggan']) && intval($_SESSION['last_nota_pelanggan']['trx_id']) === $id_trx_nota) {
            $nota_metode = $_SESSION['last_nota_pelanggan']['metode'] ?? 'QRIS';
        }
        $show_nota = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belanja Produk - Toko Kita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        @media print {
            body * { visibility: hidden; }
            #notaPrintArea, #notaPrintArea * { visibility: visible; }
            #notaPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 16px;
                background: #ffffff;
                color: #000000;
            }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700 min-h-screen flex flex-col">

    <!-- NAVBAR BELANJA PELANGGAN -->
    <header class="bg-white border-b border-slate-200/80 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-md shadow-sky-600/20">
                    <i class="fa-solid fa-bag-shopping text-base"></i>
                </div>
                <div>
                    <h1 class="font-bold text-slate-800 text-base leading-tight">Toko Kita</h1>
                    <p class="text-[11px] text-sky-600 font-semibold">Area Belanja Pelanggan • Pembayaran QRIS</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 bg-slate-50 border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs">
                    <i class="fa-regular fa-user text-sky-600"></i>
                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($nama_pelanggan_login); ?></span>
                    <span class="text-[10px] bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full font-bold uppercase">Pelanggan</span>
                </div>

                <button type="button" onclick="bukaKeranjang()" class="relative bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm shadow-sky-600/20 transition">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Keranjang Belanja</span>
                    <span class="bg-white text-sky-700 font-extrabold px-2 py-0.5 rounded-full text-[11px]">
                        <?= $total_qty_keranjang; ?>
                    </span>
                </button>

                <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari akun pelanggan?')" class="bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 border border-slate-200 hover:border-red-200 px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="hidden md:inline">Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <!-- KONTEN BELANJA -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-8 py-8 space-y-6 flex-1">

        <!-- BANNER BELANJA -->
        <div class="bg-gradient-to-r from-sky-600 to-blue-600 rounded-2xl p-6 text-white shadow-md flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <span class="inline-block bg-white/15 text-white text-[11px] font-bold px-3 py-1 rounded-full mb-2">
                    <i class="fa-solid fa-qrcode mr-1"></i> Pembayaran QRIS
                </span>
                <h2 class="text-xl font-bold tracking-tight">Halo, <?= htmlspecialchars($nama_pelanggan_login); ?>!</h2>
                <p class="text-xs text-sky-100 mt-1">Pilih produk yang ingin dibeli, masukkan ke keranjang, lalu selesaikan pembayaran melalui QRIS.</p>
            </div>
            <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-right shrink-0">
                <div class="text-[11px] text-sky-100">Total Sementara di Keranjang</div>
                <div class="text-lg font-extrabold text-white mt-0.5">Rp <?= number_format($subtotal_keranjang, 0, ',', '.'); ?></div>
            </div>
        </div>

        <!-- NOTIFIKASI -->
        <?php if (isset($_GET['status'])): ?>
            <?php $is_error = ($_GET['status'] === 'stok_kurang'); ?>
            <div class="<?= $is_error ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'; ?> border px-4 py-3 rounded-2xl text-xs flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid <?= $is_error ? 'fa-circle-exclamation text-red-600' : 'fa-circle-check text-emerald-600'; ?> text-sm"></i>
                    <span>
                        <?php if ($_GET['status'] === 'sukses_add') echo 'Produk berhasil ditambahkan ke keranjang belanja Anda.'; ?>
                        <?php if ($_GET['status'] === 'qty_diubah') echo 'Jumlah produk di keranjang berhasil diperbarui.'; ?>
                        <?php if ($_GET['status'] === 'item_dihapus') echo 'Produk dihapus dari keranjang.'; ?>
                        <?php if ($_GET['status'] === 'cart_kosong') echo 'Keranjang belanja telah dikosongkan.'; ?>
                        <?php if ($_GET['status'] === 'stok_kurang') echo 'Stok <b>' . htmlspecialchars($_GET['item'] ?? 'produk') . '</b> tidak mencukupi.'; ?>
                        <?php if ($_GET['status'] === 'sukses_belanja') echo 'Pesanan QRIS Anda berhasil diproses! Nota belanja siap dilihat atau dicetak.'; ?>
                    </span>
                </div>
                <a href="belanja_pelanggan.php" class="text-slate-500 hover:text-slate-800"><i class="fa-solid fa-xmark text-sm"></i></a>
            </div>
        <?php endif; ?>

        <!-- FILTER PENCARIAN & KATEGORI -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
                <a href="belanja_pelanggan.php<?= !empty($keyword) ? '?cari=' . urlencode($keyword) : ''; ?>" class="<?= $kategori_id === 0 ? 'bg-sky-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'; ?> px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition">
                    Semua Produk
                </a>
                <?php mysqli_data_seek($result_kategori, 0); ?>
                <?php while ($kat = mysqli_fetch_assoc($result_kategori)): ?>
                    <?php $aktif = ($kategori_id === intval($kat['id_kategori'])); ?>
                    <a href="belanja_pelanggan.php?kategori=<?= intval($kat['id_kategori']); ?><?= !empty($keyword) ? '&cari=' . urlencode($keyword) : ''; ?>" class="<?= $aktif ? 'bg-sky-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'; ?> px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition">
                        <?= htmlspecialchars($kat['nama_kategori']); ?>
                    </a>
                <?php endwhile; ?>
            </div>

            <form action="" method="GET" class="flex items-center gap-2 w-full md:w-72 shrink-0">
                <?php if ($kategori_id > 0): ?>
                    <input type="hidden" name="kategori" value="<?= $kategori_id; ?>">
                <?php endif; ?>
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" name="cari" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari barang belanjaan..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-sky-600 text-slate-800">
                </div>
                <?php if (!empty($keyword) || $kategori_id > 0): ?>
                    <a href="belanja_pelanggan.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-xl text-xs font-semibold transition" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- KATALOG PRODUK BELANJA -->
        <?php if ($result_produk && mysqli_num_rows($result_produk) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php while ($prod = mysqli_fetch_assoc($result_produk)): ?>
                    <?php
                        $ada_foto = !empty($prod['gambar']) && file_exists('uploads/' . $prod['gambar']);
                        $stok_tersedia = intval($prod['stok']);
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-2xs flex flex-col justify-between hover:border-sky-300 transition">
                        <div>
                            <div class="h-44 bg-slate-100 relative overflow-hidden flex items-center justify-center border-b border-slate-100">
                                <?php if ($ada_foto): ?>
                                    <img src="uploads/<?= htmlspecialchars($prod['gambar']); ?>" alt="<?= htmlspecialchars($prod['nama_produk']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="text-slate-400 flex flex-col items-center gap-1.5">
                                        <i class="fa-solid fa-box-open text-3xl"></i>
                                        <span class="text-[11px]">Foto Produk</span>
                                    </div>
                                <?php endif; ?>

                                <span class="absolute top-3 left-3 bg-white/95 text-slate-700 px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-2xs border border-slate-200/60">
                                    <?= htmlspecialchars($prod['nama_kategori'] ?? 'Umum'); ?>
                                </span>
                            </div>

                            <div class="p-4 space-y-2">
                                <h3 class="font-bold text-slate-800 text-sm line-clamp-1">
                                    <?= htmlspecialchars($prod['nama_produk']); ?>
                                </h3>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-base font-extrabold text-sky-600">
                                        Rp <?= number_format($prod['harga'], 0, ',', '.'); ?>
                                    </span>
                                    <?php if ($stok_tersedia > 0): ?>
                                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                            Stok: <?= $stok_tersedia; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] font-bold text-red-600 bg-red-50 border border-red-200 px-2.5 py-0.5 rounded-full">
                                            Habis
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="px-4 pb-4 pt-2">
                            <?php if ($stok_tersedia > 0): ?>
                                <button type="button" onclick="bukaModalBeli(<?= intval($prod['id_produk']); ?>, '<?= htmlspecialchars($prod['nama_produk'], ENT_QUOTES); ?>', <?= floatval($prod['harga']); ?>, <?= $stok_tersedia; ?>)" class="w-full bg-sky-600 hover:bg-sky-700 text-white py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition shadow-sm">
                                    <i class="fa-solid fa-cart-plus"></i> Beli / Masukkan Keranjang
                                </button>
                            <?php else: ?>
                                <button type="button" disabled class="w-full bg-slate-100 text-slate-400 py-2.5 rounded-xl text-xs font-bold cursor-not-allowed">
                                    Stok Habis
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center text-slate-400 space-y-2">
                <i class="fa-solid fa-store-slash text-3xl text-slate-300"></i>
                <p class="font-semibold text-slate-600 text-sm">Produk belanja tidak ditemukan.</p>
                <p class="text-xs">Coba kata kunci lain atau pilih kategori "Semua Produk".</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL PILIH JUMLAH BELI -->
    <div id="modalBeli" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Tambah ke Keranjang</h3>
                <button type="button" onclick="tutupModalBeli()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6 space-y-4 text-xs">
                <input type="hidden" id="beliIdProd" name="id_produk">
                <input type="hidden" id="beliHargaSatuan" value="0">

                <div>
                    <label class="block font-semibold text-slate-600 mb-1">Nama Produk</label>
                    <input type="text" id="beliNamaProd" readonly class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-800 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Jumlah Beli</label>
                        <input type="number" id="beliQty" name="qty" min="1" value="1" required oninput="hitungEstimasiBeli()" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-sky-600">
                        <p class="text-[10px] text-slate-400 mt-1">Stok tersedia: <span id="beliMaxStok" class="font-bold text-slate-600"></span></p>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Estimasi Total</label>
                        <input type="text" id="beliEstimasi" readonly value="Rp 0" class="w-full px-3.5 py-2.5 bg-sky-50 border border-sky-200 rounded-xl font-extrabold text-sky-700 outline-none">
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="tutupModalBeli()" class="px-4 py-2 font-semibold text-slate-500 hover:bg-slate-100 rounded-xl transition">Batal</button>
                    <button type="submit" name="tambah_keranjang" class="px-5 py-2 font-bold bg-sky-600 hover:bg-sky-700 text-white rounded-xl transition shadow-sm">
                        + Masukkan Keranjang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL KERANJANG & CHECKOUT QRIS -->
    <div id="modalKeranjang" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden border border-slate-100 flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-cart-shopping text-xs"></i>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm">Keranjang Belanja Saya</h3>
                </div>
                <button type="button" onclick="tutupKeranjang()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                <?php if (!empty($_SESSION['keranjang_pelanggan'])): ?>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                                    <th class="py-2.5 px-4">No</th>
                                    <th class="py-2.5 px-4">Produk</th>
                                    <th class="py-2.5 px-4 text-right">Harga</th>
                                    <th class="py-2.5 px-4 text-center">Qty</th>
                                    <th class="py-2.5 px-4 text-right">Subtotal</th>
                                    <th class="py-2.5 px-4 text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php $no = 1; ?>
                                <?php foreach ($_SESSION['keranjang_pelanggan'] as $idx => $item): ?>
                                    <?php $sub = floatval($item['harga']) * intval($item['qty']); ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-4 font-bold"><?= $no++; ?></td>
                                        <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($item['nama_produk']); ?></td>
                                        <td class="py-3 px-4 text-right">Rp <?= number_format($item['harga'], 0, ',', '.'); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <form action="" method="POST" class="inline-flex items-center justify-center gap-1">
                                                <input type="hidden" name="index" value="<?= $idx; ?>">
                                                <input type="number" name="qty_baru" min="1" value="<?= intval($item['qty']); ?>" class="w-14 px-2 py-1 border border-slate-300 rounded-lg text-center font-bold text-slate-800 outline-none">
                                                <button type="submit" name="update_qty" class="bg-slate-100 hover:bg-sky-100 text-sky-700 px-2 py-1 rounded-lg border border-slate-200 text-[10px] font-bold" title="Update Jumlah">
                                                    OK
                                                </button>
                                            </form>
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-slate-800">Rp <?= number_format($sub, 0, ',', '.'); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="belanja_pelanggan.php?hapus_cart=<?= $idx; ?>" class="text-red-500 hover:text-red-700 bg-red-50 p-1.5 rounded-lg border border-red-200 inline-block" title="Hapus">
                                                <i class="fa-solid fa-trash text-[11px]"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="" method="POST" class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4">
                        <input type="hidden" name="checkout_belanja" value="1">
                        <input type="hidden" name="metode_pembayaran" value="QRIS">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Pembeli</label>
                                <input type="text" readonly value="<?= htmlspecialchars($nama_pelanggan_login); ?>" class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl font-semibold text-slate-700 outline-none">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Tanggal Belanja</label>
                                <input type="date" name="tanggal" required value="<?= date('Y-m-d'); ?>" class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-800 outline-none">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 mb-1">Metode Pembayaran</label>
                                <input type="text" readonly value="QRIS (Scan QR)" class="w-full px-3.5 py-2 bg-sky-50 border border-sky-200 rounded-xl font-bold text-sky-700 outline-none">
                            </div>
                        </div>

                        <!-- PANEL PEMBAYARAN QRIS -->
                        <div class="bg-sky-50/80 border-2 border-sky-200 rounded-2xl p-5 flex flex-col items-center text-center gap-3">
                            <div class="w-full flex items-center justify-between border-b border-sky-200/80 pb-2 text-left">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700">Pembayaran Scan QRIS</div>
                                    <div class="font-bold text-slate-800 text-sm">Toko Rasya Cihuy</div>
                                    <div class="text-[11px] font-mono text-slate-600">NMID : ID1026499001851</div>
                                </div>
                                <button type="button" onclick="bukaModalQrisBesar()" class="bg-white hover:bg-sky-100 text-sky-700 border border-sky-300 px-3 py-1.5 rounded-xl text-[11px] font-bold flex items-center gap-1.5 shadow-2xs transition shrink-0">
                                    <i class="fa-solid fa-up-right-and-down-left-from-center"></i> Layar Penuh
                                </button>
                            </div>

                            <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm cursor-pointer" onclick="bukaModalQrisBesar()" title="Klik untuk memperbesar QRIS">
                                <img src="qris.jpeg" alt="QRIS Toko Zeavara" class="w-56 sm:w-64 h-auto rounded-xl object-contain mx-auto">
                            </div>

                            <div class="space-y-1">
                                <div class="text-sm font-extrabold text-sky-800">
                                    Total Tagihan QRIS: Rp <?= number_format($subtotal_keranjang, 0, ',', '.'); ?>
                                </div>
                                <p class="text-[11px] text-slate-600">Scan kode QR di atas menggunakan aplikasi m-banking / e-wallet Anda, lalu klik <b>Bayar &amp; Selesaikan QRIS</b>.</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                            <div>
                                <span class="text-slate-500 font-semibold">Total Pembayaran (QRIS):</span>
                                <div class="text-lg font-extrabold text-sky-700">Rp <?= number_format($subtotal_keranjang, 0, ',', '.'); ?></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="belanja_pelanggan.php?kosongkan_cart=1" onclick="return confirm('Kosongkan semua isi keranjang?')" class="px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 border border-slate-300 hover:border-red-200 rounded-xl font-semibold transition">
                                    Kosongkan
                                </a>
                                <button type="submit" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-bold transition shadow-sm flex items-center gap-1.5">
                                    <i class="fa-solid fa-qrcode"></i> Bayar &amp; Selesaikan QRIS
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-12 text-slate-400 space-y-2">
                        <i class="fa-solid fa-cart-shopping text-3xl text-slate-300"></i>
                        <p class="font-semibold text-slate-600">Keranjang belanja Anda masih kosong.</p>
                        <p class="text-[11px]">Pilih produk pada katalog lalu klik tombol "Beli / Masukkan Keranjang".</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL QRIS UKURAN PENUH -->
    <div id="modalQrisBesar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 p-6 text-center space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 text-left">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700">Scan QRIS Pembayaran</div>
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

            <div class="text-base font-extrabold text-sky-700">
                Total Tagihan QRIS: Rp <?= number_format($subtotal_keranjang, 0, ',', '.'); ?>
            </div>

            <button type="button" onclick="tutupModalQrisBesar()" class="w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                Kembali ke Keranjang
            </button>
        </div>
    </div>

    <!-- MODAL NOTA BELANJA SETELAH CHECKOUT -->
    <?php if ($show_nota): ?>
    <div id="modalNota" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-slate-200">
            <div id="notaPrintArea" class="p-6 space-y-4 text-xs font-mono">
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <h2 class="font-bold text-base text-slate-900 font-sans">TOKO KITA</h2>
                    <p class="text-[11px] text-slate-500">Bukti Belanja Pelanggan</p>
                    <p class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars($data_nota['tanggal']); ?></p>
                </div>

                <div class="space-y-1 text-slate-600 border-b border-dashed border-slate-300 pb-3">
                    <div class="flex justify-between">
                        <span>No. Nota:</span>
                        <span class="font-bold text-slate-800">#TRX-<?= str_pad($data_nota['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pelanggan:</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($data_nota['nama_pelanggan']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pembayaran:</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($nota_metode); ?></span>
                    </div>
                </div>

                <div class="border-b border-dashed border-slate-300 pb-3 space-y-1.5">
                    <p class="font-bold text-slate-800 font-sans text-[11px]">Item Dibeli:</p>
                    <?php foreach ($detail_nota as $det): ?>
                        <div class="flex justify-between text-[11px]">
                            <span><?= htmlspecialchars($det['nama_produk']); ?> (x<?= intval($det['jumlah']); ?>)</span>
                            <span>Rp <?= number_format(floatval($det['harga']) * intval($det['jumlah']), 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-between font-bold text-slate-900 text-sm">
                    <span>TOTAL BAYAR (QRIS):</span>
                    <span class="text-sky-600">Rp <?= number_format($data_nota['total_belanja'], 0, ',', '.'); ?></span>
                </div>

                <div class="text-center pt-2 text-[11px] text-slate-500 font-sans">
                    <p class="font-semibold text-slate-700">Terima kasih telah berbelanja!</p>
                </div>
            </div>

            <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between font-sans text-xs">
                <button type="button" onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg font-semibold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-print text-[10px]"></i> Cetak Nota
                </button>
                <a href="belanja_pelanggan.php" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-1.5 rounded-lg font-semibold transition">
                    Belanja Lagi
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function bukaModalBeli(id, nama, harga, stok) {
            document.getElementById('beliIdProd').value = id;
            document.getElementById('beliNamaProd').value = nama;
            document.getElementById('beliHargaSatuan').value = harga;
            document.getElementById('beliMaxStok').innerText = stok + ' Pcs';
            const qtyInput = document.getElementById('beliQty');
            qtyInput.max = stok;
            qtyInput.value = 1;
            hitungEstimasiBeli();
            document.getElementById('modalBeli').classList.remove('hidden');
        }

        function tutupModalBeli() {
            document.getElementById('modalBeli').classList.add('hidden');
        }

        function hitungEstimasiBeli() {
            const harga = parseFloat(document.getElementById('beliHargaSatuan').value) || 0;
            const qty   = parseInt(document.getElementById('beliQty').value, 10) || 0;
            const total = Math.max(0, harga * qty);
            document.getElementById('beliEstimasi').value = 'Rp ' + total.toLocaleString('id-ID');
        }

        function bukaKeranjang() {
            document.getElementById('modalKeranjang').classList.remove('hidden');
        }

        function tutupKeranjang() {
            document.getElementById('modalKeranjang').classList.add('hidden');
        }

        function bukaModalQrisBesar() {
            const m = document.getElementById('modalQrisBesar');
            if (m) m.classList.remove('hidden');
        }

        function tutupModalQrisBesar() {
            const m = document.getElementById('modalQrisBesar');
            if (m) m.classList.add('hidden');
        }
    </script>
</body>
</html>