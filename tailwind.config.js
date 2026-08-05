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

    theme: {
        extend: {
            colors: {
                surface: {
                    DEFAULT: '#f3f1ec',
                    1: '#ffffff',
                    2: '#e8e5de',
                    3: '#dbd8d0',
                    4: '#ccc9c1',
                },
                // El acento sale de variables CSS (:root en app.css) para que
                // cada negocio pueda elegir su color desde el panel: el override
                // se inyecta en app.blade.php. Formato "R G B" para que los
                // modificadores de opacidad (bg-accent/10) sigan funcionando.
                accent: {
                    DEFAULT: 'rgb(var(--accent) / <alpha-value>)',
                    dim: 'rgb(var(--accent-dim) / <alpha-value>)',
                    bright: 'rgb(var(--accent-bright) / <alpha-value>)',
                    muted: 'rgb(var(--accent) / 0.10)',
                },
                text: {
                    DEFAULT: '#1a1d21',
                    secondary: '#52565e',
                    muted: '#8e919a',
                },
                border: {
                    DEFAULT: 'rgba(0,0,0,0.10)',
                    hover: 'rgba(0,0,0,0.18)',
                },
            },
            fontFamily: {
                // Igual que el acento: la fuente sale de una variable CSS para
                // que el negocio la elija desde el panel. El default vive en
                // app.css; app.blade.php inyecta el override.
                sans: ['var(--fuente)', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                xl: '12px',
                '2xl': '12px',
                '3xl': '12px',
            },
        },
    },

    plugins: [forms],
};
