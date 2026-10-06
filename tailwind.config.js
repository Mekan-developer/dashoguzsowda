import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import twColors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            /*
             * Дизайн-система админки. Эталон — страница «Объявления» и токены
             * в resources/css/app.css. Старые имена (blue, ink, muted, dcard…)
             * сохранены, но смотрят в ту же палитру — сотни классов по страницам
             * получают единый вид без переписывания каждой.
             * blue / muted / link — через rgb-каналы CSS-переменных: у них разные
             * значения в светлой и тёмной теме, а модификаторы /10 должны работать.
             */
            colors: {
                navy:    '#1e2a3b',
                blue:    {
                    DEFAULT: 'rgb(var(--c-accent) / <alpha-value>)',
                    dark:    'rgb(var(--c-accent-hover) / <alpha-value>)',
                    light:   'var(--accent-tint)',
                },
                // Текст-ссылка: акцент, но светлее в тёмной теме — для контраста
                link:    'rgb(var(--c-link) / <alpha-value>)',
                teal:    '#0E9FB5',
                // Семантика: одинаковые тона в обеих темах, читаются и как текст,
                // и как заливка кнопки с белым текстом
                green:   { ...twColors.green, DEFAULT: '#22A06B' },
                orange:  '#D97706',
                pink:    '#DB2777',
                purple:  '#7C5CD6',
                // Шкала red-500/600… нужна бейджам и кнопкам-иконкам; red без номера — наш тон
                red:     { ...twColors.red, DEFAULT: '#DC4C4C' },
                ink:     '#15172B',
                muted:   'rgb(var(--c-muted) / <alpha-value>)',
                line:    '#EAEAF0',
                surface: '#F4F5F8',
                dnavy:   '#121528',
                dbg:     '#181B2E',
                dcard:   '#20233B',
                dline:   '#2E3148',
            },
            borderRadius: {
                card: '12px',
                btn:  '8px',
                pill: '9999px',
            },
            boxShadow: {
                // Карточки без рамки получают тонкий контур вместо тени
                soft:  '0 0 0 1px var(--card-border)',
                'lg2': '0 12px 32px rgba(10, 12, 30, .16)',
            },
            fontFamily: {
                sans: ['Nunito', ...defaultTheme.fontFamily.sans],
                data: ['Inter', 'sans-serif'],
                golos: ['Golos Text', ...defaultTheme.fontFamily.sans],
                plexmono: ['IBM Plex Mono', 'monospace'],
            },
        },
    },
    plugins: [forms],
};
