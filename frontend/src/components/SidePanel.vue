<script setup>
// Panel lateral: info del país, destinos, acciones y recuerdos
import { ref, computed, watch } from 'vue'
import { store, closePanel, selectCity, clearCity, findMemory } from '../composables/useStore'
import { openModal } from '../composables/useModals'
import { codeToFlag } from '../services/helpers'

const filter = ref('')   // texto del input "Filtrar destinos…"

// Lista de destinos filtrada (se recalcula sola al escribir)
const filteredCities = computed(() => {
  const q = filter.value.toLowerCase()
  return q ? store.cities.filter(c => c.toLowerCase().includes(q)) : store.cities
})

// Al cambiar de país o quitar la ciudad, limpiamos el filtro
watch(() => store.country, () => { filter.value = '' })
watch(() => store.city, (city) => { if (!city) filter.value = '' })

function leaveMemory() {
  if (!store.user) { openModal('auth'); return }
  openModal('memory', { mode: 'new' })
}

function makePlan() {
  if (!store.user) { openModal('auth'); return }
  openModal('plan')
}

function editMemory(id) {
  const m = findMemory(id)
  if (m) openModal('memory', { mode: 'edit', memory: m })
}
</script>

<template>
  <aside id="sidePanel" :class="{ open: store.panelOpen }">
    <div class="sp-header">
      <div class="sp-country-info">
        <span class="sp-flag">{{ codeToFlag(store.country?.code) }}</span>
        <div>
          <h2>{{ store.country?.nameES || store.country?.name }}</h2>
          <p>{{ store.country?.code || '—' }}</p>
        </div>
      </div>
      <button id="spClose" @click="closePanel"><i class="bi bi-x-lg"></i></button>
    </div>

    <div class="sp-body">
      <!-- Estadísticas del país -->
      <div class="sp-stats">
        <div v-for="[k, v] in store.stats" :key="k" class="sp-stat"><span>{{ k }}</span>{{ v }}</div>
      </div>

      <!-- Sin sesión -->
      <div v-if="!store.user" class="sp-notice">
        <i class="bi bi-lock-fill"></i>
        <p>Inicia sesión para explorar destinos y guardar recuerdos en este país.</p>
        <button class="btn-primary-sm" @click="openModal('auth')">Iniciar sesión</button>
      </div>

      <template v-else>
        <!-- Destinos -->
        <div>
          <p class="sp-section-label">Destinos populares</p>
          <div class="sp-city-search">
            <i class="bi bi-search"></i>
            <input type="text" v-model="filter" placeholder="Filtrar destinos…" autocomplete="off" />
          </div>
          <ul class="sp-city-list">
            <li v-if="store.citiesLoading" style="color:var(--mid);font-size:.8rem;border:none;background:none;padding:.4rem 0">Cargando…</li>
            <li v-else-if="!filteredCities.length" style="color:var(--mid);font-size:.8rem;border:none;background:none;padding:.4rem 0">Sin destinos disponibles.</li>
            <template v-else>
              <li v-for="c in filteredCities" :key="c" @click="selectCity(c)">
                <i class="bi bi-geo-alt"></i>{{ c }}
              </li>
            </template>
          </ul>
        </div>

        <!-- Acciones (cuando hay ciudad elegida) -->
        <div v-if="store.city" class="sp-actions">
          <div class="sp-selected-city">
            <i class="bi bi-geo-alt-fill"></i>
            <span>{{ store.city }}</span>
            <button id="spClearCity" @click="clearCity"><i class="bi bi-x"></i></button>
          </div>
          <button class="btn-primary-sm w-100" @click="leaveMemory">
            <i class="bi bi-camera-fill"></i> Dejar un recuerdo
          </button>
          <button class="btn-secondary-sm w-100" @click="makePlan">
            <i class="bi bi-map-fill"></i> Montar un plan
          </button>

          <div>
            <p class="sp-section-label mt-3">Recuerdos aquí</p>
            <div id="spMemoriesList">
              <p v-if="!store.countryMemories.length" style="font-size:.8rem;color:var(--mid)">Sin recuerdos aún.</p>
              <div v-for="m in store.countryMemories" :key="m.id" class="mem-mini">
                <img v-if="m.photo_url" :src="m.photo_url" class="mem-mini-thumb" alt="" @click="openModal('memView', m.id)" />
                <div v-else class="mem-mini-thumb" @click="openModal('memView', m.id)">📸</div>
                <div style="flex:1;min-width:0;cursor:pointer" @click="openModal('memView', m.id)">
                  <div class="mem-mini-title">{{ m.title }}</div>
                  <div class="mem-mini-date">{{ m.visit_date }} · {{ m.city }}</div>
                </div>
                <div class="mem-mini-btns">
                  <button class="mem-mini-action edit-mini" title="Editar" @click.stop="editMemory(m.id)"><i class="bi bi-pencil-fill"></i></button>
                  <button class="mem-mini-action delete-mini" title="Eliminar" @click.stop="openModal('delete', m.id)"><i class="bi bi-trash3-fill"></i></button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </aside>

  <div id="panelOverlay" :class="{ show: store.panelOpen }" @click="closePanel"></div>
</template>
