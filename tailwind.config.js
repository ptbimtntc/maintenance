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
                // Status colors (success/warning/danger) - warm-shifted so
                // they sit in the same family as the brand/neutral palette
                // above instead of clashing with stock, cool-toned Tailwind
                // green/amber/red.
                success: {
                    50: '#F3FAF3',
                    100: '#E3F3E2',
                    200: '#C3E6C2',
                    300: '#96D194',
                    400: '#66B563',
                    500: '#3F9142',
                    600: '#2F7532',
                    700: '#245B27',
                    800: '#1B451E',
                    900: '#123014',
                },
                warning: {
                    50: '#FFF8EC',
                    100: '#FFEFD1',
                    200: '#FFDD9E',
                    300: '#FFC562',
                    400: '#FDA92E',
                    500: '#F0900A',
                    600: '#CC7106',
                    700: '#A35804',
                    800: '#7A4203',
                    900: '#522C02',
                },
                danger: {
                    50: '#FDF3F1',
                    100: '#FBE2DD',
                    200: '#F6C1B7',
                    300: '#EE9686',
                    400: '#E26652',
                    500: '#CB4530',
                    600: '#A93624',
                    700: '#862B1C',
                    800: '#632014',
                    900: '#40150D',
                },
            },
        },
    },

    plugins: [forms],
};
