import type {
  AssistantResponse,
  CompleteSprintResponse,
  DashboardData,
  MetaData,
  Sprint,
  Ticket,
  User,
} from './types';

const API_URL = import.meta.env.VITE_API_URL ?? 'https://jira-ai-ticket-management-backend.ddev.site/api';

type ApiErrorBody = {
  message?: string;
  errors?: Record<string, string[]>;
};

function token(): string | null {
  return localStorage.getItem('jira_token');
}

export function hasToken(): boolean {
  return Boolean(token());
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers ?? {});
  headers.set('Accept', 'application/json');

  if (options.body) {
    headers.set('Content-Type', 'application/json');
  }

  const authToken = token();
  if (authToken) {
    headers.set('Authorization', `Bearer ${authToken}`);
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({ message: response.statusText })) as ApiErrorBody;
    const validation = body.errors
      ? Object.values(body.errors).flat().join(' ')
      : '';

    throw new Error(validation || body.message || `Request failed with ${response.status}`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export const api = {
  async login(email: string, password: string) {
    const result = await request<{ token: string; user: User }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    localStorage.setItem('jira_token', result.token);
    return result.user;
  },

  async register(name: string, email: string, password: string) {
    const result = await request<{ token: string; user: User }>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        name,
        email,
        password,
        password_confirmation: password,
      }),
    });

    localStorage.setItem('jira_token', result.token);
    return result.user;
  },

  me: () => request<User>('/auth/me'),

  async logout() {
    try {
      await request('/auth/logout', { method: 'POST' });
    } finally {
      localStorage.removeItem('jira_token');
    }
  },

  meta: () => request<MetaData>('/meta'),
  dashboard: () => request<DashboardData>('/dashboard'),

  tickets: (params = new URLSearchParams()) =>
    request<Ticket[]>(`/tickets${params.size ? `?${params.toString()}` : ''}`),

  ticket: (id: number) => request<Ticket>(`/tickets/${id}`),

  createTicket: (payload: Record<string, unknown>) =>
    request<Ticket>('/tickets', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  updateTicket: (id: number, payload: Record<string, unknown>) =>
    request<Ticket>(`/tickets/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),

  deleteTicket: (id: number) =>
    request<{ message: string }>(`/tickets/${id}`, {
      method: 'DELETE',
    }),

  addComment: (ticketId: number, body: string) =>
    request(`/tickets/${ticketId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ body }),
    }),

  addWorklog: (ticketId: number, payload: Record<string, unknown>) =>
    request(`/tickets/${ticketId}/worklogs`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  sprints: () => request<Sprint[]>('/sprints'),

  createSprint: (payload: Record<string, unknown>) =>
    request<Sprint>('/sprints', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  updateSprint: (id: number, payload: Record<string, unknown>) =>
    request<Sprint>(`/sprints/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),

  startSprint: (id: number) =>
    request<Sprint>(`/sprints/${id}/start`, {
      method: 'POST',
    }),

  completeSprint: (id: number, nextSprintId?: number | null) =>
    request<CompleteSprintResponse>(`/sprints/${id}/complete`, {
      method: 'POST',
      body: JSON.stringify({
        next_sprint_id: nextSprintId ?? null,
      }),
    }),

  sprintSummary: (sprintId: number) =>
    request(`/sprints/${sprintId}/summary`),

  employeeReport: (userId: number, sprintId: number) =>
    request(`/reports/employees/${userId}?sprint_id=${sprintId}`),

  ask: (question: string, sprintId?: number) =>
    request<AssistantResponse>('/assistant/query', {
      method: 'POST',
      body: JSON.stringify({
        question,
        sprint_id: sprintId,
      }),
    }),
};
