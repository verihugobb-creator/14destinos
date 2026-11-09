<script setup>
// Pinta la lista de avisos (toasts)
import { toasts } from '../composables/useToast'

const ICONS = { success: '✅', error: '❌', info: 'ℹ️' }
</script>

<template>
  <div id="toastWrap">
    <div v-for="t in toasts" :key="t.id" class="wl-toast" :class="t.type">
      <span>{{ ICONS[t.type] ?? '💬' }}</span><span>{{ t.msg }}</span>
    </div>
  </div>
</template>
