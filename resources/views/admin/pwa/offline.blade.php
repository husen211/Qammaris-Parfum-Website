<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1A1A1A">
    <title>Tidak ada koneksi - Qammaris Admin</title>
    {{-- Self-contained: shown by the service worker while offline, so it cannot depend on other files. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            background: #F9F9F7; color: #1A1A1A; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 380px; }
        .brand { margin: 0 0 32px; font-size: 13px; font-weight: 700; letter-spacing: .3em; text-transform: uppercase; color: #1A1A1A; }
        .brand span { margin-left: 6px; padding: 2px 6px; border-radius: 4px; background: #1A1A1A; color: #fff; font-size: 10px; letter-spacing: .1em; }
        h1 { margin: 0; font-size: 24px; line-height: 1.25; }
        p { margin: 12px 0 0; font-size: 16px; line-height: 1.55; color: #4B4B4B; }
        .warning { padding: 12px 14px; border: 1px solid #F0D9A8; border-radius: 10px; background: #FFF8E8; color: #6B4A00; }
        .action { display: flex; align-items: center; justify-content: center; margin-top: 28px; width: 100%; min-height: 52px; border: 0;
            border-radius: 10px; background: #1A1A1A; color: #fff; font: inherit; font-size: 16px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .action:focus-visible { outline: 3px solid #C5A059; outline-offset: 3px; }
        [hidden] { display: none !important; }
    </style>
</head>
{{-- The service worker rewrites GET to POST here when a form submission could not reach the server. --}}
<body data-request-method="GET">
    <main>
        <p class="brand">Qammaris<span>Admin</span></p>
        <h1>Tidak ada koneksi internet</h1>
        <p>Halaman admin selalu diambil langsung dari server, jadi data pesanan tidak tersimpan di HP ini.</p>
        <p class="warning" data-after-submit hidden>Perubahan yang baru Anda kirim mungkin belum tersimpan. Setelah online, buka pesanannya dan periksa dulu sebelum mengirim ulang.</p>
        <button type="button" class="action" data-retry>Coba lagi</button>
        <a href="/admin/orders" class="action" data-orders hidden>Buka daftar pesanan</a>
    </main>
    <script>
        (function () {
            var afterSubmit = document.body.getAttribute('data-request-method') !== 'GET';
            document.querySelector('[data-after-submit]').hidden = !afterSubmit;
            document.querySelector('[data-orders]').hidden = !afterSubmit;
            var retry = document.querySelector('[data-retry]');
            retry.hidden = afterSubmit;
            // A plain reload is safe only for GET pages; a failed form is never re-posted from here.
            retry.addEventListener('click', function () { location.reload(); });
        })();
    </script>
</body>
</html>
