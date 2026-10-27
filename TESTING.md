# Testing checklist

## Automated backend tests

```bash
cd jira-ai-ticket-management-backend
ddev exec php artisan test
```

The package currently contains **35 PHPUnit tests** covering:

- seeded demo credentials
- signup, login, logout/profile protection
- roles and permissions
- dashboard analytics
- ticket CRUD
- ticket search/filtering
- sprint assignment and project/sprint consistency
- story-point and hour estimates
- switching estimation type
- comments and history
- worklogs and overtime validation
- sprint reports and employee reports
- AI ticket search, overtime and average-hours queries

## Frontend build

```bash
cd jira-ai-ticket-management-frontend
npm run build
```

## Manual UI smoke test

1. Log in as `manager@example.com` / `password`.
2. Toggle light/dark mode.
3. Resize browser to a phone width; confirm bottom navigation appears and the page does not shrink like desktop.
4. Create an issue and select project, sprint, assignee, priority and estimation type.
5. Create one ticket with story points and another with estimated hours.
6. Click a ticket and verify the Jira-style detail screen.
7. Change status, priority, sprint and estimate as manager.
8. Add a comment.
9. Add a worklog and overtime entry.
10. Open Reports and AI Assistant.
11. Ask `How much overtime was spent in this sprint?` and `Find tickets about authentication`.
