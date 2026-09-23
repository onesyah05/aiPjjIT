import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { Bell, BookOpenCheck, CheckCheck, KeyRound, X } from 'lucide-react';

const statusStyles = {
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    draft: 'bg-amber-100 text-amber-900',
    disabled: 'bg-stone-200 text-stone-700',
    invalid: 'bg-red-100 text-red-800',
};

function NotificationIcon({ kind }) {
    const Icon = kind === 'credential_health' ? KeyRound : BookOpenCheck;

    return (
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-800">
            <Icon size={19} strokeWidth={1.8} aria-hidden="true" />
        </span>
    );
}

export function NotificationTrigger({ unreadCount, inverse = false, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`relative grid h-10 w-10 place-items-center rounded-lg ${inverse ? 'text-brand-200 hover:bg-white/10 hover:text-white' : 'border border-stone-200 bg-white text-stone-700 hover:bg-stone-50'}`}
            aria-label={unreadCount > 0 ? `Buka ${unreadCount} notifikasi belum dibaca` : 'Buka notifikasi'}
        >
            <Bell size={20} strokeWidth={1.8} aria-hidden="true" />
            {unreadCount > 0 && (
                <span className="absolute -right-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white ring-2 ring-brand-950">
                    {unreadCount > 99 ? '99+' : unreadCount}
                </span>
            )}
        </button>
    );
}

export default function NotificationCenter({ open, onClose, notifications }) {
    const items = notifications?.items || [];
    const unreadCount = notifications?.unread_count || 0;

    return (
        <Dialog open={open} onClose={onClose} className="relative z-[60]">
            <DialogBackdrop transition className="fixed inset-0 bg-stone-950/45 transition-opacity duration-200 data-[closed]:opacity-0" />
            <div className="fixed inset-0 flex justify-end">
                <DialogPanel transition className="flex h-full w-full max-w-md flex-col bg-paper shadow-2xl transition duration-200 ease-out data-[closed]:translate-x-full">
                    <div className="flex items-start justify-between gap-4 border-b border-stone-200 bg-white px-5 py-5 sm:px-6">
                        <div>
                            <DialogTitle className="text-xl font-bold text-ink">Notifikasi</DialogTitle>
                            <p className="mt-1 text-sm text-stone-500">Perubahan review knowledge dan kesehatan credential.</p>
                        </div>
                        <button type="button" onClick={onClose} className="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-ink" aria-label="Tutup notifikasi">
                            <X size={21} aria-hidden="true" />
                        </button>
                    </div>

                    {unreadCount > 0 && (
                        <div className="flex items-center justify-between border-b border-stone-200 bg-white px-5 py-3 sm:px-6">
                            <p className="text-xs font-semibold text-stone-500">{unreadCount} belum dibaca</p>
                            <Link method="post" as="button" href={route('notifications.read-all')} preserveScroll className="inline-flex items-center gap-1.5 text-xs font-bold text-brand-700 hover:text-brand-900">
                                <CheckCheck size={15} aria-hidden="true" /> Tandai semua dibaca
                            </Link>
                        </div>
                    )}

                    <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                        {items.length > 0 ? (
                            <div className="grid gap-3">
                                {items.map((notification) => (
                                    <Link
                                        key={notification.id}
                                        method="post"
                                        as="button"
                                        href={route('notifications.read', notification.id)}
                                        onClick={onClose}
                                        className={`flex w-full items-start gap-3 rounded-card border p-4 text-left transition-colors ${notification.read_at ? 'border-stone-200 bg-white hover:border-brand-200' : 'border-brand-200 bg-brand-50 hover:border-brand-300'}`}
                                    >
                                        <NotificationIcon kind={notification.data.kind} />
                                        <span className="min-w-0 flex-1">
                                            <span className="flex flex-wrap items-start justify-between gap-2">
                                                <strong className="text-sm text-ink">{notification.data.title}</strong>
                                                {notification.data.status && <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${statusStyles[notification.data.status] || 'bg-stone-100 text-stone-700'}`}>{notification.data.status}</span>}
                                            </span>
                                            <span className="mt-1.5 block text-sm leading-6 text-stone-600">{notification.data.message}</span>
                                            <span className="mt-2 block text-[11px] font-medium text-stone-400">{notification.created_at ? new Date(notification.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : ''}</span>
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="grid min-h-64 place-items-center rounded-card border border-dashed border-stone-300 bg-white px-6 text-center">
                                <div>
                                    <Bell size={30} className="mx-auto text-stone-300" aria-hidden="true" />
                                    <p className="mt-3 font-bold text-ink">Belum ada notifikasi</p>
                                    <p className="mt-1 text-sm leading-6 text-stone-500">Update review dan credential akan muncul di sini.</p>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
