<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$stmtNilai = $pdo->query("SELECT SUM(qty_sisa_spj * harga_satuan_murni) AS total_nilai, SUM(qty_sisa_fisik * harga_satuan_murni) AS total_fisik FROM batch_stok");
$rowNilai = $stmtNilai->fetch();
$totalNilaiSpj   = $rowNilai['total_nilai'] ?? 0;
$totalNilaiFisik = $rowNilai['total_fisik'] ?? 0;

$stmtSelisih = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok_fisik != stok_spj AND is_active = 1");
$totalSkuSelisih = (int)$stmtSelisih->fetchColumn();

$stmtKritis = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok_fisik <= min_stok AND is_active = 1");
$totalSkuKritis = (int)$stmtKritis->fetchColumn();

$totalSkuAktif = (int)$pdo->query("SELECT COUNT(*) FROM barang WHERE is_active = 1")->fetchColumn();

$filter = $_GET['filter'] ?? 'all';
$whereClause = "WHERE b.is_active = 1";
if ($filter === 'selisih') {
    $whereClause .= " AND b.stok_fisik != b.stok_spj";
} elseif ($filter === 'kritis') {
    $whereClause .= " AND b.stok_fisik <= b.min_stok";
}

$queryBarang = "
    SELECT b.*, k.nama_kategori, k.kode_rekening, s.nama_satuan,
           COALESCE((SELECT SUM(bs.qty_sisa_spj * bs.harga_satuan_murni) FROM batch_stok bs WHERE bs.id_barang = b.id), 0) AS nilai_spj_murni,
           COALESCE((SELECT SUM(bs.qty_sisa_spj * ROUND(bs.harga_satuan_murni, 2)) FROM batch_stok bs WHERE bs.id_barang = b.id), 0) AS nilai_spj_bulat
    FROM barang b
    JOIN kategori k ON b.id_kategori = k.id
    JOIN satuan s ON b.id_satuan = s.id
    $whereClause
    ORDER BY (b.stok_fisik != b.stok_spj) DESC, b.nama_barang ASC
";
$daftarBarang = $pdo->query($queryBarang)->fetchAll();

$riwayatTerbaru = $pdo->query("
    SELECT th.*, td.qty, td.subtotal, b.nama_barang, s.nama_satuan
    FROM transaksi_detail td
    JOIN transaksi_header th ON td.id_transaksi = th.id
    JOIN barang b ON td.id_barang = b.id
    JOIN satuan s ON b.id_satuan = s.id
    ORDER BY th.id DESC LIMIT 5
")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
    <div>
        <span class="badge bg-primary bg-opacity-10 text-primary mb-2 px-3 py-1 rounded-pill fw-semibold">
            <i class="bi bi-stars me-1"></i> Dashboard Penyimpan Barang
        </span>
        <h3 class="fw-bold mb-1">Command Center Inventaris BHP</h3>
        <p class="text-secondary small mb-0">Pantauan real-time antara fisik barang di lemari/gudang dengan saldo administratif SPJ (SIMBADA v2).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="modules/rekonsiliasi.php" class="btn btn-outline-primary btn-modern">
            <i class="bi bi-calculator"></i> Cek Selisih Desimal
        </a>
        <a href="modules/import_export.php?export=csv" class="btn btn-primary btn-modern">
            <i class="bi bi-download"></i> Ekspor Kertas Kerja
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-glass hover-lift p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-secondary small fw-semibold">Nilai Saldo SPJ (Presisi)</span>
                    <h4 class="fw-bold mt-2 mb-1 font-mono text-primary"><?= formatRupiah($totalNilaiSpj, 2) ?></h4>
                    <small class="text-muted">Fisik Riil: <?= formatRupiah($totalNilaiFisik, 0) ?></small>
                </div>
                <div class="metric-icon icon-blue">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-glass hover-lift p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-secondary small fw-semibold">Selisih Fisik vs SPJ</span>
                    <h4 class="fw-bold mt-2 mb-1 <?= $totalSkuSelisih > 0 ? 'text-warning' : 'text-success' ?>">
                        <?= $totalSkuSelisih ?> <small class="fs-6 fw-normal">SKU</small>
                    </h4>
                    <small class="text-muted">Kasbon bidang / Subsidi silang</small>
                </div>
                <div class="metric-icon icon-amber">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-glass hover-lift p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-secondary small fw-semibold">Stok Gudang Menipis</span>
                    <h4 class="fw-bold mt-2 mb-1 <?= $totalSkuKritis > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $totalSkuKritis ?> <small class="fs-6 fw-normal">SKU</small>
                    </h4>
                    <small class="text-muted">Stok fisik &le; batas minimum</small>
                </div>
                <div class="metric-icon icon-rose">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-glass hover-lift p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-secondary small fw-semibold">Total Item BHP Aktif</span>
                    <h4 class="fw-bold mt-2 mb-1 text-success">
                        <?= $totalSkuAktif ?> <small class="fs-6 fw-normal">Item</small>
                    </h4>
                    <small class="text-muted">Terdaftar di Master Gudang</small>
                </div>
                <div class="metric-icon icon-emerald">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-glass overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-layers-half text-primary me-1"></i> Pantauan Stok Ganda (Dual-Tracking)</h6>
                    <small class="text-muted">Membandingkan ketersediaan fisik di rak dengan laporan resmi SIMBADA</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <a href="index.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Semua</a>
                        <a href="index.php?filter=selisih" class="btn <?= $filter === 'selisih' ? 'btn-warning text-dark fw-semibold' : 'btn-outline-secondary' ?>">Berselisih (<?= $totalSkuSelisih ?>)</a>
                        <a href="index.php?filter=kritis" class="btn <?= $filter === 'kritis' ? 'btn-danger' : 'btn-outline-secondary' ?>">Kritis (<?= $totalSkuKritis ?>)</a>
                    </div>
                </div>
            </div>

            <div class="px-3 py-2 border-bottom bg-light bg-opacity-50">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="liveSearchInput" class="form-control border-0 bg-transparent" placeholder="Ketik untuk mencari nama barang, kode, atau lokasi rak..." onkeyup="filterTable()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern" id="dualTrackTable">
                    <thead>
                        <tr>
                            <th>Barang & Rak</th>
                            <th class="text-center">Fisik (Riil)</th>
                            <th class="text-center">SPJ (SIMBADA)</th>
                            <th class="text-center">Status Selisih</th>
                            <th class="text-end">Saldo SPJ (Murni)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarBarang as $b): 
                            $selisih = $b['stok_fisik'] - $b['stok_spj'];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($b['nama_barang']) ?></div>
                                <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                                    <span class="font-mono badge bg-secondary bg-opacity-10 text-secondary"><?= htmlspecialchars($b['kode_barang']) ?></span>
                                    <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($b['lokasi_rak'] ?? 'Gudang') ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 fw-bold <?= $b['stok_fisik'] <= $b['min_stok'] ? 'text-danger' : '' ?>">
                                    <?= $b['stok_fisik'] ?>
                                </span>
                                <small class="text-muted d-block"><?= htmlspecialchars($b['nama_satuan']) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 fw-bold text-primary"><?= $b['stok_spj'] ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($b['nama_satuan']) ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($selisih == 0): ?>
                                    <span class="pill-badge pill-sinkron"><i class="bi bi-check-circle-fill"></i> Sinkron (0)</span>
                                <?php elseif ($selisih < 0): ?>
                                    <span class="pill-badge pill-kasbon" title="Barang fisik sudah diambil bidang mendahului SPJ">
                                        <i class="bi bi-clock-history"></i> <?= $selisih ?> (Kasbon)
                                    </span>
                                <?php else: ?>
                                    <span class="pill-badge pill-nondpa" title="Stok fisik lebih banyak dari SPJ (Subsidi Silang / Non-DPA)">
                                        <i class="bi bi-plus-slash-minus"></i> +<?= $selisih ?> (Non-DPA)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-mono">
                                <div class="fw-bold"><?= formatRupiah($b['nilai_spj_murni'], 2) ?></div>
                                <?php if (abs($b['nilai_spj_murni'] - $b['nilai_spj_bulat']) > 0.001): ?>
                                    <small class="text-warning" title="Terdapat potensi selisih desimal pembulatan">
                                        <i class="bi bi-exclamation-diamond"></i> Bulat: <?= formatRupiah($b['nilai_spj_bulat'], 2) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-glass p-3 mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-activity text-primary me-1"></i> Mutasi & Aktivitas Terakhir</h6>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($riwayatTerbaru as $rt): ?>
                <div class="p-2 rounded-3 border d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($rt['jenis_transaksi'] === 'MASUK'): ?>
                                <span class="badge bg-success">+ MASUK</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">- KELUAR</span>
                            <?php endif; ?>
                            <span class="fw-semibold small"><?= htmlspecialchars($rt['nama_barang']) ?></span>
                        </div>
                        <div class="text-muted small mt-1">
                            <?= htmlspecialchars($rt['bidang_atau_rekanan']) ?> &bull; <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $rt['tipe_dampak'] ?></span>
                        </div>
                    </div>
                    <div class="text-end font-mono">
                        <div class="fw-bold"><?= $rt['qty'] ?> <?= htmlspecialchars($rt['nama_satuan']) ?></div>
                        <small class="text-muted"><?= formatRupiah($rt['subtotal'], 0) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-glass p-3">
            <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb-fill text-warning me-1"></i> Panduan Alur Kerja Cepat</h6>
            <ul class="small text-secondary ps-3 mb-0" style="line-height: 1.7;">
                <li><strong>Staf minta ATK mendadak (SPJ belum turun):</strong> Gunakan menu <em>Distribusi & Kasbon</em> lalu pilih tipe <code>Kasbon Bidang (Potong Fisik Saja)</code>.</li>
                <li><strong>SPJ resmi turun kemudian:</strong> Gunakan menu <em>Distribusi & Kasbon</em> lalu pilih <code>Pelunasan SPJ (Potong Saldo SPJ Saja)</code> agar kembali <strong>Sinkron (0)</strong>.</li>
                <li><strong>Ada selisih Rp 1 - Rp 100 di SIMBADA:</strong> Buka menu <em>Rekonsiliasi SIMBADA</em> untuk melihat perbandingan harga 4 desimal vs 2 desimal.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const q = document.getElementById('liveSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#dualTrackTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
