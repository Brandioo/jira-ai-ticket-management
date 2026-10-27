import {FormEvent, useEffect, useMemo, useState} from 'react';
import {api} from '../api';
import type {
    EstimationType,
    MetaData,
    Sprint,
    Ticket,
    TicketPriority,
    TicketStatus,
    TicketType,
    User
} from '../types';
import {Icon} from './Icon';
import {PriorityPill, StatusPill} from './StatusPill';

type Props = {
    ticketId: number;
    meta: MetaData | null;
    user: User;
    onBack: () => void;
    onChanged: () => Promise<void>;
};

type ActivityTab = 'comments' | 'history' | 'worklogs';

type ConfirmationState = {
    label: string;
    oldValue: string | number | null | undefined;
    newValue: string | number | null | undefined;
    payload: Record<string, unknown>;
    resolve: (value: boolean) => void;
} | null;

function formatDate(value?: string | null) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString();
}

function displayValue(value?: string | number | null) {
    if (value === null || value === undefined || value === '') {
        return 'None';
    }

    return String(value).replaceAll('_', ' ');
}

export function TicketDetailPage({
                                     ticketId,
                                     meta,
                                     user,
                                     onBack,
                                     onChanged
                                 }: Props) {
    const [ticket, setTicket] = useState<Ticket | null>(null);
    const [tab, setTab] = useState<ActivityTab>('comments');
    const [comment, setComment] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(true);
    const [confirmation, setConfirmation] =
        useState<ConfirmationState>(null);

    const [workDate, setWorkDate] = useState(
        new Date().toISOString().slice(0, 10)
    );

    const [hours, setHours] = useState('');
    const [overtimeHours, setOvertimeHours] = useState('0');
    const [workNote, setWorkNote] = useState('');

    const canEditDetails =
        user.role === 'admin' ||
        user.role === 'manager';

    const canUpdateTicket =
        canEditDetails ||
        user.id === ticket?.assignee_id ||
        user.id === ticket?.reporter_id;

    const canLogWork =
        canEditDetails ||
        user.id === ticket?.assignee_id;

    async function load() {
        setLoading(true);
        setError('');

        try {
            const response = await api.ticket(ticketId);
            setTicket(response);
        } catch (err) {
            setTicket(null);
            setError(
                err instanceof Error
                    ? err.message
                    : 'Could not load issue.'
            );
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void load();
    }, [ticketId]);

    const users = meta?.users ?? [];

    const projectSprints = useMemo<Sprint[]>(() => {
        if (!ticket) {
            return [];
        }

        const sprints: Sprint[] = meta?.sprints ?? [];

        return sprints.filter(
            (sprint: Sprint) =>
                !sprint.project_id ||
                sprint.project_id === ticket.project_id
        );
    }, [meta, ticket]);

    async function update(
        payload: Record<string, unknown>
    ): Promise<boolean> {
        if (!ticket) {
            return false;
        }

        setBusy(true);
        setError('');

        try {
            const response = await api.updateTicket(
                ticket.id,
                payload
            );

            setTicket(response);

            await onChanged();

            return true;
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Could not update issue.'
            );

            return false;
        } finally {
            setBusy(false);
        }
    }

    function confirmUpdate(
        label: string,
        oldValue: string | number | null | undefined,
        newValue: string | number | null | undefined,
        payload: Record<string, unknown>,
    ): Promise<boolean> {
        if (
            String(oldValue ?? '') ===
            String(newValue ?? '')
        ) {
            return Promise.resolve(false);
        }

        return new Promise<boolean>((resolve) => {
            setConfirmation({
                label,
                oldValue,
                newValue,
                payload,
                resolve,
            });
        });
    }

    async function approveConfirmation() {
        if (!confirmation || busy) {
            return;
        }

        const pendingConfirmation = confirmation;

        const updated = await update(
            pendingConfirmation.payload
        );

        pendingConfirmation.resolve(updated);
        setConfirmation(null);
    }

    function cancelConfirmation() {
        if (!confirmation || busy) {
            return;
        }

        confirmation.resolve(false);
        setConfirmation(null);
    }

    async function addComment(event: FormEvent) {
        event.preventDefault();

        if (!ticket || !comment.trim()) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            await api.addComment(
                ticket.id,
                comment.trim()
            );

            setComment('');

            await load();
            await onChanged();
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Could not add comment.'
            );
        } finally {
            setBusy(false);
        }
    }

    async function addWorklog(event: FormEvent) {
        event.preventDefault();

        if (!ticket || !hours) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            await api.addWorklog(ticket.id, {
                work_date: workDate,
                hours: Number(hours),
                overtime_hours: Number(
                    overtimeHours || 0
                ),
                note: workNote || null,
            });

            setHours('');
            setOvertimeHours('0');
            setWorkNote('');

            await load();
            await onChanged();
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Could not log work.'
            );
        } finally {
            setBusy(false);
        }
    }

    if (!ticket) {
        return (
            <section className="ticket-load-state">
                <button
                    className="link-button back-link ticket-load-back"
                    onClick={onBack}
                >
                    <Icon name="arrowLeft"/>
                    {' '}
                    Back to issues
                </button>

                <div className="panel ticket-load-card">
                    {loading ? (
                        <>
                            <div className="ticket-loader" aria-hidden="true"/>
                            <h2>Loading issue</h2>
                            <p>Please wait while we load the latest issue details.</p>
                        </>
                    ) : (
                        <>
                            <div className="ticket-load-error-icon" aria-hidden="true">!</div>
                            <h2>Unable to load issue</h2>
                            <p>
                                Check your internet connection or make sure the backend service is running,
                                then try again.
                            </p>

                            {error && (
                                <div className="ticket-load-error-message">
                                    {error}
                                </div>
                            )}

                            <div className="ticket-load-actions">
                                <button
                                    type="button"
                                    className="secondary"
                                    onClick={onBack}
                                >
                                    Back to issues
                                </button>

                                <button
                                    type="button"
                                    className="primary"
                                    onClick={() => void load()}
                                >
                                    Try again
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </section>
        );
    }

    /*
     * IMPORTANT:
     * Create a non-null constant after the null check.
     *
     * TypeScript/PhpStorm can otherwise lose the
     * ticket !== null narrowing inside callbacks.
     */
    const currentTicket = ticket;

    const currentStatus: TicketStatus =
        currentTicket.status ?? 'todo';

    const currentPriority: TicketPriority =
        currentTicket.priority ?? 'medium';

    const currentEstimationType: EstimationType =
        currentTicket.estimation_type ?? 'story_points';

    const currentEstimatedHours =
        currentTicket.estimated_hours ?? null;

    const currentStoryPoints =
        currentTicket.story_points ?? null;

    const estimate =
        currentEstimationType === 'hours'
            ? `${currentEstimatedHours ?? 0}h`
            : `${currentStoryPoints ?? 0} story points`;

    return (
        <section className="ticket-detail-page">

            <div className="ticket-detail-topline">

                <button
                    className="link-button back-link"
                    onClick={onBack}
                >
                    <Icon name="arrowLeft"/>
                    {' '}
                    Back to issues
                </button>

                <span>
                    {currentTicket.project?.name ?? 'Project'}
                    {' / '}
                    {currentTicket.ticket_number}
                </span>

            </div>

            {error && (
                <div className="form-error page-error">
                    {error}
                </div>
            )}

            <div className="ticket-detail-layout">

                {/* ============================= */}
                {/* MAIN TICKET CONTENT           */}
                {/* ============================= */}

                <article className="ticket-main panel">

                    <div className="ticket-heading">

                        <div
                            className={`issue-type ${currentTicket.type}`}
                        >
                            {currentTicket.type === 'bug'
                                ? '●'
                                : currentTicket.type === 'story'
                                    ? '◆'
                                    : currentTicket.type === 'epic'
                                        ? '★'
                                        : '■'}
                        </div>

                        <div>
                            <span className="issue-key">
                                {currentTicket.ticket_number}
                            </span>

                            <h2>
                                {currentTicket.title}
                            </h2>
                        </div>

                    </div>

                    {/* ============================= */}
                    {/* STATUS                        */}
                    {/* ============================= */}

                    <div className="ticket-action-row">

                        <select
                            className="status-select detail-status"
                            value={currentStatus}
                            disabled={
                                busy ||
                                !canUpdateTicket
                            }
                            onChange={(event) => {
                                const newStatus =
                                    event.target.value as TicketStatus;

                                void confirmUpdate(
                                    'status',
                                    currentStatus,
                                    newStatus,
                                    {
                                        status: newStatus
                                    }
                                );
                            }}
                        >
                            <option value="todo">
                                To do
                            </option>

                            <option value="in_progress">
                                In progress
                            </option>

                            <option value="review">
                                In review
                            </option>

                            <option value="done">
                                Done
                            </option>
                        </select>

                        <StatusPill
                            status={currentStatus}
                        />

                        <PriorityPill
                            priority={currentPriority}
                        />

                    </div>

                    {/* ============================= */}
                    {/* DESCRIPTION                   */}
                    {/* ============================= */}

                    <section className="detail-section">

                        <h3>
                            Description
                        </h3>

                        <p className="ticket-description">
                            {currentTicket.description ||
                                'No description added.'}
                        </p>

                    </section>

                    {/* ============================= */}
                    {/* ACTIVITY                      */}
                    {/* ============================= */}

                    <section className="detail-section activity-section">

                        <h3>
                            Activity
                        </h3>

                        <div className="activity-tabs">

                            <button
                                className={
                                    tab === 'comments'
                                        ? 'active'
                                        : ''
                                }
                                onClick={() =>
                                    setTab('comments')
                                }
                            >
                                <Icon name="message"/>

                                {' '}
                                Comments
                                {' '}

                                <span>
                                    {currentTicket.comments?.length ?? 0}
                                </span>
                            </button>

                            <button
                                className={
                                    tab === 'history'
                                        ? 'active'
                                        : ''
                                }
                                onClick={() =>
                                    setTab('history')
                                }
                            >
                                <Icon name="history"/>

                                {' '}
                                History
                                {' '}

                                <span>
                                    {currentTicket.histories?.length ?? 0}
                                </span>
                            </button>

                            <button
                                className={
                                    tab === 'worklogs'
                                        ? 'active'
                                        : ''
                                }
                                onClick={() =>
                                    setTab('worklogs')
                                }
                            >
                                <Icon name="worklog"/>

                                {' '}
                                Worklogs
                                {' '}

                                <span>
                                    {currentTicket.worklogs?.length ?? 0}
                                </span>
                            </button>

                        </div>

                        {/* ========================= */}
                        {/* COMMENTS                  */}
                        {/* ========================= */}

                        {tab === 'comments' && (

                            <div className="activity-list">

                                <form
                                    className="comment-form"
                                    onSubmit={addComment}
                                >

                                    <div className="small-avatar">
                                        {user.name.slice(0, 1)}
                                    </div>

                                    <div className="comment-box">

                                        <textarea
                                            rows={3}
                                            value={comment}
                                            onChange={(event) =>
                                                setComment(
                                                    event.target.value
                                                )
                                            }
                                            placeholder="Add a comment…"
                                        />

                                        <div>
                                            <button
                                                className="primary"
                                                disabled={
                                                    busy ||
                                                    !comment.trim()
                                                }
                                            >
                                                Comment
                                            </button>
                                        </div>

                                    </div>

                                </form>

                                {(currentTicket.comments ?? []).map(
                                    (item) => (

                                        <div
                                            className="activity-item"
                                            key={item.id}
                                        >

                                            <div className="small-avatar">
                                                {item.user?.name?.slice(
                                                    0,
                                                    1
                                                ) ?? '?'}
                                            </div>

                                            <div>

                                                <div className="activity-meta">

                                                    <strong>
                                                        {item.user?.name ??
                                                            'User'}
                                                    </strong>

                                                    <span>
                                                        {formatDate(
                                                            item.created_at
                                                        )}
                                                    </span>

                                                </div>

                                                <p>
                                                    {item.body}
                                                </p>

                                            </div>

                                        </div>

                                    )
                                )}

                                {!currentTicket.comments?.length && (
                                    <div className="empty-state">
                                        No comments yet.
                                    </div>
                                )}

                            </div>

                        )}

                        {/* ========================= */}
                        {/* HISTORY                   */}
                        {/* ========================= */}

                        {tab === 'history' && (

                            <div className="activity-list">

                                {(currentTicket.histories ?? []).map(
                                    (item) => (

                                        <div
                                            className="activity-item"
                                            key={item.id}
                                        >

                                            <div className="history-dot"/>

                                            <div>

                                                <div className="activity-meta">

                                                    <strong>
                                                        {item.user?.name ??
                                                            'User'}
                                                    </strong>

                                                    <span>
                                                        {formatDate(
                                                            item.created_at
                                                        )}
                                                    </span>

                                                </div>

                                                <p>
                                                    Changed{' '}

                                                    <strong>
                                                        {item.field.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </strong>

                                                    {' '}from{' '}

                                                    <code>
                                                        {displayValue(
                                                            item.old_value
                                                        )}
                                                    </code>

                                                    {' '}to{' '}

                                                    <code>
                                                        {displayValue(
                                                            item.new_value
                                                        )}
                                                    </code>.
                                                </p>

                                            </div>

                                        </div>

                                    )
                                )}

                                {!currentTicket.histories?.length && (

                                    <div className="empty-state">
                                        No changes recorded yet.
                                    </div>

                                )}

                            </div>

                        )}

                        {/* ========================= */}
                        {/* WORKLOGS                  */}
                        {/* ========================= */}

                        {tab === 'worklogs' && (

                            <div className="activity-list">

                                {canLogWork ? (

                                    <form
                                        className="worklog-form"
                                        onSubmit={addWorklog}
                                    >

                                        <div className="worklog-grid">

                                            <label>
                                                Date

                                                <input
                                                    type="date"
                                                    value={workDate}
                                                    onChange={(event) =>
                                                        setWorkDate(
                                                            event.target.value
                                                        )
                                                    }
                                                    required
                                                />
                                            </label>

                                            <label>
                                                Hours

                                                <input
                                                    type="number"
                                                    min="0.25"
                                                    step="0.25"
                                                    value={hours}
                                                    onChange={(event) =>
                                                        setHours(
                                                            event.target.value
                                                        )
                                                    }
                                                    required
                                                />
                                            </label>

                                            <label>
                                                Overtime

                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.25"
                                                    value={overtimeHours}
                                                    onChange={(event) =>
                                                        setOvertimeHours(
                                                            event.target.value
                                                        )
                                                    }
                                                />
                                            </label>

                                        </div>

                                        <label>
                                            Work note

                                            <input
                                                value={workNote}
                                                onChange={(event) =>
                                                    setWorkNote(
                                                        event.target.value
                                                    )
                                                }
                                                placeholder="What did you work on?"
                                            />
                                        </label>

                                        <button
                                            className="primary"
                                            disabled={busy}
                                        >
                                            Log work
                                        </button>

                                    </form>

                                ) : (

                                    <div className="permission-note">
                                        Only the assignee, manager or
                                        admin can log work on this issue.
                                    </div>

                                )}

                                {(currentTicket.worklogs ?? []).map(
                                    (item) => (

                                        <div
                                            className="activity-item"
                                            key={item.id}
                                        >

                                            <div className="small-avatar">
                                                {item.user?.name?.slice(
                                                    0,
                                                    1
                                                ) ?? '?'}
                                            </div>

                                            <div>

                                                <div className="activity-meta">

                                                    <strong>
                                                        {item.user?.name ??
                                                            'User'}
                                                    </strong>

                                                    <span>
                                                        {item.work_date}
                                                    </span>

                                                </div>

                                                <p>
                                                    <strong>
                                                        {item.hours}h
                                                    </strong>

                                                    {item.overtime_hours > 0
                                                        ? ` · ${item.overtime_hours}h overtime`
                                                        : ''}

                                                    {item.note
                                                        ? ` · ${item.note}`
                                                        : ''}
                                                </p>

                                            </div>

                                        </div>

                                    )
                                )}

                                {!currentTicket.worklogs?.length && (

                                    <div className="empty-state">
                                        No worklogs yet.
                                    </div>

                                )}

                            </div>

                        )}

                    </section>

                </article>

                {/* ============================= */}
                {/* RIGHT DETAILS SIDEBAR         */}
                {/* ============================= */}

                <aside className="ticket-details panel">

                    <div className="detail-card-heading">

                        <h3>
                            Details
                        </h3>

                        <span>
                            {currentTicket.ticket_number}
                        </span>

                    </div>

                    {/* ========================= */}
                    {/* ASSIGNEE                  */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Assignee
                        </span>

                        {canEditDetails ? (

                            <select
                                value={
                                    currentTicket.assignee_id ??
                                    ''
                                }
                                disabled={busy}
                                onChange={(event) => {

                                    const newId =
                                        event.target.value
                                            ? Number(
                                                event.target.value
                                            )
                                            : null;

                                    const oldName =
                                        users.find(
                                            (candidate) =>
                                                candidate.id ===
                                                currentTicket.assignee_id
                                        )?.name ??
                                        currentTicket.assignee?.name ??
                                        'Unassigned';

                                    const newName =
                                        users.find(
                                            (candidate) =>
                                                candidate.id ===
                                                newId
                                        )?.name ??
                                        'Unassigned';

                                    void confirmUpdate(
                                        'assignee',
                                        oldName,
                                        newName,
                                        {
                                            assignee_id: newId
                                        }
                                    );
                                }}
                            >

                                <option value="">
                                    Unassigned
                                </option>

                                {users.map(
                                    (candidate) => (

                                        <option
                                            key={candidate.id}
                                            value={candidate.id}
                                        >
                                            {candidate.name}
                                        </option>

                                    )
                                )}

                            </select>

                        ) : (

                            <strong>
                                {currentTicket.assignee?.name ??
                                    'Unassigned'}
                            </strong>

                        )}

                    </div>

                    {/* ========================= */}
                    {/* REPORTER                  */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Reporter
                        </span>

                        <strong>
                            {currentTicket.reporter?.name ?? '—'}
                        </strong>

                    </div>

                    {/* ========================= */}
                    {/* PRIORITY                  */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Priority
                        </span>

                        {canEditDetails ? (

                            <select
                                value={currentPriority}
                                disabled={busy}
                                onChange={(event) => {

                                    const newPriority =
                                        event.target
                                            .value as TicketPriority;

                                    void confirmUpdate(
                                        'priority',
                                        currentPriority,
                                        newPriority,
                                        {
                                            priority: newPriority
                                        }
                                    );
                                }}
                            >

                                <option value="low">
                                    Low
                                </option>

                                <option value="medium">
                                    Medium
                                </option>

                                <option value="high">
                                    High
                                </option>

                                <option value="critical">
                                    Critical
                                </option>

                            </select>

                        ) : (

                            <PriorityPill
                                priority={currentPriority}
                            />

                        )}

                    </div>

                    {/* ========================= */}
                    {/* SPRINT                    */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Sprint
                        </span>

                        {canEditDetails ? (

                            <select
                                value={
                                    currentTicket.sprint_id ??
                                    ''
                                }
                                disabled={busy}
                                onChange={(event) => {

                                    const newId =
                                        event.target.value
                                            ? Number(
                                                event.target.value
                                            )
                                            : null;

                                    const oldSprint =
                                        projectSprints.find(
                                            (sprint) =>
                                                sprint.id ===
                                                currentTicket.sprint_id
                                        )?.name ??
                                        currentTicket.sprint?.name ??
                                        'Backlog / no sprint';

                                    const newSprint =
                                        projectSprints.find(
                                            (sprint) =>
                                                sprint.id ===
                                                newId
                                        )?.name ??
                                        'Backlog / no sprint';

                                    void confirmUpdate(
                                        'sprint',
                                        oldSprint,
                                        newSprint,
                                        {
                                            sprint_id: newId
                                        }
                                    );
                                }}
                            >

                                <option value="">
                                    Backlog / no sprint
                                </option>

                                {projectSprints.map(
                                    (sprint) => (

                                        <option
                                            key={sprint.id}
                                            value={sprint.id}
                                        >
                                            {sprint.name}
                                            {' ('}
                                            {sprint.status}
                                            {')'}
                                        </option>

                                    )
                                )}

                            </select>

                        ) : (

                            <strong>
                                {currentTicket.sprint?.name ??
                                    'Backlog'}
                            </strong>

                        )}

                    </div>

                    {/* ========================= */}
                    {/* ISSUE TYPE                */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Issue type
                        </span>

                        {canEditDetails ? (

                            <select
                                value={currentTicket.type}
                                disabled={busy}
                                onChange={(event) => {
                                    const newType =
                                        event.target.value as TicketType;

                                    void confirmUpdate(
                                        'issue type',
                                        currentTicket.type,
                                        newType,
                                        {
                                            type: newType
                                        }
                                    );
                                }}
                            >
                                <option value="task">Task</option>
                                <option value="story">Story</option>
                                <option value="bug">Bug</option>
                                <option value="epic">Epic</option>
                            </select>

                        ) : (

                            <strong className="capitalize">
                                {currentTicket.type}
                            </strong>

                        )}

                    </div>

                    {/* ========================= */}
                    {/* ESTIMATION                */}
                    {/* ========================= */}

                    <div className="detail-field estimate-field">

                        <span>
                            Estimate
                        </span>

                        {canEditDetails ? (

                            <div className="estimate-editor">

                                {/* ESTIMATION TYPE */}

                                <select
                                    value={currentEstimationType}
                                    disabled={busy}
                                    onChange={(event) => {

                                        const newType =
                                            event.target
                                                .value as EstimationType;

                                        const oldLabel =
                                            currentEstimationType ===
                                            'hours'
                                                ? 'Hours'
                                                : 'Story points';

                                        const newLabel =
                                            newType === 'hours'
                                                ? 'Hours'
                                                : 'Story points';

                                        const payload =
                                            newType === 'hours'
                                                ? {
                                                    estimation_type:
                                                        'hours',
                                                    estimated_hours:
                                                        currentEstimatedHours ??
                                                        1
                                                }
                                                : {
                                                    estimation_type:
                                                        'story_points',
                                                    story_points:
                                                        currentStoryPoints ??
                                                        1
                                                };

                                        void confirmUpdate(
                                            'estimation type',
                                            oldLabel,
                                            newLabel,
                                            payload
                                        );
                                    }}
                                >

                                    <option value="story_points">
                                        Story points
                                    </option>

                                    <option value="hours">
                                        Hours
                                    </option>

                                </select>

                                {/* HOURS */}

                                {currentEstimationType ===
                                'hours' ? (

                                    <input
                                        key={
                                            `hours-${currentTicket.id}-${currentEstimatedHours ?? 'none'}`
                                        }
                                        aria-label="Estimated hours"
                                        type="number"
                                        min="0.25"
                                        step="0.25"
                                        disabled={busy}
                                        defaultValue={
                                            currentEstimatedHours ??
                                            ''
                                        }
                                        onBlur={async (event) => {

                                            const value =
                                                Number(
                                                    event.target.value
                                                );

                                            if (value <= 0) {
                                                event.currentTarget.value =
                                                    currentEstimatedHours !==
                                                    null
                                                        ? String(
                                                            currentEstimatedHours
                                                        )
                                                        : '';

                                                return;
                                            }

                                            const changed =
                                                await confirmUpdate(
                                                    'estimated hours',
                                                    currentEstimatedHours,
                                                    value,
                                                    {
                                                        estimation_type:
                                                            'hours',
                                                        estimated_hours:
                                                        value
                                                    }
                                                );

                                            if (!changed) {
                                                event.currentTarget.value =
                                                    currentEstimatedHours !==
                                                    null
                                                        ? String(
                                                            currentEstimatedHours
                                                        )
                                                        : '';
                                            }
                                        }}
                                    />

                                ) : (

                                    /* STORY POINTS */

                                    <input
                                        key={
                                            `points-${currentTicket.id}-${currentStoryPoints ?? 'none'}`
                                        }
                                        aria-label="Story points"
                                        type="number"
                                        min="1"
                                        max="100"
                                        step="1"
                                        disabled={busy}
                                        defaultValue={
                                            currentStoryPoints ??
                                            ''
                                        }
                                        onBlur={async (event) => {

                                            const value =
                                                Number(
                                                    event.target.value
                                                );

                                            if (
                                                value <= 0 ||
                                                value > 100
                                            ) {
                                                event.currentTarget.value =
                                                    currentStoryPoints !==
                                                    null
                                                        ? String(
                                                            currentStoryPoints
                                                        )
                                                        : '';

                                                return;
                                            }

                                            const changed =
                                                await confirmUpdate(
                                                    'story points',
                                                    currentStoryPoints,
                                                    value,
                                                    {
                                                        estimation_type:
                                                            'story_points',
                                                        story_points:
                                                        value
                                                    }
                                                );

                                            if (!changed) {
                                                event.currentTarget.value =
                                                    currentStoryPoints !==
                                                    null
                                                        ? String(
                                                            currentStoryPoints
                                                        )
                                                        : '';
                                            }
                                        }}
                                    />

                                )}

                            </div>

                        ) : (

                            <strong>
                                {estimate}
                            </strong>

                        )}

                    </div>

                    {/* ========================= */}
                    {/* PROJECT                   */}
                    {/* ========================= */}

                    <div className="detail-field">

                        <span>
                            Project
                        </span>

                        <strong>
                            {currentTicket.project?.name ?? '—'}
                        </strong>

                    </div>

                </aside>

            </div>

            {confirmation && (
                <div
                    className="confirmation-overlay"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirmation-title"
                    onMouseDown={(event) => {
                        if (
                            event.target === event.currentTarget &&
                            !busy
                        ) {
                            cancelConfirmation();
                        }
                    }}
                >
                    <div className="confirmation-modal">
                        <div className="confirmation-icon" aria-hidden="true">
                            !
                        </div>

                        <div className="confirmation-content">
                            <div className="confirmation-heading">
                                <span className="eyebrow">
                                    Confirm update
                                </span>

                                <h3 id="confirmation-title">
                                    Change {confirmation.label}?
                                </h3>

                                <p>
                                    Review the change before it is saved to
                                    this issue.
                                </p>
                            </div>

                            <div className="confirmation-change">
                                <div className="confirmation-value">
                                    <span>From</span>
                                    <strong>
                                        {displayValue(
                                            confirmation.oldValue
                                        )}
                                    </strong>
                                </div>

                                <div
                                    className="confirmation-arrow"
                                    aria-hidden="true"
                                >
                                    →
                                </div>

                                <div className="confirmation-value">
                                    <span>To</span>
                                    <strong>
                                        {displayValue(
                                            confirmation.newValue
                                        )}
                                    </strong>
                                </div>
                            </div>

                            <div className="confirmation-actions">
                                <button
                                    type="button"
                                    className="secondary"
                                    disabled={busy}
                                    onClick={cancelConfirmation}
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    className="primary"
                                    disabled={busy}
                                    onClick={() =>
                                        void approveConfirmation()
                                    }
                                >
                                    {busy
                                        ? 'Saving...'
                                        : 'Confirm change'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

        </section>
    );
}
