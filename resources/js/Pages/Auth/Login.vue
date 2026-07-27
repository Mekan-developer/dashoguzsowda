<script setup>
import { ref, watch, onMounted, nextTick } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
})

// ── Dark mode ──────────────────────────────────────────────────────────────
const isDark = ref(localStorage.getItem('rootboard_login_dark') === 'true')
watch(isDark, val => {
    document.documentElement.classList.toggle('dark', val)
    localStorage.setItem('rootboard_login_dark', String(val))
})
onMounted(() => document.documentElement.classList.toggle('dark', isDark.value))

// ── Toast ──────────────────────────────────────────────────────────────────
const toastVisible = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
let toastTimer = null
const showToast = (msg, type = 'success') => {
    clearTimeout(toastTimer)
    toastMessage.value = msg
    toastType.value = type
    toastVisible.value = true
    toastTimer = setTimeout(() => { toastVisible.value = false }, 3500)
}

// ── View state ─────────────────────────────────────────────────────────────
const view = ref('login')

// ── Login ──────────────────────────────────────────────────────────────────
const loginForm = useForm({ email: '', password: '', remember: false })
const showLoginPwd = ref(false)
const loginErrs = ref({})

const submitLogin = () => {
    loginErrs.value = {}
    if (!loginForm.email || !/\S+@\S+\.\S+/.test(loginForm.email))
        loginErrs.value.email = 'Введите корректный адрес электронной почты.'
    if (!loginForm.password || loginForm.password.length < 6)
        loginErrs.value.password = 'Пароль должен содержать не менее 6 символов.'
    if (Object.keys(loginErrs.value).length) return
    loginForm.post(route('login'), {
        onFinish: () => loginForm.reset('password'),
        onError: (e) => { loginErrs.value = { ...e }; view.value = 'login' },
    })
}

// ── Forgot password ────────────────────────────────────────────────────────
const fpEmail = ref('')
const fpError = ref('')
const fpProcessing = ref(false)
const sentEmail = ref('')

const submitFP = () => {
    fpError.value = ''
    if (!fpEmail.value || !/\S+@\S+\.\S+/.test(fpEmail.value)) {
        fpError.value = 'Введите корректный адрес электронной почты.'
        return
    }
    fpProcessing.value = true
    sentEmail.value = fpEmail.value
    router.post(route('password.email'), { email: fpEmail.value }, {
        preserveUrl: true,
        preserveState: true,
        onSuccess: () => { showToast(`Ссылка отправлена на ${sentEmail.value}`); view.value = 'reset-code' },
        onError: (e) => { fpError.value = e.email || 'Произошла ошибка. Попробуйте снова.' },
        onFinish: () => { fpProcessing.value = false },
    })
}

// ── OTP ────────────────────────────────────────────────────────────────────
const otp = ref(['', '', '', '', '', ''])
const otpRefs = ref([])
const otpErr = ref('')

const onOtpInput = (i, e) => {
    const v = e.target.value.replace(/\D/g, '')
    otp.value[i] = v.slice(-1)
    e.target.value = otp.value[i]
    if (v && i < 5) nextTick(() => otpRefs.value[i + 1]?.focus())
}
const onOtpKey = (i, e) => {
    if (e.key === 'Backspace' && !otp.value[i] && i > 0) otpRefs.value[i - 1]?.focus()
}
const onOtpPaste = (e) => {
    e.preventDefault()
    const digits = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
    digits.split('').forEach((d, i) => { otp.value[i] = d })
    nextTick(() => otpRefs.value[Math.min(digits.length, 5)]?.focus())
}
const submitOtp = () => {
    if (otp.value.join('').length < 6) { otpErr.value = 'Введите полный 6-значный код.'; return }
    otpErr.value = ''
    view.value = 'reset-new'
}
const resendCode = () => showToast('Новый код отправлен!')

// ── New password ───────────────────────────────────────────────────────────
const newPwd = ref('')
const newPwdConfirm = ref('')
const showNewPwd = ref(false)
const showConfirmPwd = ref(false)
const newPwdErrs = ref({})
const newPwdProcessing = ref(false)

const submitNewPwd = () => {
    newPwdErrs.value = {}
    if (!newPwd.value || newPwd.value.length < 8)
        newPwdErrs.value.password = 'Новый пароль должен содержать не менее 8 символов.'
    if (newPwd.value !== newPwdConfirm.value)
        newPwdErrs.value.confirm = 'Пароли не совпадают.'
    if (Object.keys(newPwdErrs.value).length) return
    newPwdProcessing.value = true
    setTimeout(() => { newPwdProcessing.value = false; view.value = 'reset-done' }, 800)
}

</script>

<template>
    <Head title="Войти — " />

    <div class="flex h-screen overflow-hidden" :style="{ background: isDark ? '#0f1623' : '#f8fafc' }">

        <!-- ── TOP BAR ───────────────────────────────────────────────────── -->
        <div class="fixed top-4 right-4 z-50 flex items-center gap-2">
            <button
                type="button"
                @click="isDark = !isDark"
                class="w-9 h-9 flex items-center justify-center rounded-[10px] border-2 transition-colors"
                :style="{ background: isDark ? '#1a2332' : '#ffffff', borderColor: isDark ? '#2d3748' : '#e2e8f0', color: isDark ? '#cbd5e0' : '#2d3748' }"
                aria-label="Переключить тему"
            >
                <svg v-if="!isDark" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- ── LEFT PANEL ─────────────────────────────────────────────────── -->
        <div
            class="hidden lg:flex w-[52%] flex-col justify-between relative overflow-hidden"
            style="background: linear-gradient(160deg, #1e2a3b 0%, #0f172a 100%); padding: 48px 56px;"
        >
            <div class="absolute pointer-events-none" style="top:-80px;right:-80px;width:500px;height:500px;background:radial-gradient(circle,rgba(67,97,238,.25) 0%,transparent 70%);border-radius:50%;"></div>
            <div class="absolute pointer-events-none" style="bottom:-60px;left:-60px;width:400px;height:400px;background:radial-gradient(circle,rgba(0,180,216,.15) 0%,transparent 70%);border-radius:50%;"></div>

            <!-- Main block -->
            <div class="flex-1 flex flex-col justify-center py-12">
                <h1 class="text-white font-bold mb-4" style="font-size:36px;font-family:'Nunito',sans-serif;line-height:1.2;">
                    Ваш личный кабинет,<br>
                    готов к <span style="color:#00b4d8;">новым объявлениям</span>
                </h1>
                <p class="mb-10" style="color:rgba(255,255,255,.55);font-size:15px;line-height:1.6;max-width:380px;font-family:'Nunito',sans-serif;">
                    Войдите, чтобы управлять своими объявлениями, отвечать на сообщения и находить лучшие предложения.
                </p>

                <div class="flex flex-col gap-3">
                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px 18px;backdrop-filter:blur(8px);">
                        <div class="flex items-center gap-3">
                            <div style="width:38px;height:38px;background:rgba(67,97,238,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4361ee" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                            </div>
                            <div>
                                <strong class="block text-sm" style="color:#fff;font-family:'Nunito',sans-serif;font-weight:700;">Тысячи объявлений</strong>
                                <span style="color:rgba(255,255,255,.5);font-size:12px;font-family:'Nunito',sans-serif;">Найдите всё что нужно рядом с вами</span>
                            </div>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px 18px;backdrop-filter:blur(8px);">
                        <div class="flex items-center gap-3">
                            <div style="width:38px;height:38px;background:rgba(0,180,216,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00b4d8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </div>
                            <div>
                                <strong class="block text-sm" style="color:#fff;font-family:'Nunito',sans-serif;font-weight:700;">Быстрая публикация</strong>
                                <span style="color:rgba(255,255,255,.5);font-size:12px;font-family:'Nunito',sans-serif;">Разместите объявление за минуту</span>
                            </div>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px 18px;backdrop-filter:blur(8px);">
                        <div class="flex items-center gap-3">
                            <div style="width:38px;height:38px;background:rgba(6,214,160,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#06d6a0" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div>
                                <strong class="block text-sm" style="color:#fff;font-family:'Nunito',sans-serif;font-weight:700;">Безопасные сделки</strong>
                                <span style="color:rgba(255,255,255,.5);font-size:12px;font-family:'Nunito',sans-serif;">Проверенные продавцы и покупатели</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div style="font-size:12px;font-family:'Nunito',sans-serif;color:rgba(255,255,255,.3);">
                <a href="/" style="color:rgba(255,255,255,.3);text-decoration:none;">← На главную</a>
                <span class="mx-2">·</span>
                <a href="#" style="color:rgba(255,255,255,.3);text-decoration:none;">Политика конфиденциальности</a>
                <span class="mx-2">·</span>
                <a href="#" style="color:rgba(255,255,255,.3);text-decoration:none;">Условия использования</a>
            </div>
        </div>

        <!-- ── RIGHT PANEL ────────────────────────────────────────────────── -->
        <div
            class="flex-1 flex items-center justify-center overflow-y-auto px-6 py-10 lg:px-14 lg:py-12"
            :style="{ background: isDark ? '#111827' : '#ffffff' }"
        >
            <div class="w-full" style="max-width:400px;">

                <!-- ═══ LOGIN ══════════════════════════════════════════════ -->
                <div v-if="view === 'login'">
                    <h2 class="font-bold mb-1" style="font-size:26px;font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">
                        Добро пожаловать 👋
                    </h2>
                    <p class="mb-6 text-sm" style="font-family:'Nunito',sans-serif;color:#718096;">
                        Войдите в свой аккаунт, чтобы продолжить.
                    </p>

                    <form @submit.prevent="submitLogin" novalidate>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold mb-1.5" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">
                                Электронная почта
                            </label>
                            <div class="relative">
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2" style="color:#718096;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </div>
                                <input
                                    v-model="loginForm.email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="you@gmail.com"
                                    class="w-full rounded-[10px] border-2 text-sm font-semibold transition-all duration-200 outline-none"
                                    style="padding:12px 14px 12px 42px;font-family:'Nunito',sans-serif;"
                                    :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: (loginErrs.email || loginForm.errors.email) ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                                />
                            </div>
                            <p v-if="loginErrs.email || loginForm.errors.email" class="text-xs mt-1" style="color:#e53e3e;font-family:'Nunito',sans-serif;">
                                {{ loginErrs.email || loginForm.errors.email }}
                            </p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-semibold mb-1.5" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">
                                Пароль
                            </label>
                            <div class="relative">
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2" style="color:#718096;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                </div>
                                <input
                                    v-model="loginForm.password"
                                    :type="showLoginPwd ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    placeholder="Введите ваш пароль"
                                    class="w-full rounded-[10px] border-2 text-sm font-semibold transition-all duration-200 outline-none"
                                    style="padding:12px 42px 12px 42px;font-family:'Nunito',sans-serif;"
                                    :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: (loginErrs.password || loginForm.errors.password) ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                                />
                                <button type="button" @click="showLoginPwd = !showLoginPwd" class="absolute right-3.5 top-1/2 -translate-y-1/2" style="color:#718096;" aria-label="Показать/скрыть пароль">
                                    <svg v-if="!showLoginPwd" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p v-if="loginErrs.password || loginForm.errors.password" class="text-xs mt-1" style="color:#e53e3e;font-family:'Nunito',sans-serif;">
                                {{ loginErrs.password || loginForm.errors.password }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between mb-6">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" v-model="loginForm.remember" class="w-4 h-4 rounded" style="accent-color:#4361ee;" />
                                <span class="text-sm" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">Запомнить меня</span>
                            </label>
                            <button type="button" @click="view = 'reset-email'" class="text-sm font-semibold" style="color:#4361ee;font-family:'Nunito',sans-serif;">
                                Забыли пароль?
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="loginForm.processing"
                            class="w-full flex items-center justify-center gap-2 text-white font-bold rounded-[10px] transition-all duration-200"
                            style="padding:14px;font-size:15px;font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#4361ee,#3a0ca3);box-shadow:0 4px 16px rgba(67,97,238,.35);border:none;"
                            :style="{ opacity: loginForm.processing ? .7 : 1 }"
                        >
                            <svg v-if="loginForm.processing" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            <span>{{ loginForm.processing ? 'Входим…' : 'Войти' }}</span>
                        </button>
                    </form>
                </div>

                <!-- ═══ RESET — STEP 1: EMAIL ═══════════════════════════════ -->
                <div v-else-if="view === 'reset-email'">
                    <button type="button" @click="view = 'login'" class="flex items-center gap-1.5 text-sm font-semibold mb-6" style="color:#4361ee;font-family:'Nunito',sans-serif;">
                        ← Назад к входу
                    </button>

                    <div class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#4361ee;">1</div>
                        <div class="flex-1 h-0.5" :style="{ background: isDark ? '#2d3748' : '#e2e8f0' }"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :style="{ borderColor: isDark ? '#2d3748' : '#e2e8f0', color: '#718096' }">2</div>
                        <div class="flex-1 h-0.5" :style="{ background: isDark ? '#2d3748' : '#e2e8f0' }"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :style="{ borderColor: isDark ? '#2d3748' : '#e2e8f0', color: '#718096' }">3</div>
                    </div>

                    <h2 class="font-bold mb-1" style="font-size:26px;font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">Забыли пароль?</h2>
                    <p class="mb-6 text-sm" style="font-family:'Nunito',sans-serif;color:#718096;">Введите email — мы отправим вам ссылку для сброса.</p>

                    <form @submit.prevent="submitFP" novalidate>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold mb-1.5" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">Электронная почта</label>
                            <div class="relative">
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2" style="color:#718096;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </div>
                                <input v-model="fpEmail" type="email" autocomplete="email" placeholder="you@example.com"
                                    class="w-full rounded-[10px] border-2 text-sm font-semibold transition-all duration-200 outline-none"
                                    style="padding:12px 14px 12px 42px;font-family:'Nunito',sans-serif;"
                                    :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: fpError ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                                />
                            </div>
                            <p v-if="fpError" class="text-xs mt-1" style="color:#e53e3e;font-family:'Nunito',sans-serif;">{{ fpError }}</p>
                        </div>

                        <button type="submit" :disabled="fpProcessing"
                            class="w-full flex items-center justify-center gap-2 text-white font-bold rounded-[10px] transition-all duration-200"
                            style="padding:14px;font-size:15px;font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#4361ee,#3a0ca3);box-shadow:0 4px 16px rgba(67,97,238,.35);border:none;"
                            :style="{ opacity: fpProcessing ? .7 : 1 }"
                        >
                            <svg v-if="fpProcessing" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            <span>{{ fpProcessing ? 'Отправляем…' : 'Отправить ссылку' }}</span>
                        </button>
                    </form>
                </div>

                <!-- ═══ RESET — STEP 2: CODE ═══════════════════════════════ -->
                <div v-else-if="view === 'reset-code'">
                    <button type="button" @click="view = 'reset-email'" class="flex items-center gap-1.5 text-sm font-semibold mb-6" style="color:#4361ee;font-family:'Nunito',sans-serif;">
                        ← Назад
                    </button>

                    <div class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#06d6a0;">✓</div>
                        <div class="flex-1 h-0.5" style="background:#4361ee;"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#4361ee;">2</div>
                        <div class="flex-1 h-0.5" :style="{ background: isDark ? '#2d3748' : '#e2e8f0' }"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :style="{ borderColor: isDark ? '#2d3748' : '#e2e8f0', color: '#718096' }">3</div>
                    </div>

                    <h2 class="font-bold mb-1" style="font-size:26px;font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">Введите код</h2>
                    <p class="mb-6 text-sm" style="font-family:'Nunito',sans-serif;color:#718096;">
                        Мы отправили 6-значный код на <strong :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">{{ sentEmail }}</strong>
                    </p>

                    <div class="flex gap-2 mb-2" @paste.prevent="onOtpPaste">
                        <input
                            v-for="(_, i) in otp"
                            :key="i"
                            :ref="el => { if (el) otpRefs[i] = el }"
                            :value="otp[i]"
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            class="flex-1 text-center rounded-[10px] border-2 text-xl font-bold transition-all duration-200 outline-none"
                            style="padding:12px 4px;font-family:'Nunito',sans-serif;aspect-ratio:1;"
                            :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: otpErr ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                            @input="onOtpInput(i, $event)"
                            @keydown="onOtpKey(i, $event)"
                        />
                    </div>
                    <p v-if="otpErr" class="text-xs mb-4" style="color:#e53e3e;font-family:'Nunito',sans-serif;">{{ otpErr }}</p>

                    <button type="button" @click="resendCode" class="text-sm font-semibold mb-6 block" style="color:#4361ee;font-family:'Nunito',sans-serif;">
                        Не получили? Отправить повторно
                    </button>

                    <button type="button" @click="submitOtp"
                        class="w-full flex items-center justify-center gap-2 text-white font-bold rounded-[10px] transition-all duration-200"
                        style="padding:14px;font-size:15px;font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#4361ee,#3a0ca3);box-shadow:0 4px 16px rgba(67,97,238,.35);border:none;"
                    >
                        Подтвердить код
                    </button>
                </div>

                <!-- ═══ RESET — STEP 3: NEW PASSWORD ════════════════════════ -->
                <div v-else-if="view === 'reset-new'">
                    <div class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#06d6a0;">✓</div>
                        <div class="flex-1 h-0.5" style="background:#06d6a0;"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#06d6a0;">✓</div>
                        <div class="flex-1 h-0.5" style="background:#4361ee;"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#4361ee;">3</div>
                    </div>

                    <h2 class="font-bold mb-1" style="font-size:26px;font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">Новый пароль</h2>
                    <p class="mb-6 text-sm" style="font-family:'Nunito',sans-serif;color:#718096;">Придумайте новый пароль для вашего аккаунта.</p>

                    <form @submit.prevent="submitNewPwd" novalidate>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold mb-1.5" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">Новый пароль</label>
                            <div class="relative">
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2" style="color:#718096;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                </div>
                                <input v-model="newPwd" :type="showNewPwd ? 'text' : 'password'" autocomplete="new-password" placeholder="Новый пароль"
                                    class="w-full rounded-[10px] border-2 text-sm font-semibold transition-all duration-200 outline-none"
                                    style="padding:12px 42px 12px 42px;font-family:'Nunito',sans-serif;"
                                    :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: newPwdErrs.password ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                                />
                                <button type="button" @click="showNewPwd = !showNewPwd" class="absolute right-3.5 top-1/2 -translate-y-1/2" style="color:#718096;" aria-label="Показать/скрыть пароль">
                                    <svg v-if="!showNewPwd" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p v-if="newPwdErrs.password" class="text-xs mt-1" style="color:#e53e3e;font-family:'Nunito',sans-serif;">{{ newPwdErrs.password }}</p>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-semibold mb-1.5" style="font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#cbd5e0' : '#2d3748' }">Подтвердите пароль</label>
                            <div class="relative">
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2" style="color:#718096;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                </div>
                                <input v-model="newPwdConfirm" :type="showConfirmPwd ? 'text' : 'password'" autocomplete="new-password" placeholder="Подтвердите пароль"
                                    class="w-full rounded-[10px] border-2 text-sm font-semibold transition-all duration-200 outline-none"
                                    style="padding:12px 42px 12px 42px;font-family:'Nunito',sans-serif;"
                                    :style="{ background: isDark ? '#1a2332' : '#f8fafc', borderColor: newPwdErrs.confirm ? '#e53e3e' : (isDark ? '#2d3748' : '#e2e8f0'), color: isDark ? '#cbd5e0' : '#2d3748' }"
                                />
                                <button type="button" @click="showConfirmPwd = !showConfirmPwd" class="absolute right-3.5 top-1/2 -translate-y-1/2" style="color:#718096;" aria-label="Показать/скрыть пароль">
                                    <svg v-if="!showConfirmPwd" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p v-if="newPwdErrs.confirm" class="text-xs mt-1" style="color:#e53e3e;font-family:'Nunito',sans-serif;">{{ newPwdErrs.confirm }}</p>
                        </div>

                        <button type="submit" :disabled="newPwdProcessing"
                            class="w-full flex items-center justify-center gap-2 text-white font-bold rounded-[10px] transition-all duration-200"
                            style="padding:14px;font-size:15px;font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#4361ee,#3a0ca3);box-shadow:0 4px 16px rgba(67,97,238,.35);border:none;"
                            :style="{ opacity: newPwdProcessing ? .7 : 1 }"
                        >
                            <svg v-if="newPwdProcessing" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            <span>{{ newPwdProcessing ? 'Сохраняем…' : 'Сохранить пароль' }}</span>
                        </button>
                    </form>
                </div>

                <!-- ═══ RESET — STEP 4: DONE ════════════════════════════════ -->
                <div v-else-if="view === 'reset-done'" class="text-center">
                    <div class="mx-auto mb-6 flex items-center justify-center" style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#06d6a0,#00b4d8);box-shadow:0 8px 32px rgba(6,214,160,.4);">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <h2 class="font-bold mb-2" style="font-size:26px;font-family:'Nunito',sans-serif;" :style="{ color: isDark ? '#e2e8f0' : '#2d3748' }">Пароль обновлён!</h2>
                    <p class="mb-8 text-sm" style="font-family:'Nunito',sans-serif;color:#718096;">
                        Ваш пароль успешно изменён. Теперь вы можете войти с новым паролем.
                    </p>
                    <button type="button" @click="view = 'login'"
                        class="w-full flex items-center justify-center gap-2 text-white font-bold rounded-[10px] transition-all duration-200"
                        style="padding:14px;font-size:15px;font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#4361ee,#3a0ca3);box-shadow:0 4px 16px rgba(67,97,238,.35);border:none;"
                    >
                        Вернуться к входу
                    </button>
                </div>

            </div>
        </div>

        <!-- ── TOAST ──────────────────────────────────────────────────────── -->
        <transition name="toast">
            <div v-if="toastVisible"
                class="fixed top-6 right-6 z-[999] max-w-xs px-5 py-3.5 rounded-xl text-white text-sm font-semibold"
                style="font-family:'Nunito',sans-serif;background:#1e2a3b;box-shadow:0 8px 32px rgba(0,0,0,.2);"
                :style="{ borderLeft: toastType === 'success' ? '4px solid #06d6a0' : '4px solid #e53e3e' }"
            >
                {{ toastMessage }}
            </div>
        </transition>
    </div>
</template>

<style>
.toast-enter-active,
.toast-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    transform: translateX(calc(100% + 24px));
    opacity: 0;
}
</style>
