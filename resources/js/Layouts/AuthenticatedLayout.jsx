import BrandMark from '@/Components/BrandMark';
import SidebarNavLink from '@/Components/SidebarNavLink';
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    ClipboardCheck,
    Gauge,
    GraduationCap,
    Home,
    KeyRound,
    LogOut,
    Menu,
    MessageCircle,
    ScrollText,
    ShieldCheck,
    UserRound,
    Users,
    Trophy,
    X,
} from 'lucide-react';
import { useState } from 'react';

const roleLabels = {
    admin: 'Administrator',
    reviewer: 'Reviewer',
    student: 'Mahasiswa',
};

function UserAvatar({ user, size = 'md' }) {
    const sizeClass = size === 'sm' ? 'h-9 w-9' : 'h-10 w-10';

    if (user.avatar_url) {
        return <img src={user.avatar_url} alt="" className={`${sizeClass} rounded-lg object-cover ring-1 ring-white/20`} />;
    }

    return (
        <span className={`${sizeClass} grid shrink-0 place-items-center rounded-lg bg-brand-700 text-sm font-bold text-white ring-1 ring-white/20`} aria-hidden="true">
            {user.name?.charAt(0).toUpperCase()}
        </span>
    );
}

function SidebarContent({ user, onNavigate = () => {} }) {
    const workspaceNavigation = [
        { label: 'Beranda', href: route('dashboard'), active: route().current('dashboard'), icon: Home },
        { label: 'Percakapan', href: route('conversations.index'), active: route().current('conversations.*'), icon: MessageCircle },
        { label: 'Knowledge', href: route('knowledge.index'), active: route().current('knowledge.*'), icon: BookOpen },
    ];
    const communityNavigation = [
        { label: 'Kontribusi', href: route('ai-credentials.index'), active: route().current('ai-credentials.*'), icon: KeyRound },
        { label: 'Leaderboard', href: route('leaderboard'), active: route().current('leaderboard'), icon: Trophy },
    ];
    const adminNavigation = [];

    if (['reviewer', 'admin'].includes(user.role)) {
        adminNavigation.push({ label: 'Review knowledge', href: route('admin.knowledge-reviews.index'), active: route().current('admin.knowledge-reviews.*'), icon: ClipboardCheck });
    }

    if (user.role === 'admin') {
        adminNavigation.unshift({ label: 'Ringkasan admin', href: route('admin.dashboard'), active: route().current('admin.dashboard'), icon: Gauge });
        adminNavigation.push({ label: 'Pengguna', href: route('admin.users.index'), active: route().current('admin.users.*'), icon: Users });
        adminNavigation.push({ label: 'Mata kuliah', href: route('admin.courses.index'), active: route().current('admin.courses.*'), icon: GraduationCap });
        adminNavigation.push({ label: 'Credential health', href: route('admin.credentials.index'), active: route().current('admin.credentials.*'), icon: Activity });
        adminNavigation.push({ label: 'Audit log', href: route('admin.audit-logs.index'), active: route().current('admin.audit-logs.*'), icon: ScrollText });
    }

    const NavigationGroup = ({ label, items }) => <div><p className="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-brand-400">{label}</p><nav className="mt-2 space-y-1" aria-label={label}>{items.map((item) => { const Icon = item.icon; return <SidebarNavLink key={item.label} href={item.href} active={item.active} icon={<Icon size={19} strokeWidth={1.8} />} onClick={onNavigate}>{item.label}</SidebarNavLink>; })}</nav></div>;

    return (
        <div className="flex h-full flex-col bg-brand-950 text-white">
            <div className="flex h-20 shrink-0 items-center border-b border-white/10 px-5">
                <Link href={route('dashboard')} onClick={onNavigate} aria-label="PJJ AI — Beranda">
                    <BrandMark inverse />
                </Link>
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-3 py-5">
                <NavigationGroup label="Workspace" items={workspaceNavigation} />
                <NavigationGroup label="Komunitas" items={communityNavigation} />
                {adminNavigation.length > 0 && <NavigationGroup label={user.role === 'admin' ? 'Administrasi' : 'Moderasi'} items={adminNavigation} />}

                <div className="border-t border-white/10 pt-5">
                    <p className="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-brand-400">Akun</p>
                    <nav className="mt-3 space-y-1" aria-label="Navigasi akun">
                        <SidebarNavLink href={route('profile.edit')} active={route().current('profile.*')} icon={<UserRound size={20} strokeWidth={1.8} />} onClick={onNavigate}>Profil</SidebarNavLink>
                        <SidebarNavLink href={route('privacy')} active={route().current('privacy')} icon={<ShieldCheck size={20} strokeWidth={1.8} />} onClick={onNavigate}>Privasi & kontribusi</SidebarNavLink>
                    </nav>
                </div>
            </div>

            <div className="shrink-0 border-t border-white/10 p-3">
                <div className="flex items-center gap-3 rounded-lg bg-white/[0.06] p-3">
                    <UserAvatar user={user} />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-white">{user.name}</p>
                        <p className="mt-0.5 truncate text-xs text-brand-300">{roleLabels[user.role] || user.role}</p>
                    </div>
                    <Link method="post" href={route('logout')} as="button" onClick={onNavigate} className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-brand-200 hover:bg-white/10 hover:text-white" aria-label="Keluar dari akun">
                        <LogOut size={20} strokeWidth={1.8} aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);

    return (
        <div className="min-h-screen bg-paper text-ink">
            <a href="#main-content" className="sr-only z-[100] rounded-lg bg-brand-900 px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
                Lewati ke konten utama
            </a>

            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 lg:block xl:w-72">
                <SidebarContent user={user} />
            </aside>

            <Dialog open={mobileNavigationOpen} onClose={setMobileNavigationOpen} className="relative z-50 lg:hidden">
                <DialogBackdrop transition className="fixed inset-0 bg-stone-950/55 transition-opacity duration-200 data-[closed]:opacity-0" />
                <div className="fixed inset-0 flex">
                    <DialogPanel transition className="relative w-full max-w-72 transition duration-200 ease-out data-[closed]:-translate-x-full">
                        <DialogTitle className="sr-only">Navigasi aplikasi</DialogTitle>
                        <SidebarContent user={user} onNavigate={() => setMobileNavigationOpen(false)} />
                        <button type="button" onClick={() => setMobileNavigationOpen(false)} className="absolute right-3 top-5 rounded-lg bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Tutup navigasi">
                            <X size={24} strokeWidth={2} aria-hidden="true" />
                        </button>
                    </DialogPanel>
                </div>
            </Dialog>

            <div className="lg:pl-64 xl:pl-72">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-stone-200 bg-white px-4 sm:px-6 lg:hidden">
                    <Link href={route('dashboard')} aria-label="PJJ AI — Beranda"><BrandMark compact /></Link>
                    <div className="flex items-center gap-3">
                        <div className="hidden text-right sm:block">
                            <p className="max-w-40 truncate text-sm font-semibold text-ink">{user.name}</p>
                            <p className="text-xs text-stone-500">{roleLabels[user.role] || user.role}</p>
                        </div>
                        <UserAvatar user={user} size="sm" />
                        <button type="button" onClick={() => setMobileNavigationOpen(true)} className="grid h-10 w-10 place-items-center rounded-lg border border-stone-200 bg-white text-stone-700 hover:bg-stone-50" aria-label="Buka navigasi">
                            <Menu size={20} strokeWidth={2} aria-hidden="true" />
                        </button>
                    </div>
                </header>

                {header && (
                    <header className="border-b border-stone-200/80 bg-white/95 backdrop-blur">
                        <div className="mx-auto max-w-[90rem] px-4 py-5 sm:px-6 lg:px-8 lg:py-7">{header}</div>
                    </header>
                )}

                <main id="main-content" className="min-h-screen">{children}</main>
            </div>
        </div>
    );
}
