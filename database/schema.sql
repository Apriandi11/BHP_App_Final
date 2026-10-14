CREATE DATABASE IF NOT EXISTS db_bhp_dpmptsp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_bhp_dpmptsp;

CREATE TABLE IF NOT EXISTS kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_rekening VARCHAR(50) NULL,
    nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS satuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_satuan VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(50) UNIQUE NULL,
    nama_barang VARCHAR(200) NOT NULL,
    id_kategori INT NOT NULL,
    id_satuan INT NOT NULL,
    stok_fisik INT DEFAULT 0,
    stok_spj INT DEFAULT 0,
    min_stok INT DEFAULT 5,
    lokasi_rak VARCHAR(100) DEFAULT 'Lemari Utama A1',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_satuan) REFERENCES satuan(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_stok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_barang INT NOT NULL,
    tanggal_masuk DATE NOT NULL,
    no_dokumen VARCHAR(100) NULL,
    qty_awal_spj INT NOT NULL DEFAULT 0,
    qty_sisa_spj INT NOT NULL DEFAULT 0,
    qty_awal_fisik INT NOT NULL DEFAULT 0,
    qty_sisa_fisik INT NOT NULL DEFAULT 0,
    harga_satuan_murni DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    harga_satuan_bulat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_subsidi_silang TINYINT(1) DEFAULT 0,
    catatan_silang TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_barang) REFERENCES barang(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi_header (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) UNIQUE NOT NULL,
    tanggal DATE NOT NULL,
    jenis_transaksi ENUM('MASUK', 'KELUAR', 'PENYESUAIAN') NOT NULL,
    tipe_dampak ENUM('KEDUANYA', 'FISIK_SAJA', 'SPJ_SAJA', 'SUBSIDI_SILANG') NOT NULL,
    bidang_atau_rekanan VARCHAR(150) NULL,
    nama_penerima VARCHAR(100) NULL,
    no_bukti_spj VARCHAR(100) NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_barang INT NOT NULL,
    id_batch INT NULL,
    qty INT NOT NULL,
    harga_satuan DECIMAL(15,4) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi_header(id) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_batch) REFERENCES batch_stok(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE transaksi_detail;
TRUNCATE TABLE transaksi_header;
TRUNCATE TABLE batch_stok;
TRUNCATE TABLE barang;
TRUNCATE TABLE kategori;
TRUNCATE TABLE satuan;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO kategori (kode_rekening, nama_kategori) VALUES
('5.1.02.01.01.0024', 'Alat Tulis Kantor (ATK)'),
('5.1.02.01.01.0025', 'Kertas dan Cover'),
('5.1.02.01.01.0026', 'Bahan Cetak'),
('5.1.02.01.01.0029', 'Bahan Komputer & Tinta'),
('5.1.02.01.01.0031', 'Alat Listrik'),
('5.1.02.01.01.0030', 'Alat Kebersihan & Bahan Pembersih');

INSERT INTO satuan (nama_satuan) VALUES
('Rim'), ('Kotak'), ('Pcs'), ('Lusin'), ('Buah'), ('Pak'), ('Botol'), ('Roll'), ('Set');

INSERT INTO barang (kode_barang, nama_barang, id_kategori, id_satuan, stok_fisik, stok_spj, min_stok, lokasi_rak) VALUES
('ATK-001', 'Kertas HVS F4 75gr SIDU', 2, 1, 25, 30, 10, 'Rak Kertas B1'),
('ATK-002', 'Pulpen Snowman V-5 Hitam (Box 12)', 1, 2, 12, 12, 5, 'Lemari ATK A1'),
('ATK-003', 'Tinta Epson 003 Hitam Original', 4, 7, 3, 5, 5, 'Lemari Tinta C2'),
('ATK-004', 'Map Ordner Folio Bantex Biru', 1, 5, 40, 40, 10, 'Rak Arsip B2'),
('ATK-005', 'Baterai Alkaline AA (Isi 2)', 5, 6, 8, 5, 5, 'Laci Listrik D1'),
('ATK-006', 'Amplop Putih Kabinet Jaya (Kotak)', 1, 2, 15, 15, 5, 'Lemari ATK A2');

INSERT INTO batch_stok (id_barang, tanggal_masuk, no_dokumen, qty_awal_spj, qty_sisa_spj, qty_awal_fisik, qty_sisa_fisik, harga_satuan_murni, harga_satuan_bulat, is_subsidi_silang, catatan_silang) VALUES
(1, CURDATE(), 'SPJ-TW1-001', 30, 30, 30, 25, 48500.3333, 48500.33, 0, '5 Rim dipinjam Bidang Perizinan mendahului SPJ'),
(2, CURDATE(), 'SPJ-TW1-001', 12, 12, 12, 12, 22000.0000, 22000.00, 0, NULL),
(3, CURDATE(), 'SPJ-TW1-002', 5, 5, 5, 3, 85000.6667, 85000.67, 0, '2 Botol diambil Sekretariat belum potong SPJ'),
(4, CURDATE(), 'SPJ-TW1-002', 40, 40, 40, 40, 31250.2500, 31250.25, 0, NULL),
(5, CURDATE(), 'NON-DPA-01', 5, 5, 8, 8, 18500.0000, 18500.00, 1, '3 Pak tambahan dari konversi subsidi silang'),
(6, CURDATE(), 'SPJ-TW1-003', 15, 15, 15, 15, 24333.3333, 24333.33, 0, NULL);

INSERT INTO transaksi_header (kode_transaksi, tanggal, jenis_transaksi, tipe_dampak, bidang_atau_rekanan, nama_penerima, no_bukti_spj, keterangan) VALUES
('IN-20261001-01', CURDATE(), 'MASUK', 'KEDUANYA', 'CV. Mitra Mandiri Pontianak', 'Penyimpan Barang', 'SPJ-TW1-001', 'Pengadaan ATK Triwulan berjalan'),
('OUT-20261005-01', CURDATE(), 'KELUAR', 'FISIK_SAJA', 'Bidang Pelayanan Perizinan', 'Budi Santoso', '-', 'Kasbon mendadak untuk cetak dokumen izin'),
('OUT-20261006-02', CURDATE(), 'KELUAR', 'FISIK_SAJA', 'Sekretariat Umum', 'Siti Rahma', '-', 'Pengambilan tinta printer mendahului SPJ');

INSERT INTO transaksi_detail (id_transaksi, id_barang, id_batch, qty, harga_satuan, subtotal) VALUES
(1, 1, 1, 30, 48500.3333, 1455010.00),
(2, 1, 1, 5, 48500.3333, 242501.67),
(3, 3, 3, 2, 85000.6667, 170001.33);
