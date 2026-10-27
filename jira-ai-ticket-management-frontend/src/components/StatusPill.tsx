import type { TicketPriority, TicketStatus } from '../types';

export function StatusPill({ status }: { status: TicketStatus }) {
  const labels: Record<TicketStatus, string> = {
    todo: 'TO DO',
    in_progress: 'IN PROGRESS',
    review: 'IN REVIEW',
    done: 'DONE',
  };
  return <span className={`status-pill status-${status}`}>{labels[status]}</span>;
}

export function PriorityPill({ priority }: { priority: TicketPriority }) {
  return <span className={`priority-pill priority-${priority}`}>{priority}</span>;
}
