import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Award, BookOpenCheck, Medal, Sparkles, Trophy, Zap } from 'lucide-react';

const podiumTone = ['border-amber-300 bg-amber-50', 'border-stone-300 bg-stone-50', 'border-orange-200 bg-orange-50'];

export default function Leaderboard({ leaders }) {
    const podium = leaders.slice(0, 3);
    const remaining = leaders.slice(3);

    return (
        <AuthenticatedLayout header={<PageHeader eyebrow="Kontribusi komunitas" title="Leaderboard" description="Apresiasi bagi anggota yang memperkaya knowledge dan menjaga layanan AI tetap tersedia." icon={Trophy} />}>
            <Head title="Leaderboard" />
            <PageShell size="default">
                <section className="overflow-hidden rounded-card bg-brand-950 p-6 text-white shadow-[0_18px_45px_rgba(16,28,23,0.18)] sm:p-8">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><div className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-brand-300"><Sparkles size={15} />Dibangun bersama</div><h2 className="mt-3 max-w-xl text-2xl font-bold sm:text-3xl">Setiap kontribusi membuat ruang belajar lebih kuat.</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-brand-200">Poin berasal dari knowledge yang disetujui dan respons AI yang berhasil didukung credential.</p></div><Trophy size={64} strokeWidth={1.2} className="hidden text-brand-500 sm:block" /></div>
                </section>

                {podium.length > 0 && <section className="mt-6 grid gap-4 md:grid-cols-3" aria-label="Tiga kontributor teratas">{podium.map((leader, index) => <article key={leader.id} className={`relative rounded-card border p-5 text-center shadow-sm ${podiumTone[index]}`}><span className="absolute left-4 top-4 grid h-8 w-8 place-items-center rounded-full bg-white text-sm font-bold shadow-sm">{index + 1}</span>{leader.avatar_url ? <img src={leader.avatar_url} alt="" className="mx-auto h-16 w-16 rounded-xl object-cover ring-4 ring-white" /> : <span className="mx-auto grid h-16 w-16 place-items-center rounded-xl bg-brand-800 text-xl font-bold text-white ring-4 ring-white">{leader.name.charAt(0)}</span>}<Medal className={`mx-auto mt-4 ${index === 0 ? 'text-amber-600' : 'text-stone-500'}`} size={22} /><h3 className="mt-2 truncate font-bold text-ink">{leader.name}</h3><p className="mt-1 text-2xl font-bold text-brand-800">{leader.score}<span className="ml-1 text-xs font-medium text-stone-500">poin</span></p><div className="mt-4 flex flex-wrap justify-center gap-1.5">{leader.badges.map((badge) => <span key={badge} className="rounded-full bg-white/80 px-2 py-1 text-[11px] font-bold text-brand-800">{badge}</span>)}</div></article>)}</section>}

                {remaining.length > 0 && <section className="mt-6 overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-stone-200 px-5 py-4 sm:px-6"><div><h2 className="font-bold">Peringkat komunitas</h2><p className="mt-1 text-xs text-stone-500">Diperbarui dari kontribusi yang telah tervalidasi.</p></div><Award size={20} className="text-brand-700" /></div>
                    <div className="divide-y divide-stone-100">{remaining.map((leader, index) => <article key={leader.id} className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:px-6"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-stone-100 text-sm font-bold text-stone-600">{index + 4}</span><div className="min-w-0 flex-1"><p className="font-bold text-ink">{leader.name}</p><div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500"><span className="inline-flex items-center gap-1"><BookOpenCheck size={13} />{leader.approved_knowledge_count} knowledge</span><span className="inline-flex items-center gap-1"><Zap size={13} />{leader.credential_successes} respons</span></div></div><div className="flex flex-wrap items-center gap-2">{leader.badges.slice(0, 2).map((badge) => <span key={badge} className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800">{badge}</span>)}<strong className="ml-auto min-w-20 text-right text-brand-800">{leader.score} poin</strong></div></article>)}</div>
                </section>}
                {leaders.length === 0 && <div className="mt-6 rounded-card border border-dashed border-stone-300 bg-white py-16 text-center"><Trophy size={32} className="mx-auto text-stone-300" /><p className="mt-3 font-bold">Peringkat belum tersedia</p><p className="mt-1 text-sm text-stone-500">Jadilah kontributor pertama.</p></div>}
            </PageShell>
        </AuthenticatedLayout>
    );
}
