export default function BrandMark({ inverse = false, compact = false }) {
    return (
        <span className="inline-flex min-w-0 items-center gap-3">
            <img
                src="/images/sibermu-logo.png"
                alt=""
                className={`${compact ? 'h-10 w-10' : 'h-11 w-11'} shrink-0 rounded-full object-cover shadow-sm ring-1 ${inverse ? 'ring-white/25' : 'ring-brand-200'}`}
            />
            {!compact && (
                <span className={`min-w-0 ${inverse ? 'text-white' : 'text-ink'}`}>
                    <span className="block text-sm font-bold leading-none tracking-wide">PJJ AI</span>
                    <span className={`mt-1 hidden truncate text-[10px] font-medium uppercase tracking-[0.08em] sm:block ${inverse ? 'text-brand-200' : 'text-stone-500'}`}>
                        Universitas Siber Muhammadiyah
                    </span>
                </span>
            )}
        </span>
    );
}
