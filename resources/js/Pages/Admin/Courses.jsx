import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { BookOpenText, CheckCircle2, CirclePlus, Edit3, GraduationCap, MessageCircle, Power, Trash2, TriangleAlert, X } from 'lucide-react';
import { useState } from 'react';

const fieldClassName = 'mt-2 w-full rounded-lg border-stone-300 bg-white text-sm text-ink placeholder:text-stone-400 focus:border-brand-600 focus:ring-brand-600';

function CourseFormDialog({ course, open, onClose }) {
    const form = useForm({
        code: course?.code || '',
        name: course?.name || '',
        semester: course?.semester || '',
        description: course?.description || '',
        status: course?.status || 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };

        if (course) {
            form.patch(route('admin.courses.update', course.id), options);
            return;
        }

        form.post(route('admin.courses.store'), options);
    };

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50">
            <DialogBackdrop transition className="fixed inset-0 bg-stone-950/55 transition-opacity duration-200 data-[closed]:opacity-0" />
            <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                <div className="flex min-h-full items-center justify-center">
                    <DialogPanel transition className="w-full max-w-2xl rounded-card bg-white shadow-xl transition duration-200 data-[closed]:translate-y-3 data-[closed]:opacity-0">
                        <form onSubmit={submit}>
                            <div className="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-5 sm:px-6">
                                <div>
                                    <p className="text-sm font-semibold text-brand-700">{course ? 'Ubah data' : 'Mata kuliah baru'}</p>
                                    <DialogTitle className="mt-1 font-display text-2xl font-bold text-ink">{course ? course.name : 'Tambah mata kuliah'}</DialogTitle>
                                </div>
                                <button type="button" onClick={onClose} className="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-ink" aria-label="Tutup formulir">
                                    <X size={21} aria-hidden="true" />
                                </button>
                            </div>

                            <div className="grid gap-5 px-5 py-6 sm:grid-cols-[10rem_minmax(0,1fr)] sm:px-6">
                                <label className="block text-sm font-semibold text-stone-800">
                                    Kode
                                    <input value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} className={fieldClassName} placeholder="IF101" required />
                                    {form.errors.code && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.code}</span>}
                                </label>

                                <label className="block text-sm font-semibold text-stone-800">
                                    Nama mata kuliah
                                    <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={fieldClassName} placeholder="Algoritma dan Pemrograman" required />
                                    {form.errors.name && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.name}</span>}
                                </label>

                                <label className="block text-sm font-semibold text-stone-800">
                                    Semester
                                    <input type="number" min="1" max="14" value={form.data.semester} onChange={(event) => form.setData('semester', event.target.value)} className={fieldClassName} placeholder="1" />
                                    {form.errors.semester && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.semester}</span>}
                                </label>

                                <label className="block text-sm font-semibold text-stone-800">
                                    Status
                                    <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={fieldClassName}>
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Nonaktif</option>
                                    </select>
                                    {form.errors.status && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.status}</span>}
                                </label>

                                <label className="block text-sm font-semibold text-stone-800 sm:col-span-2">
                                    Deskripsi <span className="font-normal text-stone-400">(opsional)</span>
                                    <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="4" className={fieldClassName} placeholder="Jelaskan ruang lingkup mata kuliah." />
                                    {form.errors.description && <span className="mt-2 block text-sm font-normal text-red-700">{form.errors.description}</span>}
                                </label>
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-stone-200 bg-stone-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                                <button type="button" onClick={onClose} className="min-h-11 rounded-lg border border-stone-300 bg-white px-5 text-sm font-semibold text-stone-700 hover:bg-stone-100">Batal</button>
                                <button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-900 disabled:cursor-wait disabled:opacity-60">
                                    {form.processing ? 'Menyimpan…' : course ? 'Simpan perubahan' : 'Tambah mata kuliah'}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}

function DeleteCourseDialog({ course, onClose }) {
    const form = useForm({});

    if (!course) {
        return null;
    }

    const destroy = () => {
        form.delete(route('admin.courses.destroy', course.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const linkedItems = course.knowledges_count + course.conversations_count;

    return (
        <Dialog open onClose={() => !form.processing && onClose()} className="relative z-50">
            <DialogBackdrop transition className="fixed inset-0 bg-stone-950/55 transition-opacity duration-200 data-[closed]:opacity-0" />
            <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                <div className="flex min-h-full items-center justify-center">
                    <DialogPanel transition className="w-full max-w-lg rounded-card bg-white shadow-xl transition duration-200 data-[closed]:translate-y-3 data-[closed]:opacity-0">
                        <div className="flex gap-4 px-5 py-6 sm:px-6">
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-red-50 text-red-700">
                                <TriangleAlert size={22} strokeWidth={1.8} aria-hidden="true" />
                            </span>
                            <div>
                                <DialogTitle className="font-display text-xl font-bold text-ink">Hapus mata kuliah?</DialogTitle>
                                <p className="mt-2 text-sm leading-6 text-stone-600"><strong>{course.code} — {course.name}</strong> akan dihapus permanen dari daftar mata kuliah.</p>
                                {linkedItems > 0 && (
                                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm leading-6 text-amber-900">
                                        {course.knowledges_count} knowledge dan {course.conversations_count} percakapan tetap tersimpan, tetapi akan berubah menjadi konteks umum.
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-col-reverse gap-3 border-t border-stone-200 bg-stone-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                            <button type="button" onClick={onClose} disabled={form.processing} className="min-h-11 rounded-lg border border-stone-300 bg-white px-5 text-sm font-semibold text-stone-700 hover:bg-stone-100 disabled:opacity-60">Batal</button>
                            <button type="button" onClick={destroy} disabled={form.processing} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-wait disabled:opacity-60">
                                <Trash2 size={17} strokeWidth={1.8} aria-hidden="true" />
                                {form.processing ? 'Menghapus…' : 'Ya, hapus permanen'}
                            </button>
                        </div>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}

export default function Courses({ courses, flash }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedCourse, setSelectedCourse] = useState(null);
    const [courseToDelete, setCourseToDelete] = useState(null);
    const activeCourses = courses.filter((course) => course.status === 'active').length;

    const openCreateDialog = () => {
        setSelectedCourse(null);
        setDialogOpen(true);
    };

    const openEditDialog = (course) => {
        setSelectedCourse(course);
        setDialogOpen(true);
    };

    return (
        <AuthenticatedLayout
            header={<PageHeader eyebrow="Administrasi" title="Mata kuliah" description="Atur pilihan mata kuliah yang dapat digunakan pada percakapan dan knowledge." icon={GraduationCap} actions={(
                    <button type="button" onClick={openCreateDialog} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-900">
                        <CirclePlus size={19} strokeWidth={1.8} aria-hidden="true" />
                        Tambah mata kuliah
                    </button>
            )} />}
        >
            <Head title="Kelola mata kuliah" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                {flash?.status && (
                    <div className="mb-6 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
                        <CheckCircle2 size={19} aria-hidden="true" />
                        {flash.status}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                        <p className="text-sm font-medium text-stone-500">Total mata kuliah</p>
                        <p className="mt-2 font-display text-3xl font-bold text-ink">{courses.length}</p>
                    </div>
                    <div className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                        <p className="text-sm font-medium text-stone-500">Aktif</p>
                        <p className="mt-2 font-display text-3xl font-bold text-emerald-700">{activeCourses}</p>
                    </div>
                    <div className="rounded-card border border-stone-200 bg-white p-5 shadow-sm">
                        <p className="text-sm font-medium text-stone-500">Nonaktif</p>
                        <p className="mt-2 font-display text-3xl font-bold text-stone-500">{courses.length - activeCourses}</p>
                    </div>
                </div>

                <div className="mt-8 overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm">
                    <div className="border-b border-stone-200 px-5 py-4 sm:px-6">
                        <h2 className="font-display text-xl font-bold text-ink">Daftar mata kuliah</h2>
                        <p className="mt-1 text-sm text-stone-500">Mata kuliah nonaktif tetap tersimpan, tetapi tidak muncul pada pilihan knowledge baru.</p>
                    </div>

                    {courses.length > 0 ? (
                        <div className="divide-y divide-stone-200">
                            {courses.map((course) => (
                                <article key={course.id} className="grid gap-4 px-5 py-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:px-6">
                                    <span className={`grid h-11 w-11 place-items-center rounded-lg ${course.status === 'active' ? 'bg-brand-100 text-brand-800' : 'bg-stone-100 text-stone-400'}`}>
                                        <GraduationCap size={22} strokeWidth={1.8} aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-bold uppercase tracking-wide text-brand-700">{course.code}</span>
                                            <span className={`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold ${course.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500'}`}>
                                                {course.status === 'active' ? <CheckCircle2 size={13} aria-hidden="true" /> : <Power size={13} aria-hidden="true" />}
                                                {course.status === 'active' ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <h3 className="mt-1 text-base font-semibold text-ink">{course.name}</h3>
                                        <p className="mt-1 line-clamp-2 text-sm text-stone-500">{course.description || 'Belum ada deskripsi.'}</p>
                                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                                            <span>Semester {course.semester || '—'}</span>
                                            <span className="flex items-center gap-1"><BookOpenText size={13} aria-hidden="true" /> {course.knowledges_count} knowledge</span>
                                            <span className="flex items-center gap-1"><MessageCircle size={13} aria-hidden="true" /> {course.conversations_count} percakapan</span>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 sm:flex">
                                        <button type="button" onClick={() => openEditDialog(course)} className="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-700 hover:border-brand-400 hover:text-brand-800">
                                            <Edit3 size={16} strokeWidth={1.8} aria-hidden="true" />
                                            Ubah
                                        </button>
                                        <button type="button" onClick={() => setCourseToDelete(course)} className="flex min-h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 hover:border-red-300 hover:bg-red-50">
                                            <Trash2 size={16} strokeWidth={1.8} aria-hidden="true" />
                                            Hapus
                                        </button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="px-6 py-16 text-center">
                            <GraduationCap size={34} className="mx-auto text-stone-300" aria-hidden="true" />
                            <p className="mt-3 font-semibold text-ink">Belum ada mata kuliah</p>
                            <p className="mt-1 text-sm text-stone-500">Tambahkan mata kuliah pertama agar dapat dipilih pada knowledge.</p>
                        </div>
                    )}
                </div>
            </div>

            <CourseFormDialog key={selectedCourse?.id || 'new'} course={selectedCourse} open={dialogOpen} onClose={() => setDialogOpen(false)} />
            <DeleteCourseDialog course={courseToDelete} onClose={() => setCourseToDelete(null)} />
        </AuthenticatedLayout>
    );
}
