import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-0.5 text-sm font-semibold leading-5 transition-colors focus:outline-none ' +
                (active
                    ? 'border-brand-700 text-brand-900'
                    : 'border-transparent text-stone-600 hover:border-stone-300 hover:text-ink') +
                className
            }
        >
            {children}
        </Link>
    );
}
