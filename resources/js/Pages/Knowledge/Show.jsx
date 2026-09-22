import MarkdownContent from '@/Components/MarkdownContent';
import FlashBanner from '@/Components/FlashBanner';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Clock3, FilePenLine, Save, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

const visibilityLabels = { private: 'Pribadi', course: 'Mata kuliah', community: 'Komunitas' };

export default function Show({ knowledge, courses, canEdit, canDelete, flash }) {
    const active = knowledge.versions.find((version) => version.id === knowledge.active_version_id) || knowledge.versions[0];
    const [editing, setEditing] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        _method: 'patch', title: knowledge.title, description: knowledge.description || '',
        context: knowledge.course_id ? 'course' : 'general', course_id: knowledge.course_id || '',
        visibility: knowledge.visibility, content: active?.content || '', file: null,
    });
    const submit = (event) => { event.preventDefault(); post(route('knowledge.update', knowledge.id), { forceFormData: true, preserveScroll: true, onSuccess: () => setEditing(false) }); };
    const remove = () => { if (window.confirm('Hapus knowledge beserta seluruh versinya?')) router.delete(route('knowledge.destroy', knowledge.id)); };

    return (
        <AuthenticatedLayout>
            <Head title={knowledge.title} />
            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <FlashBanner message={flash?.status} />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={route('knowledge.index')} className="text-sm font-semibold text-brand-700">← Kembali ke library</Link>
                    <div className="flex gap-2">
                        {canEdit && <button onClick={() => setEditing((value) => !value)} className="inline-flex items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-bold"><FilePenLine size={16} />{editing ? 'Tutup editor' : 'Edit knowledge'}</button>}
                        {canDelete && <button onClick={remove} className="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700"><Trash2 size={16} />Hapus</button>}
                    </div>
                </div>

                {editing && <form onSubmit={submit} className="mt-6 rounded-card border border-brand-200 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-bold">Buat versi baru</h2><p className="mt-1 text-sm text-stone-500">Perubahan isi disimpan sebagai versi baru. Kontribusi bersama akan masuk antrean review.</p>
                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-bold">Judul<input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border-stone-300" /></label>
                        <label className="text-sm font-bold">Konteks<select value={data.context} onChange={(e) => setData('context', e.target.value)} className="mt-1 w-full rounded-lg border-stone-300"><option value="general">Umum</option><option value="course">Mata kuliah</option></select></label>
                        {data.context === 'course' && <label className="text-sm font-bold">Mata kuliah<select value={data.course_id} onChange={(e) => setData('course_id', e.target.value)} className="mt-1 w-full rounded-lg border-stone-300"><option value="">Pilih</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.code} — {course.name}</option>)}</select></label>}
                        <label className="text-sm font-bold">Visibilitas<select value={data.visibility} onChange={(e) => setData('visibility', e.target.value)} className="mt-1 w-full rounded-lg border-stone-300"><option value="private">Pribadi</option><option value={data.context === 'course' ? 'course' : 'community'}>{data.context === 'course' ? 'Mata kuliah' : 'Komunitas'}</option></select></label>
                    </div>
                    <label className="mt-4 block text-sm font-bold">Deskripsi<textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows="2" className="mt-1 w-full rounded-lg border-stone-300" /></label>
                    <label className="mt-4 block text-sm font-bold">Isi Markdown<textarea value={data.content} onChange={(e) => setData('content', e.target.value)} rows="14" className="mt-1 w-full rounded-lg border-stone-300 font-mono text-sm" /></label>
                    <label className="mt-4 flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-stone-300 p-4 text-sm font-bold"><Upload size={17} />Ganti dari file .md<input type="file" accept=".md,text/markdown,text/plain" onChange={(e) => setData('file', e.target.files[0])} className="sr-only" /><span className="ml-auto font-normal text-stone-500">{data.file?.name || 'Opsional'}</span></label>
                    {Object.values(errors).length > 0 && <p className="mt-3 text-sm text-red-700">{Object.values(errors)[0]}</p>}
                    <button disabled={processing} className="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-800 px-5 py-3 font-bold text-white disabled:opacity-50"><Save size={17} />Simpan versi baru</button>
                </form>}

                <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <article className="rounded-card border border-stone-200 bg-white px-6 py-8 sm:px-10"><div className="flex flex-wrap items-center gap-3 text-sm text-stone-500"><span>{visibilityLabels[knowledge.visibility] || knowledge.visibility}</span><span>·</span><span>{knowledge.course?.name || 'Umum'}</span><StatusBadge status={knowledge.status} /></div><h1 className="mt-5 font-display text-3xl font-bold leading-tight sm:text-4xl">{knowledge.title}</h1>{knowledge.description && <p className="mt-4 text-stone-600">{knowledge.description}</p>}<div className="my-8 border-t border-stone-200" /><MarkdownContent content={active?.content || ''} /></article>
                    <aside className="rounded-card border border-stone-200 bg-white p-5 lg:self-start"><div className="flex items-center gap-2"><Clock3 size={17} className="text-brand-700" /><h2 className="font-bold">Riwayat versi</h2></div><div className="mt-4 space-y-3">{knowledge.versions.map((version) => <div key={version.id} className={`rounded-lg border p-3 ${version.id === knowledge.active_version_id ? 'border-brand-300 bg-brand-50' : 'border-stone-200'}`}><div className="flex justify-between gap-2"><strong className="text-sm">Versi {version.version}</strong><StatusBadge status={version.status} /></div><p className="mt-1 text-xs text-stone-500">{version.processing_status || 'pending'} · {new Date(version.created_at).toLocaleDateString('id-ID')}</p></div>)}</div></aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
