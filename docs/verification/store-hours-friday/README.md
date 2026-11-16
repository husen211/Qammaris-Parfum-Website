# Store opens on Friday — 2026-11-16

Owner: the store is open every day; Saturday–Thursday 09.00–21.00 WITA, Friday 13.00–21.00 WITA (previously closed on Friday).

Changed: `config/store.php` (`profile.hours`, two schedules), `StoreProfile` (one `OpeningHoursSpecification` per schedule), the Location page (Jumat 13.00–21.00 WITA, FAQ, meta description "buka setiap hari"), the About page (Jam buka and FAQ). No database, media, order or price change.

Checks:
- `php artisan test tests/Feature/StoreAboutTest.php tests/Feature/StoreVirtualTourTest.php tests/Feature/SeoTechnicalTest.php`: 14 passed. New test: the Location page shows both schedules and no "Jumat tutup"; the About structured data has the Friday 13:00–21:00 schedule.
- Local browser, 375×812 and desktop: no horizontal overflow; the Location JSON-LD lists Saturday–Thursday 09:00–21:00 and Friday 13:00–21:00.

Screenshots: [Location 375](location-375.png), [About 375](about-375.jpg), [Location desktop](location-desktop.jpg).

Not covered: the Google Business Profile (Maps) hours are set in Google, not by the website.
