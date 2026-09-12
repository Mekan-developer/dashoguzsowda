<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Чистка HTML, пришедшего из визуального редактора админки.
 *
 * Текст «О нас» пишет админ, но уезжает он в мобильное приложение и в браузер,
 * поэтому доверять разметке нельзя даже от своих: вставка из Word тянет за
 * собой <style> и <script>, а редактор их не отфильтрует. Пропускаем только
 * теги, которые умеет ставить панель редактора, и единственный атрибут —
 * href у ссылки, с проверкой схемы (javascript: и data: отсекаются).
 *
 * Не HTMLPurifier: тому нужен кэш-каталог и своя конфигурация, а здесь
 * закрытый список из десятка тегов, который целиком виден в ALLOWED_TAGS.
 */
class RichTextSanitizer
{
    /** Ровно то, что умеет ставить панель RichTextEditor.vue. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 's', 'u',
        'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a',
    ];

    /** Схемы ссылок, которые безопасно отдать мобилке и браузеру. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument();

        // Пустой документ (или совсем битая разметка) — считаем, что текста нет
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return null;
        }

        $root = $document->getElementById('root');

        if (! $root) {
            return null;
        }

        $this->cleanChildren($root);

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = trim($result);

        // Разметка без единого слова — это пустой редактор: <p></p> и подобное
        return trim(strip_tags($result)) === '' ? null : $result;
    }

    /**
     * Обход в глубину. Идём по копии списка детей: разрешённый тег может быть
     * заменён своим содержимым прямо в процессе, а живой DOMNodeList при этом
     * поедет и часть узлов останется непроверенной.
     */
    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $this->cleanChildren($child);

            if (! in_array(strtolower($child->nodeName), self::ALLOWED_TAGS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child);
        }
    }

    /**
     * Чужой тег снимаем, а его текст оставляем: админ мог вставить абзац из
     * Word в <span>, и терять из-за обёртки сам текст — хуже, чем разметку.
     * Исключение — <script> и <style>: у них «текст» и есть опасное содержимое.
     */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        if (in_array(strtolower($element->nodeName), ['script', 'style'], true)) {
            $parent->removeChild($element);

            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /** Все атрибуты долой; у ссылки оставляем href с безопасной схемой. */
    private function cleanAttributes(DOMElement $element): void
    {
        $href = strtolower($element->nodeName) === 'a' ? trim($element->getAttribute('href')) : '';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        if ($href === '') {
            return;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        // Относительная ссылка схемы не имеет — её пропускаем как есть
        if ($scheme !== '' && ! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return;
        }

        $element->setAttribute('href', $href);
        // Ссылка ведёт наружу: без noopener открытая вкладка получает доступ
        // к window.opener, а rel редактор не ставит
        $element->setAttribute('rel', 'noopener noreferrer');
        $element->setAttribute('target', '_blank');
    }
}
