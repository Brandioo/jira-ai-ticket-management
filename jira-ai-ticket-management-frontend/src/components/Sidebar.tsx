import type { User } from '../types';
import { Icon, type IconName } from './Icon';

export type Page = 'dashboard' | 'tickets' | 'analytics' | 'assistant';

type Props = {
  page: Page;
  user: User;
  onNavigate: (page: Page) => void;
};

const items: Array<{ page: Page; icon: IconName; label: string }> = [
  { page: 'dashboard', icon: 'home', label: 'Overview' },
  { page: 'tickets', icon: 'issues', label: 'Issues' },
  { page: 'analytics', icon: 'reports', label: 'Reports' },
  { page: 'assistant', icon: 'sparkles', label: 'AI Assistant' },
];

export function Sidebar({ page, user, onNavigate }: Props) {
  return (
    <aside className="sidebar">
      <div className="brand">
        <div className="brand-mark">JA</div>
        <div className="brand-copy">
          <strong>Jira AI</strong>
          <span>Work Management</span>
        </div>
      </div>

      <nav>
        <div className="nav-caption">WORKSPACE</div>
        {items.map((item) => (
          <button
            key={item.page}
            className={page === item.page ? 'active' : ''}
            onClick={() => onNavigate(item.page)}
            title={item.label}
          >
            <span className="nav-icon"><Icon name={item.icon} /></span>
            <span className="nav-label">{item.label}</span>
          </button>
        ))}
      </nav>

      <div className="sidebar-project">
        <div className="project-avatar">AI</div>
        <div>
          <strong>AI Support Platform</strong>
          <span>Sprint 24</span>
        </div>
      </div>

      <div className="sidebar-user">
        <div className="avatar">{user.name.split(' ').map((part) => part[0]).join('').slice(0, 2)}</div>
        <div>
          <strong>{user.name}</strong>
          <span>{user.role}</span>
        </div>
      </div>
    </aside>
  );
}
