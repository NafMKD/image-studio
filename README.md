# Image Studio

A Laravel and Vue image-generation studio with conversations, usage-based credits, account reporting, and admin analytics. 

## Setup

Requires PHP 8.3+, Composer, and Node.js.

```powershell
composer install
Copy-Item .env.example .env | cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
composer run dev
```

Open `http://localhost:8000`. To use the live API, configure the `PIXELFORGE_*` values in `.env`.
