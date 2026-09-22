import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({ name: user.name, email: user.email });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="font-display text-xl font-bold text-ink">Informasi profil</h2>
                <p className="mt-2 text-sm leading-6 text-stone-600">Perbarui nama dan alamat email yang terhubung dengan akunmu.</p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div>
                    <InputLabel htmlFor="name" value="Nama" />
                    <TextInput id="name" className="mt-2 block w-full" value={data.name} onChange={(event) => setData('name', event.target.value)} required isFocused autoComplete="name" />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput id="email" type="email" className="mt-2 block w-full" value={data.email} onChange={(event) => setData('email', event.target.value)} required autoComplete="username" />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Alamat email belum diverifikasi.{' '}
                        <Link href={route('verification.send')} method="post" as="button" className="font-semibold underline underline-offset-2 hover:text-amber-700">
                            Kirim ulang email verifikasi
                        </Link>
                        {status === 'verification-link-sent' && <p className="mt-2 font-medium text-emerald-800">Tautan verifikasi baru telah dikirim.</p>}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan…' : 'Simpan perubahan'}</PrimaryButton>
                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p role="status" className="text-sm font-medium text-emerald-700">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
