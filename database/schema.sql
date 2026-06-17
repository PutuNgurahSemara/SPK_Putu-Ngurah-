-- ============================================================
-- DDL Script: SPK Manajemen Aset IT (v2 — Kriteria Dinamis)
-- Database   : PostgreSQL
-- Project    : Kutai Refinery Nusantara - IT Asset SPK
-- Updated    : 2026-06-16
-- Catatan    : Gunakan migrate_v2.sql jika DB sudah ada data v1
-- ============================================================

-- Hapus tabel (urutan DROP penting karena FK)
DROP TABLE IF EXISTS penilaian_detail CASCADE;
DROP TABLE IF EXISTS penilaian_spk    CASCADE;
DROP TABLE IF EXISTS perangkat        CASCADE;
DROP TABLE IF EXISTS kriteria         CASCADE;

-- ============================================================
-- TABEL 1: kriteria
-- Master data kriteria SAW — jumlah baris bebas (dinamis)
-- ============================================================
CREATE TABLE kriteria (
    id             SERIAL       PRIMARY KEY,
    kode_kriteria  VARCHAR(10)  NOT NULL UNIQUE,
    nama_kriteria  VARCHAR(100) NOT NULL,
    atribut        VARCHAR(10)  NOT NULL DEFAULT 'Benefit'
        CHECK (atribut IN ('Benefit', 'Cost')),
    bobot          NUMERIC(5,4) NOT NULL
        CHECK (bobot > 0 AND bobot <= 1),
    nilai_1        VARCHAR(150) NOT NULL DEFAULT '1 — Nilai Rendah',
    nilai_2        VARCHAR(150) NOT NULL DEFAULT '2 — Nilai Sedang',
    nilai_3        VARCHAR(150) NOT NULL DEFAULT '3 — Nilai Tinggi',
    urutan         SMALLINT     NOT NULL DEFAULT 1
);

COMMENT ON TABLE  kriteria               IS 'Master data kriteria SAW. Total bobot semua baris wajib = 1.00. Jumlah baris bebas.';
COMMENT ON COLUMN kriteria.kode_kriteria IS 'Kode unik otomatis: C1, C2, ..., Cn';
COMMENT ON COLUMN kriteria.bobot         IS 'Bobot kriteria (0.0001–1.0000). Jumlah semua bobot harus = 1.00';
COMMENT ON COLUMN kriteria.nilai_1       IS 'Label nilai 1 (kondisi terbaik/rendah)';
COMMENT ON COLUMN kriteria.nilai_2       IS 'Label nilai 2 (kondisi sedang)';
COMMENT ON COLUMN kriteria.nilai_3       IS 'Label nilai 3 (kondisi terburuk/tinggi)';
COMMENT ON COLUMN kriteria.urutan        IS 'Urutan tampil di UI';

-- Data default 5 kriteria (total bobot = 1.00)
INSERT INTO kriteria (kode_kriteria, nama_kriteria, atribut, bobot, nilai_1, nilai_2, nilai_3, urutan) VALUES
    ('C1', 'Usia Perangkat',           'Benefit', 0.15,
     '1 — Di bawah 3 tahun',         '2 — Antara 3 sampai 5 tahun', '3 — Di atas 5 tahun',        1),
    ('C2', 'Tingkat Kerusakan',        'Benefit', 0.30,
     '1 — Kerusakan Ringan',          '2 — Kerusakan Sedang',        '3 — Kerusakan Berat',         2),
    ('C3', 'Ketersediaan Suku Cadang', 'Benefit', 0.25,
     '1 — Suku Cadang Mudah Didapat', '2 — Suku Cadang Sulit Didapat','3 — Suku Cadang Tidak Tersedia',3),
    ('C4', 'Kompleksitas Pengerjaan',  'Benefit', 0.15,
     '1 — Pengerjaan Mudah',          '2 — Pengerjaan Sedang',       '3 — Pengerjaan Sulit / Kompleks',4),
    ('C5', 'Status Garansi',           'Benefit', 0.15,
     '1 — Garansi Masih Aktif',       '2 — Garansi Habis < 1 Tahun', '3 — Garansi Habis > 1 Tahun', 5);

-- ============================================================
-- TABEL 2: perangkat
-- ============================================================
CREATE TABLE perangkat (
    id              SERIAL       PRIMARY KEY,
    kode_aset       VARCHAR(50)  NOT NULL UNIQUE,
    jenis_perangkat VARCHAR(50)  NOT NULL
        CHECK (jenis_perangkat IN ('Thin Client', 'PC Desktop', 'Printer', 'Network')),
    divisi_user     VARCHAR(100) NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE perangkat IS 'Master data perangkat keras IT Kutai Refinery Nusantara';

-- ============================================================
-- TABEL 3: penilaian_spk
-- Header penilaian — nilai per-kriteria ada di penilaian_detail
-- ============================================================
CREATE TABLE penilaian_spk (
    id                SERIAL       PRIMARY KEY,
    perangkat_id      INT          NOT NULL REFERENCES perangkat(id) ON DELETE CASCADE,
    skor_akhir        NUMERIC(5,4) DEFAULT NULL,
    rekomendasi       VARCHAR(20)  DEFAULT NULL
        CHECK (rekomendasi IN ('Servis', 'Masuk Gudang')),
    tanggal_penilaian TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE  penilaian_spk               IS 'Header penilaian SAW. Nilai per-kriteria ada di tabel penilaian_detail.';
COMMENT ON COLUMN penilaian_spk.skor_akhir    IS 'Nilai SAW akhir 0.0000–1.0000 (dihitung oleh SawEngine.php)';
COMMENT ON COLUMN penilaian_spk.rekomendasi   IS 'Servis (skor<0.70) | Masuk Gudang (skor>=0.70)';

-- ============================================================
-- TABEL 4: penilaian_detail
-- Detail nilai per-kriteria per-penilaian (EAV pattern)
-- ============================================================
CREATE TABLE penilaian_detail (
    id           SERIAL   PRIMARY KEY,
    penilaian_id INT      NOT NULL REFERENCES penilaian_spk(id) ON DELETE CASCADE,
    kriteria_id  INT      NOT NULL REFERENCES kriteria(id)       ON DELETE RESTRICT,
    nilai        SMALLINT NOT NULL CHECK (nilai BETWEEN 1 AND 3),
    UNIQUE (penilaian_id, kriteria_id)
);

COMMENT ON TABLE  penilaian_detail               IS 'Nilai per-kriteria per-penilaian. EAV pattern mendukung N kriteria dinamis.';
COMMENT ON COLUMN penilaian_detail.nilai         IS '1=kondisi terbaik/rendah, 2=sedang, 3=kondisi terburuk/tinggi';

-- ============================================================
-- INDEX
-- ============================================================
CREATE INDEX idx_penilaian_perangkat_id ON penilaian_spk(perangkat_id);
CREATE INDEX idx_penilaian_tanggal      ON penilaian_spk(tanggal_penilaian DESC);
CREATE INDEX idx_penilaian_rekomendasi  ON penilaian_spk(rekomendasi);
CREATE INDEX idx_penilaian_skor         ON penilaian_spk(skor_akhir DESC NULLS LAST);
CREATE INDEX idx_detail_penilaian       ON penilaian_detail(penilaian_id);
CREATE INDEX idx_detail_kriteria        ON penilaian_detail(kriteria_id);
CREATE INDEX idx_perangkat_jenis        ON perangkat(jenis_perangkat);

-- ============================================================
-- DATA SAMPLE (untuk testing awal)
-- ============================================================
INSERT INTO perangkat (kode_aset, jenis_perangkat, divisi_user) VALUES
    ('KRN-TC-001',  'Thin Client',  'Produksi'),
    ('KRN-TC-002',  'Thin Client',  'Produksi'),
    ('KRN-PC-001',  'PC Desktop',   'HR & GA'),
    ('KRN-PC-002',  'PC Desktop',   'Finance'),
    ('KRN-PRN-001', 'Printer',      'Finance'),
    ('KRN-NET-001', 'Network',      'IT Department');

INSERT INTO penilaian_spk (perangkat_id) VALUES (1),(2),(3),(4),(5),(6);

-- Detail nilai sample (kriteria_id 1=C1, 2=C2, 3=C3, 4=C4, 5=C5)
INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai) VALUES
    (1,1,3),(1,2,3),(1,3,3),(1,4,2),(1,5,3),  -- KRN-TC-001  : tua, rusak berat
    (2,1,2),(2,2,2),(2,3,2),(2,4,2),(2,5,2),  -- KRN-TC-002  : semua sedang
    (3,1,1),(3,2,1),(3,3,1),(3,4,1),(3,5,1),  -- KRN-PC-001  : kondisi baik
    (4,1,3),(4,2,2),(4,3,3),(4,4,3),(4,5,3),  -- KRN-PC-002  : campuran berat
    (5,1,2),(5,2,3),(5,3,2),(5,4,2),(5,5,3),  -- KRN-PRN-001 : rusak berat
    (6,1,1),(6,2,1),(6,3,2),(6,4,1),(6,5,2);  -- KRN-NET-001 : relatif bagus
