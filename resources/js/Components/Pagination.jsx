import { Link } from '@inertiajs/react';

export default function Pagination({ data, label = 'data' }) {
    if (!data || data.last_page <= 1) return null;

    return (
        <nav className="mt-5 flex flex-col gap-3 rounded-card border border-stone-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between" aria-label={`Navigasi halaman ${label}`}>
            <p className="px-2 text-sm text-stone-500">{data.from}–{data.to} dari {data.total} {label}</p>
            <div className="grid grid-cols-2 gap-2">
                {data.prev_page_url ? <Link href={data.prev_page_url} preserveScroll className="rounded-lg border border-stone-300 px-4 py-2 text-center text-sm font-semibold hover:bg-stone-50">Sebelumnya</Link> : <span className="rounded-lg bg-stone-100 px-4 py-2 text-center text-sm font-semibold text-stone-400">Sebelumnya</span>}
                {data.next_page_url ? <Link href={data.next_page_url} preserveScroll className="rounded-lg border border-stone-300 px-4 py-2 text-center text-sm font-semibold hover:bg-stone-50">Berikutnya</Link> : <span className="rounded-lg bg-stone-100 px-4 py-2 text-center text-sm font-semibold text-stone-400">Berikutnya</span>}
            </div>
        </nav>
    );
}
