const styles = {
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    pending_review: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    pending: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    processing: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200',
    streaming: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200',
    cooldown: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    rejected: 'border-red-200 bg-red-50 text-red-800 dark:border-red-400/50 dark:bg-red-500/20 dark:text-red-100',
    failed: 'border-red-200 bg-red-50 text-red-800 dark:border-red-400/50 dark:bg-red-500/20 dark:text-red-100',
    invalid: 'border-red-200 bg-red-50 text-red-800 dark:border-red-400/50 dark:bg-red-500/20 dark:text-red-100',
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
