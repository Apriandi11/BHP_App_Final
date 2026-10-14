<?php
require_once '../config/database.php';

$rekonList = $pdo->query("
    SELECT bs.*, b.kode_barang, b.nama_barang, k.nama_kategori, k.kode_rekening, s.nama_satuan
    FROM batch_stok bs
    JOIN barang b ON bs.id_barang = b.id
    JOIN kategori k ON b.id_kategori = k.id
    JOIN satuan s ON b.id_satuan = s.id
    WHERE bs.qty_sisa_spj > 0
    ORDER BY k.id ASC, b.nama_barang ASC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Kalkulator Rekonsiliasi Desimal SIMBADA v2</h4>
        <p class="text-secondary small mb-0">
            Deteksi otomatis selisih sen/rupiah antara <strong>Saldo Murni 4 Desimal</strong> dengan <strong>Pembulatan 2 Desimal</strong> serta simulasi pencocokan angka Kertas Kerja SIMBADA.
        </p>
    </div>
</div>

<div class="card-glass overflow-hidden">
    <div class="table-responsive">
        <table class="table table-modern">
            <thead>
                <tr>
                    <th>Batch / Dokumen</th>
                    <th>Nama Barang & Rekening</th>
                    <th class="text-center">Sisa SPJ</th>
                    <th class="text-end">Harga Murni (4 Des)</th>
                    <th class="text-end">Total Murni</th>
                    <th class="text-end">Total Bulat (2 Des)</th>
                    <th class="text-end">Selisih Desimal</th>
                    <th width="180">Angka di SIMBADA</th>
                    <th class="text-end">Varian & Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandMurni = 0;
                $grandBulat = 0;
                foreach ($rekonList as $idx => $r): 
                    $totalMurni = $r['qty_sisa_spj'] * $r['harga_satuan_murni'];
                    $totalBulat = $r['qty_sisa_spj'] * round($r['harga_satuan_murni'], 2);
                    $selisihInternal = $totalMurni - $totalBulat;
                    $grandMurni += $totalMurni;
                    $grandBulat += $totalBulat;
                ?>
                <tr>
                    <td class="font-mono small fw-semibold text-primary"><?= htmlspecialchars($r['no_dokumen']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['nama_barang']) ?></div>
                        <small class="text-muted font-mono"><?= htmlspecialchars($r['kode_rekening']) ?></small>
                    </td>
                    <td class="text-center fw-bold text-primary"><?= $r['qty_sisa_spj'] ?> <?= htmlspecialchars($r['nama_satuan']) ?></td>
                    <td class="text-end font-mono"><?= formatAngka($r['harga_satuan_murni'], 4) ?></td>
                    <td class="text-end font-mono fw-bold"><?= formatRupiah($totalMurni, 2) ?></td>
                    <td class="text-end font-mono"><?= formatRupiah($totalBulat, 2) ?></td>
                    <td class="text-end font-mono <?= abs($selisihInternal) > 0.001 ? 'text-warning fw-bold' : 'text-success' ?>">
                        <?= formatAngka($selisihInternal, 2) ?>
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm font-mono" 
                               value="<?= round($totalMurni, 2) ?>" 
                               oninput="hitungVarian(<?= $idx ?>, <?= $totalMurni ?>, this.value)">
                    </td>
                    <td class="text-end font-mono fw-bold text-success" id="varian_<?= $idx ?>">
                        <span class="pill-badge pill-sinkron">Klop (Rp 0,00)</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4" class="text-end">TOTAL SALDO PERSEDIAAN SPJ:</td>
                    <td class="text-end font-mono text-primary"><?= formatRupiah($grandMurni, 2) ?></td>
                    <td class="text-end font-mono"><?= formatRupiah($grandBulat, 2) ?></td>
                    <td class="text-end font-mono text-danger"><?= formatRupiah($grandMurni - $grandBulat, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
function hitungVarian(idx, totalMurni, nilaiSimbada) {
    var simbada = parseFloat(nilaiSimbada) || 0;
    var selisih = (totalMurni - simbada).toFixed(2);
    var el = document.getElementById('varian_' + idx);
    if (Math.abs(selisih) <= 0.01) {
        el.innerHTML = '<span class="pill-badge pill-sinkron">Klop (Rp 0,00)</span>';
    } else {
        el.innerHTML = '<span class="pill-badge pill-kritis">Selisih Rp ' + selisih + '</span>';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
