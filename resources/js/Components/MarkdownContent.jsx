import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { useState } from 'react';
import { X, Download } from 'lucide-react';

export default function MarkdownContent({ content }) {
    const [selectedImage, setSelectedImage] = useState(null);

    return (
        <>
            <ReactMarkdown
            remarkPlugins={[remarkGfm]}
            components={{
                h1: ({ children }) => <h1 className="mb-3 mt-6 font-display text-2xl font-bold leading-tight text-ink first:mt-0">{children}</h1>,
                h2: ({ children }) => <h2 className="mb-3 mt-6 font-display text-xl font-bold leading-tight text-ink first:mt-0">{children}</h2>,
                h3: ({ children }) => <h3 className="mb-2 mt-5 text-base font-bold text-ink first:mt-0">{children}</h3>,
                p: ({ children }) => <p className="my-3 leading-7 text-stone-700 first:mt-0 last:mb-0">{children}</p>,
                strong: ({ children }) => <strong className="font-bold text-ink">{children}</strong>,
                em: ({ children }) => <em className="text-stone-700">{children}</em>,
                ul: ({ children }) => <ul className="my-4 list-disc space-y-2 pl-6 text-stone-700 marker:text-brand-500">{children}</ul>,
                ol: ({ children }) => <ol className="my-4 list-decimal space-y-3 pl-6 text-stone-700 marker:font-bold marker:text-brand-700">{children}</ol>,
                li: ({ children }) => <li className="pl-1 leading-7">{children}</li>,
                blockquote: ({ children }) => <blockquote className="my-5 border-l-4 border-brand-300 bg-brand-50 px-4 py-2 text-stone-700">{children}</blockquote>,
                hr: () => <hr className="my-6 border-stone-200" />,
                a: ({ children, href }) => (
                    <a href={href} target="_blank" rel="noreferrer" className="font-semibold text-brand-700 underline decoration-brand-300 underline-offset-4 hover:text-brand-900">
                        {children}
                    </a>
                ),
                pre: ({ children }) => <pre className="my-5 overflow-x-auto rounded-lg border border-stone-800 bg-stone-950 p-4 text-sm leading-6 text-stone-100 shadow-inner">{children}</pre>,
                code: ({ children, className }) => {
                    const isBlock = className || String(children).includes('\n');

                    return isBlock
                        ? <code className={className}>{children}</code>
                        : <code className="rounded bg-stone-100 px-1.5 py-0.5 font-mono text-[0.875em] font-semibold text-brand-800">{children}</code>;
                },
                table: ({ children }) => (
                    <div className="my-5 overflow-x-auto border border-stone-200">
                        <table className="min-w-full divide-y divide-stone-200 text-left text-sm">{children}</table>
                    </div>
                ),
                thead: ({ children }) => <thead className="bg-stone-100 text-ink">{children}</thead>,
                th: ({ children }) => <th className="px-4 py-3 font-bold">{children}</th>,
                tbody: ({ children }) => <tbody className="divide-y divide-stone-100 bg-white">{children}</tbody>,
                td: ({ children }) => <td className="px-4 py-3 align-top leading-6 text-stone-700">{children}</td>,
                img: ({ src, alt }) => (
                    <img 
                        src={src} 
                        alt={alt} 
                        onClick={() => setSelectedImage({ src, alt })}
                        className="inline-block h-28 w-28 rounded-lg border border-stone-200 object-cover m-1 cursor-pointer hover:opacity-80 transition-opacity shadow-sm" 
                    />
                ),
            }}
        >
            {content}
        </ReactMarkdown>

        {selectedImage && (
            <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-stone-900/80 p-4 backdrop-blur-sm" onClick={() => setSelectedImage(null)}>
                <div className="relative max-h-full max-w-4xl" onClick={e => e.stopPropagation()}>
                    <div className="absolute -right-12 top-0 flex flex-col gap-2 max-sm:right-0 max-sm:-top-14 max-sm:flex-row">
                        <button 
                            onClick={() => setSelectedImage(null)} 
                            className="rounded-full bg-white/20 p-2 text-white hover:bg-white/30 transition-colors backdrop-blur"
                        >
                            <X size={20} />
                        </button>
                        <a 
                            href={selectedImage.src} 
                            download
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-full bg-brand-600 p-2 text-white hover:bg-brand-700 transition-colors shadow-lg"
                        >
                            <Download size={20} />
                        </a>
                    </div>
                    <img 
                        src={selectedImage.src} 
                        alt={selectedImage.alt} 
                        className="max-h-[85vh] rounded-xl shadow-2xl object-contain bg-white" 
                    />
                </div>
            </div>
        )}
        </>
    );
}
