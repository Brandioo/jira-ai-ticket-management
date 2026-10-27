# Jira AI Ticket Management — Backend

Laravel 12 API used by the Jira-style education project.

## Start

```bash
cp .env.example .env
ddev start
ddev composer install
ddev exec php artisan key:generate
ddev exec php artisan migrate:fresh --seed
ddev exec php artisan test
```

API base URL:

```text
https://jira-ai-ticket-management-backend.ddev.site/api
```

Health endpoint:

```bash
curl -k https://jira-ai-ticket-management-backend.ddev.site/api/health
```

Demo login: `manager@example.com` / `password`.
