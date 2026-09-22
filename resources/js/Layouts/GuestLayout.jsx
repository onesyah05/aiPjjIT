import BrandMark from '@/Components/BrandMark';
import { Link } from '@inertiajs/react';

const communityLines = [
    { top: '9%', duration: '52s', rotate: '-3deg' },
    { top: '34%', duration: '58s', rotate: '2deg', reverse: true },
    { top: '61%', duration: '55s', rotate: '-2deg' },
    { top: '86%', duration: '61s', rotate: '3deg', reverse: true },
];

function CommunityBackdrop() {
    const phrase = 'PJJ Informatika  •  Komunitas Discord  •  ';

    return (
        <div className="community-wave-field" aria-hidden="true">
            {communityLines.map((line) => (
                <div
                    key={line.top}
                    className="community-wave-line"
                    style={{ top: line.top, transform: `rotate(${line.rotate})` }}
                >
                    <div
                        className={`community-wave-track ${line.reverse ? 'community-wave-track-reverse' : ''}`}
                        style={{ animationDuration: line.duration }}
                    >
                        <span className="community-wave-copy">{phrase.repeat(3)}</span>
                        <span className="community-wave-copy">{phrase.repeat(3)}</span>
                    </div>
                </div>
            ))}
        </div>
    );
}

function ImmersiveGuestLayout({ children }) {
    return (
        <div className="relative min-h-[100svh] overflow-hidden bg-brand-950 text-ink">
            <CommunityBackdrop />

            <main className="relative z-10 mx-auto flex min-h-[100svh] w-full items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
                <div className="relative grid w-full max-w-[980px] overflow-hidden rounded-2xl border border-white/30 bg-brand-900/[0.52] shadow-[inset_0_1px_0_rgba(255,255,255,0.28),0_24px_70px_rgba(0,0,0,0.34)] backdrop-blur-[6px] lg:min-h-[34rem] lg:grid-cols-[.92fr_1.08fr]">
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/[0.1] via-transparent to-brand-500/[0.06]" aria-hidden="true" />

                    <section className="relative z-10 hidden border-r border-white/15 bg-white/[0.025] p-9 text-white lg:flex lg:flex-col lg:justify-between">
                        <div className="flex items-center gap-3">
                            <img src="/images/sibermu-logo.png" alt="Logo Universitas Siber Muhammadiyah" className="h-12 w-12 rounded-full object-cover ring-1 ring-white/25" />
                            <div>
                                <p className="text-sm font-bold tracking-wide">PJJ AI</p>
                                <p className="mt-0.5 text-xs text-brand-200">Universitas Siber Muhammadiyah</p>
                            </div>
                        </div>

                        <div className="max-w-sm">
                            <h1 className="text-[2.65rem] font-semibold leading-[1.08] tracking-[-0.02em]">Catatan kuliah milik komunitas.</h1>
                            <p className="mt-5 text-sm leading-7 text-brand-100">Cari jawaban dari materi yang sudah ditinjau. Jika punya catatan yang berguna, bagikan kepada teman sekelas.</p>
                        </div>

                        <p className="border-t border-white/15 pt-5 text-xs leading-5 text-brand-200">Akses mengikuti keanggotaan server Discord PJJ Informatika.</p>
                    </section>

                    <section className="relative z-10 flex min-h-[36rem] flex-col bg-white/[0.09] p-6 text-white sm:p-9 lg:min-h-0 lg:p-10">
                        <div className="mb-8 flex items-center justify-between lg:justify-end">
                            <div className="lg:hidden"><BrandMark /></div>
                            <Link href={route('privacy')} className="text-xs font-semibold text-brand-100 underline decoration-brand-400 underline-offset-4 hover:text-white">
                                Privasi dan kontribusi
                            </Link>
                        </div>
                        <div className="my-auto">{children}</div>
                        <p className="mt-8 text-center text-xs text-brand-300">PJJ Informatika · SIBERMU</p>
                    </section>
                </div>
            </main>
        </div>
    );
}

export default function GuestLayout({ children, wide = false, immersive = false }) {
    if (immersive) {
        return <ImmersiveGuestLayout>{children}</ImmersiveGuestLayout>;
    }

    return (
        <div className="min-h-screen bg-paper text-ink">
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex h-[4.5rem] max-w-6xl items-center justify-between px-4 sm:px-6">
                    <Link href="/" aria-label="PJJ AI"><BrandMark /></Link>
                    <Link href={route('privacy')} className="text-sm font-semibold text-stone-600 hover:text-brand-800">Privasi & kontribusi</Link>
                </div>
            </header>

            <main className={`mx-auto px-4 py-10 sm:px-6 sm:py-16 ${wide ? 'max-w-4xl' : 'max-w-5xl'}`}>
                {wide ? children : (
                    <div className="grid overflow-hidden rounded-card border border-stone-200 bg-white shadow-[0_22px_55px_rgba(6,18,38,0.12)] md:grid-cols-[1.05fr_.95fr]">
                        <section className="relative flex flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 p-7 text-white sm:p-10">
                            <div className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full border border-white/10 bg-white/[0.03]" aria-hidden="true" />
                            <div>
                                <img src="/images/sibermu-logo.png" alt="" className="mb-7 h-20 w-20 rounded-full object-cover shadow-lg ring-1 ring-white/25" />
                                <p className="text-xs font-bold uppercase tracking-[0.14em] text-brand-200">PJJ Informatika · SIBERMU</p>
                                <h1 className="mt-4 font-display text-4xl font-semibold leading-tight">Belajar dari catatan yang tumbuh bersama komunitas.</h1>
                                <p className="mt-5 max-w-md text-sm leading-7 text-brand-100">Diskusikan materi kuliah, gunakan knowledge yang telah ditinjau, dan selalu lihat sumber yang membantu membentuk jawaban.</p>
                            </div>
                            <p className="mt-12 border-t border-white/20 pt-5 text-xs leading-5 text-brand-200">Akses diverifikasi melalui membership server Discord PJJ Informatika.</p>
                        </section>
                        <section className="p-7 sm:p-10">{children}</section>
                    </div>
                )}
            </main>
        </div>
    );
}
