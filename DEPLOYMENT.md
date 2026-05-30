# Deployment Guide

This repository is a customized Snipe-IT deployment for Young Industries.

## 1. Publish this project to GitHub

Run these commands from the project root:

```bash
git init
git branch -M main
git add .
git commit -m "Initial project import"
git remote add origin https://github.com/kahitsinoprogrammer/youngindustries.git
git push -u origin main
```

Notes:

- `.env` is ignored and should never be committed.
- `vendor/` and `node_modules/` are ignored. Install dependencies on the target server after cloning.

## 2. Server requirements

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL or MariaDB
- A web server such as Apache or Nginx

## 3. First-time application deployment

Clone the repository and install dependencies:

```bash
git clone https://github.com/kahitsinoprogrammer/youngindustries.git
cd youngindustries
composer install --no-dev --optimize-autoloader
npm install
npm run production
```

Create the environment file and update it for your server:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your real values for:

- `APP_URL`
- database credentials
- mail settings
- any Firebase or storage settings you use

Finish the Laravel setup:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## 4. Web server setup

Point your web server document root to the `public/` directory, not the repository root.

For Apache, make sure:

- `mod_rewrite` is enabled
- the virtual host allows overrides for Laravel's `.htaccess`
- PHP has write access to `storage/` and `bootstrap/cache/`

## 5. Updating an existing deployment

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm install
npm run production
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6. Quick local run

For a quick local check after configuration:

```bash
php artisan serve
```

Then open the URL shown by Laravel in your browser.
