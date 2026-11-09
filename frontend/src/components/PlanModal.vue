<script setup>
// Modal para generar un plan de viaje con IA
import { ref, computed, onMounted } from 'vue'
import { marked } from 'marked'
import BaseModal from './BaseModal.vue'
import { API } from '../services/api'
import { store } from '../composables/useStore'
import { registerModal, openModal } from '../composables/useModals'
import { toast } from '../composables/useToast'

const modal = ref(null)

const INTERESTS = [
  { val: 'gastronomía', label: '🍽️ Gastronomía' },
  { val: 'cultura',     label: '🏛️ Cultura' },
  { val: 'naturaleza',  label: '🌿 Naturaleza' },
  { val: 'aventura',    label: '🧗 Aventura' },
  { val: 'relax',       label: '🧘 Relax' },
  { val: 'historia',    label: '📜 Historia' },
  { val: 'compras',     label: '🛍️ Compras' },
  { val: 'arte',        label: '🎨 Arte' },
]

const ICONS  = { tripadvisor: '🌟', trivago: '🏨', booking: '🛎️', airbnb: '🏠', skyscanner: '✈️', google_maps: '🗺️' }
const LABELS = { tripadvisor: 'TripAdvisor', trivago: 'Trivago', booking: 'Booking.com', airbnb: 'Airbnb', skyscanner: 'Skyscanner', google_maps: 'Google Maps' }

const days     = ref(3)
const selected = ref([])          // intereses marcados
const phase    = ref('config')    // 'config' | 'loading' | 'result'
const planHtml = ref('')
const links    = ref({})
const planTitle = ref('')         // "Roma — 3 días"

const bookingLinks = computed(() =>
  Object.entries(links.value).map(([k, url]) => ({ key: k, url, text: `${ICONS[k] ?? '🔗'} ${LABELS[k] ?? k}` })))

function toggleInterest(val) {
  const i = selected.value.indexOf(val)
  if (i === -1) selected.value.push(val)
  else selected.value.splice(i, 1)
}

function reset() {
  phase.value = 'config'
  selected.value = []
  days.value = 3
  planTitle.value = ''
}

function open() {
  reset()
  modal.value.show()
}

async function generate() {
  if (!store.city) { toast('Selecciona primero una ciudad', 'error'); return }
  if (!store.user) { modal.value.hide(); openModal('auth'); return }

  const tripDays = parseInt(days.value) || 3
  phase.value = 'loading'

  try {
    const res = await API.generatePlan({
      city: store.city,
      country_code: store.country?.code,
      trip_days: tripDays,
      interests: [...selected.value],
    })
    planHtml.value  = marked.parse(res.plan || '')
    links.value     = res.links || {}
    planTitle.value = `${store.city} — ${tripDays} días`
    phase.value = 'result'
  } catch (e) {
    phase.value = 'config'
    toast(e.message, 'error')
  }
}

onMounted(() => registerModal('plan', { open, close: () => modal.value.hide() }))
</script>

<template>
  <BaseModal id="planModal" ref="modal" size="xl">
    <template #title><i class="bi bi-compass-fill me-2"></i>{{ planTitle || 'Montar un plan' }}</template>

    <!-- 1) Configuración -->
    <div v-if="phase === 'config'">
      <div class="row g-4 align-items-end">
        <div class="col-12 col-md-3">
          <label class="wl-label">Días de viaje</label>
          <input type="number" class="wl-input" v-model="days" min="1" max="14" />
        </div>
        <div class="col-12 col-md-9">
          <label class="wl-label">Intereses</label>
          <div class="chips-wrap">
            <span
              v-for="it in INTERESTS"
              :key="it.val"
              class="chip"
              :class="{ active: selected.includes(it.val) }"
              @click="toggleInterest(it.val)"
            >{{ it.label }}</span>
          </div>
        </div>
      </div>
      <button class="btn-primary-sm mt-4" @click="generate">
        <i class="bi bi-stars me-2"></i>Generar plan con IA
      </button>
    </div>

    <!-- 2) Cargando -->
    <div v-else-if="phase === 'loading'" class="plan-loading">
      <div class="loader-ring"></div>
      <p>Creando tu plan perfecto…</p>
    </div>

    <!-- 3) Resultado -->
    <div v-else>
      <div class="row g-4">
        <div class="col-12 col-lg-8">
          <!-- v-html porque el plan viene en Markdown y lo convertimos a HTML -->
          <div class="plan-body" v-html="planHtml"></div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="booking-card">
            <p class="sp-section-label mb-3">Reservar</p>
            <div>
              <a v-for="l in bookingLinks" :key="l.key" :href="l.url" target="_blank" rel="noopener noreferrer" class="booking-link">{{ l.text }}</a>
            </div>
            <hr class="wl-hr mt-3 mb-3" />
            <button class="btn-ghost-sm w-100" @click="reset">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Nuevo plan
            </button>
          </div>
        </div>
      </div>
    </div>
  </BaseModal>
</template>
