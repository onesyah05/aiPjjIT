import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-3 pe-4 ps-4 ${
                active
                    ? 'border-brand-700 bg-brand-50 text-brand-900'
                    : 'border-transparent text-stone-600 hover:border-stone-300 hover:bg-stone-50 hover:text-ink'
            } text-base font-semibold transition-colors focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
