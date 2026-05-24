# queue-system

A queue system that helps organizations manage customer lines more neatly, call people in order, and show live updates on a display screen.

## Requirements

- PHP `8.3+`
- Composer
- Node.js + npm
- SQLite by default, or any other database supported by Laravel (e.g. MySQL)

## Setup

1. Clone the repository:

```bash
git clone https://github.com/AkmalFairuz/queue-system
cd queue-system
```

2. Install dependencies:

```bash
composer install
npm install
```

3. Prepare the environment:

```bash
cp .env.example .env
php artisan key:generate
```

4. If you use the default SQLite setup, create the database file:

```bash
touch database/database.sqlite
```

5. Run migrations:

```bash
php artisan migrate
```

6. Optional, seed demo data:

```bash
php artisan db:seed
```

## Run Locally

Before running the app, build the frontend assets:
```bash
npm run build
```

Start the Laravel app:

```bash
php artisan serve
```

Start Reverb for realtime features:

```bash
php artisan reverb:start
```

## Reverb Notes

This project already includes Reverb configuration in `.env.example`.

Important defaults:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=queue-system
REVERB_APP_KEY=queue-system-key
REVERB_APP_SECRET=queue-system-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

## Demo Seeder

If you run `php artisan db:seed`, the demo accounts are:

- tenant code: `rs-harapan-sehat`
- owner
  - email: `owner@example.com`
  - password: `password`
- admin
  - email: `admin@example.com`
  - password: `password`
- staff 1
  - email: `staff1@example.com`
  - password: `password`
- staff 2
  - email: `staff2@example.com`
  - password: `password`
