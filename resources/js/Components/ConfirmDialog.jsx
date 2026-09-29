import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { useEffect, useState } from 'react';

export default function ConfirmDialog({
    show = false,
    title = '',
    message = '',
    confirmLabel = 'Hapus',
    cancelLabel = 'Batal',
    showInput = false,
    initialValue = '',
    inputPlaceholder = '',
    processing = false,
    onCancel = () => {},
    onConfirm = () => {},
}) {
    const [value, setValue] = useState(initialValue);

    useEffect(() => {
        if (show) {
            setValue(initialValue);
        }
    }, [show, initialValue]);

    const submit = (event) => {
        event?.preventDefault();
        if (showInput) {
            const trimmed = value.trim();
            if (!trimmed) return;
            onConfirm(trimmed);
        } else {
            onConfirm();
        }
    };

    return (
        <Modal show={show} onClose={onCancel} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="font-display text-xl font-bold text-ink">{title}</h2>
                {message && <p className="mt-2 text-sm leading-6 text-stone-600">{message}</p>}
                {showInput && (
                    <input
                        autoFocus
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        placeholder={inputPlaceholder}
                        className="mt-4 w-full rounded-lg border-stone-300"
                    />
                )}
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton autoFocus={!showInput} type="button" onClick={onCancel}>
                        {cancelLabel}
                    </SecondaryButton>
                    <DangerButton disabled={processing}>{confirmLabel}</DangerButton>
                </div>
            </form>
        </Modal>
    );
}
