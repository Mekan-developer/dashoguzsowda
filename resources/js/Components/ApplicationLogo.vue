<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
    <img src="/icons/logo-128.png" :alt="t('layout.brandTitle')" class="object-contain" />
</template>
