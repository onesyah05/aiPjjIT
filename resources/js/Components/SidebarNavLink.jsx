import { Link } from '@inertiajs/react';

export default function SidebarNavLink({ active = false, icon, children, className = '', collapsed = false, title, ...props }) {
    return (
        <Link
            {...props}
            title={title}
            aria-current={active ? 'page' : undefined}
            className={`sidebar-nav-link group relative flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-all ${active ? 'sidebar-nav-link-active bg-white text-brand-950 shadow-sm' : 'text-brand-100 hover:bg-white/[0.08] hover:text-white'} ${collapsed ? 'justify-center px-0 w-10' : ''} ${className}`}
        >
            {active && <span className="sidebar-nav-link-bar absolute -left-1 h-5 w-1 rounded-full bg-brand-500" aria-hidden="true" />}
            <span className={`grid h-5 w-5 shrink-0 place-items-center ${active ? 'text-brand-700' : 'text-brand-300 group-hover:text-white'}`} aria-hidden="true">
                {icon}
            </span>
            {!collapsed && <span className="truncate">{children}</span>}
        </Link>
    );
}
