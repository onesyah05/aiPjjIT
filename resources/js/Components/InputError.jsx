export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <p
            {...props}
            className={'text-sm font-medium text-red-700 ' + className}
        >
            {message}
        </p>
    ) : null;
}
