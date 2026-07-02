# SIMPA — Sistem Informasi Manajemen Pengembangan Aplikasi

Aplikasi web fullstack untuk mengelola siklus hidup pengembangan aplikasi di lingkungan BSSN (Badan Siber dan Sandi Negara).

## Tech Stack

- **Backend:** Laravel (PHP)
- **Frontend:** Vue.js 3 + Vite
- **Database:** MySQL

## Project Structure

```
├── backend/          # Laravel API
│   ├── app/          # Models, Controllers, Middleware, Services
│   ├── database/     # Migrations & Seeders
│   ├── routes/       # API Routes
│   └── tests/        # Unit & Feature Tests
├── frontend/         # Vue.js SPA
│   ├── src/
│   │   ├── components/   # Reusable UI Components
│   │   ├── views/        # Page Views
│   │   ├── stores/       # Pinia State Management
│   │   ├── router/       # Vue Router
│   │   └── layouts/      # Layout Components
│   └── public/           # Static Assets
└── .github/          # CI/CD Workflows
```

## SonarCloud Analysis Trigger

This commit is used to trigger second static analysis scan for SIMPA system.
Triggering the *real* second scan to compute Quality Gate.
