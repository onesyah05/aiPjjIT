import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { BookOpenText, Check, FileText, GraduationCap, LockKeyhole, Upload, UsersRound, X } from 'lucide-react';
import { useRef, useState } from 'react';

const fieldClassName = 'mt-2 w-full rounded-lg border-stone-300 bg-white text-sm text-ink placeholder:text-stone-400 focus:border-brand-600 focus:ring-brand-600';

function OptionCard({ checked, description, icon: Icon, label, name, onChange, value }) {
    return (
        <label className={`relative flex cursor-pointer gap-3 rounded-lg border p-3.5 transition-colors focus-within:ring-2 focus-within:ring-brand-600 focus-within:ring-offset-2 ${checked ? 'border-brand-700 bg-brand-50' : 'border-stone-200 bg-white hover:border-stone-300'}`}>
            <input type="radio" name={name} value={value} checked={checked} onChange={() => onChange(value)} className="sr-only" />
            <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${checked ? 'bg-brand-800 text-white' : 'bg-stone-100 text-stone-600'}`}>
                <Icon size={18} strokeWidth={1.8} aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="flex items-center gap-2 text-sm font-semibold text-ink">
                    {label}
                    {checked && <Check size={15} strokeWidth={2.5} className="text-brand-700" aria-hidden="true" />}
                </span>
                <span className="mt-0.5 block text-xs leading-5 text-stone-500">{description}</span>
            </span>
        </label>
    );
}

export default function KnowledgeFormDialog({ courses, open, onClose }) {
    const [inputMode, setInputMode] = useState('text');
    const fileInput = useRef(null);
    const form = useForm({
        title: '',
        description: '',
        context: 'general',
        course_id: '',
        visibility: 'private',
        content: '',
        file: null,
    });

    const sharedVisibility = form.data.context === 'course' ? 'course' : 'community';
    const sharedLabel = form.data.context === 'course' ? 'Mata kuliah' : 'Komunitas';

    const changeContext = (context) => {
        form.setData({
            ...form.data,
            context,
            course_id: context === 'general' ? '' : form.data.course_id,
            visibility: 'private',
        });
    };

    const changeInputMode = (mode) => {
        setInputMode(mode);

        if (mode === 'upload') {
            form.setData('content', '');
            return;
        }

        form.setData('file', null);
        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(route('knowledge.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInputMode('text');
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
                onClose();
            },
        });
    };

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50">
            <DialogBackdrop transition className="fixed inset-0 bg-stone-950/55 transition-opacity duration-200 data-[closed]:opacity-0" />
            <div className="fixed inset-0 overflow-y-auto p-3 sm:p-6">
                <div className="flex min-h-full items-center justify-center">
                    <DialogPanel transition className="w-full max-w-5xl rounded-card bg-white shadow-xl transition duration-200 data-[closed]:translate-y-3 data-[closed]:opacity-0">
                        <form onSubmit={submit}>
                            <div className="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-5 sm:px-7">
                                <div>
                                    <p className="text-sm font-semibold text-brand-700">Kontribusi materi</p>
                                    <DialogTitle className="mt-1 font-display text-2xl font-bold text-ink">Tambah knowledge</DialogTitle>
                                    <p className="mt-1 text-sm text-stone-500">Tulis materi langsung atau unggah dokumen Markdown.</p>
                                </div>
                                <button type="button" onClick={onClose} className="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-ink" aria-label="Tutup formulir">
                                    <X size={21} aria-hidden="true" />
                                </button>
                            </div>

                            <div className="grid gap-7 px-5 py-6 sm:px-7 lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]">
                                <div className="space-y-6">
                                    <fieldset>
                                        <legend className="text-sm font-semibold text-stone-800">1. Konteks materi</legend>
                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            <OptionCard name="context" value="general" label="Umum" description="Lintas mata kuliah" icon={BookOpenText} checked={form.data.context === 'general'} onChange={changeContext} />
                                            <OptionCard name="context" value="course" label="Mata kuliah" description="Materi perkuliahan" icon={GraduationCap} checked={form.data.context === 'course'} onChange={changeContext} />
                                        </div>
                                        {form.errors.context && <p className="mt-2 text-sm text-red-700">{form.errors.context}</p>}
                                    </fieldset>

                                    {form.data.context === 'course' && (
                                        <label className="block text-sm font-semibold text-stone-800">
                                            Pilih mata kuliah
                                            <select value={form.data.course_id} onChange={(event) => form.setData('course_id', event.target.value)} className={fieldClassName} required>
                                                <option value="">Pilih mata kuliah</option>
                                                {courses.map((course) => <option key={course.id} value={course.id}>{course.code} — {course.name}</option>)}
                                            </select>
                                            {form.errors.course_id && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.course_id}</span>}
                                        </label>
                                    )}

                                    <label className="block text-sm font-semibold text-stone-800">
                                        Judul
                                        <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={fieldClassName} placeholder="Contoh: Dasar algoritma pencarian" required />
                                        {form.errors.title && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.title}</span>}
                                    </label>

                                    <label className="block text-sm font-semibold text-stone-800">
                                        Deskripsi <span className="font-normal text-stone-400">(opsional)</span>
                                        <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="3" className={fieldClassName} placeholder="Ringkasan singkat materi" />
                                        {form.errors.description && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.description}</span>}
                                    </label>

                                    <fieldset>
                                        <legend className="text-sm font-semibold text-stone-800">2. Cakupan akses</legend>
                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            <OptionCard name="visibility" value="private" label="Pribadi" description="Hanya kamu" icon={LockKeyhole} checked={form.data.visibility === 'private'} onChange={(visibility) => form.setData('visibility', visibility)} />
                                            <OptionCard name="visibility" value={sharedVisibility} label={sharedLabel} description="Dibagikan setelah review" icon={UsersRound} checked={form.data.visibility === sharedVisibility} onChange={(visibility) => form.setData('visibility', visibility)} />
                                        </div>
                                        {form.errors.visibility && <p className="mt-2 text-sm text-red-700">{form.errors.visibility}</p>}
                                    </fieldset>
                                </div>

                                <fieldset className="min-w-0">
                                    <legend className="text-sm font-semibold text-stone-800">3. Isi knowledge</legend>
                                    <div className="mt-3 grid grid-cols-2 rounded-lg bg-stone-100 p-1" role="tablist" aria-label="Cara menambahkan isi knowledge">
                                        <button type="button" role="tab" aria-selected={inputMode === 'text'} onClick={() => changeInputMode('text')} className={`flex min-h-10 items-center justify-center gap-2 rounded-md px-3 text-sm font-semibold transition-colors ${inputMode === 'text' ? 'bg-white text-ink shadow-sm' : 'text-stone-500 hover:text-stone-800'}`}>
                                            <FileText size={17} strokeWidth={1.8} aria-hidden="true" />
                                            Tulis langsung
                                        </button>
                                        <button type="button" role="tab" aria-selected={inputMode === 'upload'} onClick={() => changeInputMode('upload')} className={`flex min-h-10 items-center justify-center gap-2 rounded-md px-3 text-sm font-semibold transition-colors ${inputMode === 'upload' ? 'bg-white text-ink shadow-sm' : 'text-stone-500 hover:text-stone-800'}`}>
                                            <Upload size={17} strokeWidth={1.8} aria-hidden="true" />
                                            Unggah .md
                                        </button>
                                    </div>

                                    {inputMode === 'text' ? (
                                        <label className="mt-3 block text-sm font-medium text-stone-700">
                                            Isi materi
                                            <textarea value={form.data.content} onChange={(event) => form.setData('content', event.target.value)} rows="16" placeholder="Tulis teks biasa atau Markdown di sini…" className={`${fieldClassName} min-h-96 resize-y font-mono leading-6`} required />
                                            <span className="mt-2 block text-xs font-normal text-stone-500">Teks biasa dan sintaks Markdown sama-sama didukung.</span>
                                        </label>
                                    ) : (
                                        <label className="mt-3 flex min-h-96 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 px-6 py-8 text-center transition-colors hover:border-brand-500 hover:bg-brand-50/50">
                                            <span className="grid h-12 w-12 place-items-center rounded-lg bg-white text-brand-800 shadow-sm ring-1 ring-stone-200">
                                                <Upload size={23} strokeWidth={1.8} aria-hidden="true" />
                                            </span>
                                            <span className="mt-4 text-sm font-semibold text-ink">Pilih file Markdown</span>
                                            <span className="mt-1 text-xs text-stone-500">Hanya .md, maksimal 2 MB</span>
                                            {form.data.file && <span className="mt-4 max-w-full truncate rounded-md bg-brand-100 px-3 py-1.5 text-xs font-semibold text-brand-900">{form.data.file.name}</span>}
                                            <input ref={fileInput} type="file" accept=".md,text/markdown,text/plain" onChange={(event) => form.setData('file', event.target.files?.[0] || null)} className="sr-only" required />
                                        </label>
                                    )}
                                    {(form.errors.content || form.errors.file) && <p className="mt-2 text-sm text-red-700">{form.errors.content || form.errors.file}</p>}
                                </fieldset>
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-stone-200 bg-stone-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                                <p className="text-xs leading-5 text-stone-500">Materi bersama akan masuk antrean review sebelum dapat digunakan anggota lain.</p>
                                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                                    <button type="button" onClick={onClose} className="min-h-11 rounded-lg border border-stone-300 bg-white px-5 text-sm font-semibold text-stone-700 hover:bg-stone-100">Batal</button>
                                    <button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-900 disabled:cursor-wait disabled:opacity-60">
                                        {form.processing ? 'Menyimpan…' : 'Simpan knowledge'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
