<?php
// ============================================================
// pages/perangkat.php — CRUD Master Data Perangkat
// ============================================================
declare(strict_types=1);

$db = getDB();

// ── Ambil data perangkat + info penilaian ─────────────────
$search = trim($_GET['q'] ?? '');
$sql    = "
    SELECT p.*,
           ps.skor_akhir,
           ps.rekomendasi,
           ps.tanggal_penilaian
    FROM perangkat p
    LEFT JOIN LATERAL (
        SELECT skor_akhir, rekomendasi, tanggal_penilaian
        FROM penilaian_spk
        WHERE perangkat_id = p.id
        ORDER BY tanggal_penilaian DESC
        LIMIT 1
    ) ps ON TRUE
";
$params = [];
if ($search !== '') {
    $sql    .= " WHERE p.kode_aset ILIKE :q OR p.divisi_user ILIKE :q2 OR p.jenis_perangkat ILIKE :q3";
    $params  = [':q' => "%{$search}%", ':q2' => "%{$search}%", ':q3' => "%{$search}%"];
}
$sql .= " ORDER BY p.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$perangkatList = $stmt->fetchAll();

// ── Edit mode: ambil data 1 perangkat untuk pre-fill form ─
$editData = null;
if (!empty($_GET['edit'])) {
    $stmtEdit = $db->prepare("SELECT * FROM perangkat WHERE id = :id");
    $stmtEdit->execute([':id' => (int)$_GET['edit']]);
    $editData = $stmtEdit->fetch();
}
?>

<!-- ── Header + Tombol Tambah + Search ───────────────────── -->
<div class="flex flex-col sm:flex-row gap-3 mb-6">
    <!-- Search Bar -->
    <form method="GET" action="index.php" class="flex-1 flex gap-2">
        <input type="hidden" name="hal" value="perangkat">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari kode aset, jenis, atau divisi..."
                   class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5
                          text-sm text-slate-200 placeholder-slate-500
                          focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50">
        </div>
        <button type="submit"
                class="px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm
                       rounded-xl transition-colors border border-slate-600">
            Cari
        </button>
        <?php if ($search): ?>
        <a href="index.php?hal=perangkat"
           class="px-4 py-2.5 bg-slate-800 text-slate-400 text-sm rounded-xl border
                  border-slate-700 hover:bg-slate-700 transition-colors">✕</a>
        <?php endif; ?>
    </form>

    <!-- Tombol Tambah -->
    <button onclick="bukaModal()"
            class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-500
                   text-white text-sm font-semibold rounded-xl transition-all
                   hover:shadow-lg hover:shadow-blue-500/25 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Perangkat
    </button>
</div>

<!-- ── Tabel Data Perangkat ───────────────────────────────── -->
<div class="bg-slate-800 border border-slate-700/60 rounded-2xl overflow-hidden">

    <!-- Table Head -->
    <div class="grid grid-cols-12 gap-3 px-5 py-3 bg-slate-700/40 border-b border-slate-700/60
                text-xs font-semibold text-slate-400 uppercase tracking-wide">
        <div class="col-span-1">#</div>
        <div class="col-span-2">Kode Aset</div>
        <div class="col-span-2">Jenis</div>
        <div class="col-span-3">Divisi</div>
        <div class="col-span-2">Status SPK</div>
        <div class="col-span-2 text-right">Aksi</div>
    </div>

    <!-- Rows -->
    <?php if (empty($perangkatList)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-slate-500">
        <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <p class="text-sm">
            <?= $search ? 'Tidak ada hasil untuk "'.htmlspecialchars($search).'"' : 'Belum ada data perangkat.' ?>
        </p>
        <?php if (!$search): ?>
        <button onclick="bukaModal()" class="mt-3 text-blue-400 text-sm hover:underline">
            + Tambah perangkat pertama
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="divide-y divide-slate-700/40">
        <?php
        $jenisColors = [
            'Thin Client' => 'bg-blue-900/40 text-blue-300 border-blue-700/50',
            'PC Desktop'  => 'bg-purple-900/40 text-purple-300 border-purple-700/50',
            'Printer'     => 'bg-emerald-900/40 text-emerald-300 border-emerald-700/50',
            'Network'     => 'bg-orange-900/40 text-orange-300 border-orange-700/50',
        ];
        foreach ($perangkatList as $i => $row):
            $jColor     = $jenisColors[$row['jenis_perangkat']] ?? 'bg-slate-700 text-slate-300 border-slate-600';
            $rekClass   = match($row['rekomendasi']) {
                'Masuk Gudang' => 'bg-red-900/40 text-red-300 border-red-700/50',
                'Servis'       => 'bg-amber-900/40 text-amber-300 border-amber-700/50',
                default        => 'bg-slate-700/40 text-slate-400 border-slate-600/50',
            };
            $rekLabel   = $row['rekomendasi'] ?? 'Belum Dinilai';
        ?>
        <div class="grid grid-cols-12 gap-3 px-5 py-3.5 items-center
                    hover:bg-slate-700/20 transition-colors group">

            <div class="col-span-1 text-slate-500 text-xs font-mono"><?= $i + 1 ?></div>

            <div class="col-span-2">
                <span class="text-slate-200 text-sm font-semibold font-mono">
                    <?= htmlspecialchars($row['kode_aset']) ?>
                </span>
                <p class="text-slate-500 text-[10px] mt-0.5">
                    <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                </p>
            </div>

            <div class="col-span-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border <?= $jColor ?>">
                    <?= htmlspecialchars($row['jenis_perangkat']) ?>
                </span>
            </div>

            <div class="col-span-3 text-slate-300 text-sm truncate">
                <?= htmlspecialchars($row['divisi_user']) ?>
            </div>

            <div class="col-span-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                             text-xs font-medium border <?= $rekClass ?>">
                    <?php if ($row['rekomendasi']): ?>
                    <span class="w-1.5 h-1.5 rounded-full
                        <?= $row['rekomendasi'] === 'Masuk Gudang' ? 'bg-red-400' : 'bg-amber-400' ?>">
                    </span>
                    <?php endif; ?>
                    <?= htmlspecialchars($rekLabel) ?>
                </span>
                <?php if ($row['skor_akhir'] !== null): ?>
                <p class="text-slate-500 text-[10px] mt-0.5 font-mono">
                    Skor: <?= number_format((float)$row['skor_akhir'], 4) ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="col-span-2 flex items-center justify-end gap-1.5">
                <!-- Tombol Nilai -->
                <a href="index.php?hal=penilaian&perangkat_id=<?= $row['id'] ?>"
                   title="Beri Penilaian SPK"
                   class="p-1.5 rounded-lg bg-green-900/30 text-green-400 hover:bg-green-800/50
                          transition-colors border border-green-800/50 opacity-0 group-hover:opacity-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </a>
                <!-- Tombol Edit -->
                <button onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($row)) ?>)"
                        title="Edit"
                        class="p-1.5 rounded-lg bg-blue-900/30 text-blue-400 hover:bg-blue-800/50
                               transition-colors border border-blue-800/50 opacity-0 group-hover:opacity-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <!-- Tombol Hapus -->
                <form action="actions/perangkat_action.php" method="POST"
                      onsubmit="return confirm('Hapus perangkat <?= htmlspecialchars($row['kode_aset'], ENT_QUOTES) ?>?\nData penilaian terkait juga akan dihapus.')">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                    <button type="submit" title="Hapus"
                            class="p-1.5 rounded-lg bg-red-900/30 text-red-400 hover:bg-red-800/50
                                   transition-colors border border-red-800/50 opacity-0 group-hover:opacity-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer tabel: count -->
    <div class="px-5 py-3 border-t border-slate-700/50 text-xs text-slate-500">
        Menampilkan <?= count($perangkatList) ?> perangkat
        <?= $search ? '(hasil pencarian "'.htmlspecialchars($search).'")' : '' ?>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: Tambah / Edit Perangkat
═══════════════════════════════════════════════════════ -->
<div id="modalPerangkat"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
     onclick="tutupModal(event)">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

    <!-- Dialog -->
    <div class="relative bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl
                w-full max-w-md p-6 z-10 animate-fadeIn">

        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-bold text-base" id="modalTitle">Tambah Perangkat Baru</h3>
            <button onclick="tutupModal(null, true)"
                    class="text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form action="actions/perangkat_action.php" method="POST" class="space-y-4">
            <input type="hidden" name="aksi" id="formAksi" value="tambah">
            <input type="hidden" name="id"   id="formId"   value="">

            <!-- Kode Aset -->
            <div>
                <label class="block text-slate-400 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                    Kode Aset <span class="text-red-400">*</span>
                </label>
                <input type="text" name="kode_aset" id="formKodeAset"
                       placeholder="Contoh: KRN-TC-007"
                       class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5
                              text-slate-200 text-sm placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                              transition-all font-mono uppercase"
                       required maxlength="50"
                       oninput="this.value = this.value.toUpperCase()">
            </div>

            <!-- Jenis Perangkat -->
            <div>
                <label class="block text-slate-400 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                    Jenis Perangkat <span class="text-red-400">*</span>
                </label>
                <select name="jenis_perangkat" id="formJenis"
                        class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5
                               text-slate-200 text-sm
                               focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                               transition-all"
                        required>
                    <option value="">-- Pilih Jenis --</option>
                    <?php foreach (JENIS_PERANGKAT as $jenis): ?>
                    <option value="<?= htmlspecialchars($jenis) ?>">
                        <?= htmlspecialchars($jenis) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Divisi User -->
            <div>
                <label class="block text-slate-400 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                    Divisi / Departemen Pengguna <span class="text-red-400">*</span>
                </label>
                <input type="text" name="divisi_user" id="formDivisi"
                       placeholder="Contoh: HR & GA, Finance, Produksi..."
                       class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-2.5
                              text-slate-200 text-sm placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                              transition-all"
                       required maxlength="100">
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="tutupModal(null, true)"
                        class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-400 text-sm
                               hover:bg-slate-700 transition-all">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white
                               text-sm font-semibold transition-all">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── JavaScript Modal ───────────────────────────────────── -->
<script>
const modal      = document.getElementById('modalPerangkat');
const modalTitle = document.getElementById('modalTitle');
const formAksi   = document.getElementById('formAksi');
const formId     = document.getElementById('formId');
const formKode   = document.getElementById('formKodeAset');
const formJenis  = document.getElementById('formJenis');
const formDivisi = document.getElementById('formDivisi');

function bukaModal() {
    modalTitle.textContent = 'Tambah Perangkat Baru';
    formAksi.value  = 'tambah';
    formId.value    = '';
    formKode.value  = '';
    formJenis.value = '';
    formDivisi.value = '';
    formKode.readOnly = false;
    modal.classList.remove('hidden');
    formKode.focus();
}

function bukaModalEdit(data) {
    modalTitle.textContent  = 'Edit Perangkat: ' + data.kode_aset;
    formAksi.value   = 'edit';
    formId.value     = data.id;
    formKode.value   = data.kode_aset;
    formJenis.value  = data.jenis_perangkat;
    formDivisi.value = data.divisi_user;
    formKode.readOnly = true; // Kode aset tidak bisa diubah saat edit
    modal.classList.remove('hidden');
    formDivisi.focus();
}

function tutupModal(event, force = false) {
    if (force || event?.target === modal) {
        modal.classList.add('hidden');
    }
}

// Buka otomatis jika ada error dari session (kembali dari action)
<?php if (!empty($_GET['modal'])): ?>
bukaModal();
<?php endif; ?>
</script>

<style>
@keyframes fadeIn {
    from { opacity:0; transform: scale(0.96) translateY(8px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}
.animate-fadeIn { animation: fadeIn 0.2s ease-out; }
</style>
