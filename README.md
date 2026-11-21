# ProjectA

_What the site does (2-3 lines) - to be written once the task brief is confirmed._

## Setup

```bash
composer install
npm install && npm run build
cp .env.example .env          # then set the MySQL credentials (DB_*) in .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Running tests

```bash
php artisan test
```

Tests run on an in-memory SQLite database and never touch MySQL.

## External libraries

| Name | Version | License | Why it was chosen | Docs |
|---|---|---|---|---|
| Laravel | 13.x | MIT | MVC framework, Eloquent ORM, Blade templating | https://laravel.com/docs |
| Tailwind CSS | 4.x | MIT | Utility-first styling for a consistent UI without custom CSS files | https://tailwindcss.com/docs |
| Vite (laravel-vite-plugin) | 8.x | MIT | Builds and serves CSS/JS assets | https://laravel.com/docs/vite |
| PHPUnit | 12.x | BSD-3-Clause | Unit and feature tests | https://docs.phpunit.de |
| blade-ui-kit/blade-heroicons | 2.7 | MIT | Heroicons as Blade components (`<x-heroicon-o-plus />`) for consistent, accessible SVG icons without writing raw SVG | https://github.com/blade-ui-kit/blade-heroicons |

`blade-heroicons` pulls in `blade-ui-kit/blade-icons` (1.11, MIT) as its dependency.

> Never commit `.env` or credentials.
