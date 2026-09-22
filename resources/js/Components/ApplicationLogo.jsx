export default function ApplicationLogo({ className = '', ...props }) {
    return <img src="/images/sibermu-logo.png" alt="Logo Universitas Siber Muhammadiyah" className={`rounded-full object-cover ${className}`} {...props} />;
}
