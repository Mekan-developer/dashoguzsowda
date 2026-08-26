# Frontend админки (Inertia + Vue 3)

Подгружается, когда идёт работа с файлами `resources/js/**`.

## Правила

- Всегда `<script setup>` с Composition API
- Только Tailwind CSS — никакого кастомного CSS без крайней необходимости
- Dark/Light mode: `dark:` классы, класс `dark` на `<html>`
- Каждая страница: dark-mode классы + i18n (`vue-i18n`, словари в `resources/js/i18n/`)
- Каждый Laravel-ответ, который попадает в UI, локализован через `__()`

## Тосты — через Inertia flash, а не через store

Реализация: `Components/Toasts.vue`, позиция **bottom-right**, авто-скрытие через
**3.5 секунды**, клик по тосту закрывает его. Типы: `success`, `error`, `warning`, `info`.

Из Laravel:

```php
return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
```

`App\Http\Middleware\HandleInertiaRequests` отдаёт это как `flash.toast`,
`Toasts.vue` следит за пропом. Отдельного store для уведомлений нет —
не выдумывать `useNotificationStore`.

## Про Pinia

`pinia` есть в `package.json`, но в коде сейчас не используется: ни одного
`defineStore` нет, папки `Stores/` нет. Shared state пока живёт в пропах Inertia.
Если вводится store — заводить осознанно, а не «потому что так в правилах».

## Раскладка

- `Components/` — общие компоненты (без подпапок `Admin/`/`Shared/`)
- `Layouts/` — `AppLayout.vue`, `AuthenticatedLayout.vue`, `GuestLayout.vue`
- `Pages/` — страницы по доменам: `Listings/`, `Videos/`, `Users/`, `Categories/`,
  `Regions/`, `Tariffs/`, `News/`, `Banners/`, `Complaints/`, `Reviews/`, `Chat/`,
  `Push/`, `Statistics/`, `Settings/`, `Profile/`, `Auth/`
