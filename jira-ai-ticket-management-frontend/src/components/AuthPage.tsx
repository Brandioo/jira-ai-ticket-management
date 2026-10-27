import { FormEvent, useState } from 'react';
import { api } from '../api';
import type { User } from '../types';

type Props = {
  onAuthenticated: (user: User) => void;
};

export function AuthPage({ onAuthenticated }: Props) {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('manager@example.com');
  const [password, setPassword] = useState('password');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');

    try {
      const user = mode === 'login'
        ? await api.login(email, password)
        : await api.register(name, email, password);
      onAuthenticated(user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Authentication failed.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="auth-shell">
      <section className="auth-brand">
        <div className="auth-logo">JA</div>
        <div>
          <span className="eyebrow light">Education project</span>
          <h1>Plan work.<br />Track time.<br /><span>Ask your project.</span></h1>
          <p>A Jira-inspired workspace built with Laravel, PHP/PHPUnit, React, TypeScript, Postman and AI-assisted reporting.</p>
        </div>
        <div className="auth-features">
          <span>✓ Role-based access</span>
          <span>✓ Sprint & overtime analytics</span>
          <span>✓ Natural-language ticket search</span>
        </div>
      </section>

      <section className="auth-card-wrap">
        <form className="auth-card" onSubmit={submit}>
          <div className="auth-heading">
            <div className="mini-logo">JA</div>
            <h2>{mode === 'login' ? 'Welcome back' : 'Create your account'}</h2>
            <p>{mode === 'login' ? 'Sign in to your project workspace.' : 'New accounts are created with the Employee role.'}</p>
          </div>

          <div className="auth-tabs">
            <button type="button" className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Log in</button>
            <button type="button" className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Sign up</button>
          </div>

          {mode === 'register' && (
            <label>
              Full name
              <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Your name" autoComplete="name" required />
            </label>
          )}

          <label>
            Email
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required />
          </label>

          <label>
            Password

            <div className="password-input-wrapper">
              <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                  minLength={8}
                  required
              />

              <button
                  type="button"
                  className="password-toggle"
                  onClick={() => setShowPassword((prev) => !prev)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  title={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? (
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                      <path d="m3 3 18 18" />
                      <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                      <path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c5.5 0 9.5 5 10 8a11.8 11.8 0 0 1-2.1 3.8" />
                      <path d="M6.6 6.6C4.3 8 2.5 10.3 2 12c.8 3 4.5 8 10 8 1.5 0 2.9-.4 4.1-1" />
                    </svg>
                ) : (
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                )}
              </button>
            </div>
          </label>

          {error && <div className="form-error">{error}</div>}

          <button className="primary full" disabled={busy}>
            {busy ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Create account'}
          </button>

          <div className="demo-logins">
            <strong>Seeded accounts</strong>
            <span>manager@example.com / password</span>
            <span>alice@example.com / password</span>
          </div>
        </form>
      </section>
    </main>
  );
}
