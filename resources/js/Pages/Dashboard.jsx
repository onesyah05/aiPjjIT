import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';

export default function Dashboard({ auth, courses, recentConversations, recentKnowledge, stats }) {
    const conversation = useForm({ course_id: '', mode: 'general', title: '' });

    const createConversation = (event) => {
        event.preventDefault();
        conversation.post(route('conversations.store'));
    };

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Ruang belajar" title={`Selamat datang, ${auth.user.name}`} description="Lanjutkan materi yang sedang dipelajari atau mulai pertanyaan baru dengan konteks yang tepat." icon={Sparkles} />}
        >
            <Head title="Beranda" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <section className="grid overflow-hidden rounded-card border border-brand-900 bg-brand-900 shadow-[0_18px_45px_rgba(16,28,23,0.14)] lg:grid-cols-[1fr_22rem]">
                    <div className="p-6 text-white sm:p-9">
                        <h2 className="max-w-2xl font-display text-3xl font-semibold leading-tight sm:text-4xl">Apa yang ingin kamu pahami hari ini?</h2>
                        <p className="mt-3 max-w-2xl text-sm leading-7 text-brand-100">Pilih konteks mata kuliah dan cara tutor menjawab. Mode Knowledge Only hanya menggunakan sumber yang tersedia di library.</p>

                        <form onSubmit={createConversation} className="mt-7 grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
                            <label className="sr-only" htmlFor="dashboard-course">Mata kuliah</label>
                            <select
                                id="dashboard-course"
                                value={conversation.data.course_id}
                                onChange={(event) => conversation.setData('course_id', event.target.value)}
                                className="min-h-12 rounded-ui border-white/30 bg-white text-sm text-ink focus:border-brand-300 focus:ring-brand-300"
                            >
                                <option value="">Semua mata kuliah</option>
                                {courses.map((course) => <option key={course.id} value={course.id}>{course.code} — {course.name}</option>)}
                            </select>

                            <label className="sr-only" htmlFor="dashboard-mode">Mode jawaban</label>
                            <select
                                id="dashboard-mode"
                                value={conversation.data.mode}
                                onChange={(event) => conversation.setData('mode', event.target.value)}
                                className="min-h-12 rounded-ui border-white/30 bg-white text-sm text-ink focus:border-brand-300 focus:ring-brand-300"
                            >
                                <option value="general">Umum</option>
                                <option value="knowledge_only">Hanya knowledge</option>
                            </select>

                            <button
                                type="submit"
                                disabled={conversation.processing}
                                className="min-h-12 rounded-ui bg-white px-5 text-sm font-bold text-brand-900 transition-colors hover:bg-brand-50 disabled:cursor-wait disabled:opacity-60"
                            >
                                {conversation.processing ? 'Menyiapkan…' : 'Mulai percakapan'}
                            </button>
                        </form>
                    </div>

                    <aside className="border-t border-white/20 bg-brand-800 p-6 text-brand-100 lg:border-l lg:border-t-0 sm:p-8">
                        <div className="flex items-center gap-3"><img src="/images/sibermu-logo.png" alt="" className="h-11 w-11 rounded-full object-cover ring-1 ring-white/25" /><div><h3 className="text-sm font-bold text-white">Ruang belajarmu</h3><p className="mt-0.5 text-[11px] text-brand-200">PJJ AI · SIBERMU</p></div></div>
                        <dl className="mt-5 divide-y divide-white/15">
                            <div className="flex items-baseline justify-between gap-4 py-3 first:pt-0">
                                <dt className="text-sm">Percakapan tersimpan</dt>
                                <dd className="font-display text-2xl font-semibold text-white">{stats.conversation_count}</dd>
                            </div>
                            <div className="flex items-baseline justify-between gap-4 py-3">
                                <dt className="text-sm">Knowledge pribadi</dt>
                                <dd className="font-display text-2xl font-semibold text-white">{stats.knowledge_count}</dd>
                            </div>
                            <div className="flex items-baseline justify-between gap-4 py-3 pb-0">
                                <dt className="text-sm">Credential aktif</dt>
                                <dd className="font-display text-2xl font-semibold text-white">{stats.active_credentials}</dd>
                            </div>
                        </dl>
                        <p className="mt-6 text-xs leading-5 text-brand-200">Jawaban AI dapat keliru. Gunakan sumber untuk memeriksa materi penting.</p>
                    </aside>
                </section>

                <div className="mt-10 grid gap-10 lg:grid-cols-[1.45fr_.75fr]">
                    <section aria-labelledby="recent-conversations-heading" className="rounded-card border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex items-end justify-between gap-4 border-b border-stone-300 pb-3">
                            <div>
                                <h2 id="recent-conversations-heading" className="font-display text-2xl font-semibold text-ink">Lanjutkan percakapan</h2>
                                <p className="mt-1 text-sm text-stone-600">Enam sesi yang terakhir kamu buka.</p>
                            </div>
                            {recentConversations.length > 0 && <Link href={route('conversations.index')} className="text-sm font-bold text-brand-800 hover:text-brand-600">Semua percakapan</Link>}
                        </div>

                        {recentConversations.length === 0 ? (
                            <div className="mt-5">
                                <EmptyState title="Belum ada percakapan" description="Pilih mata kuliah di atas untuk memulai sesi belajar pertamamu." />
                            </div>
                        ) : (
                            <ol className="mt-1 divide-y divide-stone-200">
                                {recentConversations.map((item) => (
                                    <li key={item.id}>
                                        <Link href={route('conversations.show', item.id)} className="group grid gap-1 rounded-lg py-5 transition-colors hover:bg-brand-50/60 sm:grid-cols-[1fr_auto] sm:px-3">
                                            <div>
                                                <h3 className="font-semibold text-ink group-hover:text-brand-800">{item.title || 'Percakapan baru'}</h3>
                                                <p className="mt-1 text-sm text-stone-500">{item.course?.name || 'Lintas mata kuliah'}</p>
                                            </div>
                                            <p className="text-xs font-semibold text-stone-500 sm:self-center">{item.mode === 'knowledge_only' ? 'Hanya knowledge' : 'Umum'}</p>
                                        </Link>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    <section aria-labelledby="recent-knowledge-heading" className="rounded-card border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex items-end justify-between gap-4 border-b border-stone-300 pb-3">
                            <div>
                                <h2 id="recent-knowledge-heading" className="font-display text-2xl font-semibold text-ink">Knowledge terbaru</h2>
                                <p className="mt-1 text-sm text-stone-600">Catatan yang terakhir kamu buat.</p>
                            </div>
                            <Link href={route('knowledge.index')} className="text-sm font-bold text-brand-800 hover:text-brand-600">Kelola</Link>
                        </div>

                        {recentKnowledge.length === 0 ? (
                            <p className="mt-5 border-l-2 border-stone-300 pl-4 text-sm leading-6 text-stone-600">Belum ada catatan. Tambahkan knowledge agar tutor dapat memakai konteks belajarmu.</p>
                        ) : (
                            <ul className="divide-y divide-stone-200">
                                {recentKnowledge.map((item) => (
                                    <li key={item.id} className="py-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <Link href={route('knowledge.show', item.id)} className="font-semibold text-ink hover:text-brand-800">{item.title}</Link>
                                            <StatusBadge status={item.status} />
                                        </div>
                                        <p className="mt-1 text-xs capitalize text-stone-500">{item.course?.name || 'Umum'} · {item.visibility}</p>
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
