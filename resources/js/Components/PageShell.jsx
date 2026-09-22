export default function PageShell({ children, size = 'wide', className = '' }) {
    const widths = { narrow: 'max-w-4xl', default: 'max-w-6xl', wide: 'max-w-7xl' };

    return <div className={`mx-auto w-full ${widths[size] || widths.wide} px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10 ${className}`}>{children}</div>;
}
