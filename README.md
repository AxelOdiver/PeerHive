# PeerHive - Run Instructions

## Prerequisites
- PHP 8.2+
- Composer
- Node.js + npm
- MySQL running locally (XAMPP)

## Run the project
1. Install backend dependencies:
   ```bash
   composer install
   ```
2. Install frontend dependencies:
   ```bash
   npm install
   ```
3. Create environment file (if missing):
   ```bash
   copy .env.example .env
   ```
4. Configure database in `.env` (this project uses MySQL by default).
5. Generate app key:
   ```bash
   php artisan key:generate
   ```
6. Run migrations:
   ```bash
   php artisan migrate
   ```
7. Start the app (Laravel server + queue + logs + Vite):
   ```bash
   composer dev
   ```

## One-command setup (optional)
You can run initial setup with:
```bash
composer setup
```
Then start with:
```bash
composer dev
```
