{{-- ANALYTICS-01: public pages in production only. Order links (orders.*) are excluded: their URLs carry private tokens. --}}
@if (app()->environment('production') && ! request()->routeIs('orders.*'))
    @php($googleVerification = (string) config('analytics.google_site_verification'))
    @php($cloudflareToken = (string) config('analytics.cloudflare_token'))
    @if (preg_match('/^[A-Za-z0-9_-]{10,100}$/', $googleVerification))
        <meta name="google-site-verification" content="{{ $googleVerification }}">
    @endif
    @if (preg_match('/^[A-Za-z0-9]{16,64}$/', $cloudflareToken))
        <script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "{{ $cloudflareToken }}"}'></script>
    @endif
@endif
