import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand palette anchored on the official brand colour #395750.
                brand: {
                    50: '#f3f6f5',
                    100: '#e2eae7',
                    200: '#c4d6d0',
                    300: '#9dbbb2',
                    400: '#719b90',
                    500: '#517c71',
                    600: '#395750',
                    700: '#2f4842',
                    800: '#283b37',
                    900: '#22322e',
                    950: '#101a17',
                },
            },
        },
    },

    plugins: [forms],
};
