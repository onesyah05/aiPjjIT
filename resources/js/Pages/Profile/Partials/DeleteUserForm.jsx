import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const { delete: destroy, processing, reset, clearErrors } = useForm({});

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    const deleteUser = (event) => {
        event.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: closeModal,
            onFinish: reset,
        });
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="font-display text-xl font-bold text-red-900">Hapus akun</h2>
                <p className="mt-2 text-sm leading-6 text-stone-600">Tindakan ini menghapus akun beserta percakapan, knowledge, dan kontribusi secara permanen. Unduh informasi yang ingin disimpan terlebih dahulu.</p>
            </header>

            <DangerButton onClick={() => setConfirmingUserDeletion(true)}>Hapus akun</DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="lg">
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="font-display text-xl font-bold text-ink">Hapus akun secara permanen?</h2>
                    <p className="mt-2 text-sm leading-6 text-stone-600">Setelah akun dihapus, percakapan, knowledge, dan kontribusi Anda tidak dapat dipulihkan.</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={closeModal}>Batal</SecondaryButton>
                        <DangerButton disabled={processing}>{processing ? 'Menghapus…' : 'Ya, hapus akun'}</DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
