import FlashBanner from '@/Components/FlashBanner';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Search, UserRoundCheck, Users as UsersIcon } from 'lucide-react';
import { useState } from 'react';

export default function Users({ users, filters, flash }) {
    const [query, setQuery] = useState(filters.q || '');
    const search = (event) => { event.preventDefault(); router.get(route('admin.users.index'), { q: query || undefined }, { preserveState: true, replace: true }); };
    const update = (user, values) => router.patch(route('admin.users.update', user.id), values, { preserveScroll: true });

    return (
        <AuthenticatedLayout header={<PageHeader eyebrow="Administrasi" title="Pengguna" description="Kelola akses, peran, serta status anggota yang terhubung melalui Discord." icon={UsersIcon} />}>
            <Head title="Kelola pengguna" />
            <PageShell>
                <FlashBanner message={flash?.status} />
                <div className="mb-5 flex flex-col gap-3 rounded-card border border-stone-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <form onSubmit={search} className="flex w-full max-w-xl gap-2">
                        <label className="relative flex-1"><span className="sr-only">Cari pengguna</span><Search size={18} className="pointer-events-none absolute left-3 top-3 text-stone-400" /><input value={query} onChange={(event) => setQuery(event.target.value)} className="w-full rounded-lg border-stone-300 pl-10 text-sm" placeholder="Cari nama atau email" /></label>
                        <button className="rounded-lg bg-brand-800 px-4 text-sm font-bold text-white hover:bg-brand-900">Cari</button>
                    </form>
                    <p className="shrink-0 text-sm text-stone-500"><strong className="text-ink">{users.total}</strong> pengguna</p>
                </div>

                <section className="overflow-hidden rounded-card border border-stone-200 bg-white shadow-sm" aria-label="Daftar pengguna">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th className="px-5 py-3 font-semibold sm:px-6">Pengguna</th><th className="px-4 py-3 font-semibold">Discord</th><th className="px-4 py-3 font-semibold">Aktivitas</th><th className="px-4 py-3 font-semibold">Peran</th><th className="px-5 py-3 text-right font-semibold sm:px-6">Akses</th></tr></thead>
                            <tbody>{users.data.map((user) => <tr key={user.id} className="border-t border-stone-100 hover:bg-stone-50/70">
                                <td className="px-5 py-4 sm:px-6"><div className="flex items-center gap-3">{user.avatar_url ? <img src={user.avatar_url} alt="" className="h-10 w-10 rounded-lg object-cover" /> : <span className="grid h-10 w-10 place-items-center rounded-lg bg-brand-100 font-bold text-brand-800">{user.name.charAt(0)}</span>}<div><p className="font-bold text-ink">{user.name}</p><p className="mt-0.5 text-xs text-stone-500">{user.email}</p></div></div></td>
                                <td className="px-4 py-4"><p className="font-medium">{user.discord_account?.username || 'Belum terhubung'}</p><p className="mt-0.5 text-xs text-stone-500">{user.discord_account ? 'Discord terverifikasi' : 'Tidak ada akun'}</p></td>
                                <td className="px-4 py-4 text-stone-600">{user.knowledges_count} knowledge<br /><span className="text-xs text-stone-500">{user.conversations_count} percakapan</span></td>
                                <td className="px-4 py-4"><label><span className="sr-only">Peran {user.name}</span><select value={user.role} onChange={(event) => update(user, { role: event.target.value })} className="rounded-lg border-stone-300 text-sm"><option value="student">Mahasiswa</option><option value="reviewer">Reviewer</option><option value="admin">Admin</option></select></label></td>
                                <td className="px-5 py-4 text-right sm:px-6"><button onClick={() => update(user, { is_active: !user.is_active, status: user.is_active ? 'suspended' : 'active' })} className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ${user.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-stone-100 text-stone-600'}`}><span className={`h-1.5 w-1.5 rounded-full ${user.is_active ? 'bg-emerald-500' : 'bg-stone-400'}`} />{user.is_active ? 'Aktif' : 'Nonaktif'}</button></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                    {users.data.length === 0 && <div className="grid place-items-center px-6 py-16 text-center text-stone-500"><UserRoundCheck size={32} className="text-stone-300" /><p className="mt-3 font-semibold text-ink">Pengguna tidak ditemukan</p><p className="mt-1 text-sm">Coba kata kunci lain atau hapus filter pencarian.</p></div>}
                </section>
                <Pagination data={users} label="pengguna" />
            </PageShell>
        </AuthenticatedLayout>
    );
}
