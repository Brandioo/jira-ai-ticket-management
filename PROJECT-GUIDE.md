# Jira AI Ticket Management — Complete Education Project

This package contains two separate projects plus Postman assets:

```text
jira-ai-ticket-management/
├── jira-ai-ticket-management-backend/   # Laravel 12 + PHP 8.4 + PHPUnit + DDEV/MySQL
├── jira-ai-ticket-management-frontend/  # React 19 + TypeScript + Vite
└── postman/                             # API regression collection/environment
```

## Features

- Login and signup
- Admin / Manager / Employee roles
- Projects and multiple sprints
- Jira-style issue list and issue detail view
- Ticket CRUD, status, priority, assignee, reporter, sprint and type
- Story-point or hour-based estimation
- Comments, worklogs and ticket history
- Sprint/team analytics, employee averages and overtime
- AI-style assistant for ticket search and project analytics
- Light/dark theme
- Mobile-first responsive layout with mobile bottom navigation
- DDEV backend URL: `https://jira-ai-ticket-management-backend.ddev.site`
- Frontend URL: `http://localhost:5173`

## Seeded accounts

All seeded demo accounts use password `password`:

- `admin@example.com` — Admin
- `manager@example.com` — Manager
- `alice@example.com` — Employee
- `bob@example.com` — Employee

## Clean install

### Backend

```bash
cd ~/workspace/ContenanceProjects/jira-ai-ticket-management/jira-ai-ticket-management-backend
cp .env.example .env
ddev start
ddev composer install
ddev exec php artisan key:generate
ddev exec php artisan migrate:fresh --seed
ddev exec php artisan optimize:clear
ddev exec php artisan test  # 35 PHPUnit tests
curl -k https://jira-ai-ticket-management-backend.ddev.site/api/health
```

### Frontend

Open another terminal:

```bash
cd ~/workspace/ContenanceProjects/jira-ai-ticket-management/jira-ai-ticket-management-frontend
cp .env.example .env
npm install
npm run build
npm run dev
```

Open `http://localhost:5173`.

## Everyday start

Terminal 1:

```bash
cd ~/workspace/ContenanceProjects/jira-ai-ticket-management/jira-ai-ticket-management-backend
ddev start
```

Terminal 2:

```bash
cd ~/workspace/ContenanceProjects/jira-ai-ticket-management/jira-ai-ticket-management-frontend
npm run dev
```

## Verification

Backend:

```bash
ddev exec php artisan test
ddev exec php artisan route:list
```

Frontend:

```bash
npm run build
```

Backend logs:

```bash
ddev logs -f
```

Laravel log:

```bash
ddev exec tail -f storage/logs/laravel.log
```

## Git

The backend and frontend each contain their own `.gitignore` and can be pushed as separate repositories.

```bash
cd jira-ai-ticket-management-backend
git init
```

and separately:

```bash
cd jira-ai-ticket-management-frontend
git init
```

## Postman

Import both files from the `postman/` folder. Run **Login manager** first so the collection token is populated, then test tickets, comments/worklogs, reports and AI Assistant endpoints.
