<!DOCTYPE html>
<html lang="id" x-data="{ mobileOpen: false }" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="SyntaxTrust - Jasa Pembuatan Website Mahasiswa & UMKM" />
  <meta name="theme-color" content="#0ea5e9" />
  <title>Jasa Pembuatan Website | SyntaxTrust</title>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- TailwindCSS CDN + Config -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
          colors: {
            brand: {
              50: '#ecfeff', 100: '#cffafe', 200: '#a5f3fc', 300: '#67e8f9',
              400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2', 700: '#0e7490',
              800: '#155e75', 900: '#164e63'
            },
            primary: '#0ea5e9',
            dark: '#0b1220',
          },
          boxShadow: {
            soft: '0 10px 25px -5px rgba(2,132,199,0.15), 0 8px 10px -6px rgba(2,132,199,0.12)'
          }
        }
      }
    }
  </script>

  <!-- Alpine.js for interactivity -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    :root { color-scheme: light dark; }
    html, body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
  </style>
</head>
<body class="bg-white text-slate-800 dark:bg-dark dark:text-slate-100">
  <!-- Navbar -->
  <header class="sticky top-0 z-50 border-b border-slate-100/70 bg-white/80 backdrop-blur dark:bg-slate-900/70">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <a href="/syntaxtrust/public/index.php" class="flex items-center gap-2 group">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20 shadow-soft">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 3v18M3 12h18" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="text-lg font-semibold tracking-tight group-hover:text-primary transition">SyntaxTrust</span>
        </a>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-6 text-sm">
          <a href="#layanan" class="hover:text-primary">Layanan</a>
          <a href="#portofolio" class="hover:text-primary">Portofolio</a>
          <a href="#pricing" class="hover:text-primary">Harga</a>
          <a href="#tim" class="hover:text-primary">Tim</a>
          <a href="#blog" class="hover:text-primary">Blog</a>
          <a href="#testimoni" class="hover:text-primary">Testimoni</a>
          <a href="#kontak" class="hover:text-primary">Kontak</a>
          <a href="/syntaxtrust/login.php" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Masuk</a>
        </nav>

        <!-- Mobile button -->
        <button class="md:hidden inline-flex items-center justify-center rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen = !mobileOpen" aria-label="Toggle Menu">
          <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Mobile Menu -->
      <div x-show="mobileOpen" x-transition.opacity x-collapse class="md:hidden border-t border-slate-100/70 py-3 dark:border-slate-700/50">
        <div class="flex flex-col gap-2 text-sm">
          <a href="#layanan" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Layanan</a>
          <a href="#portofolio" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Portofolio</a>
          <a href="#pricing" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Harga</a>
          <a href="#tim" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Tim</a>
          <a href="#blog" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Blog</a>
          <a href="#testimoni" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Testimoni</a>
          <a href="#kontak" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen=false">Kontak</a>
          <a href="/syntaxtrust/login.php" class="rounded-lg bg-primary px-3 py-2 text-center text-white" @click="mobileOpen=false">Masuk</a>
        </div>
      </div>
    </div>
  </header>

  <main>
