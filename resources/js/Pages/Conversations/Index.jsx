import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { MessageCircle, Pencil, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
}).format(new Date(value));

export default function Index({ conversations, filters }) {
    const [query, setQuery] = useState(filters.q || '');
    const newConversation = useForm({ course_id: '', mode: 'general', title: '' });
    const startConversation = () => newConversation.post(route('conversations.store'));
    const rename = (conversation) => { const title = window.prompt('Judul percakapan', conversation.title || ''); if (title?.trim()) router.patch(route('conversations.update', conversation.id), { title: title.trim() }, { preserveScroll: true }); };
    const remove = (conversation) => { if (window.confirm('Hapus percakapan ini?')) router.delete(route('conversations.destroy', conversation.id), { preserveScroll: true }); };
    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Ruang belajar" title="Percakapan" description="Temukan kembali sesi lama, ubah judul, atau mulai diskusi baru." icon={MessageCircle} actions={<button type="button" onClick={startConversation} disabled={newConversation.processing} className="rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-900 disabled:cursor-wait disabled:opacity-60">{newConversation.processing ? 'Menyiapkan…' : 'Mulai percakapan'}</button>} />}
        >
            <Head title="Percakapan" />

            <PageShell size="default">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <p className="max-w-2xl text-sm leading-6 text-stone-600">
                        Lanjutkan pembahasan sebelumnya atau buka sesi baru untuk mata kuliah lain.
                    </p>
                </div>
                <form onSubmit={(event) => { event.preventDefault(); router.get(route('conversations.index'), { q: query }, { preserveState: true }); }} className="mb-5 flex max-w-lg gap-2"><div className="relative flex-1"><Search size={18} className="absolute left-3 top-3 text-stone-400" /><input value={query} onChange={(event) => setQuery(event.target.value)} className="w-full rounded-lg border-stone-300 pl-10" placeholder="Cari percakapan" /></div><button className="rounded-lg border border-stone-300 bg-white px-4 text-sm font-bold">Cari</button></form>

                {conversations.data.length > 0 ? (
                    <div className="overflow-hidden rounded-card border border-stone-200 bg-white">
                        {conversations.data.map((conversation, index) => (
                            <div
                                key={conversation.id}
                                className={`group flex items-center justify-between gap-5 px-5 py-4 hover:bg-brand-50/60 ${index > 0 ? 'border-t border-stone-200' : ''}`}
                            >
                                <Link href={route('conversations.show', conversation.id)} className="min-w-0 flex-1">
                                    <h2 className="truncate font-semibold text-ink group-hover:text-brand-800">
                                        {conversation.title || 'Percakapan baru'}
                                    </h2>
                                    <p className="mt-1 text-sm text-stone-500">
                                        {conversation.course?.name || 'Lintas mata kuliah'} · {conversation.mode === 'knowledge_only' ? 'Hanya knowledge' : 'Umum'}
                                    </p>
                                </Link>
                                <time className="hidden shrink-0 text-xs text-stone-500 md:block" dateTime={conversation.updated_at}>
                                    {formatDate(conversation.updated_at)}
                                </time>
                                <div className="flex shrink-0"><button onClick={() => rename(conversation)} className="rounded-lg p-2 text-stone-500 hover:bg-white hover:text-brand-800" aria-label="Ubah judul"><Pencil size={16} /></button><button onClick={() => remove(conversation)} className="rounded-lg p-2 text-stone-500 hover:bg-red-50 hover:text-red-700" aria-label="Hapus percakapan"><Trash2 size={16} /></button></div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="Belum ada percakapan"
                        description="Mulai dengan satu pertanyaan. Riwayat belajarmu akan tersimpan di sini."
                        action={<button type="button" onClick={startConversation} disabled={newConversation.processing} className="font-semibold text-brand-700 hover:text-brand-900 disabled:cursor-wait disabled:opacity-60">{newConversation.processing ? 'Menyiapkan percakapan…' : 'Ajukan pertanyaan pertama'}</button>}
                    />
                )}
                <Pagination data={conversations} label="percakapan" />
            </PageShell>
        </AuthenticatedLayout>
    );
}
