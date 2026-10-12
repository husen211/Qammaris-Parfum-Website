<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <title>Tidak ada koneksi - Qammaris Perfumes</title>
    {{-- Self-contained: shown by the public service worker while offline. Only the precached icon is loaded. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            background: #F9F9F7; color: #1A1A1A; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 380px; }
        .brand { display: flex; align-items: center; gap: 12px; margin: 0 0 32px; font-size: 13px; font-weight: 600; letter-spacing: .3em; text-transform: uppercase; }
        .brand img { width: 40px; height: 40px; }
        h1 { margin: 0; font-size: 24px; font-weight: 600; line-height: 1.25; }
        p { margin: 12px 0 0; font-size: 16px; line-height: 1.55; color: #4B4B4B; }
        .action { display: flex; align-items: center; justify-content: center; margin-top: 28px; width: 100%; min-height: 52px; border: 1px solid #1A1A1A;
            background: #1A1A1A; color: #fff; font: inherit; font-size: 14px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; text-decoration: none; cursor: pointer; }
        .secondary { margin-top: 12px; background: transparent; color: #1A1A1A; }
        .action:focus-visible { outline: 3px solid #C5A059; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <p class="brand"><img src="/images/pwa/public-icon-192.png" alt="" width="40" height="40">Qammaris</p>
        <h1>Tidak ada koneksi internet</h1>
        <p>Katalog, keranjang, dan status pesanan selalu diambil langsung dari server. Sambungkan internet, lalu coba lagi.</p>
        <p>Pesanan tidak pernah dikirim otomatis dari halaman ini. Jika Anda baru saja menekan tombol pesan, buka Pesanan Saya setelah online untuk memeriksanya sebelum mengirim ulang.</p>
        <button type="button" class="action" data-retry>Coba lagi</button>
        <a href="/pesanan-saya" class="action secondary">Pesanan Saya</a>
    </main>
    <script>
        // The worker only shows this page for GET navigations, so a reload never re-posts a form.
        document.querySelector('[data-retry]').addEventListener('click', function () { location.reload(); });
    </script>
</body>
</html>
