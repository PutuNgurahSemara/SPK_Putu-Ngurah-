<?php
// ============================================================
// pages/penilaian.php — Form Penilaian SPK (v2 Dinamis)
// Kriteria dimuat dari DB — mendukung N kriteria secara dinamis
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../lib/SawEngine.php';

$db      = getDB();
$engine  = new SawEngine($db);
$bobotDB = $engine->getBobot(); // [kriteria_id => bobot]

// Ambil semua perangkat untuk dropdown
$stmtP = $db->query("SELECT id, kode_aset, jenis_perangkat, divisi_user FROM perangkat ORDER BY kode_aset");
$semuaPerangkat = $stmtP->fetchAll();

// Pre-select jika ada param dari URL
$preSelectedId = (int)($_GET['perangkat_id'] ?? 0);

// Ambil penilaian terbaru perangkat yang di-preselect
$existingPenilaian = null;
$existingNilai     = []; // [kriteria_id => nilai]

if ($preSelectedId > 0) {
    $stmtEx = $db->prepare("
        SELECT * FROM penilaian_spk
        WHERE perangkat_id = :pid
        ORDER BY tanggal_penilaian DESC
        LIMIT 1
    ");
    $stmtEx->execute([':pid' => $preSelectedId]);
    $existingPenilaian = $stmtEx->fetch();

    if ($existingPenilaian) {
        // Ambil nilai detail per kriteria
        $stmtD = $db->prepare(
            "SELECT kriteria_id, nilai FROM penilaian_detail WHERE penilaian_id = :pid"
        );
        $stmtD->execute([':pid' => $existingPenilaian['id']]);
        foreach ($stmtD->fetchAll() as $d) {
            $existingNilai[(int)$d['kriteria_id']] = (int)$d['nilai'];
        }
    }
}

// Ambil semua kriteria dari DB (dinamis)
$semuaKriteria = getAllKriteria();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Kolom Kiri: Form Input ─────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Card Form -->
        <div class="bg-slate-800 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <h2 class="text-white font-semibold text-sm">Form Penilaian SPK</h2>
                <p class="text-slate-400 text-xs mt-0.5">
                    Pilih perangkat dan isi nilai setiap kriteria. Perhitungan SAW dilakukan otomatis.
                </p>
            </div>

            <form action="actions/penilaian_action.php" method="POST"
                  id="formPenilaian" class="p-6 space-y-5">

                <!-- Pilih Perangkat -->
                <div>
                    <label class="block text-slate-400 text-xs font-semibold mb-2 uppercase tracking-wide">
                        Perangkat yang Dinilai <span class="text-red-400">*</span>
                    </label>
                    <?php if (empty($semuaPerangkat)): ?>
                    <div class="bg-amber-900/20 border border-amber-700/50 rounded-xl p-4 text-amber-300 text-sm">
                        ⚠️ Belum ada data perangkat.
                        <a href="index.php?hal=perangkat" class="underline ml-1">Tambah perangkat dulu →</a>
                    </div>
                    <?php else: ?>
                    <select name="perangkat_id" id="selectPerangkat"
                            class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5
                                   text-slate-200 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50"
                            required onchange="loadExistingData(this.value)">
                        <option value="">-- Pilih Perangkat --</option>
                        <?php foreach ($semuaPerangkat as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                <?= $preSelectedId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['kode_aset']) ?>
                            (<?= htmlspecialchars($p['jenis_perangkat']) ?> — <?= htmlspecialchars($p['divisi_user']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <!-- Separator -->
                <div class="border-t border-slate-700/50 pt-4">
                    <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide mb-4">
                        Nilai Kriteria (Skala 1–3) — <?= count($semuaKriteria) ?> kriteria aktif
                    </p>

                    <div class="space-y-4">
                        <?php foreach ($semuaKriteria as $kr):
                            $kid      = (int)$kr['id'];
                            $bobotPct = round((float)$kr['bobot'] * 100, 1);
                            $existVal = $existingNilai[$kid] ?? '';
                        ?>
                        <div class="grid grid-cols-5 gap-4 items-start">
                            <!-- Label + Bobot -->
                            <div class="col-span-2">
                                <p class="text-slate-300 text-sm font-medium">
                                    <?= htmlspecialchars($kr['kode_kriteria']) ?> —
                                    <?= htmlspecialchars($kr['nama_kriteria']) ?>
                                </p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="text-blue-400 text-xs font-mono font-semibold">
                                        Bobot: <?= number_format((float)($bobotDB[$kid] ?? 0), 4) ?>
                                    </span>
                                    <span class="text-slate-600 text-[10px]">(<?= $bobotPct ?>%)</span>
                                </div>
                                <!-- Mini bar bobot -->
                                <div class="mt-1 h-0.5 bg-slate-700 rounded-full overflow-hidden w-24">
                                    <div class="h-full bg-blue-500 rounded-full"
                                         style="width: <?= min(100, $bobotPct) ?>%"></div>
                                </div>
                            </div>
                            <!-- Dropdown Nilai -->
                            <div class="col-span-3">
                                <select name="nilai[<?= $kid ?>]"
                                        id="sel_<?= $kid ?>"
                                        class="kriteria-select w-full bg-slate-700/50 border border-slate-600
                                               rounded-xl px-3 py-2 text-slate-200 text-sm
                                               focus:outline-none focus:ring-2 focus:ring-blue-500/50
                                               focus:border-blue-500/50 transition-all"
                                        required onchange="updatePreview()">
                                    <option value="">-- Pilih Nilai --</option>
                                    <option value="1" <?= $existVal == 1 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kr['nilai_1']) ?>
                                    </option>
                                    <option value="2" <?= $existVal == 2 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kr['nilai_2']) ?>
                                    </option>
                                    <option value="3" <?= $existVal == 3 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kr['nilai_3']) ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-2 border-t border-slate-700/50">
                    <button type="reset" onclick="resetPreview()"
                            class="px-5 py-2.5 rounded-xl border border-slate-600 text-slate-400 text-sm
                                   hover:bg-slate-700 transition-all">
                        Reset
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white
                                   text-sm font-semibold transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 7h16a1 1 0 010 2H4a1 1 0 010-2zm0 6h16a1 1 0 010 2H4a1 1 0 010-2z"/>
                        </svg>
                        Hitung &amp; Simpan Penilaian
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Kolom Kanan: Info + Preview ───────────────────── -->
    <div class="space-y-5">

        <!-- Info Bobot Aktif -->
        <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
            <h3 class="text-white font-semibold text-sm mb-3">Bobot Kriteria Aktif</h3>
            <div class="space-y-2.5">
                <?php
                $bobotColors = ['bg-blue-500','bg-red-500','bg-amber-500','bg-purple-500','bg-emerald-500',
                                'bg-cyan-500','bg-rose-500','bg-lime-500','bg-indigo-500','bg-orange-500'];
                $ki = 0;
                foreach ($semuaKriteria as $kr):
                    $pct   = round((float)$kr['bobot'] * 100, 1);
                    $color = $bobotColors[$ki % count($bobotColors)];
                    $ki++;
                ?>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-300 font-medium">
                            <?= htmlspecialchars($kr['kode_kriteria']) ?> — <?= htmlspecialchars($kr['nama_kriteria']) ?>
                        </span>
                        <span class="text-slate-400 font-mono"><?= number_format((float)$kr['bobot'], 4) ?></span>
                    </div>
                    <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full <?= $color ?> rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="index.php?hal=pengaturan_bobot"
               class="mt-4 flex items-center gap-1.5 text-xs text-blue-400 hover:text-blue-300 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                </svg>
                Ubah Pengaturan Bobot / Tambah Kriteria
            </a>
        </div>

        <!-- Preview Skor (Live) -->
        <div class="bg-slate-800 border border-slate-700/60 rounded-2xl p-5" id="previewCard">
            <h3 class="text-white font-semibold text-sm mb-3">Preview Skor SAW</h3>
            <div id="previewContent">
                <div class="flex flex-col items-center justify-center py-6 text-slate-500 text-center">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <p class="text-xs">Isi semua nilai kriteria<br>untuk melihat preview skor</p>
                </div>
            </div>
        </div>

        <!-- Panduan Nilai -->
        <div class="bg-blue-900/20 border border-blue-700/40 rounded-2xl p-4 text-xs text-blue-300/80">
            <p class="font-semibold text-blue-200 mb-2">📖 Panduan Skala Nilai</p>
            <div class="space-y-1">
                <p><strong class="text-blue-200">Nilai 1</strong> → Kondisi terbaik (cenderung Servis)</p>
                <p><strong class="text-blue-200">Nilai 2</strong> → Kondisi sedang (perlu pertimbangan)</p>
                <p><strong class="text-blue-200">Nilai 3</strong> → Kondisi terburuk (cenderung Gudang)</p>
            </div>
            <div class="mt-3 pt-3 border-t border-blue-700/30">
                <p>Threshold rekomendasi: Skor <strong class="text-blue-200">≥ <?= SAW_THRESHOLD ?></strong> = Masuk Gudang</p>
            </div>
        </div>
    </div>
</div>

<!-- ── JavaScript: Preview Skor Real-time ────────────────── -->
<script>
// Data kriteria dari PHP (id, bobot) untuk kalkulasi client-side
const kriteriaData = <?= json_encode(
    array_map(fn($k) => [
        'id'    => (int)$k['id'],
        'kode'  => $k['kode_kriteria'],
        'nama'  => $k['nama_kriteria'],
        'bobot' => (float)$k['bobot'],
    ], $semuaKriteria)
) ?>;

const bobotMap  = Object.fromEntries(kriteriaData.map(k => [k.id, k.bobot]));
const THRESHOLD = <?= SAW_THRESHOLD ?>;

function updatePreview() {
    const nilai = {};
    let allFilled = true;

    kriteriaData.forEach(k => {
        const sel = document.getElementById('sel_' + k.id);
        const v   = sel ? parseInt(sel.value) : 0;
        if (!v) { allFilled = false; }
        nilai[k.id] = v || 0;
    });

    if (!allFilled) {
        document.getElementById('previewContent').innerHTML = `
            <div class="flex flex-col items-center justify-center py-6 text-slate-500 text-center">
                <svg class="w-8 h-8 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <p class="text-xs">Isi semua nilai kriteria<br>untuk melihat preview skor</p>
            </div>`;
        return;
    }

    // Hitung MAX = 3 (skala tetap 1-3) → preview approximation
    const MAX = 3;
    let skor = 0;
    let rows = '';

    kriteriaData.forEach(k => {
        const v      = nilai[k.id];
        const norm   = v / MAX;
        const kontrib = norm * k.bobot;
        skor += kontrib;
        rows += `<tr class="border-b border-slate-700/40">
            <td class="py-1 text-slate-400 text-xs">${k.kode} — ${k.nama}</td>
            <td class="py-1 text-center text-slate-300 text-xs font-mono">${v}</td>
            <td class="py-1 text-center text-slate-400 text-xs font-mono">${norm.toFixed(4)}</td>
            <td class="py-1 text-center text-blue-400 text-xs font-mono">${kontrib.toFixed(4)}</td>
        </tr>`;
    });

    const isGudang = skor >= THRESHOLD;
    const rekClass = isGudang ? 'text-red-400 bg-red-900/30 border-red-700/50'
                               : 'text-amber-400 bg-amber-900/30 border-amber-700/50';
    const rekLabel = isGudang ? '🗂 Masuk Gudang' : '🔧 Servis';

    document.getElementById('previewContent').innerHTML = `
        <div class="text-center mb-4">
            <p class="text-4xl font-extrabold ${isGudang ? 'text-red-400' : 'text-amber-400'}">
                ${skor.toFixed(4)}
            </p>
            <p class="text-slate-400 text-xs mt-1">Skor SAW</p>
            <span class="inline-flex items-center mt-2 px-3 py-1 rounded-full text-sm font-semibold border ${rekClass}">
                ${rekLabel}
            </span>
        </div>
        <div class="text-xs text-slate-500 text-center mb-3">
            Threshold: ${THRESHOLD} | ${isGudang ? 'Skor ≥ threshold' : 'Skor < threshold'}
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="text-slate-500 border-b border-slate-700/60">
                    <th class="py-1 text-left font-medium">Kriteria</th>
                    <th class="py-1 text-center font-medium">Nilai</th>
                    <th class="py-1 text-center font-medium">Norm</th>
                    <th class="py-1 text-center font-medium">Kontrib</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
        </div>
        <p class="text-[10px] text-slate-600 text-center mt-2">
            *Preview menggunakan MAX=3. Skor final dihitung server berdasarkan data aktual.
        </p>`;
}

function resetPreview() {
    document.getElementById('previewContent').innerHTML = `
        <div class="flex flex-col items-center justify-center py-6 text-slate-500 text-center">
            <p class="text-xs">Isi semua nilai kriteria<br>untuk melihat preview skor</p>
        </div>`;
}

function loadExistingData(perangkatId) {
    window.location.href = 'index.php?hal=penilaian&perangkat_id=' + perangkatId;
}

<?php if ($existingPenilaian): ?>
setTimeout(updatePreview, 100);
<?php endif; ?>
</script>
