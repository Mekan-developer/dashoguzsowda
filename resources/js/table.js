/**
 * Классы таблиц админки — эталон страница «Объявления».
 * Заголовок 11.5px uppercase, ячейка 13.5px, строка 60–68px, тонкий разделитель.
 *
 *   import { th, td, tr, thead } from '@/table'
 *   <thead><tr :class="thead"><th :class="th">…</th></tr></thead>
 *   <tr :class="tr"><td :class="td">…</td></tr>
 */
export const thead = 'border-b border-[var(--card-border)] bg-black/[.015] dark:bg-white/[.02]'
export const th = 'h-11 px-5 text-left text-[11.5px] font-semibold uppercase tracking-[.06em] text-[var(--text-muted)] whitespace-nowrap'
export const td = 'px-5 py-2.5 text-[13.5px] font-medium'
export const tr = 'h-[64px] border-b border-[var(--card-border)] transition-colors duration-150 ease-out last:border-b-0 hover:bg-[var(--nav-hover)]'
