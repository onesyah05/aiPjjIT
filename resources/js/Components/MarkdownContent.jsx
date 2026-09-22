import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

export default function MarkdownContent({ content }) {
    return (
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
            }}
        >
            {content}
        </ReactMarkdown>
    );
}
