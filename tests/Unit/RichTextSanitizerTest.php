<?php

use App\Services\RichTextSanitizer;

/**
 * Чистка HTML из визуального редактора.
 *
 * Текст пишет админ, но уезжает он в мобильное приложение — доверять разметке
 * нельзя даже от своих: вставка из Word тянет <style>, а любая вставка может
 * принести обработчик события.
 */
beforeEach(fn () => $this->sanitizer = new RichTextSanitizer());

it('оставляет разметку, которую ставит панель редактора', function () {
    $html = '<h2>О нас</h2><p><strong>Дашогуз Совда</strong> — доска <em>объявлений</em>.</p>'
        .'<ul><li>Первое</li><li>Второе</li></ul><ol><li>Раз</li></ol><blockquote>Цитата</blockquote>';

    expect($this->sanitizer->clean($html))->toBe($html);
});

it('вырезает скрипт вместе с его содержимым', function () {
    $result = $this->sanitizer->clean('<p>Текст</p><script>alert(1)</script>');

    expect($result)->toBe('<p>Текст</p>')
        ->and($result)->not->toContain('alert');
});

it('вырезает style — его приносит вставка из Word', function () {
    expect($this->sanitizer->clean('<style>p{color:red}</style><p>Текст</p>'))->toBe('<p>Текст</p>');
});

it('снимает обработчики событий и классы, но оставляет текст', function () {
    $result = $this->sanitizer->clean('<p onclick="alert(1)" class="x" style="color:red">Текст</p>');

    expect($result)->toBe('<p>Текст</p>');
});

it('разворачивает чужой тег, сохраняя его текст', function () {
    expect($this->sanitizer->clean('<div><span>Текст</span> абзаца</div>'))->toBe('Текст абзаца');
});

it('оставляет ссылку и добавляет ей безопасный rel', function () {
    $result = $this->sanitizer->clean('<p><a href="https://example.com">Сайт</a></p>');

    expect($result)->toContain('href="https://example.com"')
        ->and($result)->toContain('rel="noopener noreferrer"')
        ->and($result)->toContain('target="_blank"');
});

it('выбрасывает href со схемой javascript, оставляя саму ссылку текстом', function () {
    $result = $this->sanitizer->clean('<p><a href="javascript:alert(1)">Клик</a></p>');

    expect($result)->not->toContain('javascript')
        ->and($result)->toContain('Клик');
});

it('пропускает tel и mailto — это рабочие ссылки страницы «О нас»', function () {
    expect($this->sanitizer->clean('<p><a href="tel:+99361234567">Позвонить</a></p>'))
        ->toContain('href="tel:+99361234567"');
});

it('считает пустой редактор отсутствием текста', function () {
    expect($this->sanitizer->clean('<p></p>'))->toBeNull()
        ->and($this->sanitizer->clean('   '))->toBeNull()
        ->and($this->sanitizer->clean(null))->toBeNull();
});

it('не ломается на кириллице', function () {
    expect($this->sanitizer->clean('<p>Тölegler we söwda</p>'))->toBe('<p>Тölegler we söwda</p>');
});
