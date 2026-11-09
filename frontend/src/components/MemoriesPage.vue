<script setup>
// Página "Nuestros recuerdos": cuadrícula con todos los recuerdos
import { store } from '../composables/useStore'
import { openModal } from '../composables/useModals'
</script>

<template>
  <section v-if="store.memoriesPageOpen" id="memoriesPage">
    <div class="memories-page-inner">
      <div class="memories-page-header">
        <h2>Nuestros recuerdos</h2>
        <button class="btn-icon" @click="store.memoriesPageOpen = false"><i class="bi bi-x-lg"></i></button>
      </div>

      <div v-if="store.memories.length" class="memories-grid">
        <div v-for="m in store.memories" :key="m.id" class="mem-card">
          <div class="mem-card-img" @click="openModal('memView', m.id)">
            <img v-if="m.photo_url" :src="m.photo_url" :alt="m.title" loading="lazy" />
            <template v-else>📍</template>
            <div class="mem-card-actions">
              <button class="mem-action-btn edit-btn" title="Editar" @click.stop="openModal('memory', { mode: 'edit', memory: m })">
                <i class="bi bi-pencil-fill"></i>
              </button>
              <button class="mem-action-btn delete-btn" title="Eliminar" @click.stop="openModal('delete', m.id)">
                <i class="bi bi-trash3-fill"></i>
              </button>
            </div>
          </div>
          <div class="mem-card-body" @click="openModal('memView', m.id)">
            <div class="mem-card-title">{{ m.title }}</div>
            <div class="mem-card-loc"><i class="bi bi-geo-alt-fill me-1"></i>{{ m.city }}, {{ m.country_name }}</div>
            <div class="mem-card-date">{{ m.visit_date }}</div>
          </div>
        </div>
      </div>

      <div v-else class="memories-empty">
        <i class="bi bi-globe2"></i>
        <p>Aún no hay recuerdos. ¡Explora el mapa y empieza!</p>
      </div>
    </div>
  </section>
</template>
