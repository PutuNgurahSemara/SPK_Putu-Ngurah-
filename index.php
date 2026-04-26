<?php
// ============================================================
// index.php — Front Controller / Router Utama
// Semua request masuk ke sini, lalu di-route ke halaman yang tepat
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';

// Whitelist halaman yang valid
const HALAMAN_VALID = [
    'dashboard',
    'pengaturan_bobot',
    'perangkat',
    'penilaian',
    'hasil',
];

// Ambil parameter halaman dari URL, default: dashboard
$halaman = $_GET['hal'] ?? 'dashboard';

// Sanitasi: hanya izinkan halaman dari whitelist
if (!in_array($halaman, HALAMAN_VALID, true)) {
    $halaman = 'dashboard';
}

$filePage = __DIR__ . '/pages/' . $halaman . '.php';

// Pastikan file halaman benar-benar ada
if (!file_exists($filePage)) {
    $halaman  = 'dashboard';
    $filePage = __DIR__ . '/pages/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Pendukung Keputusan Manajemen Aset IT - Kutai Refinery Nusantara">
    <title>SPK Aset IT — Kutai Refinery Nusantara</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        sidebar: '#0f172a',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Scrollbar custom */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Sidebar active item glow */
        .nav-active {
            background: linear-gradient(90deg, rgba(59,130,246,0.25) 0%, rgba(59,130,246,0.08) 100%);
            border-left: 3px solid #3b82f6;
        }
        .nav-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.05);
            border-left: 3px solid rgba(59,130,246,0.5);
        }

        /* Card shimmer */
        @keyframes shimmer {
            0%   { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        /* Smooth page transition */
        .page-content { animation: fadeIn 0.25s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Alert flash messages */
        .alert-flash { animation: slideDown 0.3s ease-out; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 font-sans h-full flex overflow-hidden">

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside id="sidebar"
       class="w-64 min-h-screen bg-sidebar flex flex-col flex-shrink-0
              border-r border-slate-700/50 transition-all duration-300 z-30">

    <!-- Logo / Brand -->
    <div class="px-5 py-5 border-b border-slate-700/50">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-blue-500/30">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                </svg>
            </div>
            <div class="leading-tight">
                <p class="text-white font-bold text-sm tracking-wide">SPK Aset IT</p>
                <p class="text-slate-500 text-[10px] font-medium uppercase tracking-widest">Kutai Refinery</p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

        <!-- Label grup -->
        <p class="text-slate-600 text-[10px] font-semibold uppercase tracking-widest px-3 pb-2">Menu Utama</p>

        <!-- Dashboard -->
        <a href="index.php?hal=dashboard"
           id="nav-dashboard"
           class="nav-item <?= $halaman === 'dashboard' ? 'nav-active' : '' ?>
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $halaman === 'dashboard' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <svg class="w-4.5 h-4.5 w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard Utama
        </a>

        <!-- Pengaturan Bobot -->
        <a href="index.php?hal=pengaturan_bobot"
           id="nav-pengaturan_bobot"
           class="nav-item <?= $halaman === 'pengaturan_bobot' ? 'nav-active' : '' ?>
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $halaman === 'pengaturan_bobot' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 4v-2m0 2a2 2 0 100 4m0-4a2 2 0 110 4m6-4v-2m0 2a2 2 0 100 4m0-4a2 2 0 110 4"/>
            </svg>
            Pengaturan Bobot
        </a>

        <p class="text-slate-600 text-[10px] font-semibold uppercase tracking-widest px-3 pt-4 pb-2">Manajemen Data</p>

        <!-- Data Perangkat -->
        <a href="index.php?hal=perangkat"
           id="nav-perangkat"
           class="nav-item <?= $halaman === 'perangkat' ? 'nav-active' : '' ?>
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $halaman === 'perangkat' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8l-5-5H9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v5h10"/>
            </svg>
            Data Perangkat
        </a>

        <!-- Penilaian SPK -->
        <a href="index.php?hal=penilaian"
           id="nav-penilaian"
           class="nav-item <?= $halaman === 'penilaian' ? 'nav-active' : '' ?>
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $halaman === 'penilaian' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            Penilaian SPK
        </a>

        <!-- Hasil Keputusan -->
        <a href="index.php?hal=hasil"
           id="nav-hasil"
           class="nav-item <?= $halaman === 'hasil' ? 'nav-active' : '' ?>
                  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $halaman === 'hasil' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200' ?>">
            <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Hasil Keputusan
        </a>

    </nav>

    <!-- Footer Sidebar -->
    <div class="px-5 py-4 border-t border-slate-700/50">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-full bg-brand-700 flex items-center justify-center text-xs font-bold text-white">IT</div>
            <div class="leading-tight">
                <p class="text-slate-300 text-xs font-semibold">IT Support</p>
                <p class="text-slate-600 text-[10px]">KRN — <?= date('Y') ?></p>
            </div>
        </div>
    </div>
</aside>

<!-- ============================================================
     MAIN CONTENT AREA
============================================================ -->
<div class="flex-1 flex flex-col min-h-screen overflow-y-auto">

    <!-- Top Bar -->
    <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-sm border-b border-slate-700/50
                   flex items-center justify-between px-6 py-3.5 flex-shrink-0">
        <div>
            <h1 class="text-slate-100 font-semibold text-base" id="page-title">
                <?php
                $pageTitles = [
                    'dashboard'         => 'Dashboard Utama',
                    'pengaturan_bobot'  => 'Pengaturan Bobot Kriteria',
                    'perangkat'         => 'Data Perangkat',
                    'penilaian'         => 'Penilaian SPK',
                    'hasil'             => 'Hasil Keputusan',
                ];
                echo htmlspecialchars($pageTitles[$halaman] ?? 'Dashboard');
                ?>
            </h1>
            <p class="text-slate-500 text-xs">Sistem Pendukung Keputusan — Manajemen Aset IT</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="hidden sm:inline-flex items-center gap-1.5 text-xs text-slate-400 bg-slate-800 px-3 py-1.5 rounded-full border border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                Online
            </span>
            <span class="text-slate-500 text-xs hidden md:block"><?= date('d M Y') ?></span>
        </div>
    </header>

    <!-- Flash Messages (dari session) -->
    <?php
    session_start();
    if (!empty($_SESSION['flash'])):
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $isError   = $flash['type'] === 'error';
        $alertBase = $isError
            ? 'bg-red-900/40 border-red-500/60 text-red-300'
            : 'bg-green-900/40 border-green-500/60 text-green-300';
    ?>
    <div class="alert-flash mx-6 mt-4 px-4 py-3 rounded-lg border <?= $alertBase ?> flex items-start gap-3 text-sm">
        <span class="text-lg leading-none"><?= $isError ? '❌' : '✅' ?></span>
        <span><?= htmlspecialchars($flash['message']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="flex-1 p-6 page-content">
        <?php require_once $filePage; ?>
    </main>

    <!-- Footer -->
    <footer class="px-6 py-3 border-t border-slate-800 text-center text-slate-600 text-xs flex-shrink-0">
        SPK Manajemen Aset IT &copy; <?= date('Y') ?> &mdash; Kutai Refinery Nusantara &mdash; Metode SAW
    </footer>
</div>

<!-- ============================================================
     GLOBAL SCRIPTS
============================================================ -->
<script>
    // Auto-hide flash messages setelah 5 detik
    const flash = document.querySelector('.alert-flash');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 5000);
    }
</script>

</body>
</html>
