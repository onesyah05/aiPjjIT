import EmptyState from '@/Components/EmptyState';
import FlashBanner from '@/Components/FlashBanner';
import MarkdownContent from '@/Components/MarkdownContent';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Check, ClipboardCheck, MessageSquareWarning, X } from 'lucide-react';
import { useState } from 'react';

export default function KnowledgeReviews({ versions, flash }) {
    const [notes, setNotes] = useState({});

    const review = (version, action) => router.patch(
        route('admin.knowledge-reviews.update', version.id),
        { action, note: notes[version.id] || '' },
        { preserveScroll: true },
    );

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Moderasi komunitas" title="Review knowledge" description="Periksa kejelasan, relevansi, dan keamanan materi sebelum tersedia bagi anggota lain." icon={ClipboardCheck} />}
        >
            <Head title="Review knowledge" />

            <PageShell size="default">
                <FlashBanner message={flash?.status} />
                {versions.data.length > 0 ? (
                    <div className="space-y-6">
                        {versions.data.map((version) => (
                            <article key={version.id} className="rounded-card border border-stone-200 bg-white p-6">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-medium text-brand-700">{version.knowledge.course?.name || 'Umum'}</p>
                                        <h2 className="mt-1 font-display text-2xl font-bold text-ink">{version.knowledge.title}</h2>
                                        <p className="mt-1 text-xs text-stone-500">Kontributor: {version.knowledge.user.name} · Versi {version.version}</p>
                                    </div>
                                    <StatusBadge status="pending_review" />
                                </div>

                                <div className="mt-6 max-h-96 overflow-auto rounded-lg border border-stone-200 bg-stone-50 p-5 text-sm"><MarkdownContent content={version.content} /></div>

                                <label className="mt-5 block text-sm font-medium text-stone-700">
                                    Catatan reviewer
                                    <textarea value={notes[version.id] || ''} onChange={(event) => setNotes({ ...notes, [version.id]: event.target.value })} placeholder="Wajib diisi ketika menolak atau meminta revisi." className="mt-2 w-full rounded-lg border-stone-300 bg-white text-sm text-ink focus:border-brand-600 focus:ring-brand-600" rows="3" />
                                </label>

                                <div className="mt-4 flex flex-wrap gap-2">
                                    <button type="button" onClick={() => review(version, 'approve')} className="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-800"><Check size={15} />Setujui</button>
                                    <button type="button" onClick={() => review(version, 'request_revision')} className="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"><MessageSquareWarning size={15} />Minta revisi</button>
                                    <button type="button" onClick={() => review(version, 'reject')} className="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50"><X size={15} />Tolak</button>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <EmptyState title="Antrean review kosong" description="Semua knowledge komunitas sudah ditinjau. Materi baru akan muncul di halaman ini." />
                )}
                <Pagination data={versions} label="knowledge" />
            </PageShell>
        </AuthenticatedLayout>
    );
}
