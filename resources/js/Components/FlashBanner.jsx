import { CheckCircle2, XCircle } from 'lucide-react';

export default function FlashBanner({ message, tone = 'success' }) {
    if (!message) return null;
    const error = tone === 'error';
    const Icon = error ? XCircle : CheckCircle2;

    return <div role={error ? 'alert' : 'status'} className={`mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm font-medium ${error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`}><Icon size={18} className="mt-0.5 shrink-0" aria-hidden="true" /><span>{message}</span></div>;
}
