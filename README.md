# Sewa Garasi

A garage rental website. Admins create garages in batches (bike, car and electric car garages), approve or reject tenant registrations, extend running rentals and view revenue reports. Approved tenants register their vehicles and rent a free garage daily or monthly, with electric charging in electric garages.

## Requirements

- PHP 8.3 or newer, with Composer
- Node.js 20.19+ or 22.12+, with npm
- MySQL 8

## Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yohanajocelyn/certification.git
   cd certification
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**
   ```bash
   npm install
   ```

4. **Configure Environment Variables**
   Copy the example environment file to create your `.env`:
   ```bash
   cp .env.example .env
   ```
   Set your MySQL connection in `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=garage_rental
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```
   Set the administrator account that the seeder creates:
   ```env
   ADMIN_NAME="Administrator"
   ADMIN_EMAIL=admin@example.com
   ADMIN_PASSWORD=your-strong-password
   ADMIN_NIK=0000000000000000
   ADMIN_PHONE=080000000000
   ```
   > **Note:** `ADMIN_PASSWORD` is empty in `.env.example`. If it stays empty, the admin account is not created.

5. **Create the Database**
   ```sql
   CREATE DATABASE garage_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

6. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

7. **Run Migrations and Seed Database**
   Creates the tables, the administrator account and demo data (tenants, garages and rentals):
   ```bash
   php artisan migrate --seed
   ```

8. **Link the Storage Folder**
   Makes uploaded payment proof images viewable in the browser:
   ```bash
   php artisan storage:link
   ```

9. **Build Frontend Assets**
   ```bash
   npm run build
   ```
   *(Alternatively, run `npm run dev` to watch assets during development.)*

10. **Start the Application Server**
    ```bash
    php artisan serve
    ```
    The application is now running at `http://localhost:8000`.

11. **Log in as Administrator**
    - Open `http://localhost:8000` in your browser.
    - Log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from your `.env`.

12. **Log in as a Tenant**
    - **New tenant:** log out, open **Daftar** (`/register`) and register. Then log in as the administrator, open **Penyewa** and approve the registration. The tenant can now log in.
    - **Demo tenant:** log in as the administrator and open **Penyewa** to see the seeded tenants' emails. Every demo tenant's password is `password`.

## Running Tests

1. **Create the Test Database** (once)
   ```sql
   CREATE DATABASE garage_rental_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   The tests use this database (set in `phpunit.xml`) and wipe it on every run. The app's own database is never touched.

2. **Run the Tests**
   ```bash
   php artisan test
   ```

## External Libraries

| Name | Version | License | Why it was chosen | Docs |
|---|---|---|---|---|
| Laravel | 13.x | MIT | MVC framework, Eloquent ORM, Blade templating | https://laravel.com/docs |
| Tailwind CSS | 4.x | MIT | Utility-first styling for a consistent UI without custom CSS files | https://tailwindcss.com/docs |
| Vite (laravel-vite-plugin) | 8.x | MIT | Builds and serves CSS/JS assets | https://laravel.com/docs/vite |
| PHPUnit | 12.x | BSD-3-Clause | Unit tests | https://docs.phpunit.de |
| blade-ui-kit/blade-heroicons | 2.7 | MIT | Heroicons as Blade components (`<x-heroicon-o-plus />`) for consistent, accessible SVG icons without writing raw SVG | https://github.com/blade-ui-kit/blade-heroicons |

Font: Instrument Sans (SIL Open Font License 1.1), downloaded at build time by laravel-vite-plugin's fonts feature and served from the app.

`blade-heroicons` pulls in `blade-ui-kit/blade-icons` (1.11, MIT) as its dependency.

> Never commit `.env` or credentials.
