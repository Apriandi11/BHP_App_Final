<?php
require_once '../config/database.php';

$pesan = '';
$tipe_alert = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang = (int)$_POST['id_barang'];
    $tanggal   = $_POST['tanggal'];
    $bidang    = trim($_POST['bidang']);
    $penerima  = trim($_POST['nama_penerima']) ?: '-';
    $tipe      = $_POST['tipe_dampak'];
    $qty_req   = (int)$_POST['qty'];
    $ket       = trim($_POST['keterangan']);

    $stmtBrg = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
    $stmtBrg->execute([$id_barang]);
    $brg = $stmtBrg->fetch();

    $pdo->beginTransaction();
    try {
        if (($tipe === 'KEDUANYA' || $tipe === 'FISIK_SAJA') && $brg['stok_fisik'] < $qty_req) {
            throw new Exception("Stok Fisik di gudang tidak mencukupi! (Sisa Fisik: {$brg['stok_fisik']}, Diminta: $qty_req)");
        }
        if (($tipe === 'KEDUANYA' || $tipe === 'SPJ_SAJA') && $brg['stok_spj'] < $qty_req) {
            throw new Exception("Saldo Stok SPJ tidak mencukupi! (Sisa SPJ: {$brg['stok_spj']}, Diminta: $qty_req). Gunakan opsi 'Kasbon Bidang (Potong Fisik Saja)' jika barang diambil mendahului SPJ.");
        }

        $kode_trx = 'SBBK-' . date('Ymd-His');
        $stmtHead = $pdo->prepare("INSERT INTO transaksi_header (kode_transaksi, tanggal, jenis_transaksi, tipe_dampak, bidang_atau_rekanan, nama_penerima, keterangan) VALUES (?, ?, 'KELUAR', ?, ?, ?, ?)");
        $stmtHead->execute([$kode_trx, $tanggal, $tipe, $bidang, $penerima, $ket]);
        $id_trx = $pdo->lastInsertId();

        $kolom_fifo = ($tipe === 'FISIK_SAJA') ? 'qty_sisa_fisik' : 'qty_sisa_spj';
        $stmtBatch = $pdo->prepare("SELECT * FROM batch_stok WHERE id_barang = ? AND $kolom_fifo > 0 ORDER BY tanggal_masuk ASC, id ASC");
        $stmtBatch->execute([$id_barang]);
        $batches = $stmtBatch->fetchAll();

        $sisa_potong = $qty_req;
        foreach ($batches as $batch) {
            if ($sisa_potong <= 0) break;
            $tersedia = $batch[$kolom_fifo];
            $ambil = min($sisa_potong, $tersedia);

            if ($tipe === 'KEDUANYA') {
                $updB = $pdo->prepare("UPDATE batch_stok SET qty_sisa_fisik = GREATEST(0, qty_sisa_fisik - ?), qty_sisa_spj = qty_sisa_spj - ? WHERE id = ?");
                $updB->execute([$ambil, $ambil, $batch['id']]);
            } elseif ($tipe === 'FISIK_SAJA') {
                $updB = $pdo->prepare("UPDATE batch_stok SET qty_sisa_fisik = qty_sisa_fisik - ? WHERE id = ?");
                $updB->execute([$ambil, $batch['id']]);
            } elseif ($tipe === 'SPJ_SAJA') {
                $updB = $pdo->prepare("UPDATE batch_stok SET qty_sisa_spj = qty_sisa_spj - ? WHERE id = ?");
                $updB->execute([$ambil, $batch['id']]);
            }

            $subtotal = round($ambil * $batch['harga_satuan_murni'], 2);
            $stmtDet = $pdo->prepare("INSERT INTO transaksi_detail (id_transaksi, id_barang, id_batch, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtDet->execute([$id_trx, $id_barang, $batch['id'], $ambil, $batch['harga_satuan_murni'], $subtotal]);

            $sisa_potong -= $ambil;
        }

        $potong_fisik = ($tipe === 'KEDUANYA' || $tipe === 'FISIK_SAJA') ? $qty_req : 0;
        $potong_spj   = ($tipe === 'KEDUANYA' || $tipe === 'SPJ_SAJA') ? $qty_req : 0;

        $updMaster = $pdo->prepare("UPDATE barang SET stok_fisik = stok_fisik - ?, stok_spj = stok_spj - ? WHERE id = ?");
        $updMaster->execute([$potong_fisik, $potong_spj, $id_barang]);

        $pdo->commit();
        $pesan = "Distribusi barang tercatat & otomatis dipotong mengikuti Batch FIFO! (No: $kode_trx)";
        $tipe_alert = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = $e->getMessage();
        $tipe_alert = 'danger';
    }
}

$barangList = $pdo->query("SELECT b.*, s.nama_satuan FROM barang b JOIN satuan s ON b.id_satuan = s.id WHERE b.is_active = 1 ORDER BY b.nama_barang")->fetchAll();
$riwayatKeluar = $pdo->query("
    SELECT th.*, td.qty, td.harga_satuan, td.subtotal, b.nama_barang, b.kode_barang, s.nama_satuan
    FROM transaksi_detail td
    JOIN transaksi_header th ON td.id_transaksi = th.id
    JOIN barang b ON td.id_barang = b.id
    JOIN satuan s ON b.id_satuan = s.id
    WHERE th.jenis_transaksi = 'KELUAR'
    ORDER BY th.id DESC LIMIT 15
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Distribusi Barang ke Bidang & Pencatatan Kasbon</h4>
    <p class="text-secondary small mb-0">Catat pengambilan ATK oleh bidang, pisahkan antara pengeluaran resmi SPJ dan kasbon fisik, serta cetak bukti pengeluaran (SBBK).</p>
</div>

<?php if ($pesan): ?>
    <div class="alert alert-<?= $tipe_alert ?> rounded-3 border-0 shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($pesan) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-glass p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-send-check-fill text-warning me-2"></i>Form Pengeluaran / Bon ATK</h6>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Barang</label>
                    <select name="id_barang" class="form-select" required>
                        <?php foreach ($barangList as $b): ?>
                            <option value="<?= $b['id'] ?>">
                                <?= htmlspecialchars($b['nama_barang']) ?> (Fisik: <?= $b['stok_fisik'] ?> | SPJ: <?= $b['stok_spj'] ?> <?= $b['nama_satuan'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tanggal Keluar</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Kuantitas Keluar</label>
                        <input type="number" name="qty" class="form-control fw-bold" value="1" min="1" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Bidang / Unit Kerja</label>
                        <input type="text" name="bidang" class="form-control" placeholder="Bidang Perizinan / Sekretariat" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Nama Pengambil</label>
                        <input type="text" name="nama_penerima" class="form-control" placeholder="Nama Staf" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mode Pemotongan Stok</label>
                    <select name="tipe_dampak" class="form-select" required>
                        <option value="KEDUANYA">Reguler Resmi (Potong Fisik Gudang &amp; Saldo SPJ)</option>
                        <option value="FISIK_SAJA">Kasbon Bidang / Mendahului SPJ (Potong Fisik Saja)</option>
                        <option value="SPJ_SAJA">Pelunasan Administrasi SPJ (Potong Saldo SPJ Saja)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold">Keperluan / Keterangan Bon</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Kebutuhan cetak berkas layanan perizinan"></textarea>
                </div>

                <button type="submit" class="btn btn-warning w-100 btn-modern justify-content-center">
                    <i class="bi bi-check2-square"></i> Catat Pengeluaran (FIFO)
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-glass overflow-hidden">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">Riwayat Distribusi & SBBK</h6>
                    <small class="text-muted">Klik ikon cetak untuk mencetak bukti pengambilan barang (SBBK)</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>No. Bukti & Bidang</th>
                            <th>Barang</th>
                            <th class="text-center">Mode</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Nilai FIFO</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatKeluar as $rk): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($rk['bidang_atau_rekanan']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($rk['nama_penerima'] ?? '-') ?> &bull; <?= $rk['tanggal'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($rk['nama_barang']) ?></td>
                            <td class="text-center">
                                <?php if ($rk['tipe_dampak'] === 'FISIK_SAJA'): ?>
                                    <span class="pill-badge pill-kasbon">FISIK SAJA</span>
                                <?php elseif ($rk['tipe_dampak'] === 'SPJ_SAJA'): ?>
                                    <span class="pill-badge pill-nondpa">SPJ SAJA</span>
                                <?php else: ?>
                                    <span class="pill-badge pill-sinkron">REGULER</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold"><?= $rk['qty'] ?> <?= htmlspecialchars($rk['nama_satuan']) ?></td>
                            <td class="text-end font-mono"><?= formatRupiah($rk['subtotal'], 2) ?></td>
                            <td class="text-center">
                                <a href="cetak_sbbk.php?id=<?= $rk['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Cetak SBBK / Bon">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
