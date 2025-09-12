import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    darkMode: 'class', // Opción para manejar el modo oscuro mediante una clase
    theme: {
        extend: {
            fontFamily: {
                sans: ['"DM Sans"', 'sans-serif'],
            },
            colors: {
                primary: '#0355B5',
                primarylight: '#FB9D9FE',
                secondary: '#D90537',
                secondarylight: '#FEBFCE',
                gray1: '#9A9A9A',
                gray2: '#D9D9D9',
            },
            animation: {
                'ping-delay': 'ping 1s cubic-bezier(0, 0, 0.2, 1) infinite 0.5s',
            }
        },
    },

    plugins: [forms, typography],
};
