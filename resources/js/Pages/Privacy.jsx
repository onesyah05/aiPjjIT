import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, usePage } from '@inertiajs/react';
import { Bot, KeyRound, MessageCircle, ShieldCheck } from 'lucide-react';

const sections = [
    { title: 'Data Discord', icon: ShieldCheck, content: 'Kami menyimpan identitas Discord, role yang relevan, dan waktu pemeriksaan membership. Token OAuth disimpan terenkripsi dan hanya digunakan backend untuk memeriksa akses.' },
    { title: 'Percakapan dan knowledge', icon: MessageCircle, content: 'Percakapan disimpan sebagai riwayat belajar. Knowledge pribadi hanya dapat dipakai pemilik; knowledge mata kuliah atau komunitas dapat dibaca anggota lain setelah disetujui reviewer.' },
    { title: 'Data yang dikirim ke Gemini', icon: Bot, content: 'Pertanyaan, konteks percakapan terbaru, instruksi sistem, dan potongan knowledge relevan dapat dikirim ke provider. Token Discord, credential lain, dan log admin tidak ikut dikirim.' },
    { title: 'Kontribusi credential', icon: KeyRound, content: 'Credential bersifat sukarela, terenkripsi, dan tidak ditampilkan kembali. Pemakaian dapat mengurangi kuota atau menimbulkan billing pada project provider. Kontributor dapat membatasi, menonaktifkan, atau menghapus credential kapan saja.' },
];

function Content() {
    return <article className="overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm"><div className="grid divide-y divide-stone-200">{sections.map((section, index) => { const Icon = section.icon; return <section key={section.title} className="grid gap-4 p-5 sm:grid-cols-[3rem_13rem_minmax(0,1fr)] sm:items-start sm:p-6"><span className="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-700"><Icon size={19} strokeWidth={1.8} /></span><div><p className="text-xs font-bold uppercase tracking-wider text-stone-400">Bagian {index + 1}</p><h2 className="mt-1 font-bold text-ink">{section.title}</h2></div><p className="text-sm leading-7 text-stone-600">{section.content}</p></section>; })}</div><div className="border-t border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950 sm:px-6"><strong>Catatan penting:</strong> AI dapat menghasilkan jawaban yang keliru. Verifikasi materi penting pada sumber akademik asli.</div></article>;
}

export default function Privacy() {
    const authenticated = Boolean(usePage().props.auth?.user);
    const header = <PageHeader eyebrow="Kepercayaan & keamanan" title="Privasi dan kontribusi" description="Ketahui data apa yang digunakan, mengapa dibutuhkan, dan kendali yang tetap Anda miliki." icon={ShieldCheck} />;

    if (authenticated) return <AuthenticatedLayout header={header}><Head title="Privasi dan kontribusi" /><PageShell size="default"><Content /></PageShell></AuthenticatedLayout>;

    return <GuestLayout wide><Head title="Privasi dan kontribusi" /><div className="mb-6">{header}</div><Content /></GuestLayout>;
}
