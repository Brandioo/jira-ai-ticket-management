import type { ReactNode, SVGProps } from 'react';

export type IconName =
  | 'home'
  | 'issues'
  | 'reports'
  | 'sparkles'
  | 'clock'
  | 'trending'
  | 'check'
  | 'sun'
  | 'moon'
  | 'arrowLeft'
  | 'message'
  | 'history'
  | 'worklog'
  | 'search'
  | 'plus'
  | 'chevronRight';

type Props = SVGProps<SVGSVGElement> & { name: IconName };

const paths: Record<IconName, ReactNode> = {
  home: <><path d="M3 10.8 12 3l9 7.8"/><path d="M5.5 9.7V21h13V9.7"/><path d="M9 21v-6h6v6"/></>,
  issues: <><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></>,
  reports: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></>,
  sparkles: <><path d="m12 3 1.1 3.2L16 7.5l-2.9 1.3L12 12l-1.1-3.2L8 7.5l2.9-1.3L12 3Z"/><path d="m18.5 13 0.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/><path d="m5.5 13 .8 2.2 2.2.8-2.2.8L5.5 19l-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/></>,
  clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
  trending: <><path d="M4 17 10 11l4 4 6-8"/><path d="M15 7h5v5"/></>,
  check: <><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></>,
  sun: <><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></>,
  moon: <path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z"/>,
  arrowLeft: <><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></>,
  message: <><path d="M4 5h16v11H9l-5 4V5Z"/><path d="M8 9h8M8 12h5"/></>,
  history: <><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></>,
  worklog: <><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></>,
  search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
  plus: <path d="M12 5v14M5 12h14"/>,
  chevronRight: <path d="m9 6 6 6-6 6"/>,
};

export function Icon({ name, className = '', ...props }: Props) {
  return (
    <svg
      className={`icon ${className}`.trim()}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.9"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      {...props}
    >
      {paths[name]}
    </svg>
  );
}
