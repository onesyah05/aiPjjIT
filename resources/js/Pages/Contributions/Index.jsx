import EmptyState from '@/Components/EmptyState';
import FlashBanner from '@/Components/FlashBanner';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';

export default function Index({ credentials, flash }) {
    const form = useForm({ label: '', secret: '', daily_request_limit: '', community_enabled: true, consent: false });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('ai-credentials.store'), { onSuccess: () => form.reset() });
    };

    const toggle = (credential) => router.patch(
        route('ai-credentials.update', credential.id),
        { status: credential.status === 'active' ? 'disabled' : 'active' },
        { preserveScroll: true },
    );

    const fieldClassName = 'mt-2 w-full rounded-lg border-stone-300 bg-white text-sm text-ink placeholder:text-stone-400 focus:border-brand-600 focus:ring-brand-600';

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Resource bersama" title="Kontribusi Gemini" description="Kelola credential yang Anda bagikan, batas pemakaian, dan kesehatan kontribusi secara transparan." icon={KeyRound} />}
        >
            <Head title="Kontribusi" />

            <PageShell size="default">
                <FlashBanner message={flash?.status} />
                <div className="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <form onSubmit={submit} className="h-fit rounded-card border border-stone-200 bg-white p-6 shadow-sm lg:sticky lg:top-6">
                    <h2 className="font-display text-xl font-bold text-ink">Donasikan credential</h2>
                    <p className="mt-2 text-sm leading-6 text-stone-600">Credential dipakai backend untuk permintaan komunitas, disimpan terenkripsi, dan tidak pernah dikirim kembali ke browser.</p>

                    <div className="mt-6 space-y-5">
                        <label className="block text-sm font-medium text-stone-700">
                            Label
                            <input value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} placeholder="Contoh: Project belajar" className={fieldClassName} required />
                        </label>
                        {form.errors.label && <p className="text-sm text-red-700">{form.errors.label}</p>}

                        <label className="block text-sm font-medium text-stone-700">
                            Gemini API key
                            <input type="password" value={form.data.secret} onChange={(event) => form.setData('secret', event.target.value)} autoComplete="off" className={fieldClassName} required />
                        </label>
                        {form.errors.secret && <p className="text-sm text-red-700">{form.errors.secret}</p>}

                        <label className="block text-sm font-medium text-stone-700">
                            Batas request per hari <span className="font-normal text-stone-500">(opsional)</span>
                            <input type="number" min="1" value={form.data.daily_request_limit} onChange={(event) => form.setData('daily_request_limit', event.target.value)} className={fieldClassName} />
                        </label>

                        <label className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                            <input type="checkbox" checked={form.data.consent} onChange={(event) => form.setData('consent', event.target.checked)} className="mt-1 rounded border-amber-400 text-brand-700 focus:ring-brand-600" />
                            <span>Saya memahami credential akan dipakai untuk permintaan AI komunitas, dapat menggunakan kuota atau billing project saya, dan dapat dinonaktifkan kapan saja.</span>
                        </label>
                        {form.errors.consent && <p className="text-sm text-red-700">{form.errors.consent}</p>}

                        <button type="submit" disabled={form.processing} className="w-full rounded-lg bg-brand-700 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50">
                            {form.processing ? 'Memvalidasi…' : 'Validasi dan simpan'}
                        </button>
                    </div>
                </form>

                <section aria-labelledby="credentials-heading">
                    <div className="border-b border-stone-200 pb-4">
                        <h2 id="credentials-heading" className="font-display text-2xl font-bold text-ink">Credential saya</h2>
                        <p className="mt-1 text-sm text-stone-600">Raw secret tidak dapat dilihat kembali setelah disimpan.</p>
                    </div>

                    {credentials.length > 0 ? (
                        <div className="mt-5 space-y-4">
                            {credentials.map((credential) => (
                                <article key={credential.id} className="rounded-card border border-stone-200 bg-white p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <h3 className="font-semibold text-ink">{credential.label}</h3>
                                            <p className="mt-1 font-mono text-xs text-stone-500">{credential.masked_preview} · {credential.provider}</p>
                                        </div>
                                        <StatusBadge status={credential.status} />
                                    </div>

                                    <dl className="mt-5 grid grid-cols-3 gap-4 border-y border-stone-200 py-4">
                                        <div><dt className="text-xs text-stone-500">Berhasil</dt><dd className="mt-1 text-lg font-semibold text-ink">{credential.success_count}</dd></div>
                                        <div><dt className="text-xs text-stone-500">Gagal</dt><dd className="mt-1 text-lg font-semibold text-ink">{credential.failure_count}</dd></div>
                                        <div><dt className="text-xs text-stone-500">Limit per hari</dt><dd className="mt-1 text-lg font-semibold text-ink">{credential.daily_request_limit || 'Tanpa batas'}</dd></div>
                                    </dl>

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <button type="button" onClick={() => toggle(credential)} className="rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                                            {credential.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}
                                        </button>
                                        <button type="button" onClick={() => window.confirm('Hapus credential ini? Tindakan ini tidak dapat dibatalkan.') && router.delete(route('ai-credentials.destroy', credential.id))} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Hapus
                                        </button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="mt-5"><EmptyState title="Belum ada credential" description="Credential yang berhasil divalidasi akan muncul di sini beserta status dan pemakaiannya." /></div>
                    )}
                </section>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
