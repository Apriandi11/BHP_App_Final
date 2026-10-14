<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) ? '../' : '';

$notif_selisih = 0;
$notif_kritis = 0;
if (isset($pdo)) {
    try {
        $notif_selisih = (int)$pdo->query("SELECT COUNT(*) FROM barang WHERE stok_fisik != stok_spj AND is_active = 1")->fetchColumn();
        $notif_kritis  = (int)$pdo->query("SELECT COUNT(*) FROM barang WHERE stok_fisik <= min_stok AND is_active = 1")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMBADA Companion — BHP DPMPTSP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar macOS Style -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-header">
            <div class="mac-dots">
                <span class="mac-dot red"></span>
                <span class="mac-dot yellow"></span>
                <span class="mac-dot green"></span>
            </div>
            <div class="brand-title">
                <i class="bi bi-box-seam-fill text-primary"></i>
                <span>BHP Companion</span>
            </div>
            <div class="brand-subtitle">Penyimpan Barang &bull; DPMPTSP</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Pusat Kendali</div>
            <a href="<?= $base_url ?>index.php" class="sidebar-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Command Center</span>
                <?php if ($notif_selisih > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark ms-auto"><?= $notif_selisih ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base_url ?>modules/barang.php" class="sidebar-link <?= $current_page == 'barang.php' ? 'active' : '' ?>">
                <i class="bi bi-archive-fill"></i>
                <span>Master Barang & Rak</span>
                <?php if ($notif_kritis > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-auto"><?= $notif_kritis ?></span>
                <?php endif; ?>
            </a>

            <div class="nav-section-title mt-3">Operasional Gudang & SPJ</div>
            <a href="<?= $base_url ?>modules/mutasi_masuk.php" class="sidebar-link <?= $current_page == 'mutasi_masuk.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-down-left-square-fill text-success"></i>
                <span>Penerimaan (Masuk)</span>
            </a>
            <a href="<?= $base_url ?>modules/mutasi_keluar.php" class="sidebar-link <?= $current_page == 'mutasi_keluar.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-up-right-square-fill text-warning"></i>
                <span>Distribusi & Kasbon</span>
            </a>

            <div class="nav-section-title mt-3">Audit & Pelaporan</div>
            <a href="<?= $base_url ?>modules/rekonsiliasi.php" class="sidebar-link <?= $current_page == 'rekonsiliasi.php' ? 'active' : '' ?>">
                <i class="bi bi-calculator-fill text-info"></i>
                <span>Rekonsiliasi SIMBADA</span>
            </a>
            <a href="<?= $base_url ?>modules/import_export.php" class="sidebar-link <?= $current_page == 'import_export.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Kertas Kerja CSV/Excel</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center justify-content-between">
                <span><i class="bi bi-shield-check text-success me-1"></i> Dual-Track FIFO</span>
                <span class="font-mono badge bg-secondary bg-opacity-25 text-light">v2.0</span>
            </div>
        </div>
    </aside>

    <!-- Main Area -->
    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggleBtn">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <span class="text-muted small fw-medium">Sistem Pendamping Inventaris ATK/BHP</span>
                    <span class="mx-2 text-muted">&bull;</span>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">DPMPTSP Kota Pontianak</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= $base_url ?>modules/mutasi_masuk.php" class="btn btn-sm btn-success btn-modern d-none d-md-inline-flex">
                    <i class="bi bi-plus-circle"></i> Barang Masuk
                </a>
                <a href="<?= $base_url ?>modules/mutasi_keluar.php" class="btn btn-sm btn-warning btn-modern d-none d-md-inline-flex">
                    <i class="bi bi-send-check"></i> Distribusi Bidang
                </a>
                <button class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1 ms-1" id="themeToggleBtn" title="Ganti Mode Gelap / Terang">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>
            </div>
        </header>

        <main class="app-content">
