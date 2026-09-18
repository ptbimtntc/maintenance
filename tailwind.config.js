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
                // PT Bekaert Indonesia brand palette.
                brand: {
                    50: '#FFF4F0',
                    100: '#FFE9E0',
                    200: '#FFD3C2',
                    300: '#FFB59B',
                    400: '#FF8158',
                    500: '#FF602C', // brand orange
                    600: '#F04D1B',
                    700: '#CC3D12',
                    800: '#A3300E',
                    900: '#7A240A',
                },
                accent: {
                    50: '#F2FBFE',
                    100: '#E5F7FD',
                    200: '#CCEEFC',
                    300: '#99DEF8',
                    400: '#4DC5F3',
                    500: '#01ADEF', // brand blue
                    600: '#0189BE',
                    700: '#016690',
                    800: '#014566',
                    900: '#012C42',
                },
                neutral: {
                    50: '#FBFBFA',
                    100: '#F5F4F2',
                    200: '#EBE9E5',
                    300: '#D7D2CB',
                    400: '#B8B2A8',
                    500: '#999087',
                    600: '#7A7267',
                    700: '#5C564D',
                    800: '#3D3934',
                    900: '#211F1C',
                },
            },
        },
    },

    plugins: [forms],
};
