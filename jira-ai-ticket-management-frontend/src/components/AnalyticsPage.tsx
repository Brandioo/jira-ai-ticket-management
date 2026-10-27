import type { DashboardData, User } from '../types';
import { Icon } from './Icon';

export function AnalyticsPage({ dashboard, user }: { dashboard: DashboardData | null; user: User }) {
  const analytics = dashboard?.analytics as any;
  const managerView = user.role === 'admin' || user.role === 'manager';
  const employees = managerView ? analytics?.employees ?? [] : [];

  return (
    <>
      <section className="report-hero">
        <div><span className="eyebrow">SPRINT REPORT</span><h2>Workload & overtime</h2><p>Track logged time, averages and overtime for the active sprint.</p></div>
        <div className="report-date">{dashboard?.active_sprint?.name ?? 'No sprint'}</div>
      </section>

      <section className="stat-grid report-stats">
        <article className="stat-card"><span className="stat-icon blue"><Icon name="clock" /></span><div><small>{managerView ? 'Total team hours' : 'My hours'}</small><strong>{analytics?.total_hours ?? analytics?.hours ?? 0}h</strong><span>Logged this sprint</span></div></article>
        <article className="stat-card"><span className="stat-icon orange"><Icon name="trending" /></span><div><small>Overtime hours</small><strong>{analytics?.overtime_hours ?? 0}h</strong><span>Above standard time</span></div></article>
        <article className="stat-card"><span className="stat-icon violet"><Icon name="reports" /></span><div><small>Average daily</small><strong>{analytics?.average_daily_hours ?? 0}h</strong><span>Per logged day</span></div></article>
        <article className="stat-card"><span className="stat-icon green"><Icon name="check" /></span><div><small>Issues done</small><strong>{dashboard?.ticket_counts.done ?? 0}</strong><span>Current sprint</span></div></article>
      </section>

      <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">WORKLOG REPORT</span><h3>{managerView ? 'Team workload' : 'My workload'}</h3></div></div>
        {managerView ? (
          <div className="report-table">
            <div className="report-head"><span>Employee</span><span>Total hours</span><span>Overtime</span><span>Daily average</span><span>Load</span></div>
            {employees.map((employee: any) => (
              <div className="report-row" key={employee.user_id}>
                <div className="assignee"><div className="small-avatar">{employee.name.slice(0, 1)}</div><strong>{employee.name}</strong></div>
                <strong>{employee.hours}h</strong>
                <span className={employee.overtime_hours > 0 ? 'overtime-text' : ''}>{employee.overtime_hours}h</span>
                <span>{employee.average_daily_hours}h</span>
                <div className="load-track"><span style={{ width: `${Math.min(100, (employee.hours / 40) * 100)}%` }} /></div>
              </div>
            ))}
          </div>
        ) : (
          <div className="personal-report">
            <div><span>Logged days</span><strong>{analytics?.days_logged ?? 0}</strong></div>
            <div><span>Worklog entries</span><strong>{analytics?.entries ?? 0}</strong></div>
            <div><span>Average/day</span><strong>{analytics?.average_daily_hours ?? 0}h</strong></div>
          </div>
        )}
      </section>
    </>
  );
}
