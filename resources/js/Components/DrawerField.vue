<script setup>
defineProps({
    label: String,
    error: String,
    required: Boolean,
})
</script>

<template>
  <div class="mb-4">
    <label v-if="label" class="field-label">
      {{ label }}<span v-if="required" class="ml-0.5 text-red">*</span>
    </label>
    <slot />
    <p v-if="error" class="mt-1 text-[12px] font-medium text-red">{{ error }}</p>
  </div>
</template>
