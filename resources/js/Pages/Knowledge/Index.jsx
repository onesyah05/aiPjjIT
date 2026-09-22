import EmptyState from '@/Components/EmptyState';
import FlashBanner from '@/Components/FlashBanner';
import KnowledgeFormDialog from '@/Components/KnowledgeFormDialog';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, BookOpenText, CirclePlus, GraduationCap, Library, Search, Settings2, UserRound } from 'lucide-react';
import { useState } from 'react';

const visibilityLabels = {
    private: 'Pribadi',
    course: 'Mata kuliah',
    community: 'Komunitas',
};

export default function Index({ auth, knowledge, courses, filters, counts, flash }) {
    const [formOpen, setFormOpen] = useState(false);
    const [query, setQuery] = useState(filters.q || '');
    const libraryFilters = [
        { key: 'all', label: 'Semua', icon: Library, count: counts.all },
        { key: 'general', label: 'Umum', icon: BookOpenText, count: counts.general },
        { key: 'course', label: 'Mata kuliah', icon: GraduationCap, count: counts.course },
        { key: 'mine', label: 'Milik saya', icon: UserRound, count: counts.mine },
    ];

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Sumber belajar bersama" title="Knowledge" description="Catatan umum dan materi mata kuliah yang dapat digunakan sebagai konteks belajar." icon={BookOpenText} actions={(
                    <>
                        {auth.user.role === 'admin' && (
                            <Link href={route('admin.courses.index')} className="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-700 hover:border-brand-400 hover:text-brand-800">
                                <Settings2 size={18} strokeWidth={1.8} aria-hidden="true" />
                                Kelola mata kuliah
                            </Link>
                        )}
                        <button type="button" onClick={() => setFormOpen(true)} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-900">
                            <CirclePlus size={19} strokeWidth={1.8} aria-hidden="true" />
                            Tambah knowledge
                        </button>
                    </>
            )} />}
        >
            <Head title="Knowledge" />

            <PageShell>
                <FlashBanner message={flash?.status} />

                <section aria-labelledby="library-heading">
                    <form onSubmit={(event) => { event.preventDefault(); router.get(route('knowledge.index'), { context: filters.context === 'all' ? undefined : filters.context, q: query }, { preserveState: true }); }} className="mb-4 flex max-w-xl gap-2">
                        <div className="relative flex-1"><Search size={18} className="absolute left-3 top-3 text-stone-400" /><input value={query} onChange={(event) => setQuery(event.target.value)} className="w-full rounded-lg border-stone-300 pl-10" placeholder="Cari judul atau deskripsi knowledge" /></div>
                        <button className="rounded-lg bg-brand-800 px-4 text-sm font-bold text-white">Cari</button>
                    </form>
                    <div className="rounded-card border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="library-heading" className="font-display text-2xl font-bold text-ink">Library knowledge</h2>
                                <p className="mt-1 text-sm text-stone-500">Materi milikmu dan kontribusi bersama yang telah disetujui.</p>
                            </div>
                            <nav className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap" aria-label="Filter knowledge">
                                {libraryFilters.map(({ key, label, icon: Icon, count }) => {
                                    const active = filters.context === key;

                                    return (
                                        <Link key={key} href={route('knowledge.index', key === 'all' ? {} : { context: key })} preserveScroll className={`flex min-h-11 items-center justify-center gap-2 rounded-lg border px-3.5 text-sm font-semibold transition-colors ${active ? 'border-brand-800 bg-brand-800 text-white' : 'border-stone-200 bg-white text-stone-600 hover:border-stone-300 hover:text-ink'}`} aria-current={active ? 'page' : undefined}>
                                            <Icon size={17} strokeWidth={1.8} aria-hidden="true" />
                                            <span>{label}</span>
                                            <span className={`min-w-5 text-center text-xs ${active ? 'text-brand-200' : 'text-stone-400'}`}>{count}</span>
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                    </div>

                    {knowledge.data.length > 0 ? (
                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            {knowledge.data.map((item) => {
                                const ContextIcon = item.course ? GraduationCap : BookOpenText;

                                return (
                                    <Link key={item.id} href={route('knowledge.show', item.id)} className="group flex min-h-64 flex-col rounded-card border border-stone-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md sm:p-6">
                                        <div className="flex items-start justify-between gap-4">
                                            <span className={`grid h-11 w-11 place-items-center rounded-lg ${item.course ? 'bg-amber-50 text-amber-800' : 'bg-brand-100 text-brand-800'}`}>
                                                <ContextIcon size={22} strokeWidth={1.8} aria-hidden="true" />
                                            </span>
                                            <StatusBadge status={item.status} />
                                        </div>

                                        <div className="mt-5 flex-1">
                                            <p className="text-xs font-bold uppercase tracking-[0.08em] text-stone-500">{item.course ? `${item.course.code} · ${item.course.name}` : 'Knowledge umum'}</p>
                                            <h3 className="mt-2 line-clamp-2 text-lg font-bold leading-7 text-ink group-hover:text-brand-800">{item.title}</h3>
                                            <p className="mt-2 line-clamp-3 text-sm leading-6 text-stone-600">{item.description || 'Tidak ada deskripsi untuk materi ini.'}</p>
                                        </div>

                                        <div className="mt-5 flex items-center justify-between gap-4 border-t border-stone-200 pt-4 text-xs text-stone-500">
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold text-stone-700">{item.user.name}</p>
                                                <p className="mt-0.5">{visibilityLabels[item.visibility] || item.visibility}</p>
                                            </div>
                                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-stone-400 group-hover:bg-brand-100 group-hover:text-brand-800">
                                                <ArrowUpRight size={18} strokeWidth={1.8} aria-hidden="true" />
                                            </span>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="mt-6">
                            <EmptyState title="Belum ada knowledge di kategori ini" description="Pilih kategori lain atau tambahkan materi baru." action={(
                                <button type="button" onClick={() => setFormOpen(true)} className="mx-auto flex min-h-11 items-center gap-2 rounded-lg bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-900">
                                    <CirclePlus size={18} aria-hidden="true" />
                                    Tambah knowledge
                                </button>
                            )} />
                        </div>
                    )}

                    <Pagination data={knowledge} label="knowledge" />
                </section>
            </PageShell>

            <KnowledgeFormDialog courses={courses} open={formOpen} onClose={() => setFormOpen(false)} />
        </AuthenticatedLayout>
    );
}
