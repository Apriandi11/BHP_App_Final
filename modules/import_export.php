<?php
require_once '../config/database.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Kertas_Kerja_BHP_DPMPTSP_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Kode Barang', 'Nama Barang', 'Kode Rekening', 'Kategori', 'Satuan', 'Lokasi Rak', 'Stok Fisik Gudang', 'Stok SPJ SIMBADA', 'Selisih Fisik vs SPJ', 'Nilai Saldo SPJ Murni (4 Des)', 'Nilai Saldo SPJ Bulat (2 Des)']);

    $rows = $pdo->query("
        SELECT b.kode_barang, b.nama_barang, k.kode_rekening, k.nama_kategori, s.nama_satuan, b.lokasi_rak, b.stok_fisik, b.stok_spj,
               COALESCE((SELECT SUM(bs.qty_sisa_spj * bs.harga_satuan_murni) FROM batch_stok bs WHERE bs.id_barang = b.id), 0) AS nilai_murni,
               COALESCE((SELECT SUM(bs.qty_sisa_spj * ROUND(bs.harga_satuan_murni, 2)) FROM batch_stok bs WHERE bs.id_barang = b.id), 0) AS nilai_bulat
        FROM barang b
        JOIN kategori k ON b.id_kategori = k.id
        JOIN satuan s ON b.id_satuan = s.id
        WHERE b.is_active = 1
        ORDER BY k.kode_rekening ASC, b.kode_barang ASC
    ")->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['kode_barang'],
            $r['nama_barang'],
            $r['kode_rekening'],
            $r['nama_kategori'],
            $r['nama_satuan'],
            $r['lokasi_rak'],
            $r['stok_fisik'],
            $r['stok_spj'],
            $r['stok_fisik'] - $r['stok_spj'],
            number_format((float)$r['nilai_murni'], 2, '.', ''),
            number_format((float)$r['nilai_bulat'], 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

require_once '../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Ekspor Kertas Kerja Rekonsiliasi & Cadangan Data</h4>
    <p class="text-secondary small mb-0">Unduh laporan rekapitulasi stok ganda dan nilai persediaan presisi dalam format spreadsheet (.CSV) untuk pencocokan triwulanan SIMBADA.</p>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card-glass p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="metric-icon icon-blue mb-3">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                </div>
                <h5 class="fw-bold mb-2">Unduh Kertas Kerja Rekonsiliasi (.CSV / Excel)</h5>
                <p class="text-secondary small">
                    File ini berisi seluruh kolom standar rekonsiliasi: Kode Rekening, Nama Barang, Sisa Stok Fisik, Sisa Saldo SPJ, Selisih Kasbon/Non-DPA, serta perbandingan Nilai Saldo Murni vs Bulat.
                </p>
            </div>
            <a href="import_export.php?export=csv" class="btn btn-primary btn-modern justify-content-center mt-3">
                <i class="bi bi-cloud-arrow-down-fill"></i> Download Kertas Kerja BHP (.CSV)
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-glass p-4 h-100">
            <div class="metric-icon icon-emerald mb-3">
                <i class="bi bi-hdd-network"></i>
            </div>
            <h5 class="fw-bold mb-2">Sinkronisasi Lintas Perangkat (Linux &harr; Windows)</h5>
            <p class="text-secondary small mb-3">
                Untuk memindahkan pekerjaan beserta isi database terbaru dari laptop ke PC kantor:
            </p>
            <div class="p-3 rounded-3 bg-light bg-opacity-50 border font-mono small">
                <div class="text-muted mb-1"># Backup database ke file SQL:</div>
                <code>mysqldump -u root db_bhp_dpmptsp &gt; database/backup_terbaru.sql</code>
                <div class="text-muted mt-2 mb-1"># Push ke GitHub:</div>
                <code>git add . && git commit -m "Update stok" && git push</code>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
