<?php
require_once '../config/database.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT th.*, td.qty, td.harga_satuan, td.subtotal, b.kode_barang, b.nama_barang, s.nama_satuan
    FROM transaksi_header th
    JOIN transaksi_detail td ON td.id_transaksi = th.id
    JOIN barang b ON td.id_barang = b.id
    JOIN satuan s ON b.id_satuan = s.id
    WHERE th.id = ?
");
$stmt->execute([$id]);
$rows = $stmt->fetchAll();

if (!$rows) {
    die("Data transaksi tidak ditemukan.");
}
$head = $rows[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak SBBK - <?= htmlspecialchars($head['kode_transaksi']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; }
        .kop-surat { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-4">
<div class="container" style="max-width: 780px;">
    <div class="no-print mb-4 d-flex justify-content-between">
        <a href="mutasi_keluar.php" class="btn btn-secondary btn-sm">&larr; Kembali</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Cetak SBBK Sekarang</button>
    </div>

    <div class="kop-surat text-center">
        <h5 class="mb-0 fw-bold text-uppercase">Pemerintah Kota Pontianak</h5>
        <h4 class="mb-1 fw-bold text-uppercase">Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu</h4>
        <small>Jl. Sutoyo No. 1, Kota Pontianak, Kalimantan Barat</small>
    </div>

    <div class="text-center mb-4">
        <h5 class="fw-bold text-decoration-underline mb-1">SURAT BUKTI BARANG KELUAR (SBBK)</h5>
        <div>Nomor: <?= htmlspecialchars($head['kode_transaksi']) ?></div>
    </div>

    <table class="table table-borderless table-sm mb-3">
        <tr>
            <td width="180">Tanggal Pengeluaran</td>
            <td width="10">:</td>
            <td><strong><?= htmlspecialchars($head['tanggal']) ?></strong></td>
        </tr>
        <tr>
            <td>Diserahkan Kepada Bidang</td>
            <td>:</td>
            <td><strong><?= htmlspecialchars($head['bidang_atau_rekanan']) ?></strong> (Penerima: <?= htmlspecialchars($head['nama_penerima'] ?? '-') ?>)</td>
        </tr>
        <tr>
            <td>Status Pencatatan</td>
            <td>:</td>
            <td><?= htmlspecialchars($head['tipe_dampak']) ?> &mdash; <?= htmlspecialchars($head['keterangan']) ?></td>
        </tr>
    </table>

    <table class="table table-bordered border-dark align-middle mb-4">
        <thead class="text-center">
            <tr>
                <th width="50">No</th>
                <th>Kode Barang</th>
                <th>Nama & Spesifikasi Barang</th>
                <th width="120">Jumlah</th>
                <th width="120">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center"><?= htmlspecialchars($r['kode_barang']) ?></td>
                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <td class="text-center fw-bold"><?= $r['qty'] ?></td>
                <td class="text-center"><?= htmlspecialchars($r['nama_satuan']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row text-center mt-5">
        <div class="col-6">
            <p class="mb-5">Yang Menerima,<br>Staf Bidang / Unit Kerja</p>
            <p class="fw-bold text-decoration-underline mt-4">( <?= htmlspecialchars($head['nama_penerima'] ?: '....................................') ?> )</p>
        </div>
        <div class="col-6">
            <p class="mb-5">Yang Menyerahkan,<br>Penyimpan Barang DPMPTSP</p>
            <p class="fw-bold text-decoration-underline mt-4">( Penyimpan Barang )</p>
        </div>
    </div>
</div>
</body>
</html>
