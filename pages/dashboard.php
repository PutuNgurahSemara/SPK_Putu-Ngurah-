<?php
// ============================================================
// pages/dashboard.php — Halaman Dashboard Utama
// Menampilkan ringkasan statistik dan distribusi data SPK
// ============================================================
declare(strict_types=1);

$db = getDB();

// ── Query Statistik Utama ──────────────────────────────────
$stmtTotal = $db->query("SELECT COUNT(*) AS total FROM penilaian_spk");
$totalDinilai = (int) $stmtTotal->fetchColumn();

$stmtServis = $db->query("SELECT COUNT(*) FROM penilaian_spk WHERE rekomendasi = 'Servis'");
$totalServis = (int) $stmtServis->fetchColumn();

$stmtGudang = $db->query("SELECT COUNT(*) FROM penilaian_spk WHERE rekomendasi = 'Masuk Gudang'");
$totalGudang = (int) $stmtGudang->fetchColumn();

$totalBelumDihitung = $totalDinilai - $totalServis - $totalGudang;

// ── Distribusi per Jenis Perangkat ────────────────────────
$stmtJenis = $db->query("
    SELECT p.jenis_perangkat,
           COUNT(ps.id)                                                     AS total,
           COUNT(ps.id) FILTER (WHERE ps.rekomendasi = 'Servis')            AS servis,
           COUNT(ps.id) FILTER (WHERE ps.rekomendasi = 'Masuk Gudang')      AS gudang
    FROM perangkat p
    LEFT JOIN penilaian_spk ps ON ps.perangkat_id = p.id
    GROUP BY p.jenis_perangkat
    ORDER BY total DESC
");
$distribusiJenis = $stmtJenis->fetchAll();

// ── 5 Penilaian Terbaru ───────────────────────────────────
$stmtRecent = $db->query("
    SELECT p.kode_aset, p.jenis_perangkat, p.divisi_user,
           ps.skor_akhir, ps.rekomendasi, ps.tanggal_penilaian
    FROM penilaian_spk ps
    JOIN perangkat p ON p.id = ps.perangkat_id
    ORDER BY ps.tanggal_penilaian DESC
    LIMIT 5
");
$recentPenilaian = $stmtRecent->fetchAll();

// ── Rata-rata Skor Per Kriteria ───────────────────────────
$stmtAvg = $db->query("
    SELECT ROUND(AVG(c1_usia)::numeric, 2)         AS avg_c1,
           ROUND(AVG(c2_kerusakan)::numeric, 2)     AS avg_c2,
           ROUND(AVG(c3_part)::numeric, 2)           AS avg_c3,
           ROUND(AVG(c4_kompleksitas)::numeric, 2)   AS avg_c4,
           ROUND(AVG(c5_garansi)::numeric, 2)        AS avg_c5
    FROM penilaian_spk
");
$avgKriteria = $stmtAvg->fetch();
?>

<!-- ── Stat Cards ─────────────────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    <!-- Total Dinilai -->
    <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4
                hover:border-blue-500/40 hover:-translate-y-0.5 transition-all duration-200 group">
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center flex-shrink-0
                    group-hover:bg-blue-500/20 transition-colors">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <div>
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Total Dinilai</p>
            <p class="text-3xl font-extrabold text-white mt-0.5"><?= $totalDinilai ?></p>
            <p class="text-slate-500 text-xs mt-0.5">Perangkat</p>
        </div>
    </div>

    <!-- Total Servis -->
    <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4
                hover:border-amber-500/40 hover:-translate-y-0.5 transition-all duration-200 group">
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0
                    group-hover:bg-amber-500/20 transition-colors">
            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Rekomendasi Servis</p>
            <p class="text-3xl font-extrabold text-amber-400 mt-0.5"><?= $totalServis ?></p>
            <p class="text-slate-500 text-xs mt-0.5">Skor &lt; 0.70</p>
        </div>
    </div>

    <!-- Total Gudang -->
    <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4
                hover:border-red-500/40 hover:-translate-y-0.5 transition-all duration-200 group">
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center flex-shrink-0
                    group-hover:bg-red-500/20 transition-colors">
            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
        </div>
        <div>
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Masuk Gudang</p>
            <p class="text-3xl font-extrabold text-red-400 mt-0.5"><?= $totalGudang ?></p>
            <p class="text-slate-500 text-xs mt-0.5">Skor &ge; 0.70</p>
        </div>
    </div>

    <!-- Belum Dihitung -->
    <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5 flex items-center gap-4
                hover:border-slate-500/40 hover:-translate-y-0.5 transition-all duration-200 group">
        <div class="w-12 h-12 rounded-xl bg-slate-600/20 flex items-center justify-center flex-shrink-0
                    group-hover:bg-slate-600/30 transition-colors">
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Menunggu Hitung</p>
            <p class="text-3xl font-extrabold text-slate-300 mt-0.5"><?= $totalBelumDihitung ?></p>
            <p class="text-slate-500 text-xs mt-0.5">Belum diproses</p>
        </div>
    </div>
</div>

<!-- ── Row: Distribusi + Aktivitas Terbaru ─────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-5 mb-5">

    <!-- Distribusi per Jenis (3 col) -->
    <div class="lg:col-span-3 bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-white font-semibold text-sm">Distribusi per Jenis Perangkat</h2>
                <p class="text-slate-500 text-xs mt-0.5">Breakdown Servis vs Masuk Gudang</p>
            </div>
            <a href="index.php?hal=hasil"
               class="text-xs text-blue-400 hover:text-blue-300 font-medium transition-colors">
                Lihat semua →
            </a>
        </div>

        <?php if (empty($distribusiJenis)): ?>
            <div class="flex flex-col items-center justify-center py-10 text-slate-500">
                <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-sm">Belum ada data perangkat</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
            <?php
            $jenisColors = [
                'Thin Client' => ['bar' => 'bg-blue-500',   'dot' => 'bg-blue-400'],
                'PC Desktop'  => ['bar' => 'bg-purple-500', 'dot' => 'bg-purple-400'],
                'Printer'     => ['bar' => 'bg-emerald-500','dot' => 'bg-emerald-400'],
                'Network'     => ['bar' => 'bg-orange-500', 'dot' => 'bg-orange-400'],
            ];
            $maxTotal = max(array_column($distribusiJenis, 'total')) ?: 1;

            foreach ($distribusiJenis as $row):
                $color   = $jenisColors[$row['jenis_perangkat']] ?? ['bar' => 'bg-slate-500', 'dot' => 'bg-slate-400'];
                $pct     = round(($row['total'] / $maxTotal) * 100);
                $pctBar  = max(4, $pct); // minimal 4% agar bar terlihat
            ?>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?= $color['dot'] ?>"></span>
                        <span class="text-slate-300 text-sm font-medium"><?= htmlspecialchars($row['jenis_perangkat']) ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span class="text-amber-400"><?= $row['servis'] ?> Servis</span>
                        <span class="text-red-400"><?= $row['gudang'] ?> Gudang</span>
                        <span class="text-white font-semibold w-6 text-right"><?= $row['total'] ?></span>
                    </div>
                </div>
                <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full <?= $color['bar'] ?> rounded-full transition-all duration-500"
                         style="width: <?= $pctBar ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Aktivitas Terbaru (2 col) -->
    <div class="lg:col-span-2 bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
        <div class="mb-5">
            <h2 class="text-white font-semibold text-sm">Penilaian Terbaru</h2>
            <p class="text-slate-500 text-xs mt-0.5">5 entri terakhir</p>
        </div>

        <?php if (empty($recentPenilaian)): ?>
            <div class="flex flex-col items-center justify-center py-10 text-slate-500">
                <p class="text-sm">Belum ada penilaian</p>
                <a href="index.php?hal=penilaian"
                   class="mt-3 text-xs text-blue-400 hover:underline">Mulai penilaian →</a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
            <?php foreach ($recentPenilaian as $r):
                $isGudang = $r['rekomendasi'] === 'Masuk Gudang';
                $badgeClass = $isGudang
                    ? 'bg-red-900/50 text-red-300 border border-red-700/50'
                    : 'bg-amber-900/50 text-amber-300 border border-amber-700/50';
                $skor = $r['skor_akhir'] !== null ? number_format((float)$r['skor_akhir'], 4) : '—';
            ?>
            <div class="flex items-center justify-between gap-2 py-2.5 border-b border-slate-700/40 last:border-0">
                <div class="min-w-0">
                    <p class="text-slate-200 text-xs font-semibold truncate"><?= htmlspecialchars($r['kode_aset']) ?></p>
                    <p class="text-slate-500 text-[10px] truncate"><?= htmlspecialchars($r['jenis_perangkat']) ?> &mdash; <?= htmlspecialchars($r['divisi_user']) ?></p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-slate-300 text-xs font-mono"><?= $skor ?></span>
                    <?php if ($r['rekomendasi']): ?>
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full <?= $badgeClass ?>">
                        <?= $isGudang ? 'Gudang' : 'Servis' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Rata-rata Kriteria ──────────────────────────────── -->
<?php if ($totalDinilai > 0): ?>
<div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
    <div class="mb-5">
        <h2 class="text-white font-semibold text-sm">Rata-rata Nilai per Kriteria</h2>
        <p class="text-slate-500 text-xs mt-0.5">Dari seluruh <?= $totalDinilai ?> data penilaian (skala 1–3)</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <?php
        $kriteriaInfo = [
            ['label' => 'C1 Usia',         'key' => 'avg_c1', 'color' => 'text-blue-400',   'bg' => 'bg-blue-500'],
            ['label' => 'C2 Kerusakan',    'key' => 'avg_c2', 'color' => 'text-red-400',    'bg' => 'bg-red-500'],
            ['label' => 'C3 Suku Cadang',  'key' => 'avg_c3', 'color' => 'text-amber-400',  'bg' => 'bg-amber-500'],
            ['label' => 'C4 Kompleksitas', 'key' => 'avg_c4', 'color' => 'text-purple-400', 'bg' => 'bg-purple-500'],
            ['label' => 'C5 Garansi',      'key' => 'avg_c5', 'color' => 'text-emerald-400','bg' => 'bg-emerald-500'],
        ];
        foreach ($kriteriaInfo as $k):
            $val  = (float)($avgKriteria[$k['key']] ?? 0);
            $pct  = round(($val / 3) * 100);
        ?>
        <div class="bg-slate-700/40 rounded-xl p-4 text-center">
            <p class="text-slate-400 text-xs font-medium mb-2"><?= $k['label'] ?></p>
            <p class="text-2xl font-bold <?= $k['color'] ?>"><?= number_format($val, 2) ?></p>
            <div class="mt-2 h-1.5 bg-slate-600 rounded-full overflow-hidden">
                <div class="h-full <?= $k['bg'] ?> rounded-full"
                     style="width: <?= $pct ?>%"></div>
            </div>
            <p class="text-slate-600 text-[10px] mt-1"><?= $pct ?>% dari maks</p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
