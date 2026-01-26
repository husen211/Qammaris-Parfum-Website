# Qammaris Perfumes Website

Laravel 12 app for Qammaris Perfumes. Stack: Blade, Tailwind CSS, Vite, and small React components.

## Requirements
- PHP 8.2
- Composer
- Node.js 18+
- MySQL 8+

## Local setup
1. cp .env.example .env
2. composer install
3. php artisan key:generate
4. php artisan migrate
5. npm install
6. npm run dev

## Build assets
npm run build

## Deployment (Hostinger shared hosting)
Recommended layout:
- /home/USER/domains/DOMAIN/laravel_app (full project)
- /home/USER/domains/DOMAIN/public_html (public/ only)

Steps:
1. git clone into laravel_app
2. composer install --no-dev --optimize-autoloader
3. Create .env, set production values, run php artisan key:generate
4. php artisan migrate --force
5. Build assets locally and upload public/build to laravel_app/public/build
6. Copy public/ to public_html
7. Update public_html/index.php to load ../laravel_app/vendor and bootstrap
8. Create storage link:
   ln -s /home/USER/domains/DOMAIN/laravel_app/storage/app/public /home/USER/domains/DOMAIN/public_html/storage

## Data import
- Export SQL from local phpMyAdmin and import into hosting DB.

## Image uploads
- Upload files to storage/app/public (or public_html/storage if using the symlink).
- DB paths must match the folder structure (example: products/filename.jpg).

## Updating
1. git pull
2. composer install --no-dev --optimize-autoloader
3. php artisan migrate --force
4. Upload new public/build and re-copy public/ to public_html
