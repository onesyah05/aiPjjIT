const styles = {
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    pending_review: 'border-amber-200 bg-amber-50 text-amber-900',
    pending: 'border-amber-200 bg-amber-50 text-amber-900',
    processing: 'border-sky-200 bg-sky-50 text-sky-800',
    streaming: 'border-sky-200 bg-sky-50 text-sky-800',
    cooldown: 'border-amber-200 bg-amber-50 text-amber-900',
    rejected: 'border-red-200 bg-red-50 text-red-800',
    failed: 'border-red-200 bg-red-50 text-red-800',
    invalid: 'border-red-200 bg-red-50 text-red-800',
    disabled: 'border-stone-200 bg-stone-100 text-stone-700',
    inactive: 'border-stone-200 bg-stone-100 text-stone-700',
    draft: 'border-stone-200 bg-stone-100 text-stone-700',
};

const labels = {
    approved: 'Disetujui',
    active: 'Aktif',
    completed: 'Selesai',
    pending_review: 'Menunggu review',
    pending: 'Menunggu',
    processing: 'Diproses',
    streaming: 'Streaming',
    cooldown: 'Jeda sementara',
    rejected: 'Ditolak',
    failed: 'Gagal',
    invalid: 'Tidak valid',
    disabled: 'Nonaktif',
    inactive: 'Nonaktif',
    draft: 'Draf',
};

export default function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold ${styles[status] || styles.draft}`}>
            {labels[status] || status}
        </span>
    );
}
