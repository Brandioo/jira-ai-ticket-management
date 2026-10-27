import { useCallback, useEffect, useState } from 'react';
import { api, hasToken } from './api';
import { AnalyticsPage } from './components/AnalyticsPage';
import { AssistantPage } from './components/AssistantPage';
import { AuthPage } from './components/AuthPage';
import { DashboardPage } from './components/DashboardPage';
import { Sidebar, type Page } from './components/Sidebar';
import { TicketsPage } from './components/TicketsPage';
import { Topbar } from './components/Topbar';
import type { DashboardData, MetaData, Ticket, User } from './types';
import './styles.css';

const titles: Record<Page, string> = {
  dashboard: 'Overview',
  tickets: 'Issues',
  analytics: 'Reports',
  assistant: 'AI Assistant',
};

export default function App() {
  const [user, setUser] = useState<User | null>(null);
  const [page, setPage] = useState<Page>('dashboard');
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [meta, setMeta] = useState<MetaData | null>(null);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [requestedTicketId, setRequestedTicketId] = useState<number | null>(null);
  const [booting, setBooting] = useState(true);
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    if (!user) return;
    setError('');
    try {
      const [dashboardData, metaData, ticketData] = await Promise.all([
        api.dashboard(),
        api.meta(),
        api.tickets(),
      ]);
      setDashboard(dashboardData);
      setMeta(metaData);
      setTickets(ticketData);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load project data.');
    }
  }, [user]);

  useEffect(() => {
    async function bootstrap() {
      if (!hasToken()) {
        setBooting(false);
        return;
      }

      try {
        setUser(await api.me());
      } catch {
        localStorage.removeItem('jira_token');
      } finally {
        setBooting(false);
      }
    }
    bootstrap();
  }, []);

  useEffect(() => {
    if (user) refresh();
  }, [user, refresh]);

  function navigate(nextPage: Page) {
    setPage(nextPage);
    if (nextPage !== 'tickets') setRequestedTicketId(null);
  }

  function openTicket(ticketId: number) {
    setRequestedTicketId(ticketId);
    setPage('tickets');
  }

  async function logout() {
    await api.logout();
    setUser(null);
    setDashboard(null);
    setTickets([]);
    setRequestedTicketId(null);
    setPage('dashboard');
  }

  if (booting) {
    return <div className="boot-screen"><div className="brand-mark big">JA</div><span>Loading workspace…</span></div>;
  }

  if (!user) {
    return <AuthPage onAuthenticated={setUser} />;
  }

  return (
    <div className="app-shell">
      <Sidebar page={page} user={user} onNavigate={navigate} />
      <main className="workspace">
        <Topbar title={titles[page]} user={user} onLogout={logout} />
        <div className="page-content">
          {error && <div className="form-error page-error">{error}</div>}

          {page === 'dashboard' && (
            <DashboardPage
              dashboard={dashboard}
              tickets={tickets}
              user={user}
              meta={meta}
              onRefresh={refresh}
              onOpenTickets={() => navigate('tickets')}
              onOpenTicket={openTicket}
              onOpenAssistant={() => navigate('assistant')}
            />
          )}

          {page === 'tickets' && (
            <TicketsPage
              tickets={tickets}
              meta={meta}
              user={user}
              onRefresh={refresh}
              requestedTicketId={requestedTicketId}
              onRequestedTicketHandled={() => setRequestedTicketId(null)}
            />
          )}

          {page === 'analytics' && (
            <AnalyticsPage dashboard={dashboard} user={user} />
          )}

          {page === 'assistant' && (
            <AssistantPage
              dashboard={dashboard}
              onChanged={refresh}
            />
          )}
        </div>
      </main>
    </div>
  );
}
