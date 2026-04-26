-- ============================================================
-- DDL Script: SPK Manajemen Aset IT
-- Database   : PostgreSQL
-- Project    : Kutai Refinery Nusantara - IT Asset SPK
-- Updated    : 2026-04-26
-- ============================================================

-- Hapus tabel jika sudah ada (urutan DROP penting karena FK)
DROP TABLE IF EXISTS penilaian_spk CASCADE;
DROP TABLE IF EXISTS perangkat CASCADE;
DROP TABLE IF EXISTS kriteria CASCADE;

-- ============================================================
-- TABEL 1: kriteria
-- Master data kriteria SAW beserta bobot-nya (dinamis, tidak hardcoded)
-- ============================================================
CREATE TABLE kriteria (
    id             SERIAL       PRIMARY KEY,
    kode_kriteria  VARCHAR(5)   NOT NULL UNIQUE,    -- C1, C2, C3, C4, C5
    nama_kriteria  VARCHAR(100) NOT NULL,
    atribut        VARCHAR(10)  NOT NULL DEFAULT 'Benefit'
        CHECK (atribut IN ('Benefit', 'Cost')),
    bobot          NUMERIC(4,2) NOT NULL
        CHECK (bobot > 0 AND bobot <= 1)
);

COMMENT ON TABLE kriteria IS 'Master data kriteria SAW. Total bobot wajib = 1.00';
COMMENT ON COLUMN kriteria.kode_kriteria IS 'Kode unik kriteria: C1 s/d C5';
COMMENT ON COLUMN kriteria.atribut       IS 'Benefit = semakin besar semakin baik (ke Gudang)';
COMMENT ON COLUMN kriteria.bobot         IS 'Bobot kriteria (0.01 - 1.00), total semua baris = 1.00';

-- Data default kriteria (sesuai spesifikasi bisnis)
INSERT INTO kriteria (kode_kriteria, nama_kriteria, atribut, bobot) VALUES
    ('C1', 'Usia Perangkat',            'Benefit', 0.15),
    ('C2', 'Tingkat Kerusakan',         'Benefit', 0.30),
    ('C3', 'Ketersediaan Suku Cadang',  'Benefit', 0.25),
    ('C4', 'Kompleksitas Pengerjaan',   'Benefit', 0.15),
    ('C5', 'Status Garansi',            'Benefit', 0.15);

-- ============================================================
-- TABEL 2: perangkat
-- Master data perangkat keras IT yang dimiliki perusahaan
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
COMMENT ON COLUMN perangkat.kode_aset       IS 'Kode unik identifikasi aset (misal: KRN-TC-001)';
COMMENT ON COLUMN perangkat.jenis_perangkat IS 'Thin Client | PC Desktop | Printer | Network';
COMMENT ON COLUMN perangkat.divisi_user     IS 'Divisi/departemen pengguna perangkat';

-- ============================================================
-- TABEL 3: penilaian_spk
-- Hasil penilaian SAW untuk setiap perangkat
-- ============================================================
CREATE TABLE penilaian_spk (
    id                SERIAL       PRIMARY KEY,
    perangkat_id      INT          NOT NULL REFERENCES perangkat(id) ON DELETE CASCADE,

    -- C1: Usia Perangkat | Skala 1-3 (BENEFIT)
    -- 1 = < 3 tahun  |  2 = 3-5 tahun  |  3 = > 5 tahun
    c1_usia           SMALLINT     NOT NULL CHECK (c1_usia BETWEEN 1 AND 3),

    -- C2: Tingkat Kerusakan | Skala 1-3 (BENEFIT)
    -- 1 = Ringan  |  2 = Sedang  |  3 = Berat
    c2_kerusakan      SMALLINT     NOT NULL CHECK (c2_kerusakan BETWEEN 1 AND 3),

    -- C3: Ketersediaan Suku Cadang | Skala 1-3 (BENEFIT)
    -- 1 = Mudah didapat  |  2 = Sulit  |  3 = Tidak tersedia
    c3_part           SMALLINT     NOT NULL CHECK (c3_part BETWEEN 1 AND 3),

    -- C4: Kompleksitas Pengerjaan | Skala 1-3 (BENEFIT)
    -- 1 = Mudah  |  2 = Sedang  |  3 = Sulit/Kompleks
    c4_kompleksitas   SMALLINT     NOT NULL CHECK (c4_kompleksitas BETWEEN 1 AND 3),

    -- C5: Status Garansi | Skala 1-3 (BENEFIT)
    -- 1 = Masih aktif  |  2 = Habis < 1 thn  |  3 = Habis > 1 thn
    c5_garansi        SMALLINT     NOT NULL CHECK (c5_garansi BETWEEN 1 AND 3),

    -- Hasil perhitungan SAW (diisi otomatis oleh PHP)
    skor_akhir        NUMERIC(5,4) DEFAULT NULL,
    rekomendasi       VARCHAR(20)  DEFAULT NULL
        CHECK (rekomendasi IN ('Servis', 'Masuk Gudang')),

    tanggal_penilaian TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE penilaian_spk IS 'Hasil penilaian SAW untuk penentuan nasib perangkat';
COMMENT ON COLUMN penilaian_spk.c1_usia          IS 'Usia: 1=<3thn, 2=3-5thn, 3=>5thn';
COMMENT ON COLUMN penilaian_spk.c2_kerusakan      IS 'Kerusakan: 1=Ringan, 2=Sedang, 3=Berat';
COMMENT ON COLUMN penilaian_spk.c3_part           IS 'Suku cadang: 1=Mudah, 2=Sulit, 3=Tidak ada';
COMMENT ON COLUMN penilaian_spk.c4_kompleksitas   IS 'Kompleksitas: 1=Mudah, 2=Sedang, 3=Sulit';
COMMENT ON COLUMN penilaian_spk.c5_garansi        IS 'Garansi: 1=Aktif, 2=Habis<1thn, 3=Habis>1thn';
COMMENT ON COLUMN penilaian_spk.skor_akhir        IS 'Nilai SAW akhir 0.0000-1.0000 (dihitung oleh PHP)';
COMMENT ON COLUMN penilaian_spk.rekomendasi       IS 'Servis (skor<0.70) | Masuk Gudang (skor>=0.70)';

-- ============================================================
-- INDEX untuk performa query
-- ============================================================
CREATE INDEX idx_penilaian_perangkat_id ON penilaian_spk(perangkat_id);
CREATE INDEX idx_penilaian_tanggal      ON penilaian_spk(tanggal_penilaian DESC);
CREATE INDEX idx_penilaian_rekomendasi  ON penilaian_spk(rekomendasi);
CREATE INDEX idx_penilaian_skor         ON penilaian_spk(skor_akhir DESC NULLS LAST);
CREATE INDEX idx_perangkat_jenis        ON perangkat(jenis_perangkat);

-- ============================================================
-- DATA SAMPLE (Opsional - untuk testing awal)
-- Catatan: skor_akhir & rekomendasi akan dihitung ulang oleh PHP
-- ============================================================
INSERT INTO perangkat (kode_aset, jenis_perangkat, divisi_user) VALUES
    ('KRN-TC-001',  'Thin Client',  'Produksi'),
    ('KRN-TC-002',  'Thin Client',  'Produksi'),
    ('KRN-PC-001',  'PC Desktop',   'HR & GA'),
    ('KRN-PC-002',  'PC Desktop',   'Finance'),
    ('KRN-PRN-001', 'Printer',      'Finance'),
    ('KRN-NET-001', 'Network',      'IT Department');

INSERT INTO penilaian_spk (perangkat_id, c1_usia, c2_kerusakan, c3_part, c4_kompleksitas, c5_garansi) VALUES
    (1, 3, 3, 3, 2, 3),   -- KRN-TC-001  : Tua, kerusakan berat, no suku cadang
    (2, 2, 2, 2, 2, 2),   -- KRN-TC-002  : Semua nilai sedang
    (3, 1, 1, 1, 1, 1),   -- KRN-PC-001  : Kondisi masih sangat baik
    (4, 3, 2, 3, 3, 3),   -- KRN-PC-002  : Campuran nilai berat
    (5, 2, 3, 2, 2, 3),   -- KRN-PRN-001 : Kerusakan berat, garansi habis
    (6, 1, 1, 2, 1, 2);   -- KRN-NET-001 : Relatif bagus
