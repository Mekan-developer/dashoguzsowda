<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Icon from '@/Components/Icon.vue'

// Визуальный редактор для статических текстов админки (сейчас — «О нас»).
// Отдаёт HTML: набор тегов ровно тот, что чистит App\Services\RichTextSanitizer,
// поэтому кнопок здесь не больше, чем переживёт сохранение.

const { t } = useI18n()

const props = defineProps({
    modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        // Заголовки только h2/h3: h1 — это заголовок экрана в приложении
        StarterKit.configure({
            heading: { levels: [2, 3] },
            codeBlock: false,
            code: false,
            horizontalRule: false,
        }),
        Link.configure({ openOnClick: false, autolink: false }),
    ],
    onUpdate: ({ editor }) => {
        // Пустой редактор отдаёт <p></p> — наружу это должно уходить пустой
        // строкой, иначе «текста нет» не отличить от «текст есть»
        emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML())
    },
})

// Переключение языка меняет modelValue снаружи — перезаливаем содержимое.
// Сравнение с текущим HTML обязательно: без него setContent сработал бы на
// собственный onUpdate и сбрасывал каретку в начало на каждом нажатии.
watch(() => props.modelValue, (value) => {
    if (!editor.value) return
    const current = editor.value.isEmpty ? '' : editor.value.getHTML()
    if (value !== current) editor.value.commands.setContent(value || '', { emitUpdate: false })
})

onBeforeUnmount(() => editor.value?.destroy())

const buttonClass = (active) => [
    'flex h-8 min-w-8 items-center justify-center rounded-btn px-2 text-[13px] font-bold transition',
    active
        ? 'bg-blue text-white'
        : 'text-[var(--text-secondary)] hover:bg-surface dark:hover:bg-dbg',
]

function setLink() {
    const previous = editor.value?.getAttributes('link').href || ''
    const url = window.prompt(t('editor.linkPrompt'), previous)

    if (url === null) return

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
        return
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}
</script>

<template>
  <div class="overflow-hidden rounded-[8px] border border-[var(--field-border)]">
    <!-- Панель: набор кнопок = набор тегов, которые переживут сохранение -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-line bg-surface px-2 py-1.5 dark:border-dline dark:bg-dbg">
      <button type="button" :title="t('editor.bold')" :class="buttonClass(editor.isActive('bold'))"
              @click="editor.chain().focus().toggleBold().run()">
        <span class="font-bold">B</span>
      </button>
      <button type="button" :title="t('editor.italic')" :class="buttonClass(editor.isActive('italic'))"
              @click="editor.chain().focus().toggleItalic().run()">
        <span class="italic">I</span>
      </button>
      <button type="button" :title="t('editor.strike')" :class="buttonClass(editor.isActive('strike'))"
              @click="editor.chain().focus().toggleStrike().run()">
        <span class="line-through">S</span>
      </button>

      <span class="mx-1 h-5 w-px bg-line dark:bg-dline"></span>

      <button type="button" :title="t('editor.heading2')" :class="buttonClass(editor.isActive('heading', { level: 2 }))"
              @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
      <button type="button" :title="t('editor.heading3')" :class="buttonClass(editor.isActive('heading', { level: 3 }))"
              @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>

      <span class="mx-1 h-5 w-px bg-line dark:bg-dline"></span>

      <button type="button" :title="t('editor.bulletList')" :class="buttonClass(editor.isActive('bulletList'))"
              @click="editor.chain().focus().toggleBulletList().run()">•—</button>
      <button type="button" :title="t('editor.orderedList')" :class="buttonClass(editor.isActive('orderedList'))"
              @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
      <button type="button" :title="t('editor.quote')" :class="buttonClass(editor.isActive('blockquote'))"
              @click="editor.chain().focus().toggleBlockquote().run()">❝</button>

      <span class="mx-1 h-5 w-px bg-line dark:bg-dline"></span>

      <button type="button" :title="t('editor.link')" :class="buttonClass(editor.isActive('link'))" @click="setLink">
        <Icon kind="link" :size="15" />
      </button>
      <button type="button" :title="t('editor.clearFormat')" :class="buttonClass(false)"
              @click="editor.chain().focus().unsetAllMarks().clearNodes().run()">
        <Icon kind="close" :size="15" />
      </button>

      <span class="mx-1 h-5 w-px bg-line dark:bg-dline"></span>

      <button type="button" :title="t('editor.undo')" :class="buttonClass(false)"
              :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">↶</button>
      <button type="button" :title="t('editor.redo')" :class="buttonClass(false)"
              :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">↷</button>
    </div>

    <EditorContent :editor="editor" class="rich-text bg-white dark:bg-dcard" />
  </div>
</template>

<style scoped>
/* ProseMirror строит разметку сам, навесить на неё Tailwind-классы неоткуда —
   это тот случай «крайней необходимости», о котором говорит resources/js/CLAUDE.md.
   Стилей ровно столько, чтобы абзацы и списки читались при наборе. */
.rich-text :deep(.ProseMirror) {
    min-height: 220px;
    max-height: 460px;
    overflow-y: auto;
    padding: 12px 16px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--text);
    outline: none;
}
.rich-text :deep(.ProseMirror > * + *) { margin-top: 0.7em; }
.rich-text :deep(.ProseMirror h2)      { font-size: 18px; font-weight: 800; }
.rich-text :deep(.ProseMirror h3)      { font-size: 15px; font-weight: 800; }
.rich-text :deep(.ProseMirror ul)      { list-style: disc;    padding-left: 1.4em; }
.rich-text :deep(.ProseMirror ol)      { list-style: decimal; padding-left: 1.4em; }
.rich-text :deep(.ProseMirror li)      { margin-top: 0.25em; }
.rich-text :deep(.ProseMirror a)       { color: #4361ee; text-decoration: underline; }
.rich-text :deep(.ProseMirror blockquote) {
    border-left: 3px solid var(--card-border);
    padding-left: 0.9em;
    color: var(--text-secondary);
}
</style>
