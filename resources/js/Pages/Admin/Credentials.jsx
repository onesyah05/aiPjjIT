import FlashBanner from '@/Components/FlashBanner';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { HeartPulse, KeyRound, RefreshCw, ShieldCheck } from 'lucide-react';

export default function Credentials({ credentials, flash }) {
    const activeCount = credentials.data.filter((item) => item.status === 'active').length;

    return (
        <AuthenticatedLayout header={<PageHeader eyebrow="Operasional AI" title="Credential health" description="Periksa ketersediaan credential komunitas tanpa membuka atau mengekspos secret." icon={HeartPulse} />}>
            <Head title="Credential health" />
            <PageShell>
                <FlashBanner message={flash?.status} />
                <div className="mb-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="flex items-start gap-3 rounded-card border border-brand-200 bg-brand-50 p-4 text-sm text-brand-950"><ShieldCheck size={20} className="mt-0.5 shrink-0 text-brand-700" /><div><p className="font-bold">Secret tetap terlindungi</p><p className="mt-1 leading-6 text-brand-800">Pemeriksaan dilakukan dari backend. Admin hanya melihat label, pemilik, preview tersamarkan, dan hasil koneksi.</p></div></div>
                    <div className="rounded-card border border-stone-200 bg-white p-4"><p className="text-sm text-stone-500">Aktif di halaman ini</p><p className="mt-1 text-3xl font-bold text-ink">{activeCount}<span className="ml-1 text-base font-medium text-stone-400">/ {credentials.data.length}</span></p></div>
                </div>

                <div className="grid gap-4">
                    {credentials.data.map((item) => <article key={item.id} className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-center">
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-800"><KeyRound size={21} strokeWidth={1.8} /></span>
                            <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="font-bold text-ink">{item.label}</h2><StatusBadge status={item.status} /></div><p className="mt-1 text-sm text-stone-500">{item.user?.name} · <span className="font-mono">{item.masked_preview}</span> · {item.provider}</p></div>
                            <dl className="grid grid-cols-3 gap-4 rounded-lg bg-stone-50 px-4 py-3 text-center lg:min-w-72"><div><dt className="text-[11px] text-stone-500">Sukses</dt><dd className="mt-1 font-bold tabular-nums text-emerald-700">{item.success_count}</dd></div><div><dt className="text-[11px] text-stone-500">Gagal</dt><dd className="mt-1 font-bold tabular-nums text-red-700">{item.failure_count}</dd></div><div><dt className="text-[11px] text-stone-500">Divalidasi</dt><dd className="mt-1 text-xs font-bold text-ink">{item.validated_at ? new Date(item.validated_at).toLocaleDateString('id-ID') : 'Belum'}</dd></div></dl>
                            <div className="flex gap-2"><button onClick={() => router.post(route('admin.credentials.validate', item.id), {}, { preserveScroll: true })} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-stone-300 px-3 text-sm font-bold hover:border-brand-400 hover:text-brand-800"><RefreshCw size={16} /> Uji ulang</button><label><span className="sr-only">Status {item.label}</span><select value={item.status} onChange={(event) => router.patch(route('admin.credentials.update', item.id), { status: event.target.value }, { preserveScroll: true })} className="min-h-10 rounded-lg border-stone-300 text-sm"><option value="active">Aktif</option><option value="disabled">Nonaktif</option><option value="invalid">Invalid</option><option value="cooldown">Cooldown</option></select></label></div>
                        </div>
                    </article>)}
                    {credentials.data.length === 0 && <div className="rounded-card border border-dashed border-stone-300 bg-white px-6 py-16 text-center"><KeyRound size={32} className="mx-auto text-stone-300" /><p className="mt-3 font-bold">Belum ada credential</p><p className="mt-1 text-sm text-stone-500">Credential komunitas akan tampil di sini setelah disumbangkan pengguna.</p></div>}
                </div>
                <Pagination data={credentials} label="credential" />
            </PageShell>
        </AuthenticatedLayout>
    );
}
