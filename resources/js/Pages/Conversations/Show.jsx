import EmptyState from '@/Components/EmptyState';
import MarkdownContent from '@/Components/MarkdownContent';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { BookOpenText, Check, ChevronDown, ExternalLink, RotateCcw, Send, Sparkles, ThumbsDown, ThumbsUp, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const csrfToken = () => decodeURIComponent(document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || '');

export default function Show({ conversation, courses }) {
    const [messages, setMessages] = useState(conversation.messages);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState('');
    const formRef = useRef(null);
    const messagesEndRef = useRef(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: streaming ? 'auto' : 'smooth', block: 'end' });
    }, [messages, streaming]);

    const streamMessage = async (content) => {
        const clientRequestId = crypto.randomUUID();

        if (!content || streaming) {
            return;
        }

        setMessages((current) => [
            ...current,
            { id: `user-${Date.now()}`, role: 'user', content, sources: [] },
            { id: `assistant-${Date.now()}`, role: 'assistant', content: '', sources: [] },
        ]);
        setInput('');
        setStreaming(true);
        setError('');

        try {
            const response = await fetch(route('conversations.messages.store', conversation.id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message: content, client_request_id: clientRequestId }),
            });

            if (!response.ok || !response.body) {
                throw new Error('Permintaan chat gagal. Silakan coba kembali.');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const events = buffer.split('\n\n');
                buffer = events.pop() || '';

                events.forEach((eventBlock) => {
                    const dataLine = eventBlock.split('\n').find((line) => line.startsWith('data: '));

                    if (!dataLine) {
                        return;
                    }

                    const raw = dataLine.slice(6);

                    if (raw === '[DONE]') {
                        return;
                    }

                    const data = JSON.parse(raw);

                    if (data.message) {
                        setError(data.message);
                    }

                    setMessages((current) => current.map((message, index) => (
                        index === current.length - 1
                            ? { ...message, id: data.message_id || message.id, content: message.content + (data.content || ''), sources: data.sources || message.sources }
                            : message
                    )));
                });
            }
        } catch (exception) {
            setError(exception.message);
        } finally {
            setStreaming(false);
        }
    };

    const send = (event) => {
        event.preventDefault();
        streamMessage(input.trim());
    };

    const regenerate = (messageIndex) => {
        const previousUserMessage = [...messages.slice(0, messageIndex)].reverse().find((message) => message.role === 'user');

        if (previousUserMessage) {
            streamMessage(previousUserMessage.content);
        }
    };

    const updateSetting = (key, value) => router.patch(
        route('conversations.update', conversation.id),
        { [key]: value },
        { preserveScroll: true },
    );

    const submitOnEnter = (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
            event.preventDefault();
            formRef.current?.requestSubmit();
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={conversation.title || 'Percakapan'} />

            <div className="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden lg:h-dvh">
                <header className="shrink-0 border-b border-stone-200 bg-paper/95 backdrop-blur">
                    <div className="mx-auto max-w-5xl px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-brand-700">
                                    <Sparkles size={14} aria-hidden="true" />
                                    Ruang belajar
                                </div>
                                <h1 className="mt-1 truncate font-display text-xl font-bold leading-tight text-ink sm:text-2xl">{conversation.title || 'Percakapan baru'}</h1>
                            </div>
                            <div className="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                                <div className="relative min-w-0 sm:w-52">
                                    <label className="sr-only" htmlFor="conversation-course">Mata kuliah</label>
                                    <BookOpenText size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stone-500" aria-hidden="true" />
                                    <select id="conversation-course" defaultValue={conversation.course_id || ''} onChange={(event) => updateSetting('course_id', event.target.value || null)} className="w-full appearance-none rounded-lg border-stone-300 bg-white py-2 pl-9 pr-9 text-sm font-semibold text-stone-700 shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                        <option value="">Semua mata kuliah</option>
                                        {courses.map((course) => <option key={course.id} value={course.id}>{course.code}</option>)}
                                    </select>
                                    <ChevronDown size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-stone-500" aria-hidden="true" />
                                </div>
                                <div className="relative min-w-0 sm:w-48">
                                    <label className="sr-only" htmlFor="conversation-mode">Mode jawaban</label>
                                    <select id="conversation-mode" defaultValue={conversation.mode} onChange={(event) => updateSetting('mode', event.target.value)} className="w-full appearance-none rounded-lg border-stone-300 bg-white py-2 pl-3 pr-9 text-sm font-semibold text-stone-700 shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                        <option value="general">Jawaban umum</option>
                                        <option value="knowledge_only">Hanya knowledge</option>
                                    </select>
                                    <ChevronDown size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-stone-500" aria-hidden="true" />
                                </div>
                            </div>
                        </div>
                        <p className="mt-3 flex items-start gap-1.5 text-xs leading-5 text-stone-500">
                            <Check size={13} className="mt-0.5 shrink-0 text-brand-600" aria-hidden="true" />
                            Jawaban disusun dari konteks yang tersedia. Tetap periksa informasi penting pada sumber asli.
                        </p>
                    </div>
                </header>

                <main className="min-h-0 flex-1 overflow-y-auto overscroll-contain" aria-live="polite" aria-busy={streaming}>
                    <div className="mx-auto w-full max-w-4xl space-y-7 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
                        {messages.length === 0 && <EmptyState title="Mulai sesi belajar" description="Tanyakan sebuah konsep, minta contoh, atau bahas materi dari library knowledge." />}

                        {messages.map((message, messageIndex) => (
                            message.role === 'user' ? (
                                <article key={message.id} className="flex items-start justify-end gap-3">
                                    <div className="max-w-[85%] rounded-card rounded-tr-sm bg-brand-800 px-5 py-3.5 text-sm leading-7 text-white shadow-sm sm:max-w-2xl sm:px-6">
                                        <div className="whitespace-pre-wrap">{message.content}</div>
                                    </div>
                                    <div className="mt-1 hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-800 sm:flex">
                                        <UserRound size={18} aria-hidden="true" />
                                        <span className="sr-only">Anda</span>
                                    </div>
                                </article>
                            ) : (
                                <article key={message.id} className="grid grid-cols-[2.25rem_minmax(0,1fr)] items-start gap-3">
                                    <img src="/images/sibermu-logo.png" alt="" className="h-9 w-9 rounded-full object-cover shadow-sm ring-1 ring-brand-200" />
                                    <div className="min-w-0">
                                        <div className="mb-2">
                                            <p className="text-sm font-bold leading-5 text-ink">PJJ AI</p>
                                            <p className="text-xs leading-5 text-stone-500">Asisten belajar SIBERMU</p>
                                        </div>
                                        <div className="rounded-card rounded-tl-sm border border-stone-200 bg-white px-5 py-5 shadow-sm sm:px-7 sm:py-6">
                                            {message.content ? (
                                                <div className="text-[15px] sm:text-base">
                                                    <MarkdownContent content={message.content} />
                                                </div>
                                            ) : streaming ? (
                                                <div className="flex items-center gap-3 text-sm text-stone-500">
                                                    <span className="flex gap-1" aria-hidden="true">
                                                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-500" />
                                                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-500 [animation-delay:150ms]" />
                                                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-500 [animation-delay:300ms]" />
                                                    </span>
                                                    Menyusun jawaban…
                                                </div>
                                            ) : null}

                                            {message.sources?.length > 0 && (
                                                <section className="mt-6 border-t border-stone-200 pt-5" aria-label="Sumber knowledge">
                                                    <div className="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-stone-500">
                                                        <BookOpenText size={14} aria-hidden="true" />
                                                        Sumber yang digunakan
                                                    </div>
                                                    <ul className="grid gap-2 sm:grid-cols-2">
                                                        {message.sources.map((source, index) => (
                                                            <li key={source.id || `${source.title}-${index}`}>
                                                                <Link href={route('knowledge.show', source.knowledge_id || source.chunk?.version?.knowledge?.id)} className="group/source block rounded-lg border border-stone-200 bg-stone-50 px-3.5 py-3 hover:border-brand-300 hover:bg-brand-50">
                                                                    <span className="flex items-start justify-between gap-3"><span className="text-sm font-semibold text-ink group-hover/source:text-brand-800">{source.title || source.chunk?.version?.knowledge?.title || 'Knowledge'}</span><ExternalLink size={14} className="shrink-0 text-stone-400" /></span>
                                                                    {(source.heading || source.chunk?.heading_path) && <span className="mt-1 block text-xs leading-5 text-stone-500">Bagian: {source.heading || source.chunk?.heading_path}</span>}
                                                                </Link>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </section>
                                            )}
                                            {Number.isInteger(message.id) && message.status !== 'streaming' && (
                                                <div className="mt-4 flex items-center gap-2 border-t border-stone-100 pt-3 text-xs text-stone-500">
                                                    <span>Jawaban ini membantu?</span>
                                                    <button type="button" onClick={() => router.post(route('messages.feedback.store', message.id), { rating: 'helpful' }, { preserveScroll: true })} className="rounded-lg p-2 hover:bg-emerald-50 hover:text-emerald-700" aria-label="Jawaban membantu"><ThumbsUp size={15} /></button>
                                                    <button type="button" onClick={() => router.post(route('messages.feedback.store', message.id), { rating: 'not_helpful' }, { preserveScroll: true })} className="rounded-lg p-2 hover:bg-red-50 hover:text-red-700" aria-label="Jawaban tidak membantu"><ThumbsDown size={15} /></button>
                                                    <button type="button" onClick={() => regenerate(messageIndex)} disabled={streaming} className="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 font-semibold hover:bg-stone-100 disabled:opacity-50"><RotateCcw size={14} />Coba lagi</button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            )
                        ))}
                        <div ref={messagesEndRef} aria-hidden="true" />
                    </div>
                </main>

                <footer className="shrink-0 border-t border-stone-200 bg-paper/95 px-4 py-3 backdrop-blur sm:px-6 sm:py-4">
                    <div className="mx-auto max-w-4xl">
                        {error && <div role="alert" className="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{error}</div>}

                        <form ref={formRef} onSubmit={send} className="rounded-card border border-stone-300 bg-white p-2 shadow-[0_10px_30px_rgba(30,41,37,0.08)] sm:p-3">
                            <div className="flex items-end gap-2 sm:gap-3">
                                <label className="sr-only" htmlFor="chat-message">Pesan</label>
                                <textarea id="chat-message" value={input} onChange={(event) => setInput(event.target.value)} onKeyDown={submitOnEnter} rows="1" maxLength="6000" placeholder="Tanyakan sesuatu tentang materi…" className="min-h-11 max-h-32 flex-1 resize-none border-0 bg-transparent px-2 py-2.5 text-sm leading-6 text-ink placeholder:text-stone-400 focus:ring-0 sm:text-base" />
                                <button type="submit" disabled={streaming || !input.trim()} className="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50 sm:px-5">
                                    <Send size={16} aria-hidden="true" />
                                    <span className="hidden sm:inline">{streaming ? 'Mengirim…' : 'Kirim'}</span>
                                </button>
                            </div>
                            <p className="px-2 pt-1 text-[11px] leading-4 text-stone-400">Enter untuk mengirim · Shift + Enter untuk baris baru</p>
                        </form>
                    </div>
                </footer>
            </div>
        </AuthenticatedLayout>
    );
}
