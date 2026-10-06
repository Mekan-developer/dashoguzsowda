<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import ChatDialogList from '@/Components/ChatDialogList.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()

const props = defineProps({ chatUser: Object, messages: Array, dialogs: Array })

const replyText     = ref('')
const inputEl       = ref(null)
const messagesEl    = ref(null)
const localMessages = ref([...(props.messages ?? [])])

onMounted(() => {
    scrollToBottom()

    window.Echo?.private(`chat.${props.chatUser.id}`)
        .listen('.new-message', (e) => {
            if (e.sender !== 'user') return
            localMessages.value.push(e)
            scrollToBottom()
            router.patch(route('chat.read', props.chatUser.id), {}, { preserveScroll: true, preserveState: true, only: [] })
        })
        // Пользователь открыл чат в мобилке — гасим галочки на своих ответах,
        // не перезагружая страницу.
        .listen('.messages-read', (e) => {
            if (e.sender !== 'admin') return
            localMessages.value.forEach((m) => {
                if (m.sender === 'admin') m.is_read = true
            })
        })
})

onUnmounted(() => {
    window.Echo?.leave(`chat.${props.chatUser.id}`)
})

function scrollToBottom() {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight
        }
    })
}

// Поле ответа растёт по тексту до 140px, дальше прокручивается
function autoGrow() {
    const el = inputEl.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 140) + 'px'
}

function sendReply() {
    if (!replyText.value.trim()) return
    router.post(route('chat.reply', props.chatUser.id), { text: replyText.value }, {
        onSuccess: () => { replyText.value = ''; nextTick(autoGrow); scrollToBottom() },
        preserveState: false,
    })
}

function formatTime(d) {
    if (!d) return ''
    return new Date(d).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' })
}
// Разделители по дням в переписке
function dayKey(msg) {
    return msg?.created_at ? new Date(msg.created_at).toDateString() : null
}
function dayLabel(d) {
    const date = new Date(d)
    if (date.toDateString() === new Date().toDateString()) return t('chat.today')
    return date.toLocaleDateString(locale.value, { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.chat') }}</template>

    <div class="card flex h-[calc(100vh-212px)] min-h-[460px] overflow-hidden">
      <!-- Диалоги (на узком экране скрыты — есть ссылка «К диалогам») -->
      <aside class="hidden w-[320px] flex-none flex-col border-r border-[var(--card-border)] md:flex">
        <div class="flex h-[64px] flex-none items-center border-b border-[var(--card-border)] px-4">
          <h2 class="card-title">{{ t('chat.dialogs') }}</h2>
        </div>
        <div class="flex-1 overflow-y-auto">
          <ChatDialogList :dialogs="dialogs" :active-id="chatUser.id" />
        </div>
      </aside>

      <!-- Переписка -->
      <section class="flex min-w-0 flex-1 flex-col">
        <!-- Шапка: кто это и переход в профиль -->
        <header class="flex h-[64px] flex-none items-center gap-3 border-b border-[var(--card-border)] px-4 sm:px-5">
          <Link :href="route('chat.index')" class="icon-btn md:hidden" :title="t('chat.back')" :aria-label="t('chat.back')">
            <Icon kind="chevronLeft" :size="16" />
          </Link>
          <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-[var(--accent-tint)] text-[14px] font-semibold text-link">
            {{ (chatUser.name || chatUser.phone || '?').charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="truncate text-[14px] font-semibold text-[var(--text)]">{{ chatUser.name || chatUser.phone }}</div>
            <div class="font-data text-[12px] text-[var(--text-muted)]">{{ chatUser.phone }}</div>
          </div>
          <StatusBadge v-if="chatUser.status" :status="chatUser.status" class="hidden sm:inline-flex" />
          <Link :href="route('users.show', chatUser.id)" class="btn btn-secondary btn-sm ml-auto">
            <Icon kind="users" :size="15" /><span class="hidden sm:inline">{{ t('chat.profileLink') }}</span>
          </Link>
        </header>

        <!-- Сообщения, сгруппированные по дням -->
        <div ref="messagesEl" class="flex-1 overflow-y-auto bg-black/[.012] px-4 py-5 dark:bg-white/[.012] sm:px-6">
          <template v-for="(msg, i) in localMessages" :key="msg.id">
            <div v-if="dayKey(msg) !== dayKey(localMessages[i - 1])" class="my-4 flex items-center gap-3 first:mt-0">
              <span class="h-px flex-1 bg-[var(--card-border)]"></span>
              <span class="text-[11.5px] font-medium text-[var(--text-muted)]">{{ dayLabel(msg.created_at) }}</span>
              <span class="h-px flex-1 bg-[var(--card-border)]"></span>
            </div>
            <div class="mb-2 flex" :class="msg.sender === 'admin' ? 'justify-end' : 'justify-start'">
              <div
                class="max-w-[min(70%,560px)] whitespace-pre-line break-words px-3.5 py-2 text-[13.5px] leading-relaxed"
                :class="msg.sender === 'admin'
                  ? 'rounded-[12px] rounded-br-[4px] bg-[var(--accent)] text-white'
                  : 'rounded-[12px] rounded-bl-[4px] border border-[var(--card-border)] bg-[var(--card-bg)] text-[var(--text)]'"
              >
                {{ msg.text }}
                <div class="mt-0.5 flex items-center gap-1 font-data text-[10.5px]" :class="msg.sender === 'admin' ? 'justify-end text-white/70' : 'text-[var(--text-muted)]'">
                  <span>{{ formatTime(msg.created_at) }}</span>
                  <!-- Статус только на своих ответах: входящие оператор читает самим фактом открытия диалога -->
                  <svg
                    v-if="msg.sender === 'admin'"
                    class="h-3.5 w-3.5 flex-shrink-0"
                    :class="msg.is_read ? 'text-white' : ''"
                    viewBox="0 0 20 20" fill="none" stroke="currentColor"
                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                    role="img"
                    :aria-label="msg.is_read ? t('chat.markRead') : t('chat.markSent')"
                  >
                    <title>{{ msg.is_read ? t('chat.markRead') : t('chat.markSent') }}</title>
                    <path d="M1.5 10.6 5.2 14.3 12.4 5.9" />
                    <path v-if="msg.is_read" d="M7.6 14.3 14.8 5.9" />
                  </svg>
                </div>
              </div>
            </div>
          </template>
          <EmptyState v-if="!localMessages?.length" compact icon="chat" :title="t('chat.noMessages')" />
        </div>

        <!-- Ответ -->
        <form class="flex-none border-t border-[var(--card-border)] p-3 sm:p-4" @submit.prevent="sendReply">
          <div class="flex items-end gap-2">
            <textarea
              ref="inputEl"
              v-model="replyText"
              rows="1"
              :placeholder="t('chat.inputPlaceholder')"
              :aria-label="t('chat.inputPlaceholder')"
              class="input max-h-[140px] flex-1 resize-none"
              @input="autoGrow"
              @keydown.enter.exact.prevent="sendReply"
            ></textarea>
            <button type="submit" class="btn btn-primary" :disabled="!replyText.trim()">
              <Icon kind="send" :size="16" /><span class="hidden sm:inline">{{ t('actions.send') }}</span>
            </button>
          </div>
          <p class="mt-1.5 hidden text-[11.5px] text-[var(--text-muted)] sm:block">{{ t('chat.sendHint') }}</p>
        </form>
      </section>
    </div>
  </AppLayout>
</template>
