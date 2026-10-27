import { useEffect, useState } from 'react';
import type { User } from '../types';
import { Icon } from './Icon';

type Props = {
  title: string;
  user: User;
  onLogout: () => void;
};

type Theme = 'light' | 'dark';

function initialTheme(): Theme {
  const stored = localStorage.getItem('jira_theme');
  if (stored === 'light' || stored === 'dark') return stored;
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function Topbar({ title, user, onLogout }: Props) {
  const [theme, setTheme] = useState<Theme>(initialTheme);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('jira_theme', theme);
  }, [theme]);

  return (
    <header className="topbar">
      <div className="topbar-title">
        <span className="breadcrumbs">Projects / AI Support Platform</span>
        <h1>{title}</h1>
      </div>
      <div className="topbar-actions">
        <button
          className="icon-button theme-toggle"
          type="button"
          onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
          aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} theme`}
          title={`Switch to ${theme === 'light' ? 'dark' : 'light'} theme`}
        >
          <Icon name={theme === 'light' ? 'moon' : 'sun'} />
        </button>
        <span className={`role-pill ${user.role}`}>{user.role}</span>
        <button className="ghost logout-button" onClick={onLogout}>Log out</button>
      </div>
    </header>
  );
}
