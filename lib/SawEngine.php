<?php
// ============================================================
// lib/SawEngine.php — Engine Perhitungan Metode SAW
// Bobot diambil DINAMIS dari tabel `kriteria` (tidak hardcoded)
// ============================================================
declare(strict_types=1);

class SawEngine
{
    private \PDO  $db;
    private array $bobot;       // ['c1_usia' => 0.15, ...]
    private float $threshold;   // default 0.70

    // Kolom kriteria yang dihitung (urutan harus sama dengan kode C1–C5)
    private const KOLOM_KRITERIA = [
        'c1_usia',
        'c2_kerusakan',
        'c3_part',
        'c4_kompleksitas',
        'c5_garansi',
    ];

    public function __construct(\PDO $db)
    {
        $this->db        = $db;
        $this->bobot     = getBobotKriteria();   // ambil dari DB, bukan hardcode
        $this->threshold = SAW_THRESHOLD;         // konstanta dari koneksi.php (0.70)
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
        // 1. Ambil semua data penilaian
        $allData = $this->db
            ->query("SELECT id, c1_usia, c2_kerusakan, c3_part, c4_kompleksitas, c5_garansi
                     FROM penilaian_spk")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($allData)) {
            return 0;
        }

        // 2. Cari nilai MAX tiap kolom kriteria
        $maxKolom = $this->hitungMax($allData);

        // 3. Siapkan statement UPDATE
        $stmt = $this->db->prepare("
            UPDATE penilaian_spk
               SET skor_akhir   = :skor,
                   rekomendasi  = :rek
             WHERE id = :id
        ");

        $updated = 0;

        foreach ($allData as $row) {
            // 4. Normalisasi & hitung V_i
            $skor = $this->hitungSkor($row, $maxKolom);

            // 5. Tentukan rekomendasi berdasarkan threshold
            $rekomendasi = ($skor >= $this->threshold) ? 'Masuk Gudang' : 'Servis';

            $stmt->execute([
                ':skor' => round($skor, 4),
                ':rek'  => $rekomendasi,
                ':id'   => (int) $row['id'],
            ]);

            $updated++;
        }

        return $updated;
    }

    // ── PUBLIC: Hitung skor untuk 1 set nilai (preview sebelum simpan) ─
    /**
     * Menghitung skor SAW untuk satu baris nilai tanpa menyentuh database.
     * Berguna untuk preview real-time di form penilaian.
     *
     * @param array $nilaiBaru ['c1_usia'=>2, 'c2_kerusakan'=>3, ...]
     * @return array ['skor' => 0.7833, 'rekomendasi' => 'Masuk Gudang', 'detail' => [...]]
     */
    public function previewSkor(array $nilaiBaru): array
    {
        // Ambil semua data + tambahkan baris baru untuk perhitungan MAX
        $allData = $this->db
            ->query("SELECT c1_usia, c2_kerusakan, c3_part, c4_kompleksitas, c5_garansi
                     FROM penilaian_spk")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $allData[] = $nilaiBaru; // gabungkan baris baru untuk menentukan MAX
        $maxKolom  = $this->hitungMax($allData);

        $skor        = $this->hitungSkor($nilaiBaru, $maxKolom);
        $rekomendasi = ($skor >= $this->threshold) ? 'Masuk Gudang' : 'Servis';

        // Rincian normalisasi per kriteria (untuk transparansi)
        $detail = [];
        foreach (self::KOLOM_KRITERIA as $kolom) {
            $nilai   = (float) ($nilaiBaru[$kolom] ?? 0);
            $max     = $maxKolom[$kolom] ?: 1;
            $norm    = $nilai / $max;
            $bobot   = $this->bobot[$kolom] ?? 0;
            $kontrib = $norm * $bobot;

            $detail[$kolom] = [
                'nilai'    => $nilai,
                'max'      => $max,
                'norm'     => round($norm, 4),
                'bobot'    => $bobot,
                'kontrib'  => round($kontrib, 4),
            ];
        }

        return [
            'skor'        => round($skor, 4),
            'rekomendasi' => $rekomendasi,
            'detail'      => $detail,
            'threshold'   => $this->threshold,
        ];
    }

    // ── PRIVATE: Cari nilai MAX tiap kolom ────────────────────
    private function hitungMax(array $data): array
    {
        $max = array_fill_keys(self::KOLOM_KRITERIA, 0);

        foreach ($data as $row) {
            foreach (self::KOLOM_KRITERIA as $kolom) {
                $val = (float) ($row[$kolom] ?? 0);
                if ($val > $max[$kolom]) {
                    $max[$kolom] = $val;
                }
            }
        }

        return $max;
    }

    // ── PRIVATE: Hitung V_i = Σ (w_j * r_ij) ─────────────────
    private function hitungSkor(array $row, array $maxKolom): float
    {
        $skor = 0.0;

        foreach (self::KOLOM_KRITERIA as $kolom) {
            $nilai = (float) ($row[$kolom] ?? 0);
            $max   = $maxKolom[$kolom] ?: 1; // hindari division by zero
            $bobot = $this->bobot[$kolom]  ?? 0;

            $skor += ($nilai / $max) * $bobot;
        }

        return $skor;
    }

    // ── PUBLIC: Getter bobot (untuk ditampilkan di UI) ────────
    public function getBobot(): array
    {
        return $this->bobot;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }
}
