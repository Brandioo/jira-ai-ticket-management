export type Role = 'admin' | 'manager' | 'employee';
export type TicketStatus = 'todo' | 'in_progress' | 'review' | 'done';
export type TicketPriority = 'low' | 'medium' | 'high' | 'critical';
export type TicketType = 'task' | 'bug' | 'story' | 'epic';
export type EstimationType = 'story_points' | 'hours';
export type SprintStatus = 'planned' | 'active' | 'completed';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
}

export interface Project {
  id: number;
  key: string;
  name: string;
}

export interface Sprint {
  id: number;
  project_id: number;
  name: string;
  goal?: string | null;
  status: SprintStatus;
  starts_at?: string | null;
  ends_at?: string | null;
  project?: Project;
}

export interface Comment {
  id: number;
  ticket_id: number;
  user_id: number;
  body: string;
  created_at?: string;
  updated_at?: string;
  user?: User;
}

export interface Worklog {
  id: number;
  ticket_id: number;
  user_id: number;
  work_date: string;
  hours: number;
  overtime_hours: number;
  note?: string | null;
  created_at?: string;
  user?: User;
}

export interface TicketHistory {
  id: number;
  ticket_id: number;
  user_id: number;
  field: string;
  old_value?: string | null;
  new_value?: string | null;
  created_at?: string;
  user?: User;
}

export interface Ticket {
  id: number;
  project_id: number;
  sprint_id: number | null;
  reporter_id: number;
  assignee_id: number | null;
  ticket_number: string;
  title: string;
  description: string | null;
  type: TicketType;
  status: TicketStatus;
  priority: TicketPriority;
  estimation_type: EstimationType;
  story_points: number | null;
  estimated_hours: number | null;
  project?: Project;
  sprint?: Sprint | null;
  reporter?: User;
  assignee?: User | null;
  comments?: Comment[];
  worklogs?: Worklog[];
  histories?: TicketHistory[];
}

export interface EmployeeAnalytics {
  user_id?: number;
  name?: string;
  hours: number;
  overtime_hours: number;
  average_daily_hours: number;
  days_logged?: number;
  entries?: number;
}

export interface SprintSummary {
  sprint_id: number;
  sprint_name: string;
  total_hours: number;
  overtime_hours: number;
  average_daily_hours: number;
  employees: EmployeeAnalytics[];
}

export interface DashboardData {
  active_sprint: Sprint | null;
  ticket_counts: {
    total?: number;
    todo?: number;
    in_progress?: number;
    review?: number;
    done?: number;
  };
  my_open_tickets: number;
  analytics: SprintSummary | EmployeeAnalytics | null;
}

export interface MetaData {
  projects: Project[];
  sprints: Sprint[];
  users: User[];
  statuses: TicketStatus[];
  priorities: TicketPriority[];
  types: TicketType[];
}

export interface AssistantResponse {
  mode: string;
  engine: string;
  answer: string;
  data: unknown;
}

export interface CompleteSprintResponse {
  completed_sprint: Sprint;
  next_sprint: Sprint;
  moved_tickets: number;
}
