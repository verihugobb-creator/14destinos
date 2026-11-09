<script setup>
// Modal de confirmación para borrar un recuerdo
import { ref, onMounted } from 'vue'
import BaseModal from './BaseModal.vue'
import { API } from '../services/api'
import { store, findMemory, refreshCountryMemories } from '../composables/useStore'
import { registerModal, closeModal } from '../composables/useModals'
import { toast } from '../composables/useToast'

const modal    = ref(null)
const memory   = ref(null)
const deleting = ref(false)

function open(id) {
  const m = findMemory(id)
  if (!m) return
  memory.value = m
  closeModal('memView')
  modal.value.show()
}

async function confirmDelete() {
  const id = memory.value.id
  deleting.value = true
  try {
    await API.deleteMemory(id)
    store.memories = store.memories.filter(x => String(x.id) !== String(id))
    refreshCountryMemories()
    modal.value.hide()
    toast('Recuerdo eliminado', 'info')
  } catch (e) {
    toast(e.message, 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(() => registerModal('delete', { open, close: () => modal.value.hide() }))
</script>

<template>
  <BaseModal id="deleteModal" ref="modal" size="sm">
    <template #title><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Eliminar recuerdo</template>

    <p style="font-size:.9rem;color:var(--mid)">
      ¿Seguro que quieres eliminar <strong style="color:var(--hi)">{{ memory?.title }}</strong>?<br>
      <span style="font-size:.8rem">Esta acción no se puede deshacer.</span>
    </p>

    <template #footer>
      <button class="btn-ghost-sm" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn-secondary-sm" :disabled="deleting" @click="confirmDelete">
        <i class="bi bi-trash3-fill me-1"></i>Sí, eliminar
      </button>
    </template>
  </BaseModal>
</template>
