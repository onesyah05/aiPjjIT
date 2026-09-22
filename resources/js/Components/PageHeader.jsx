export default function PageHeader({ eyebrow, title, description, actions, icon: Icon }) {
    return (
        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-brand-700">
                    {Icon && <Icon size={15} strokeWidth={1.8} aria-hidden="true" />}
                    {eyebrow}
                </div>
                <h1 className="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">{title}</h1>
                {description && <p className="mt-2 max-w-3xl text-sm leading-6 text-stone-600">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
