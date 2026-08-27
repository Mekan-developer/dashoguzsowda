<?php

namespace App\Repositories\Concerns;

trait BuildsLikeSearch
{
    /**
     * Шаблон для LIKE-поиска по подстроке.
     *
     * Регистронезависимость обеспечивает collation базы (utf8mb4_0900_ai_ci),
     * поэтому LOWER() вокруг колонки не нужен: в MySQL он ничего не менял,
     * в SQLite не работает для кириллицы вовсе, а считался на каждой строке.
     *
     * `%`, `_` и `\` в пользовательском вводе экранируются — иначе запрос
     * из одного символа `%` возвращает всю таблицу.
     */
    protected static function likeTerm(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }
}
