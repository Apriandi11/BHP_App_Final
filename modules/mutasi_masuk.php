<?php
require_once '../config/database.php';

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang   = (int)$_POST['id_barang'];
    $tanggal     = $_POST['tanggal'];
    $no_dokumen  = trim($_POST['no_dokumen']);
    $rekanan     = trim($_POST['rekanan']) ?: 'Rekanan Pengadaan';
    $tipe_masuk  = $_POST['tipe_masuk'];
    $qty_fisik   = (int)$_POST['qty_fisik'];
    $qty_spj     = (int)$_POST['qty_spj'];
    $harga_murni = (float)$_POST['harga_satuan_murni'];
    $harga_bulat = round($harga_murni, 2);
    $catatan     = trim($_POST['catatan']);

    if ($tipe_masuk === 'KEDUANYA') {
        $qty_spj = $qty_fisik;
    } elseif ($tipe_masuk === 'FISIK_SAJA') {
        $qty_spj = 0;
    } elseif ($tipe_masuk === 'SPJ_SAJA') {
        $qty_fisik = 0;
    }

    $is_silang = ($tipe_masuk === 'SUBSIDI_SILANG') ? 1 : 0;

    $pdo->beginTransaction();
    try {
        $stmtBatch = $pdo->prepare("
            INSERT INTO batch_stok 
            (id_barang, tanggal_masuk, no_dokumen, qty_awal_spj, qty_sisa_spj, qty_awal_fisik, qty_sisa_fisik, harga_satuan_murni, harga_satuan_bulat, is_subsidi_silang, catatan_silang)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtBatch->execute([$id_barang, $tanggal, $no_dokumen, $qty_spj, $qty_spj, $qty_fisik, $qty_fisik, $harga_murni, $harga_bulat, $is_silang, $catatan]);
        $id_batch = $pdo->lastInsertId();

        $stmtUpd = $pdo->prepare("UPDATE barang SET stok_fisik = stok_fisik + ?, stok_spj = stok_spj + ? WHERE id = ?");
        $stmtUpd->execute([$qty_fisik, $qty_spj, $id_barang]);

        $kode_trx = 'IN-' . date('YmdHis');
        $stmtHead = $pdo->prepare("INSERT INTO transaksi_header (kode_transaksi, tanggal, jenis_transaksi, tipe_dampak, bidang_atau_rekanan, no_bukti_spj, keterangan) VALUES (?, ?, 'MASUK', ?, ?, ?, ?)");
        $stmtHead->execute([$kode_trx, $tanggal, $tipe_masuk, $rekanan, $no_dokumen, $catatan]);
        $id_trx = $pdo->lastInsertId();

        $qty_catat = max($qty_fisik, $qty_spj);
        $subtotal = round($qty_catat * $harga_murni, 2);
        $stmtDet = $pdo->prepare("INSERT INTO transaksi_detail (id_transaksi, id_barang, id_batch, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtDet->execute([$id_trx, $id_barang, $id_batch, $qty_catat, $harga_murni, $subtotal]);

        $pdo->commit();
        $pesan = "Batch Penerimaan baru berhasil dicatat ke antrean FIFO! (Kode: $kode_trx)";
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = "Gagal menyimpan: " . $e->getMessage();
    }
}

$barangList = $pdo->query("SELECT b.*, s.nama_satuan FROM barang b JOIN satuan s ON b.id_satuan = s.id WHERE b.is_active = 1 ORDER BY b.nama_barang")->fetchAll();
$batchTerbaru = $pdo->query("
    SELECT bs.*, b.nama_barang, b.kode_barang, s.nama_satuan 
    FROM batch_stok bs 
    JOIN barang b ON bs.id_barang = b.id 
    JOIN satuan s ON b.id_satuan = s.id 
    ORDER BY bs.id DESC LIMIT 12
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Penerimaan Barang Masuk & Kalkulator Batch FIFO</h4>
    <p class="text-secondary small mb-0">Catat barang masuk berdasarkan SPJ/Nota, hitung otomatis harga satuan 4 desimal dari nilai kontrak/PPN, atau catat barang Subsidi Silang (Non-DPA).</p>
</div>

<?php if ($pesan): ?>
    <div class="alert alert-success rounded-3 border-0 shadow-sm"><i class="bi bi-check2-circle me-2"></i><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-glass p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-box-arrow-in-down-left text-success me-2"></i>Form Penerimaan Batch Baru</h6>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Barang</label>
                    <select name="id_barang" class="form-select" required>
                        <?php foreach ($barangList as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['kode_barang']) ?> &mdash; <?= htmlspecialchars($b['nama_barang']) ?> (Fisik: <?= $b['stok_fisik'] ?> | SPJ: <?= $b['stok_spj'] ?> <?= $b['nama_satuan'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tanggal Masuk</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">No. Bukti SPJ / Kontrak</label>
                        <input type="text" name="no_dokumen" class="form-control font-mono" placeholder="SPJ-TW2-001" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Penyedia / Rekanan</label>
                    <input type="text" name="rekanan" class="form-control" placeholder="Contoh: CV. Mitra Mandiri / Toko ATK">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mode Dampak Stok (Dual-Tracking)</label>
                    <select name="tipe_masuk" id="tipe_masuk" class="form-select" onchange="toggleSilang()" required>
                        <option value="KEDUANYA">Reguler Resmi (Tambah Fisik Gudang &amp; Saldo SPJ Sama)</option>
                        <option value="FISIK_SAJA">Barang Titipan / Mendahului SPJ (Tambah Fisik Saja)</option>
                        <option value="SPJ_SAJA">Susulan Dokumen SPJ (Tambah Saldo SPJ Saja)</option>
                        <option value="SUBSIDI_SILANG">Subsidi Silang / Non-DPA (Qty Fisik &ne; Qty SPJ)</option>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Qty Masuk Fisik (Riil)</label>
                        <input type="number" name="qty_fisik" id="qty_fisik" class="form-control fw-bold" value="10" min="0" oninput="hitungOtomatis()" required>
                    </div>
                    <div class="col-6" id="col_qty_spj" style="display:none;">
                        <label class="form-label small fw-semibold text-primary">Qty Tertulis di SPJ</label>
                        <input type="number" name="qty_spj" id="qty_spj" class="form-control border-primary fw-bold" value="10" min="0" oninput="hitungOtomatis()">
                    </div>
                </div>

                <div class="p-3 rounded-3 mb-3 border bg-light bg-opacity-50">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-primary"><i class="bi bi-calculator me-1"></i> Kalkulator Bantu Harga Kontrak/PPN</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary">4 Desimal Otomatis</span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Opsional: Masukkan Total Nilai Kontrak Baris Ini (Rp)</label>
                        <input type="number" step="0.01" id="total_kontrak_bantu" class="form-control form-control-sm font-mono" placeholder="Misal: 1455010 (Otomatis dibagi Qty)" oninput="hitungOtomatis()">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold mb-1">Harga Satuan Murni (Presisi 4 Desimal)</label>
                        <input type="number" step="0.0001" name="harga_satuan_murni" id="harga_satuan_murni" class="form-control font-mono fw-bold" placeholder="48500.3333" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Catatan / Keterangan Subsidi Silang</label>
                    <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan audit internal (misal: konversi dari item SPJ kertas ke map)"></textarea>
                </div>

                <button type="submit" class="btn btn-success w-100 btn-modern justify-content-center">
                    <i class="bi bi-plus-circle-fill"></i> Simpan Penerimaan &amp; Buat Batch FIFO
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-glass overflow-hidden">
            <div class="p-3 border-bottom">
                <h6 class="mb-0 fw-bold">Antrean Batch Stok Aktif (Urutan Pemotongan FIFO)</h6>
                <small class="text-muted">Setiap pengeluaran barang akan otomatis memotong batch dari tanggal masuk paling awal.</small>
            </div>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Dokumen & Tanggal</th>
                            <th>Barang</th>
                            <th class="text-center">Sisa Fisik</th>
                            <th class="text-center">Sisa SPJ</th>
                            <th class="text-end">Harga Satuan (4 Des)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batchTerbaru as $bs): ?>
                        <tr>
                            <td>
                                <span class="font-mono fw-bold small text-primary"><?= htmlspecialchars($bs['no_dokumen']) ?></span>
                                <small class="d-block text-muted"><?= $bs['tanggal_masuk'] ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($bs['nama_barang']) ?></div>
                                <?php if ($bs['catatan_silang']): ?>
                                    <span class="pill-badge pill-kasbon mt-1"><?= htmlspecialchars($bs['catatan_silang']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold"><?= $bs['qty_sisa_fisik'] ?> <small class="text-muted fw-normal">/ <?= $bs['qty_awal_fisik'] ?></small></td>
                            <td class="text-center fw-bold text-primary"><?= $bs['qty_sisa_spj'] ?> <small class="text-muted fw-normal">/ <?= $bs['qty_awal_spj'] ?></small></td>
                            <td class="text-end font-mono">
                                <div class="fw-semibold">Rp <?= formatAngka($bs['harga_satuan_murni'], 4) ?></div>
                                <small class="text-muted">Bulat: Rp <?= formatAngka($bs['harga_satuan_bulat'], 2) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSilang() {
    var val = document.getElementById('tipe_masuk').value;
    document.getElementById('col_qty_spj').style.display = (val === 'SUBSIDI_SILANG') ? 'block' : 'none';
}

function hitungOtomatis() {
    var totalKontrak = parseFloat(document.getElementById('total_kontrak_bantu').value);
    if (!totalKontrak || totalKontrak <= 0) return;
    var tipe = document.getElementById('tipe_masuk').value;
    var qty = (tipe === 'SUBSIDI_SILANG') 
        ? parseFloat(document.getElementById('qty_spj').value) 
        : parseFloat(document.getElementById('qty_fisik').value);
    if (qty && qty > 0) {
        document.getElementById('harga_satuan_murni').value = (totalKontrak / qty).toFixed(4);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
