<script setup>
// Modal para VER un recuerdo (foto, historia, fecha, lugar)
import { ref, onMounted } from 'vue'
import BaseModal from './BaseModal.vue'
import { findMemory } from '../composables/useStore'
import { registerModal, openModal } from '../composables/useModals'

const modal  = ref(null)
const memory = ref(null)   // recuerdo que se está viendo

function open(id) {
  const m = findMemory(id)
  if (!m) return
  memory.value = m
  modal.value.show()
}

function edit() {
  modal.value.hide()
  openModal('memory', { mode: 'edit', memory: memory.value })
}

function remove() {
  modal.value.hide()
  openModal('delete', memory.value.id)
}

onMounted(() => registerModal('memView', { open, close: () => modal.value.hide() }))
</script>

<template>
  <BaseModal id="memViewModal" ref="modal" size="lg" body-id="memViewBody">
    <template #title>{{ memory?.title }}</template>

    <template v-if="memory">
      <img v-if="memory.photo_url" :src="memory.photo_url" class="mv-img" :alt="memory.title" />
      <p v-if="memory.story" class="mv-story">{{ memory.story }}</p>
      <div class="mv-meta">
        <span><i class="bi bi-calendar3 me-1"></i>{{ memory.visit_date }}</span>
        <span><i class="bi bi-geo-alt me-1"></i>{{ memory.city }}, {{ memory.country_name }}</span>
        <span v-if="memory.latitude"><i class="bi bi-crosshair me-1"></i>{{ memory.latitude }}, {{ memory.longitude }}</span>
      </div>
    </template>

    <template #footer>
      <button class="btn-ghost-sm" data-bs-dismiss="modal">Cerrar</button>
      <button class="btn-ghost-sm" @click="edit"><i class="bi bi-pencil-fill me-1"></i>Editar</button>
      <button class="btn-secondary-sm" @click="remove"><i class="bi bi-trash3-fill me-1"></i>Eliminar</button>
    </template>
  </BaseModal>
</template>
