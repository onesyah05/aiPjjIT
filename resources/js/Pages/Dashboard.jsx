import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenCheck,
    Bot,
    ChevronRight,
    Clock3,
    KeyRound,
    MessageCircle,
    Sparkles,
    Trophy,
} from 'lucide-react';

function MemberAvatar({ leader }) {
    if (leader.avatar_url) {
        return <img src={leader.avatar_url} alt="" className="h-10 w-10 rounded-lg object-cover ring-1 ring-stone-200" />;
    }

    return (
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-100 text-sm font-bold text-brand-800" aria-hidden="true">
            {leader.name.charAt(0).toUpperCase()}
        </span>
    );
}

function SectionHeading({ eyebrow, title, description, action }) {
    return (
        <div className="flex flex-col gap-3 border-b border-stone-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-brand-600">{eyebrow}</p>
                <h2 className="mt-1 text-xl font-bold tracking-tight text-ink">{title}</h2>
                {description && <p className="mt-1 text-sm leading-6 text-stone-500">{description}</p>}
            </div>
            {action}
        </div>
    );
}

export default function Dashboard({ auth, courses, recentConversations, recentKnowledge, stats, leaders }) {
    const conversation = useForm({ course_id: '', mode: 'general', title: '' });

    const createConversation = (event) => {
        event.preventDefault();
        conversation.post(route('conversations.store'));
    };

    const overview = [
        { label: 'Percakapan', value: stats.conversation_count, icon: MessageCircle },
        { label: 'Knowledge', value: stats.knowledge_count, icon: BookOpenCheck },
        { label: 'Credential aktif', value: stats.active_credentials, icon: KeyRound },
    ];

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Ruang belajar" title={`Selamat datang, ${auth.user.name}`} description="Mulai sesi baru atau lanjutkan aktivitas belajar terakhir kamu." icon={Sparkles} />}
        >
            <Head title="Beranda" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,.75fr)]">
                    <section className="relative overflow-hidden rounded-card bg-brand-950 text-white shadow-[0_20px_55px_rgba(6,18,38,0.18)]" aria-labelledby="quick-start-heading">
                        <div className="pointer-events-none absolute -right-20 -top-32 h-80 w-80 rounded-full border border-white/10" />
                        <div className="pointer-events-none absolute -right-5 -top-20 h-52 w-52 rounded-full bg-brand-600/20 blur-3xl" />

                        <div className="relative p-6 sm:p-8">
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.07] px-3 py-1.5 text-xs font-semibold text-brand-100">
                                <Bot size={15} aria-hidden="true" />
                                Tutor berbasis knowledge komunitas
                            </div>
                            <h2 id="quick-start-heading" className="mt-5 max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                                Apa yang ingin kamu pelajari hari ini?
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-brand-200">
                                Pilih mata kuliah dan mode jawaban. Tutor akan menyesuaikan konteks sebelum percakapan dimulai.
                            </p>

                            <form onSubmit={createConversation} className="mt-7 rounded-xl border border-white/15 bg-white/[0.07] p-4 backdrop-blur-sm">
                                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_13rem]">
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold text-brand-100" htmlFor="dashboard-course">Mata kuliah</label>
                                        <select
                                            id="dashboard-course"
                                            value={conversation.data.course_id}
                                            onChange={(event) => conversation.setData('course_id', event.target.value)}
                                            className="min-h-12 w-full rounded-ui border-white/20 bg-white text-sm text-ink shadow-sm focus:border-brand-300 focus:ring-brand-300"
                                        >
                                            <option value="">Semua mata kuliah</option>
                                            {courses.map((course) => <option key={course.id} value={course.id}>{course.code} — {course.name}</option>)}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-2 block text-xs font-semibold text-brand-100" htmlFor="dashboard-mode">Mode jawaban</label>
                                        <select
                                            id="dashboard-mode"
                                            value={conversation.data.mode}
                                            onChange={(event) => conversation.setData('mode', event.target.value)}
                                            className="min-h-12 w-full rounded-ui border-white/20 bg-white text-sm text-ink shadow-sm focus:border-brand-300 focus:ring-brand-300"
                                        >
                                            <option value="general">Jawaban umum</option>
                                            <option value="knowledge_only">Hanya knowledge</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-xs leading-5 text-brand-200">Sumber akan ditampilkan pada jawaban agar dapat kamu periksa.</p>
                                    <button
                                        type="submit"
                                        disabled={conversation.processing}
                                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-ui bg-white px-5 text-sm font-bold text-brand-950 shadow-sm transition hover:bg-brand-50 disabled:cursor-wait disabled:opacity-60"
                                    >
                                        {conversation.processing ? 'Menyiapkan…' : 'Mulai percakapan'}
                                        {!conversation.processing && <ArrowRight size={17} aria-hidden="true" />}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <dl className="relative grid grid-cols-3 border-t border-white/10 bg-black/10">
                            {overview.map(({ label, value, icon: Icon }) => (
                                <div key={label} className="border-r border-white/10 px-4 py-4 last:border-r-0 sm:px-6">
                                    <dt className="flex items-center gap-2 text-[11px] font-medium text-brand-300 sm:text-xs"><Icon size={14} aria-hidden="true" /><span className="truncate">{label}</span></dt>
                                    <dd className="mt-1 text-xl font-bold text-white sm:text-2xl">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section className="overflow-hidden rounded-card border border-stone-200 bg-white shadow-[0_10px_30px_rgba(17,28,45,0.06)]" aria-labelledby="dashboard-leaderboard-heading">
                        <div className="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-5 sm:px-6">
                            <div>
                                <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-clay">
                                    <Trophy size={15} aria-hidden="true" />
                                    Komunitas
                                </div>
                                <h2 id="dashboard-leaderboard-heading" className="mt-1 text-xl font-bold tracking-tight text-ink">Leaderboard</h2>
                                <p className="mt-1 text-xs leading-5 text-stone-500">Kontributor paling aktif saat ini.</p>
                            </div>
                            <Link href={route('leaderboard')} className="grid h-9 w-9 place-items-center rounded-lg border border-stone-200 text-stone-500 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-800" aria-label="Buka leaderboard lengkap">
                                <ArrowRight size={17} aria-hidden="true" />
                            </Link>
                        </div>

                        {leaders.length > 0 ? (
                            <ol className="divide-y divide-stone-100 px-3 py-2">
                                {leaders.map((leader) => (
                                    <li key={leader.id} className={`flex items-center gap-3 rounded-lg px-2 py-3 ${leader.id === auth.user.id ? 'bg-brand-50' : ''}`}>
                                        <span className={`grid h-7 w-7 shrink-0 place-items-center rounded-md text-xs font-bold ${leader.rank === 1 ? 'bg-amber-100 text-amber-800' : 'bg-stone-100 text-stone-600'}`}>{leader.rank}</span>
                                        <MemberAvatar leader={leader} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-bold text-ink">{leader.name}{leader.id === auth.user.id && <span className="ml-1 font-medium text-brand-700">· Kamu</span>}</p>
                                            <p className="mt-0.5 truncate text-xs text-stone-500">{leader.badges[0] || 'Anggota komunitas'}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-bold text-brand-800">{leader.score}</p>
                                            <p className="text-[10px] uppercase tracking-wide text-stone-400">poin</p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="px-6 py-12 text-center">
                                <Trophy size={28} className="mx-auto text-stone-300" aria-hidden="true" />
                                <p className="mt-3 text-sm font-semibold text-ink">Peringkat belum tersedia</p>
                                <p className="mt-1 text-xs text-stone-500">Kontribusi pertama akan muncul di sini.</p>
                            </div>
                        )}

                        <Link href={route('leaderboard')} className="flex items-center justify-between border-t border-stone-200 px-5 py-3.5 text-sm font-semibold text-brand-800 hover:bg-brand-50 sm:px-6">
                            Lihat peringkat lengkap
                            <ChevronRight size={17} aria-hidden="true" />
                        </Link>
                    </section>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,.6fr)]">
                    <section aria-labelledby="recent-conversations-heading" className="rounded-card border border-stone-200 bg-white p-5 shadow-[0_8px_24px_rgba(17,28,45,0.04)] sm:p-6">
                        <SectionHeading
                            eyebrow="Aktivitas terakhir"
                            title="Lanjutkan percakapan"
                            description="Buka kembali sesi belajar yang terakhir diperbarui."
                            action={recentConversations.length > 0 && <Link href={route('conversations.index')} className="inline-flex items-center gap-1 text-sm font-bold text-brand-800 hover:text-brand-600">Semua percakapan <ChevronRight size={16} /></Link>}
                        />

                        {recentConversations.length === 0 ? (
                            <div className="pt-5">
                                <EmptyState title="Belum ada percakapan" description="Pilih mata kuliah di atas untuk memulai sesi belajar pertamamu." />
                            </div>
                        ) : (
                            <ol className="divide-y divide-stone-100">
                                {recentConversations.map((item) => (
                                    <li key={item.id}>
                                        <Link href={route('conversations.show', item.id)} className="group flex items-center gap-4 rounded-lg px-1 py-4 transition-colors hover:bg-brand-50/70 sm:px-3">
                                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100"><MessageCircle size={18} aria-hidden="true" /></span>
                                            <div className="min-w-0 flex-1">
                                                <h3 className="truncate text-sm font-bold text-ink group-hover:text-brand-800">{item.title || 'Percakapan baru'}</h3>
                                                <p className="mt-1 truncate text-xs text-stone-500">{item.course?.name || 'Lintas mata kuliah'} · {item.mode === 'knowledge_only' ? 'Hanya knowledge' : 'Jawaban umum'}</p>
                                            </div>
                                            <ChevronRight size={18} className="shrink-0 text-stone-300 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-700" aria-hidden="true" />
                                        </Link>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    <section aria-labelledby="recent-knowledge-heading" className="rounded-card border border-stone-200 bg-white p-5 shadow-[0_8px_24px_rgba(17,28,45,0.04)] sm:p-6">
                        <SectionHeading
                            eyebrow="Library pribadi"
                            title="Knowledge terbaru"
                            description="Catatan yang terakhir kamu tambahkan."
                            action={<Link href={route('knowledge.index')} className="inline-flex items-center gap-1 text-sm font-bold text-brand-800 hover:text-brand-600">Kelola <ChevronRight size={16} /></Link>}
                        />

                        {recentKnowledge.length === 0 ? (
                            <div className="py-8 text-center">
                                <BookOpenCheck size={27} className="mx-auto text-stone-300" aria-hidden="true" />
                                <p className="mt-3 text-sm font-semibold text-ink">Belum ada catatan</p>
                                <p className="mt-1 text-xs leading-5 text-stone-500">Tambahkan knowledge agar tutor dapat memakai konteks belajarmu.</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-stone-100">
                                {recentKnowledge.map((item) => (
                                    <li key={item.id} className="py-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <Link href={route('knowledge.show', item.id)} className="line-clamp-2 text-sm font-bold leading-5 text-ink hover:text-brand-800">{item.title}</Link>
                                            <StatusBadge status={item.status} />
                                        </div>
                                        <p className="mt-2 flex items-center gap-1.5 text-xs capitalize text-stone-500"><Clock3 size={13} aria-hidden="true" />{item.course?.name || 'Umum'} · {item.visibility}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
