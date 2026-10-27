import { FormEvent, useEffect, useMemo, useState } from 'react';
import { api } from '../api';
import type { EstimationType, MetaData, Ticket, TicketPriority, TicketStatus, TicketType, User } from '../types';
import { Icon } from './Icon';
import { PriorityPill, StatusPill } from './StatusPill';
import { TicketDetailPage } from './TicketDetailPage';

type Props = {
  tickets: Ticket[];
  meta: MetaData | null;
  user: User;
  onRefresh: () => Promise<void>;
  requestedTicketId?: number | null;
  onRequestedTicketHandled?: () => void;
};

type ListConfirmationState = {
  kind: 'status' | 'delete';
  title: string;
  message: string;
  oldValue?: string;
  newValue?: string;
  confirmLabel: string;
  action: () => Promise<void>;
} | null;

function issueSymbol(type: TicketType) {
  if (type === 'bug') return '●';
  if (type === 'story') return '◆';
  if (type === 'epic') return '★';
  return '■';
}

export function TicketsPage({ tickets, meta, user, onRefresh, requestedTicketId = null, onRequestedTicketHandled }: Props) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [sprintId, setSprintId] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [confirmation, setConfirmation] = useState<ListConfirmationState>(null);
  const [confirmationBusy, setConfirmationBusy] = useState(false);

  useEffect(() => {
    if (requestedTicketId) {
      setSelectedTicketId(requestedTicketId);
      onRequestedTicketHandled?.();
    }
  }, [requestedTicketId, onRequestedTicketHandled]);

  const filtered = useMemo(() => {
    const q = search.toLowerCase().trim();
    return tickets.filter((ticket) => {
      const matchesSearch = !q || `${ticket.ticket_number} ${ticket.title} ${ticket.description ?? ''}`.toLowerCase().includes(q);
      return matchesSearch
        && (!status || ticket.status === status)
        && (!priority || ticket.priority === priority)
        && (!sprintId || String(ticket.sprint_id ?? '') === sprintId);
    });
  }, [tickets, search, status, priority, sprintId]);

  async function updateStatus(ticket: Ticket, newStatus: TicketStatus) {
    setError('');

    try {
      await api.updateTicket(ticket.id, { status: newStatus });
      await onRefresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not update ticket.');
    }
  }

  async function remove(ticket: Ticket) {
    setError('');

    try {
      await api.deleteTicket(ticket.id);
      await onRefresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not delete ticket.');
    }
  }

  function requestStatusChange(ticket: Ticket, newStatus: TicketStatus) {
    if (ticket.status === newStatus) return;

    setConfirmation({
      kind: 'status',
      title: 'Change status?',
      message: `Review the status change for ${ticket.ticket_number} before it is saved.`,
      oldValue: ticket.status.replaceAll('_', ' '),
      newValue: newStatus.replaceAll('_', ' '),
      confirmLabel: 'Confirm change',
      action: () => updateStatus(ticket, newStatus),
    });
  }

  function requestDelete(ticket: Ticket) {
    setConfirmation({
      kind: 'delete',
      title: 'Delete issue?',
      message: `${ticket.ticket_number} — ${ticket.title} will be permanently deleted.`,
      oldValue: ticket.ticket_number,
      confirmLabel: 'Delete issue',
      action: () => remove(ticket),
    });
  }

  async function approveConfirmation() {
    if (!confirmation || confirmationBusy) return;

    const pending = confirmation;
    setConfirmationBusy(true);

    try {
      await pending.action();
      setConfirmation(null);
    } finally {
      setConfirmationBusy(false);
    }
  }

  function cancelConfirmation() {
    if (confirmationBusy) return;
    setConfirmation(null);
  }


  if (selectedTicketId) {
    return (
      <TicketDetailPage
        ticketId={selectedTicketId}
        meta={meta}
        user={user}
        onBack={() => setSelectedTicketId(null)}
        onChanged={onRefresh}
      />
    );
  }

  return (
    <>
      <section className="toolbar">
        <div className="search-box">
          <Icon name="search" />
          <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search issues by key, title or description…" />
        </div>
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All statuses</option>
          <option value="todo">To do</option>
          <option value="in_progress">In progress</option>
          <option value="review">In review</option>
          <option value="done">Done</option>
        </select>
        <select value={priority} onChange={(e) => setPriority(e.target.value)}>
          <option value="">All priorities</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
        <select value={sprintId} onChange={(e) => setSprintId(e.target.value)}>
          <option value="">All sprints</option>
          {meta?.sprints.map((sprint) => <option key={sprint.id} value={sprint.id}>{sprint.name}</option>)}
        </select>
        <button className="primary create-button" onClick={() => setShowCreate(true)}><Icon name="plus" /> Create issue</button>
      </section>

      {error && <div className="form-error page-error">{error}</div>}

      <section className="panel issues-panel">
        <div className="issues-header">
          <span>TYPE</span><span>KEY / SUMMARY</span><span>SPRINT</span><span>PRIORITY</span><span>ASSIGNEE</span><span>STATUS</span><span></span>
        </div>
        {filtered.map((ticket) => (
          <div className="issues-line" key={ticket.id} onClick={() => setSelectedTicketId(ticket.id)}>
            <div className={`issue-type ${ticket.type}`}>{issueSymbol(ticket.type)}</div>
            <div className="issue-summary">
              <span className="issue-key">{ticket.ticket_number}</span>
              <strong>{ticket.title}</strong>
              <small>{ticket.description}</small>
            </div>
            <span className="sprint-chip">{ticket.sprint?.name ?? 'Backlog'}</span>
            <PriorityPill priority={ticket.priority} />
            <div className="assignee">
              <div className="small-avatar">{ticket.assignee?.name?.slice(0, 1) ?? '?'}</div>
              <span>{ticket.assignee?.name ?? 'Unassigned'}</span>
            </div>
            <select
              className="status-select"
              value={ticket.status}
              onClick={(e) => e.stopPropagation()}
              onChange={(e) => requestStatusChange(ticket, e.target.value as TicketStatus)}
            >
              <option value="todo">To do</option>
              <option value="in_progress">In progress</option>
              <option value="review">In review</option>
              <option value="done">Done</option>
            </select>
            {user.role !== 'employee'
              ? <button className="icon-button danger" onClick={(e) => { e.stopPropagation(); requestDelete(ticket); }}>×</button>
              : <Icon name="chevronRight" className="row-chevron" />}
          </div>
        ))}
        {!filtered.length && <div className="empty-state issues-empty">No issues match these filters.</div>}
      </section>

      {confirmation && (
        <div
          className="confirmation-overlay"
          role="dialog"
          aria-modal="true"
          aria-labelledby="list-confirmation-title"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !confirmationBusy) {
              cancelConfirmation();
            }
          }}
        >
          <div className={`confirmation-modal ${confirmation.kind === 'delete' ? 'confirmation-modal-danger' : ''}`}>
            <div
              className={`confirmation-icon ${confirmation.kind === 'delete' ? 'confirmation-icon-danger' : ''}`}
              aria-hidden="true"
            >
              !
            </div>

            <div className="confirmation-content">
              <div className="confirmation-heading">
                <span className="eyebrow">
                  {confirmation.kind === 'delete' ? 'Confirm deletion' : 'Confirm update'}
                </span>

                <h3 id="list-confirmation-title">{confirmation.title}</h3>
                <p>{confirmation.message}</p>
              </div>

              {confirmation.kind === 'status' && (
                <div className="confirmation-change">
                  <div className="confirmation-value">
                    <span>From</span>
                    <strong>{confirmation.oldValue}</strong>
                  </div>

                  <div className="confirmation-arrow" aria-hidden="true">→</div>

                  <div className="confirmation-value">
                    <span>To</span>
                    <strong>{confirmation.newValue}</strong>
                  </div>
                </div>
              )}

              {confirmation.kind === 'delete' && (
                <div className="confirmation-delete-note">
                  This action cannot be undone.
                </div>
              )}

              <div className="confirmation-actions">
                <button
                  type="button"
                  className="secondary"
                  disabled={confirmationBusy}
                  onClick={cancelConfirmation}
                >
                  Cancel
                </button>

                <button
                  type="button"
                  className={confirmation.kind === 'delete' ? 'danger-action' : 'primary'}
                  disabled={confirmationBusy}
                  onClick={() => void approveConfirmation()}
                >
                  {confirmationBusy ? 'Saving...' : confirmation.confirmLabel}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {showCreate && meta && (
        <CreateIssueModal
          meta={meta}
          onClose={() => setShowCreate(false)}
          onCreated={async (ticket) => {
            setShowCreate(false);
            await onRefresh();
            setSelectedTicketId(ticket.id);
          }}
        />
      )}
    </>
  );
}

function CreateIssueModal({ meta, onClose, onCreated }: { meta: MetaData; onClose: () => void; onCreated: (ticket: Ticket) => Promise<void> }) {
  const defaultProject = meta.projects[0]?.id ?? '';
  const [projectId, setProjectId] = useState<number | ''>(defaultProject);
  const sprintsForProject = meta.sprints.filter((sprint) => !sprint.project_id || sprint.project_id === projectId);
  const defaultSprint = sprintsForProject.find((sprint) => sprint.status === 'active')?.id ?? '';
  const [sprintId, setSprintId] = useState<number | ''>(defaultSprint);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [assigneeId, setAssigneeId] = useState<number | ''>(meta.users.find((u) => u.role === 'employee')?.id ?? '');
  const [type, setType] = useState<TicketType>('task');
  const [priority, setPriority] = useState<TicketPriority>('medium');
  const [estimationType, setEstimationType] = useState<EstimationType>('story_points');
  const [storyPoints, setStoryPoints] = useState('3');
  const [estimatedHours, setEstimatedHours] = useState('4');
  const [error, setError] = useState('');

  function changeProject(value: string) {
    const id = Number(value);
    setProjectId(id);
    const matching = meta.sprints.filter((sprint) => !sprint.project_id || sprint.project_id === id);
    setSprintId(matching.find((sprint) => sprint.status === 'active')?.id ?? '');
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const ticket = await api.createTicket({
        project_id: projectId,
        sprint_id: sprintId || null,
        assignee_id: assigneeId || null,
        title,
        description,
        type,
        status: 'todo',
        priority,
        estimation_type: estimationType,
        story_points: estimationType === 'story_points' ? Number(storyPoints) || null : null,
        estimated_hours: estimationType === 'hours' ? Number(estimatedHours) || null : null,
      });
      await onCreated(ticket);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create issue.');
    }
  }

  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <form className="modal" onSubmit={submit} onMouseDown={(e) => e.stopPropagation()}>
        <div className="modal-heading">
          <div><span className="eyebrow">NEW ISSUE</span><h3>Create issue</h3></div>
          <button className="icon-button" type="button" onClick={onClose}>×</button>
        </div>

        <label>Summary<input value={title} onChange={(e) => setTitle(e.target.value)} required placeholder="What needs to be done?" /></label>
        <label>Description<textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={5} placeholder="Add details, acceptance criteria or context…" /></label>

        <div className="form-grid">
          <label>Project<select value={projectId} onChange={(e) => changeProject(e.target.value)}>{meta.projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label>
          <label>Sprint<select value={sprintId} onChange={(e) => setSprintId(e.target.value ? Number(e.target.value) : '')}><option value="">Backlog / no sprint</option>{sprintsForProject.map((sprint) => <option key={sprint.id} value={sprint.id}>{sprint.name} ({sprint.status})</option>)}</select></label>
          <label>
            Type
            <select value={type} onChange={(e) => setType(e.target.value as TicketType)}>
              <option value="task">Task</option>
              <option value="story">Story</option>
              <option value="bug">Bug</option>
              <option value="epic">Epic</option>
            </select>
          </label>
          <label>Priority<select value={priority} onChange={(e) => setPriority(e.target.value as TicketPriority)}><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select></label>
          <label>Assignee<select value={assigneeId} onChange={(e) => setAssigneeId(e.target.value ? Number(e.target.value) : '')}><option value="">Unassigned</option>{meta.users.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}</select></label>
          <label>Estimate with<select value={estimationType} onChange={(e) => setEstimationType(e.target.value as EstimationType)}><option value="story_points">Story points</option><option value="hours">Hours</option></select></label>
          {estimationType === 'story_points'
            ? <label>Story points<input type="number" min="1" max="100" value={storyPoints} onChange={(e) => setStoryPoints(e.target.value)} /></label>
            : <label>Estimated hours<input type="number" min="0.25" max="999" step="0.25" value={estimatedHours} onChange={(e) => setEstimatedHours(e.target.value)} /></label>}
        </div>

        {error && <div className="form-error">{error}</div>}
        <div className="modal-actions"><button type="button" className="secondary" onClick={onClose}>Cancel</button><button className="primary">Create issue</button></div>
      </form>
    </div>
  );
}
