import { FormEvent, useMemo, useState } from 'react';
import { api } from '../api';
import type { DashboardData, MetaData, Sprint, Ticket, User } from '../types';
import { Icon } from './Icon';
import { PriorityPill, StatusPill } from './StatusPill';

type Props = {
  dashboard: DashboardData | null;
  tickets: Ticket[];
  user: User;
  meta: MetaData | null;
  onRefresh: () => Promise<void>;
  onOpenTickets: () => void;
  onOpenTicket: (ticketId: number) => void;
  onOpenAssistant: () => void;
};

type SprintConfirmation = {
  kind: 'start' | 'complete';
  sprint: Sprint;
} | null;

function issueSymbol(type: Ticket['type']) {
  if (type === 'bug') return '●';
  if (type === 'story') return '◆';
  if (type === 'epic') return '★';
  return '■';
}

function formatDate(value?: string | null) {
  if (!value) return 'Not set';

  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString();
}

export function DashboardPage({
  dashboard,
  tickets,
  user,
  meta,
  onRefresh,
  onOpenTickets,
  onOpenTicket,
  onOpenAssistant,
}: Props) {
  const counts = dashboard?.ticket_counts ?? {};
  const analytics = dashboard?.analytics as unknown as Record<string, unknown> | null;
  const totalHours = Number(analytics?.total_hours ?? analytics?.hours ?? 0);
  const overtime = Number(analytics?.overtime_hours ?? 0);

  const [showCreateSprint, setShowCreateSprint] = useState(false);
  const [sprintConfirmation, setSprintConfirmation] = useState<SprintConfirmation>(null);
  const [sprintBusy, setSprintBusy] = useState(false);
  const [sprintError, setSprintError] = useState('');

  const canManageSprints = user.role === 'admin' || user.role === 'manager';

  const plannedSprint = useMemo(() => {
    const sprints = meta?.sprints ?? [];
    const activeProjectId = dashboard?.active_sprint?.project_id;

    return sprints.find((sprint) =>
      sprint.status === 'planned' &&
      (!activeProjectId || sprint.project_id === activeProjectId)
    ) ?? null;
  }, [dashboard?.active_sprint?.project_id, meta?.sprints]);

  async function approveSprintAction() {
    if (!sprintConfirmation || sprintBusy) return;

    setSprintBusy(true);
    setSprintError('');

    try {
      if (sprintConfirmation.kind === 'start') {
        await api.startSprint(sprintConfirmation.sprint.id);
      } else {
        await api.completeSprint(sprintConfirmation.sprint.id);
      }

      setSprintConfirmation(null);
      await onRefresh();
    } catch (err) {
      setSprintError(
        err instanceof Error
          ? err.message
          : 'Could not update sprint.'
      );
    } finally {
      setSprintBusy(false);
    }
  }

  return (
    <>
      <section className="sprint-banner">
        <div>
          <span className="eyebrow">ACTIVE SPRINT</span>
          <h2>{dashboard?.active_sprint?.name ?? 'No active sprint'}</h2>
          <p>
            {dashboard?.active_sprint?.goal ??
              'Track delivery, workload and project health.'}
          </p>

          {dashboard?.active_sprint && (
            <div className="sprint-date-line">
              {formatDate(dashboard.active_sprint.starts_at)}
              <span>→</span>
              {formatDate(dashboard.active_sprint.ends_at)}
            </div>
          )}
        </div>

        <div className="sprint-actions">
          <button className="secondary" onClick={onOpenAssistant}>
            <Icon name="sparkles" /> Ask AI
          </button>

          <button className="primary" onClick={onOpenTickets}>
            View issues
          </button>
        </div>
      </section>

      {canManageSprints && (
        <section className="panel sprint-management">
          <div className="sprint-management-copy">
            <span className="eyebrow">SPRINT MANAGEMENT</span>
            <h3>Plan, start and complete two-week sprints</h3>

            {plannedSprint ? (
              <p>
                Next planned sprint: <strong>{plannedSprint.name}</strong>
                {' · '}
                {formatDate(plannedSprint.starts_at)}
                {' → '}
                {formatDate(plannedSprint.ends_at)}
              </p>
            ) : (
              <p>No planned sprint yet. Create the next two-week sprint.</p>
            )}
          </div>

          <div className="sprint-management-actions">
            <button
              className="secondary"
              onClick={() => setShowCreateSprint(true)}
            >
              + Create sprint
            </button>

            {plannedSprint && !dashboard?.active_sprint && (
              <button
                className="primary"
                onClick={() =>
                  setSprintConfirmation({
                    kind: 'start',
                    sprint: plannedSprint,
                  })
                }
              >
                Start {plannedSprint.name}
              </button>
            )}

            {dashboard?.active_sprint && (
              <button
                className="primary"
                onClick={() =>
                  setSprintConfirmation({
                    kind: 'complete',
                    sprint: dashboard.active_sprint as Sprint,
                  })
                }
              >
                Complete {dashboard.active_sprint.name}
              </button>
            )}
          </div>
        </section>
      )}

      {sprintError && (
        <div className="form-error page-error">{sprintError}</div>
      )}

      <section className="stat-grid">
        <article className="stat-card">
          <span className="stat-icon blue"><Icon name="issues" /></span>
          <div>
            <small>Sprint issues</small>
            <strong>{counts.total ?? 0}</strong>
            <span>{counts.done ?? 0} completed</span>
          </div>
        </article>

        <article className="stat-card">
          <span className="stat-icon violet"><Icon name="clock" /></span>
          <div>
            <small>{user.role === 'employee' ? 'My logged hours' : 'Team hours'}</small>
            <strong>{totalHours}h</strong>
            <span>Current sprint</span>
          </div>
        </article>

        <article className="stat-card">
          <span className="stat-icon orange"><Icon name="trending" /></span>
          <div>
            <small>Overtime</small>
            <strong>{overtime}h</strong>
            <span>Current sprint</span>
          </div>
        </article>

        <article className="stat-card">
          <span className="stat-icon green"><Icon name="check" /></span>
          <div>
            <small>My open issues</small>
            <strong>{dashboard?.my_open_tickets ?? 0}</strong>
            <span>Assigned to you</span>
          </div>
        </article>
      </section>

      <section className="content-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <span className="eyebrow">RECENT WORK</span>
              <h3>Current sprint issues</h3>
            </div>

            <button className="link-button" onClick={onOpenTickets}>
              View all →
            </button>
          </div>

          <div className="issue-list">
            {tickets.slice(0, 5).map((ticket) => (
              <button
                className="issue-row"
                key={ticket.id}
                onClick={() => onOpenTicket(ticket.id)}
              >
                <div className={`issue-type ${ticket.type}`}>
                  {issueSymbol(ticket.type)}
                </div>

                <div className="issue-main">
                  <span className="issue-key">{ticket.ticket_number}</span>
                  <strong>{ticket.title}</strong>
                </div>

                <PriorityPill priority={ticket.priority} />
                <StatusPill status={ticket.status} />
                <div className="small-avatar">
                  {ticket.assignee?.name?.slice(0, 1) ?? '?'}
                </div>
              </button>
            ))}
          </div>
        </div>

        <div className="panel assistant-preview">
          <div className="spark"><Icon name="sparkles" /></div>
          <span className="eyebrow">PROJECT INTELLIGENCE</span>
          <h3>Ask or change your Jira data</h3>
          <p>
            Search tickets, check workload, create issues or epics,
            manage sprints and edit existing issues using natural language.
          </p>
          <div className="prompt-chip">“Create epic Customer onboarding”</div>
          <div className="prompt-chip">“Change AIP-103 status to done”</div>
          <div className="prompt-chip">“Complete Sprint 24”</div>
          <button className="primary full" onClick={onOpenAssistant}>
            Open AI Assistant
          </button>
        </div>
      </section>

      {showCreateSprint && meta && (
        <CreateSprintModal
          meta={meta}
          activeSprint={dashboard?.active_sprint ?? null}
          onClose={() => setShowCreateSprint(false)}
          onCreated={async () => {
            setShowCreateSprint(false);
            await onRefresh();
          }}
        />
      )}

      {sprintConfirmation && (
        <div
          className="confirmation-overlay"
          role="dialog"
          aria-modal="true"
          aria-labelledby="sprint-confirmation-title"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !sprintBusy) {
              setSprintConfirmation(null);
            }
          }}
        >
          <div className="confirmation-modal">
            <div className="confirmation-icon" aria-hidden="true">!</div>

            <div className="confirmation-content">
              <div className="confirmation-heading">
                <span className="eyebrow">
                  {sprintConfirmation.kind === 'complete'
                    ? 'COMPLETE SPRINT'
                    : 'START SPRINT'}
                </span>

                <h3 id="sprint-confirmation-title">
                  {sprintConfirmation.kind === 'complete'
                    ? `Complete ${sprintConfirmation.sprint.name}?`
                    : `Start ${sprintConfirmation.sprint.name}?`}
                </h3>

                <p>
                  {sprintConfirmation.kind === 'complete'
                    ? 'Done tickets stay in this sprint. Every unfinished ticket will move to the next planned sprint automatically.'
                    : `This will make ${sprintConfirmation.sprint.name} the active sprint.`}
                </p>
              </div>

              <div className="confirmation-change sprint-confirmation-dates">
                <div className="confirmation-value">
                  <span>Start</span>
                  <strong>{formatDate(sprintConfirmation.sprint.starts_at)}</strong>
                </div>

                <div className="confirmation-arrow" aria-hidden="true">→</div>

                <div className="confirmation-value">
                  <span>End</span>
                  <strong>{formatDate(sprintConfirmation.sprint.ends_at)}</strong>
                </div>
              </div>

              <div className="confirmation-actions">
                <button
                  type="button"
                  className="secondary"
                  disabled={sprintBusy}
                  onClick={() => setSprintConfirmation(null)}
                >
                  Cancel
                </button>

                <button
                  type="button"
                  className="primary"
                  disabled={sprintBusy}
                  onClick={() => void approveSprintAction()}
                >
                  {sprintBusy
                    ? 'Saving...'
                    : sprintConfirmation.kind === 'complete'
                      ? 'Complete sprint'
                      : 'Start sprint'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function CreateSprintModal({
  meta,
  activeSprint,
  onClose,
  onCreated,
}: {
  meta: MetaData;
  activeSprint: Sprint | null;
  onClose: () => void;
  onCreated: () => Promise<void>;
}) {
  const defaultProjectId =
    activeSprint?.project_id ??
    meta.projects[0]?.id ??
    0;

  const maxSprintNumber = meta.sprints.reduce((max, sprint) => {
    const match = sprint.name.match(/^Sprint\s+(\d+)$/i);
    return match ? Math.max(max, Number(match[1])) : max;
  }, 0);

  const [projectId, setProjectId] = useState(defaultProjectId);
  const [name, setName] = useState(`Sprint ${maxSprintNumber + 1}`);
  const [goal, setGoal] = useState('');
  const [startsAt, setStartsAt] = useState(() => todayString());
  const [endsAt, setEndsAt] = useState(() => addDays(todayString(), 13));
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  function changeStart(value: string) {
    setStartsAt(value);

    if (value) {
      setEndsAt(addDays(value, 13));
    }
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    if (!projectId) return;

    setBusy(true);
    setError('');

    try {
      await api.createSprint({
        project_id: projectId,
        name: name.trim() || null,
        goal: goal.trim() || null,
        starts_at: startsAt,
        ends_at: endsAt,
      });

      await onCreated();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Could not create sprint.'
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <form
        className="modal sprint-create-modal"
        onSubmit={submit}
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="modal-heading">
          <div>
            <span className="eyebrow">NEW SPRINT</span>
            <h3>Create two-week sprint</h3>
          </div>

          <button
            className="icon-button"
            type="button"
            onClick={onClose}
          >
            ×
          </button>
        </div>

        <label>
          Project
          <select
            value={projectId}
            onChange={(event) =>
              setProjectId(Number(event.target.value))
            }
          >
            {meta.projects.map((project) => (
              <option key={project.id} value={project.id}>
                {project.name}
              </option>
            ))}
          </select>
        </label>

        <label>
          Sprint name
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Sprint 26"
          />
        </label>

        <label>
          Goal
          <textarea
            rows={3}
            value={goal}
            onChange={(event) => setGoal(event.target.value)}
            placeholder="What should this sprint achieve?"
          />
        </label>

        <div className="form-grid">
          <label>
            Start date
            <input
              type="date"
              value={startsAt}
              onChange={(event) => changeStart(event.target.value)}
              required
            />
          </label>

          <label>
            End date
            <input
              type="date"
              value={endsAt}
              min={startsAt}
              onChange={(event) => setEndsAt(event.target.value)}
              required
            />
          </label>
        </div>

        <div className="sprint-duration-note">
          Default duration: 14 days. You can change the dates if needed.
        </div>

        {error && <div className="form-error">{error}</div>}

        <div className="modal-actions">
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={onClose}
          >
            Cancel
          </button>

          <button className="primary" disabled={busy}>
            {busy ? 'Creating...' : 'Create sprint'}
          </button>
        </div>
      </form>
    </div>
  );
}

function todayString() {
  const date = new Date();

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-');
}

function addDays(value: string, days: number) {
  const date = new Date(`${value}T12:00:00`);
  date.setDate(date.getDate() + days);

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-');
}
