# ORD-19 verification: pin first, kecamatan filled from the pin (local, 2026-11-12)

The Owner asked that the pin come first and the kecamatan second, filled automatically: "prioritas alamat ini kita pin aja dulu". The Owner chose **Palu only**: outside Palu the customer searches the kecamatan/kota as before.

## Change

- **Alamat pengiriman order:**
  1. **Titik antar**, with the hint "Untuk Palu dan sekitarnya. Kecamatan terisi otomatis.";
  2. **Kecamatan atau kota** ("Terisi dari titik antar. Kirim ke luar Palu? Cari di sini.");
  3. **Alamat lengkap**.
- After "Pakai titik ini", Instan is priced from the pin straight away. The field reads "Membaca kecamatan dari titik…" while the server answers:
  - **One area** (OpenStreetMap and the nearest Palu kecamatan centre agree; Sigi/Donggala from OpenStreetMap; or no OpenStreetMap answer and a centre within 5 km): the field is filled and Reguler (J&T) is priced.
  - **Two areas** (they disagree, e.g. Balaroa read as Ulujadi): "Titik ini di perbatasan kecamatan. Pilih yang sesuai:" with two buttons.
  - **Nothing read:** "Kecamatan belum terbaca dari titik ini." with a "Cari kecamatan" button.
- The filled area is Biteship's own (same search, cache and session memory as a searched one), so it prices and is saved with Biteship's label.

## Checks

| Check | Result |
| --- | --- |
| Pin endpoint (PHP test) | Agree: one Palu area (not the Makassar namesake). Disagree: Ulujadi and Palu Barat. No OpenStreetMap answer: Tatanga (nearest). Sigi: Sigi Biromaru. Far and unread: none. Outside the box: 422. One Nominatim call per rounded pin. |
| 390, before the pin | Titik antar first, then kecamatan, then alamat ([shot](after-390-pin-first.jpg)) |
| 390, pin on the Palu Selatan map shortcut (real Nominatim, Biteship test key) | Kecamatan "Palu Selatan, Palu, Sulawesi Tengah. 94231" filled; GrabExpress Rp16.000, GoSend Rp17.000 (struck Rp20.500), J&T Rp5.500 (struck Rp8.000) ([shot](after-390-pin-filled.jpg)) |
| Two kecamatan (pin answer simulated with real Biteship areas for Ulujadi and Palu Barat) | Choice shown, field empty; tapping "Palu Barat" filled it and priced J&T Rp5.500 ([390](after-390-pin-two-choices.jpg), [1440](after-1440-pin-two-choices.jpg)) |
| 1440 | Same flow ([before](after-1440-pin-first.jpg), [filled](after-1440-pin-filled.jpg)) |

## Live (qammarisparfum.id, after PR #174 and #175)

- On a phone-size screen, a pin on the Palu Selatan shortcut filled "Palu Selatan, Palu, Sulawesi Tengah. 94231" by itself.
- Prices came from Biteship live: GrabExpress Rp18.000, GoSend Rp17.000 (struck Rp20.500), J&T Rp5.500 (struck Rp8.000).
- Screenshots: [before the pin](live-390-pin-first.jpg), [filled](live-390-pin-filled.jpg).
- No order was placed.

