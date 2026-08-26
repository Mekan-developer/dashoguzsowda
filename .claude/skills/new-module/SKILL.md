---
name: new-module
description: Чеклист создания новой фичи/модуля в этом проекте — порядок слоёв от миграции до языковых файлов. Использовать, когда добавляется новая сущность, раздел админки или ресурс API.
---

# Чеклист модуля (для каждой новой фичи)

Порядок важен: каждый следующий слой опирается на предыдущий.

```
1.  Migration
2.  Model
3.  Repository Interface
4.  Repository Implementation
5.  Service (если нужна бизнес-логика)
6.  Action (если нужно изолированное действие)
7.  Form Request (Admin + API отдельно)
8.  Controller (Admin + API отдельно)
9.  API Resource
10. Observer (если нужны model events)
11. Event + Listener (если есть сайд-эффекты)
12. Job (если есть фоновая обработка)
13. Vue Component / Page (Admin)
14. Pinia Store (если shared state)
15. Pest тесты (Feature + Unit)
16. Lang files (tk + ru)
```

Ограничения слоёв — в корневом `CLAUDE.md`, раздел «Архитектурные правила»:
контроллеры тонкие, Eloquent только в Repository, валидация только в Form Request,
сайд-эффекты только через Events.

Пункт 14 — по факту: Pinia в проекте пока не используется (см. `resources/js/CLAUDE.md`),
shared state живёт в пропах Inertia. Заводить store только если он действительно нужен.
