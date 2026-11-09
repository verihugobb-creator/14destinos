<script setup>
// Banner "Instalar 14 Destinos" (PWA). Aparece 3 s después de que el
// navegador diga que la app se puede instalar.
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { toast } from '../composables/useToast'

const visible = ref(false)
let deferredPrompt = null
let timer = null

function onBeforeInstall(e) {
  e.preventDefault()
  deferredPrompt = e
  timer = setTimeout(() => { if (deferredPrompt) visible.value = true }, 3000)
}

function onInstalled() {
  visible.value = false
  deferredPrompt = null
  toast('¡App instalada! 🎉', 'success')
}

async function install() {
  if (!deferredPrompt) return
  deferredPrompt.prompt()
  await deferredPrompt.userChoice
  deferredPrompt = null
  visible.value = false
}

function dismiss() {
  visible.value = false
  deferredPrompt = null
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', onBeforeInstall)
  window.addEventListener('appinstalled', onInstalled)
})
onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeinstallprompt', onBeforeInstall)
  window.removeEventListener('appinstalled', onInstalled)
})
</script>

<template>
  <div v-if="visible" id="installBanner" class="install-banner">
    <div style="display:flex;align-items:center;gap:.75rem;flex:1">
      <span style="font-size:1.5rem">📱</span>
      <div>
        <div style="font-weight:600;font-size:.9rem">Instalar 14 Destinos</div>
        <div style="font-size:.78rem;color:var(--mid)">Añade la app a tu pantalla de inicio</div>
      </div>
    </div>
    <button class="install-btn" @click="install">Instalar</button>
    <button class="install-dismiss" @click="dismiss">✕</button>
  </div>
</template>

<style scoped>
.install-banner {
  position: fixed; bottom: 1.5rem; left: 1rem; right: 1rem;
  background: var(--s1); border: 1px solid var(--ln2); border-radius: 1rem;
  padding: 1rem 1.1rem; display: flex; align-items: center; gap: .75rem;
  z-index: 3000; box-shadow: 0 8px 32px rgba(0,0,0,.5);
  animation: slideUp .3s ease both; max-width: 480px; margin: 0 auto;
}
.install-btn {
  background: var(--blue); color: #fff; border: none; border-radius: .6rem;
  padding: .45rem .9rem; font-weight: 600; font-size: .82rem; cursor: pointer; white-space: nowrap;
}
.install-dismiss {
  background: transparent; border: none; color: var(--low); cursor: pointer; padding: .25rem; font-size: 1rem;
}
</style>
