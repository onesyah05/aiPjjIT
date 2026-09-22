import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Fingerprint, ScrollText } from 'lucide-react';

const actionLabel = (action) => action.replaceAll('.', ' · ').replaceAll('_', ' ');

export default function AuditLogs({ logs }) {
    return (
        <AuthenticatedLayout header={<PageHeader eyebrow="Keamanan & kepatuhan" title="Audit log" description="Jejak perubahan administratif penting untuk investigasi dan akuntabilitas." icon={ScrollText} />}>
            <Head title="Audit log" />
            <PageShell>
                <div className="mb-5 flex items-start gap-3 rounded-card border border-stone-200 bg-white p-4 text-sm text-stone-600"><Fingerprint size={20} className="mt-0.5 shrink-0 text-brand-700" /><p className="leading-6">Alamat IP disimpan dalam bentuk hash. Metadata sensitif dan secret credential tidak ditampilkan di halaman ini.</p></div>
                <section className="overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm" aria-label="Daftar audit log">
                    <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-left text-sm"><thead className="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th className="px-5 py-3 font-semibold sm:px-6">Waktu</th><th className="px-4 py-3 font-semibold">Pelaku</th><th className="px-4 py-3 font-semibold">Tindakan</th><th className="px-4 py-3 font-semibold">Resource</th><th className="px-5 py-3 font-semibold sm:px-6">Detail</th></tr></thead><tbody>{logs.data.map((log) => <tr key={log.id} className="border-t border-stone-100 align-top hover:bg-stone-50/70"><td className="whitespace-nowrap px-5 py-4 text-stone-500 sm:px-6">{new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}</td><td className="px-4 py-4 font-semibold text-ink">{log.actor_name || 'Sistem'}</td><td className="px-4 py-4"><span className="inline-flex rounded-md bg-brand-50 px-2 py-1 text-xs font-bold capitalize text-brand-800">{actionLabel(log.action)}</span></td><td className="px-4 py-4 text-stone-600">{log.resource_type}<span className="block text-xs text-stone-400">ID {log.resource_id || '—'}</span></td><td className="max-w-sm px-5 py-4 sm:px-6"><code className="line-clamp-2 break-all text-xs leading-5 text-stone-500">{log.metadata_json || 'Tidak ada metadata'}</code></td></tr>)}</tbody></table></div>
                    {logs.data.length === 0 && <div className="grid place-items-center py-16 text-center"><ScrollText size={32} className="text-stone-300" /><p className="mt-3 font-bold">Belum ada aktivitas tercatat</p><p className="mt-1 text-sm text-stone-500">Perubahan administratif akan muncul secara otomatis.</p></div>}
                </section>
                <Pagination data={logs} label="aktivitas" />
            </PageShell>
        </AuthenticatedLayout>
    );
}
