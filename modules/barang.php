<?php
require_once '../config/database.php';

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'tambah_barang') {
        $kode = trim($_POST['kode_barang']);
        $nama = trim($_POST['nama_barang']);
        $kat  = (int)$_POST['id_kategori'];
        $sat  = (int)$_POST['id_satuan'];
        $min  = (int)$_POST['min_stok'];
        $rak  = trim($_POST['lokasi_rak']) ?: 'Lemari Utama';

        $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, id_kategori, id_satuan, min_stok, lokasi_rak) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kode, $nama, $kat, $sat, $min, $rak]);
        $pesan = "Barang '$nama' berhasil ditambahkan ke Master BHP!";
    } elseif ($_POST['aksi'] === 'tambah_kategori') {
        $rek = trim($_POST['kode_rekening']);
        $nam = trim($_POST['nama_kategori']);
        $stmt = $pdo->prepare("INSERT INTO kategori (kode_rekening, nama_kategori) VALUES (?, ?)");
        $stmt->execute([$rek, $nam]);
        $pesan = "Kategori Rekening '$nam' berhasil ditambahkan!";
    } elseif ($_POST['aksi'] === 'tambah_satuan') {
        $sat = trim($_POST['nama_satuan']);
        $stmt = $pdo->prepare("INSERT INTO satuan (nama_satuan) VALUES (?)");
        $stmt->execute([$sat]);
        $pesan = "Satuan '$sat' berhasil ditambahkan!";
    }
}

$kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
$satuanList   = $pdo->query("SELECT * FROM satuan ORDER BY nama_satuan")->fetchAll();
$barangList   = $pdo->query("
    SELECT b.*, k.nama_kategori, k.kode_rekening, s.nama_satuan 
    FROM barang b 
    JOIN kategori k ON b.id_kategori = k.id 
    JOIN satuan s ON b.id_satuan = s.id 
    WHERE b.is_active = 1 
    ORDER BY b.kode_barang ASC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Master Barang, Kategori Rekening & Rak</h4>
        <p class="text-secondary small mb-0">Kelola spesifikasi SKU Barang Habis Pakai (BHP), kode rekening belanja, dan posisi penyimpanan gudang.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-modern" data-bs-toggle="modal" data-bs-target="#modalKategori">
            <i class="bi bi-folder-plus"></i> + Kategori Rekening
        </button>
        <button class="btn btn-outline-secondary btn-modern" data-bs-toggle="modal" data-bs-target="#modalSatuan">
            <i class="bi bi-tags"></i> + Satuan
        </button>
    </div>
</div>

<?php if ($pesan): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-glass p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-plus-square-fill text-primary me-2"></i>Registrasi SKU Barang Baru</h6>
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah_barang">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Kode Barang</label>
                        <input type="text" name="kode_barang" class="form-control font-mono" placeholder="ATK-007" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Lokasi Rak/Lemari</label>
                        <input type="text" name="lokasi_rak" class="form-control" placeholder="Rak A1" value="Lemari ATK A1">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama & Spesifikasi Barang</label>
                    <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: Kertas HVS A4 80gr SIDU" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kategori / Kode Rekening</label>
                    <select name="id_kategori" class="form-select" required>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?> (<?= htmlspecialchars($k['kode_rekening']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row mb-4">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Satuan Ukur</label>
                        <select name="id_satuan" class="form-select" required>
                            <?php foreach ($satuanList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama_satuan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Batas Stok Kritis</label>
                        <input type="number" name="min_stok" class="form-control" value="5" min="0" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-modern justify-content-center">
                    <i class="bi bi-save"></i> Simpan ke Master BHP
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-glass overflow-hidden">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Daftar Katalog Barang Habis Pakai (<?= count($barangList) ?> SKU)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Kode & Rak</th>
                            <th>Nama Spesifikasi</th>
                            <th>Rekening Belanja</th>
                            <th class="text-center">Stok Fisik</th>
                            <th class="text-center">Stok SPJ</th>
                            <th class="text-center">Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barangList as $b): ?>
                        <tr>
                            <td>
                                <span class="font-mono fw-bold text-primary small"><?= htmlspecialchars($b['kode_barang']) ?></span>
                                <small class="d-block text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($b['lokasi_rak']) ?></small>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($b['nama_barang']) ?></td>
                            <td>
                                <div class="small fw-medium"><?= htmlspecialchars($b['nama_kategori']) ?></div>
                                <span class="font-mono text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($b['kode_rekening']) ?></span>
                            </td>
                            <td class="text-center fw-bold"><?= $b['stok_fisik'] ?> <small class="text-muted fw-normal"><?= htmlspecialchars($b['nama_satuan']) ?></small></td>
                            <td class="text-center fw-bold text-primary"><?= $b['stok_spj'] ?> <small class="text-muted fw-normal"><?= htmlspecialchars($b['nama_satuan']) ?></small></td>
                            <td class="text-center small text-muted"><?= $b['min_stok'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalKategori" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah_kategori">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Kategori / Kode Rekening</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kode Rekening SIPD/SIMBADA</label>
                    <input type="text" name="kode_rekening" class="form-control font-mono" placeholder="5.1.02.01.01.00XX" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Kategori Belanja</label>
                    <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Benda Pos / Materai" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary btn-modern">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalSatuan" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah_satuan">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Satuan Ukur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Nama Satuan</label>
                <input type="text" name="nama_satuan" class="form-control" placeholder="Contoh: Lembar / Buku" required>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary btn-modern w-100">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
