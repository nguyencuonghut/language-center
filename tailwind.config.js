import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    darkMode: 'class', // Enable class-based dark mode

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Add smooth transitions for theme switching
            transitionProperty: {
                'colors': 'background-color, border-color, color, fill, stroke',
            },
            transitionDuration: {
                'theme': '300ms',
            },
        },
    },

    plugins: [forms],
};
