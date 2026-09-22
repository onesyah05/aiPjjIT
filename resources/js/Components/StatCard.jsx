export default function StatCard({ label, value, help, icon: Icon, tone = 'brand' }) {
    const tones = {
        brand: 'bg-brand-50 text-brand-800 ring-brand-100',
        green: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
        red: 'bg-red-50 text-red-700 ring-red-100',
        stone: 'bg-stone-100 text-stone-600 ring-stone-200',
    };

    return (
        <article className="rounded-card border border-stone-200 bg-white p-5 shadow-[0_1px_2px_rgba(30,41,37,0.04)]">
            <div className="flex items-start justify-between gap-4">
                <div><p className="text-sm font-medium text-stone-500">{label}</p><p className="mt-2 text-3xl font-bold tracking-tight text-ink">{value}</p></div>
                {Icon && <span className={`grid h-10 w-10 place-items-center rounded-lg ring-1 ${tones[tone] || tones.brand}`}><Icon size={19} strokeWidth={1.8} aria-hidden="true" /></span>}
            </div>
            {help && <p className="mt-3 text-xs leading-5 text-stone-500">{help}</p>}
        </article>
    );
}
