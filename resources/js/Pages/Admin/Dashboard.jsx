import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import StatCard from '@/Components/StatCard';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, BookOpen, Gauge, KeyRound, ShieldAlert, Users } from 'lucide-react';

export default function Dashboard({ stats, credentialHealth, recentRequests, dailyRequests }) {
    const maxDailyRequests = Math.max(...dailyRequests.map((day) => day.total), 1);

    return (
        <AuthenticatedLayout header={<PageHeader eyebrow="Pusat kendali" title="Ringkasan admin" description="Pantau kesehatan layanan, aktivitas AI, dan pekerjaan moderasi dari satu tempat." icon={Gauge} actions={<><Link href={route('admin.users.index')} className="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-bold text-stone-700 hover:border-brand-400 hover:text-brand-800">Kelola pengguna</Link><Link href={route('admin.audit-logs.index')} className="rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-900">Buka audit log</Link></>} />}>
            <Head title="Admin dashboard" />
            <PageShell>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan sistem">
                    <StatCard label="Pengguna aktif" value={`${stats.active_users}/${stats.users}`} help="Akun yang dapat mengakses workspace" icon={Users} />
                    <StatCard label="Knowledge" value={stats.knowledge} help={`${stats.pending_knowledge} menunggu review`} icon={BookOpen} tone="green" />
                    <StatCard label="Request hari ini" value={stats.requests_today} help="Chat dan proses AI tercatat" icon={Activity} tone="amber" />
                    <StatCard label="Gagal hari ini" value={stats.failed_today} help="Perlu diperiksa jika terus meningkat" icon={ShieldAlert} tone={stats.failed_today > 0 ? 'red' : 'stone'} />
                </section>

                <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_22rem]">
                    <section className="overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between gap-4 border-b border-stone-200 px-5 py-4 sm:px-6">
                            <div><h2 className="font-bold text-ink">Aktivitas AI terbaru</h2><p className="mt-1 text-xs text-stone-500">10 request terakhir dari seluruh pengguna.</p></div>
                            <Activity size={19} className="text-brand-700" aria-hidden="true" />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[620px] text-left text-sm">
                                <thead className="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th className="px-5 py-3 font-semibold sm:px-6">Pengguna</th><th className="px-4 py-3 font-semibold">Operasi</th><th className="px-4 py-3 font-semibold">Status</th><th className="px-5 py-3 text-right font-semibold sm:px-6">Latensi</th></tr></thead>
                                <tbody>{recentRequests.map((item) => <tr key={item.id} className="border-t border-stone-100"><td className="px-5 py-3.5 font-semibold sm:px-6">{item.user?.name || 'Sistem'}</td><td className="px-4 py-3.5 capitalize text-stone-600">{item.operation}</td><td className="px-4 py-3.5"><StatusBadge status={item.status} /></td><td className="px-5 py-3.5 text-right tabular-nums text-stone-500 sm:px-6">{item.latency_ms ? `${item.latency_ms} ms` : '—'}</td></tr>)}</tbody>
                            </table>
                            {recentRequests.length === 0 && <p className="px-6 py-12 text-center text-sm text-stone-500">Belum ada request AI tercatat.</p>}
                        </div>
                    </section>

                    <div className="space-y-6">
                        <section className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center gap-2"><KeyRound size={18} className="text-brand-700" /><h2 className="font-bold">Kesehatan credential</h2></div>
                            <dl className="mt-4 space-y-2">{Object.entries(credentialHealth).map(([status, total]) => <div key={status} className="flex items-center justify-between rounded-lg bg-stone-50 px-3 py-2.5 text-sm"><dt className="capitalize text-stone-600">{status}</dt><dd className="font-bold tabular-nums">{total}</dd></div>)}</dl>
                            <Link href={route('admin.credentials.index')} className="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:text-brand-900">Buka pemeriksaan <ArrowRight size={15} /></Link>
                        </section>

                        <section className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                            <div><h2 className="font-bold">Tren 7 hari</h2><p className="mt-1 text-xs text-stone-500">Jumlah request per hari.</p></div>
                            <div className="mt-5 flex h-32 items-end gap-2" aria-label="Grafik request tujuh hari terakhir">{dailyRequests.map((day) => <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[10px] font-semibold tabular-nums text-stone-500">{day.total}</span><div title={`${day.total} request`} className="w-full rounded-t bg-brand-500" style={{ height: `${Math.max(8, (day.total / maxDailyRequests) * 82)}%` }} /><span className="text-[10px] text-stone-500">{day.date.slice(5)}</span></div>)}</div>
                            {dailyRequests.length === 0 && <p className="mt-5 text-sm text-stone-500">Belum ada data mingguan.</p>}
                        </section>
                    </div>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
