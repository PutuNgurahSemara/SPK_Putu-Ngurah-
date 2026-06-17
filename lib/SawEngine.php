<?php
// ============================================================
// lib/SawEngine.php — Engine Perhitungan Metode SAW (v2 Dinamis)
// Bobot & daftar kriteria diambil DINAMIS dari tabel `kriteria`
// Nilai per-kriteria diambil dari tabel `penilaian_detail` (EAV)
// ============================================================
declare(strict_types=1);

class SawEngine
{
    private \PDO  $db;
    private array $bobot;        // [kriteria_id => bobot_float]
    private array $kriteriaIds;  // [kriteria_id, ...]
    private float $threshold;

    public function __construct(\PDO $db)
    {
        $this->db        = $db;
        $this->threshold = SAW_THRESHOLD;
        $this->loadKriteria();
    }

    // ── PRIVATE: Muat daftar kriteria & bobot dari DB ──────────
    private function loadKriteria(): void
    {
        $rows = $this->db->query(
            "SELECT id, bobot FROM kriteria ORDER BY urutan, kode_kriteria ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->bobot       = [];
        $this->kriteriaIds = [];

        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $this->bobot[$id]    = (float)$r['bobot'];
            $this->kriteriaIds[] = $id;
        }
    }

    // ── PUBLIC: Hitung ulang SEMUA baris penilaian_spk ────────
    /**
     * Menghitung ulang skor_akhir dan rekomendasi untuk SEMUA baris.
     * Dipanggil setiap kali ada INSERT/UPDATE penilaian atau perubahan bobot.
     *
     * Algoritma SAW (Benefit):
     *   r_ij = x_ij / max(x_j)          ← normalisasi
     *   V_i  = Σ (w_j * r_ij)           ← nilai preferensi
     *
     * @return int Jumlah baris yang berhasil di-update
     */
    public function hitungUlangSemua(): int
    {
        if (empty($this->kriteriaIds)) {
            return 0;
        }

        // 1. Ambil semua penilaian
        $allPenilaian = $this->db
            ->query("SELECT id FROM penilaian_spk")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($allPenilaian)) {
            return 0;
        }

        // 2. Ambil semua nilai detail
        $allDetail = $this->db
            ->query("SELECT penilaian_id, kriteria_id, nilai FROM penilaian_detail")
            ->fetchAll(\PDO::FETCH_ASSOC);

        // Kelompokkan: nilaiMap[penilaian_id][kriteria_id] = nilai
        $nilaiMap = [];
        foreach ($allDetail as $d) {
            $nilaiMap[(int)$d['penilaian_id']][(int)$d['kriteria_id']] = (float)$d['nilai'];
        }

        // 3. Cari nilai MAX tiap kriteria dari semua penilaian
        $maxKriteria = array_fill_keys($this->kriteriaIds, 0.0);
        foreach ($nilaiMap as $values) {
            foreach ($this->kriteriaIds as $kid) {
                $val = $values[$kid] ?? 0.0;
                if ($val > $maxKriteria[$kid]) {
                    $maxKriteria[$kid] = $val;
                }
            }
        }

        // 4. Siapkan statement UPDATE
        $stmt = $this->db->prepare("
            UPDATE penilaian_spk
               SET skor_akhir  = :skor,
                   rekomendasi = :rek
             WHERE id = :id
        ");

        $updated = 0;

        foreach ($allPenilaian as $p) {
            $pid    = (int)$p['id'];
            $values = $nilaiMap[$pid] ?? [];

            // 5. Hitung V_i = Σ (w_j * r_ij)
            $skor = $this->hitungSkor($values, $maxKriteria);

            // 6. Tentukan rekomendasi
            $rekomendasi = ($skor >= $this->threshold) ? 'Masuk Gudang' : 'Servis';

            $stmt->execute([
                ':skor' => round($skor, 4),
                ':rek'  => $rekomendasi,
                ':id'   => $pid,
            ]);

            $updated++;
        }

        return $updated;
    }

    // ── PUBLIC: Preview skor untuk 1 set nilai (tanpa simpan) ──
    /**
     * Menghitung skor SAW untuk satu baris nilai tanpa menyentuh database.
     * Berguna untuk preview real-time di form penilaian.
     *
     * @param array<int, int> $nilaiBaru [kriteria_id => nilai (1-3)]
     * @return array ['skor'=>float, 'rekomendasi'=>string, 'detail'=>array]
     */
    public function previewSkor(array $nilaiBaru): array
    {
        // Ambil semua detail existing untuk menghitung MAX yang akurat
        $allDetail = $this->db
            ->query("SELECT penilaian_id, kriteria_id, nilai FROM penilaian_detail")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $allValues = [];
        foreach ($allDetail as $d) {
            $allValues[(int)$d['penilaian_id']][(int)$d['kriteria_id']] = (float)$d['nilai'];
        }
        $allValues['preview'] = $nilaiBaru; // sertakan baris baru

        // Hitung MAX
        $maxKriteria = array_fill_keys($this->kriteriaIds, 0.0);
        foreach ($allValues as $values) {
            foreach ($this->kriteriaIds as $kid) {
                $val = (float)($values[$kid] ?? 0);
                if ($val > $maxKriteria[$kid]) {
                    $maxKriteria[$kid] = $val;
                }
            }
        }

        $skor   = 0.0;
        $detail = [];

        foreach ($this->kriteriaIds as $kid) {
            $nilai   = (float)($nilaiBaru[$kid] ?? 0);
            $max     = $maxKriteria[$kid] ?: 1.0;
            $norm    = $nilai / $max;
            $bobot   = $this->bobot[$kid] ?? 0.0;
            $kontrib = $norm * $bobot;
            $skor   += $kontrib;

            $detail[$kid] = [
                'nilai'   => $nilai,
                'max'     => $max,
                'norm'    => round($norm, 4),
                'bobot'   => $bobot,
                'kontrib' => round($kontrib, 4),
            ];
        }

        return [
            'skor'        => round($skor, 4),
            'rekomendasi' => ($skor >= $this->threshold) ? 'Masuk Gudang' : 'Servis',
            'detail'      => $detail,
            'threshold'   => $this->threshold,
        ];
    }

    // ── PRIVATE: Hitung V_i = Σ (w_j * r_ij) ─────────────────
    private function hitungSkor(array $values, array $maxKriteria): float
    {
        $skor = 0.0;

        foreach ($this->kriteriaIds as $kid) {
            $nilai = $values[$kid]          ?? 0.0;
            $max   = $maxKriteria[$kid]     ?: 1.0; // hindari division by zero
            $bobot = $this->bobot[$kid]     ?? 0.0;
            $skor += ($nilai / $max) * $bobot;
        }

        return $skor;
    }

    // ── PUBLIC: Getter ─────────────────────────────────────────
    /** @return array<int, float> [kriteria_id => bobot] */
    public function getBobot(): array
    {
        return $this->bobot;
    }

    /** @return array<int> Daftar kriteria_id aktif */
    public function getKriteriaIds(): array
    {
        return $this->kriteriaIds;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }
}
