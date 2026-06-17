-- ============================================================
-- MIGRASI v2: Kriteria Dinamis (EAV Pattern)
-- Database   : PostgreSQL / spk_aset_it
-- Jalankan SEKALI di database yang sudah ada data v1
-- ============================================================

BEGIN;

-- ── LANGKAH 1: Tambah kolom baru ke tabel kriteria ───────────
ALTER TABLE kriteria
    ADD COLUMN IF NOT EXISTS nilai_1  VARCHAR(150) NOT NULL DEFAULT '1 — Nilai Rendah',
    ADD COLUMN IF NOT EXISTS nilai_2  VARCHAR(150) NOT NULL DEFAULT '2 — Nilai Sedang',
    ADD COLUMN IF NOT EXISTS nilai_3  VARCHAR(150) NOT NULL DEFAULT '3 — Nilai Tinggi',
    ADD COLUMN IF NOT EXISTS urutan   SMALLINT     NOT NULL DEFAULT 1;

-- Update label deskriptif dan urutan untuk kriteria yang sudah ada
UPDATE kriteria SET
    nilai_1 = '1 — Di bawah 3 tahun',
    nilai_2 = '2 — Antara 3 sampai 5 tahun',
    nilai_3 = '3 — Di atas 5 tahun',
    urutan  = 1
WHERE kode_kriteria = 'C1';

UPDATE kriteria SET
    nilai_1 = '1 — Kerusakan Ringan',
    nilai_2 = '2 — Kerusakan Sedang',
    nilai_3 = '3 — Kerusakan Berat',
    urutan  = 2
WHERE kode_kriteria = 'C2';

UPDATE kriteria SET
    nilai_1 = '1 — Suku Cadang Mudah Didapat',
    nilai_2 = '2 — Suku Cadang Sulit Didapat',
    nilai_3 = '3 — Suku Cadang Tidak Tersedia',
    urutan  = 3
WHERE kode_kriteria = 'C3';

UPDATE kriteria SET
    nilai_1 = '1 — Pengerjaan Mudah',
    nilai_2 = '2 — Pengerjaan Sedang',
    nilai_3 = '3 — Pengerjaan Sulit / Kompleks',
    urutan  = 4
WHERE kode_kriteria = 'C4';

UPDATE kriteria SET
    nilai_1 = '1 — Garansi Masih Aktif',
    nilai_2 = '2 — Garansi Habis < 1 Tahun',
    nilai_3 = '3 — Garansi Habis > 1 Tahun',
    urutan  = 5
WHERE kode_kriteria = 'C5';

-- Perluas kode_kriteria dari VARCHAR(5) ke VARCHAR(10) untuk mendukung C10+
ALTER TABLE kriteria ALTER COLUMN kode_kriteria TYPE VARCHAR(10);

-- ── LANGKAH 2: Buat tabel penilaian_detail (EAV) ─────────────
CREATE TABLE IF NOT EXISTS penilaian_detail (
    id           SERIAL   PRIMARY KEY,
    penilaian_id INT      NOT NULL REFERENCES penilaian_spk(id) ON DELETE CASCADE,
    kriteria_id  INT      NOT NULL REFERENCES kriteria(id)       ON DELETE RESTRICT,
    nilai        SMALLINT NOT NULL CHECK (nilai BETWEEN 1 AND 3),
    UNIQUE (penilaian_id, kriteria_id)
);

COMMENT ON TABLE  penilaian_detail               IS 'Nilai per-kriteria per-penilaian (EAV). Mendukung N kriteria dinamis.';
COMMENT ON COLUMN penilaian_detail.penilaian_id  IS 'FK ke penilaian_spk.id';
COMMENT ON COLUMN penilaian_detail.kriteria_id   IS 'FK ke kriteria.id';
COMMENT ON COLUMN penilaian_detail.nilai         IS 'Nilai skala 1–3 (1=terbaik/rendah, 3=terburuk/tinggi)';

CREATE INDEX IF NOT EXISTS idx_detail_penilaian ON penilaian_detail(penilaian_id);
CREATE INDEX IF NOT EXISTS idx_detail_kriteria  ON penilaian_detail(kriteria_id);

-- ── LANGKAH 3: Migrasi data lama c1–c5 → penilaian_detail ────
-- Hanya insert jika belum ada data di penilaian_detail
INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
SELECT ps.id, k.id, ps.c1_usia
FROM penilaian_spk ps
CROSS JOIN kriteria k
WHERE k.kode_kriteria = 'C1'
  AND ps.c1_usia IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM penilaian_detail pd
      WHERE pd.penilaian_id = ps.id AND pd.kriteria_id = k.id
  );

INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
SELECT ps.id, k.id, ps.c2_kerusakan
FROM penilaian_spk ps
CROSS JOIN kriteria k
WHERE k.kode_kriteria = 'C2'
  AND ps.c2_kerusakan IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM penilaian_detail pd
      WHERE pd.penilaian_id = ps.id AND pd.kriteria_id = k.id
  );

INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
SELECT ps.id, k.id, ps.c3_part
FROM penilaian_spk ps
CROSS JOIN kriteria k
WHERE k.kode_kriteria = 'C3'
  AND ps.c3_part IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM penilaian_detail pd
      WHERE pd.penilaian_id = ps.id AND pd.kriteria_id = k.id
  );

INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
SELECT ps.id, k.id, ps.c4_kompleksitas
FROM penilaian_spk ps
CROSS JOIN kriteria k
WHERE k.kode_kriteria = 'C4'
  AND ps.c4_kompleksitas IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM penilaian_detail pd
      WHERE pd.penilaian_id = ps.id AND pd.kriteria_id = k.id
  );

INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
SELECT ps.id, k.id, ps.c5_garansi
FROM penilaian_spk ps
CROSS JOIN kriteria k
WHERE k.kode_kriteria = 'C5'
  AND ps.c5_garansi IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM penilaian_detail pd
      WHERE pd.penilaian_id = ps.id AND pd.kriteria_id = k.id
  );

-- ── LANGKAH 4: Hapus kolom lama dari penilaian_spk ───────────
ALTER TABLE penilaian_spk
    DROP COLUMN IF EXISTS c1_usia,
    DROP COLUMN IF EXISTS c2_kerusakan,
    DROP COLUMN IF EXISTS c3_part,
    DROP COLUMN IF EXISTS c4_kompleksitas,
    DROP COLUMN IF EXISTS c5_garansi;

-- ── LANGKAH 5: Verifikasi ─────────────────────────────────────
-- Tampilkan ringkasan migrasi
DO $$
DECLARE
    cnt_kriteria   INT;
    cnt_penilaian  INT;
    cnt_detail     INT;
    total_bobot    NUMERIC(5,4);
BEGIN
    SELECT COUNT(*) INTO cnt_kriteria  FROM kriteria;
    SELECT COUNT(*) INTO cnt_penilaian FROM penilaian_spk;
    SELECT COUNT(*) INTO cnt_detail    FROM penilaian_detail;
    SELECT ROUND(SUM(bobot)::numeric, 4) INTO total_bobot FROM kriteria;

    RAISE NOTICE '=== Migrasi v2 Selesai ===';
    RAISE NOTICE 'Jumlah kriteria  : %', cnt_kriteria;
    RAISE NOTICE 'Jumlah penilaian : %', cnt_penilaian;
    RAISE NOTICE 'Jumlah detail    : % (expected: % × %)', cnt_detail, cnt_penilaian, cnt_kriteria;
    RAISE NOTICE 'Total bobot      : % (harus = 1.00)', total_bobot;
END $$;

COMMIT;
