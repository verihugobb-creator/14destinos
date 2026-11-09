<script setup>
// Modal reutilizable: envuelve el Modal de Bootstrap para usarlo desde Vue.
// Uso:  <BaseModal ref="modal" size="lg"> <template #title>…</template> … </BaseModal>
//       modal.value.show() / modal.value.hide()
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Modal } from 'bootstrap'

const props = defineProps({
  id:     { type: String, required: true },
  size:   { type: String, default: '' },   // '', 'sm', 'lg', 'xl'
  bodyId: { type: String, default: undefined },
})
const emit = defineEmits(['hidden'])

const el = ref(null)
let instance = null

function onHidden() { emit('hidden') }

onMounted(() => {
  instance = new Modal(el.value)
  el.value.addEventListener('hidden.bs.modal', onHidden)
})

onBeforeUnmount(() => {
  el.value?.removeEventListener('hidden.bs.modal', onHidden)
  instance?.dispose()
})

// Lo que el componente padre puede llamar desde fuera
defineExpose({
  show: () => instance?.show(),
  hide: () => instance?.hide(),
})
</script>

<template>
  <div class="modal fade" :id="props.id" tabindex="-1" aria-modal="true" ref="el">
    <div class="modal-dialog modal-dialog-centered" :class="props.size ? 'modal-' + props.size : ''">
      <div class="modal-content wl-modal">
        <div class="modal-header">
          <h5 class="modal-title"><slot name="title" /></h5>
          <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" :id="props.bodyId">
          <slot />
        </div>
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </div>
</template>
