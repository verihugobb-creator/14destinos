<script setup>
// Modal para crear o editar un recuerdo (con foto)
import { ref, reactive, computed, onMounted } from 'vue'
import BaseModal from './BaseModal.vue'
import { API } from '../services/api'
import { store, refreshCountryMemories } from '../composables/useStore'
import { registerModal } from '../composables/useModals'
import { toast } from '../composables/useToast'

const modal     = ref(null)
const fileInput = ref(null)

// Datos del formulario
const form = reactive({ title: '', date: '', story: '', lat: '', lng: '' })

const mode       = ref('new')   // 'new' | 'edit' | 'location'
const editId     = ref(null)    // id del recuerdo si estamos editando
const photoFile  = ref(null)    // foto nueva elegida (File)
const previewUrl = ref('')      // URL para la vista previa
const keepUrl    = ref(null)    // foto que ya tenía el recuerdo (al editar)
const badge      = ref('')      // texto "Barcelona, Cataluña, España"
const dragging   = ref(false)
const error      = ref('')
const saving     = ref(false)

const titleIcon = computed(() =>
  mode.value === 'edit' ? 'bi-pencil-fill' : mode.value === 'location' ? 'bi-geo-alt-fill' : 'bi-camera-heart-fill')
const titleText = computed(() =>
  mode.value === 'edit' ? 'Editar recuerdo' : mode.value === 'location' ? 'Recuerdo en mi ubicación' : 'Dejar un recuerdo')

// ── Foto ──────────────────────────────────────────────────────────────────
function previewPhoto(f) {
  if (previewUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
  photoFile.value  = f
  previewUrl.value = URL.createObjectURL(f)
}

function clearPhoto() {
  if (previewUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
  photoFile.value  = null
  previewUrl.value = ''
  keepUrl.value    = null
  if (fileInput.value) fileInput.value.value = ''
}

function onFileChange(e) {
  const f = e.target.files[0]
  if (f) previewPhoto(f)
}

function onDrop(e) {
  dragging.value = false
  const f = e.dataTransfer.files[0]
  if (f) previewPhoto(f)
}

// ── Abrir el modal ────────────────────────────────────────────────────────
function resetForm() {
  clearPhoto()
  Object.assign(form, { title: '', date: '', story: '', lat: '', lng: '' })
  editId.value = null
  badge.value  = ''
  error.value  = ''
}

// opts.mode = 'new' | 'edit' | 'location'.  Sin opts → reabre tal cual estaba.
function open(opts) {
  if (opts?.mode === 'new') {
    resetForm()
    mode.value = 'new'
  }

  if (opts?.mode === 'edit') {
    const m = opts.memory
    resetForm()
    mode.value   = 'edit'
    editId.value = m.id
    Object.assign(form, {
      title: m.title,
      date:  m.visit_date,
      story: m.story || '',
      lat:   m.latitude  || '',
      lng:   m.longitude || '',
    })
    if (m.photo_url) {
      previewUrl.value = m.photo_url
      keepUrl.value    = m.photo_url
    }
    // Contexto país/ciudad del propio recuerdo
    store.country = store.country || { code: m.country_code, name: m.country_name }
    store.city    = m.city
  }

  if (opts?.mode === 'location') {
    resetForm()
    mode.value = 'location'
    Object.assign(form, {
      title: opts.title,
      date:  new Date().toISOString().split('T')[0],
      lat:   opts.lat.toFixed(6),
      lng:   opts.lng.toFixed(6),
    })
    badge.value = opts.badge
  }

  modal.value.show()
}

// Botón "Usar mi ubicación"
function useGeo() {
  navigator.geolocation?.getCurrentPosition(
    p => {
      form.lat = p.coords.latitude.toFixed(6)
      form.lng = p.coords.longitude.toFixed(6)
      toast('Ubicación obtenida ✓', 'success')
    },
    () => toast('No se pudo obtener la ubicación', 'error')
  )
}

// ── Guardar ───────────────────────────────────────────────────────────────
async function save() {
  error.value = ''
  saving.value = true

  const fd = new FormData()
  fd.append('title',        form.title.trim())
  fd.append('visit_date',   form.date)
  fd.append('story',        form.story.trim())
  fd.append('city',         store.city || '')
  fd.append('country_code', store.country?.code || '')
  fd.append('country_name', store.country?.name || '')
  if (form.lat !== '' && form.lat !== null) fd.append('latitude',  form.lat)
  if (form.lng !== '' && form.lng !== null) fd.append('longitude', form.lng)
  if (photoFile.value) fd.append('photo', photoFile.value)
  // Al editar sin foto nueva: le decimos al backend que conserve la que había
  if (!photoFile.value && keepUrl.value) fd.append('keep_photo', '1')

  try {
    if (editId.value) {
      fd.append('_method', 'PUT')
      const m = await API.updateMemory(editId.value, fd)
      store.memories = store.memories.map(x => String(x.id) === String(editId.value) ? m : x)
      toast('Recuerdo actualizado ✅', 'success')
    } else {
      const m = await API.saveMemory(fd)
      store.memories = [m, ...store.memories]
      toast('Recuerdo guardado 💖', 'success')
    }
    refreshCountryMemories()
    modal.value.hide()
    editId.value = null
    mode.value = 'new'
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

onMounted(() => registerModal('memory', { open, close: () => modal.value.hide() }))
</script>

<template>
  <BaseModal id="memoryModal" ref="modal" size="lg">
    <template #title><i class="bi me-2" :class="titleIcon"></i>{{ titleText }}</template>

    <div class="row g-4">
      <!-- Zona de la foto (click o arrastrar) -->
      <div class="col-12 col-md-5">
        <div
          class="photo-zone"
          :class="{ drag: dragging }"
          @click="fileInput.click()"
          @dragover.prevent="dragging = true"
          @dragleave="dragging = false"
          @drop.prevent="onDrop"
        >
          <input type="file" ref="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none" @change="onFileChange" />
          <div v-if="previewUrl" id="photoPrev">
            <img :src="previewUrl" alt="" />
            <button id="photoRemove" @click.stop="clearPhoto"><i class="bi bi-trash3-fill"></i></button>
          </div>
          <div v-else id="photoHint">
            <i class="bi bi-cloud-arrow-up-fill"></i>
            <p>Sube una foto</p>
            <span>JPG, PNG, WebP — máx 10MB</span>
          </div>
        </div>
      </div>

      <!-- Datos -->
      <div class="col-12 col-md-7">
        <div v-if="badge" class="mb-3">
          <div class="mem-location-badge">
            <i class="bi bi-geo-alt-fill me-2"></i>
            <span>{{ badge }}</span>
          </div>
        </div>
        <input type="text" class="wl-input mb-3" v-model="form.title" placeholder="Título del recuerdo" />
        <input type="date" class="wl-input mb-3" v-model="form.date" />
        <textarea class="wl-input mb-3" v-model="form.story" rows="4" placeholder="¿Qué recuerdas de este lugar?"></textarea>
        <div class="row g-2 mb-2">
          <div class="col-6"><input type="number" class="wl-input" v-model="form.lat" placeholder="Latitud" step="any" /></div>
          <div class="col-6"><input type="number" class="wl-input" v-model="form.lng" placeholder="Longitud" step="any" /></div>
        </div>
        <button class="btn-ghost-sm w-100" @click="useGeo"><i class="bi bi-crosshair2 me-1"></i>Usar mi ubicación</button>
      </div>
    </div>

    <div v-if="error" class="wl-error mt-3">{{ error }}</div>

    <template #footer>
      <button class="btn-ghost-sm" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn-primary-sm" :disabled="saving" @click="save">
        <template v-if="editId"><i class="bi bi-check-lg me-1"></i>Guardar cambios</template>
        <template v-else><i class="bi bi-heart-fill me-1"></i>Guardar</template>
      </button>
    </template>
  </BaseModal>
</template>
