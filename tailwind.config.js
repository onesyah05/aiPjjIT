import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Georgia', 'Cambria', 'Times New Roman', 'serif'],
            },
            colors: {
                brand: {
                    50: '#f3f6fb',
                    100: '#e5ecf7',
                    200: '#cbd9ed',
                    300: '#a6bcdb',
                    400: '#7899c4',
                    500: '#5278ad',
                    600: '#3b5e93',
                    700: '#2d4977',
                    800: '#21385d',
                    900: '#142b50',
                    950: '#061226',
                },
                paper: '#f5f7fb',
                ink: '#111c2d',
                clay: '#b78935',
            },
            borderRadius: {
                ui: '0.5rem',
                card: '0.75rem',
            },
        },
    },

    plugins: [forms],
};
