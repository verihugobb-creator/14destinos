<script setup>
// Barra de navegación superior
import { store, applyLogout, closePanel, firstName, nameES } from '../composables/useStore'
import { theme, toggleTheme } from '../composables/useTheme'
import { flyTo, setLocationMarker } from '../composables/useMap'
import { openModal } from '../composables/useModals'
import { toast } from '../composables/useToast'
import SearchBox from './SearchBox.vue'

// Botón Entrar / Salir
function onAuthClick() {
  if (store.user) {
    applyLogout()
    closePanel()
    toast('Sesión cerrada', 'info')
  } else {
    openModal('auth')
  }
}

function toggleMemoriesPage() {
  store.memoriesPageOpen = !store.memoriesPageOpen
}

// Botón "Estoy aquí": coge la ubicación y abre el modal de recuerdo
function useCurrentLocation() {
  if (!store.user) { openModal('auth'); return }
  if (!navigator.geolocation) { toast('Tu navegador no soporta geolocalización', 'error'); return }
  toast('Obteniendo ubicación…', 'info')

  navigator.geolocation.getCurrentPosition(async (pos) => {
    const lat = pos.coords.latitude
    const lng = pos.coords.longitude

    flyTo(lat, lng, 14)
    // Al pulsar la chincheta, se vuelve a abrir el modal tal cual estaba
    setLocationMarker(lat, lng, () => openModal('memory'))

    // Geocodificación inversa: coordenadas → nombre del sitio
    let placeName = '', countryName = '', region = ''
    try {
      const url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng +
        '&format=json&accept-language=es&addressdetails=1&zoom=14'
      const r = await fetch(url, { headers: { 'Accept-Language': 'es' } })
      if (r.ok) {
        const data = await r.json()
        const addr = data.address || {}
        placeName   = addr.town || addr.village || addr.city || addr.hamlet || addr.suburb || addr.neighbourhood || data.name || ''
        countryName = addr.country || ''
        region      = addr.state || addr.county || ''
        const code  = (addr.country_code || '').toUpperCase()
        if (code) {
          const c = store.countryList.find(x => x.code === code)
          store.country = { code, name: c ? nameES(c.name) : countryName }
        }
      }
    } catch {}

    if (placeName) store.city = placeName

    openModal('memory', {
      mode: 'location',
      lat, lng,
      title: placeName || 'Mi ubicación actual',
      badge: [placeName, region, countryName].filter(Boolean).join(', '),
    })
    toast('📍 ' + (placeName || 'Ubicación obtenida'), 'success')
  }, (err) => {
    const msgs = { 1: 'Permiso denegado', 2: 'Ubicación no disponible', 3: 'Tiempo agotado' }
    toast(msgs[err.code] || 'Error de geolocalización', 'error')
  }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 })
}
</script>

<template>
  <nav id="mainNav">
    <div class="nav-inner">
      <a class="nav-brand" href="#">
        <div class="nav-logo-mark">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="none" class="logo-globe">
            <circle cx="10" cy="10" r="8.2" stroke="#4d9fff" stroke-width="1.4"/>
            <line x1="1.8" y1="10" x2="18.2" y2="10" stroke="#4d9fff" stroke-width="1.4"/>
            <g class="logo-globe-spin">
              <ellipse cx="10" cy="10" rx="3.2" ry="8.2" stroke="#4d9fff" stroke-width="1.4"/>
              <path d="M2.5 6.8 Q10 8.8 17.5 6.8" stroke="#4d9fff" stroke-width="1.1" fill="none"/>
              <path d="M2.5 13.2 Q10 11.2 17.5 13.2" stroke="#4d9fff" stroke-width="1.1" fill="none"/>
            </g>
          </svg>
        </div>
        <span class="nav-brand-text">14<em>destinos</em></span>
      </a>

      <div class="nav-search-wrap">
        <SearchBox />
      </div>

      <div class="nav-right">
        <button v-if="store.user" class="nav-btn nav-btn-memories" id="btnMemories" @click="toggleMemoriesPage">
          <span class="icon-flip-wrap"><i class="bi bi-images"></i></span><span>Recuerdos</span>
        </button>
        <button class="nav-btn nav-btn-location" id="btnCurrentLocation" title="Añadir recuerdo en mi ubicación actual" @click="useCurrentLocation">
          <span class="icon-pin-wrap"><i class="bi bi-geo-alt-fill"></i></span><span>Estoy aquí</span>
        </button>
        <button class="nav-btn nav-btn-theme" id="btnThemeToggle" title="Cambiar tema" @click="toggleTheme">
          <i :class="theme === 'light' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
        </button>
        <button class="nav-btn nav-btn-accent" id="btnAuthToggle" @click="onAuthClick">
          <span class="icon-user-wrap"><i :class="store.user ? 'bi bi-person-fill' : 'bi bi-person-circle'"></i></span>
          <span>{{ store.user ? firstName() : 'Entrar' }}</span>
        </button>
      </div>
    </div>
  </nav>
</template>
