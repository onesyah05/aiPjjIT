import { Inbox } from 'lucide-react';

export default function EmptyState({ title, description, action, icon: Icon = Inbox }) {
    return (
        <div className="rounded-card border border-dashed border-stone-300 bg-white px-6 py-10 text-center sm:px-10">
            <span className="mx-auto grid h-11 w-11 place-items-center rounded-lg bg-stone-100 text-stone-400"><Icon size={21} strokeWidth={1.7} aria-hidden="true" /></span>
            <h3 className="mt-4 text-lg font-bold text-ink">{title}</h3>
            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-600">{description}</p>
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
