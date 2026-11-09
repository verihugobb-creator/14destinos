<script setup>
// Buscador global de países y ciudades (barra de arriba)
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { store, nameES, selectCountry, selectCity, loadCountryList } from '../composables/useStore'
import { flyTo } from '../composables/useMap'
import { CITIES } from '../data/cities'

const query   = ref('')
const results = ref([])
const open    = ref(false)
const wrapEl  = ref(null)
let timer = null

// Cada vez que se escribe: esperamos 300 ms (debounce) y buscamos
function onInput() {
  const q = query.value.trim().toLowerCase()
  clearTimeout(timer)
  if (q.length < 2) { open.value = false; return }
  timer = setTimeout(() => search(q), 300)
}

async function search(q) {
  const list = []

  // 1) Países (local, instantáneo)
  store.countryList
    .filter(c => c.name.toLowerCase().includes(q) || nameES(c.name).toLowerCase().includes(q))
    .slice(0, 3)
    .forEach(c => list.push({
      type: 'country', label: nameES(c.name), sub: 'País',
      code: c.code, name: nameES(c.name),
      lat: c.latlng[0], lng: c.latlng[1], area: c.area, flag: c.flag,
    }))

  // 2) Nominatim: cualquier lugar del mundo
  try {
    const url = 'https://nominatim.openstreetmap.org/search?' +
      'q=' + encodeURIComponent(q) + '&format=json&limit=8&accept-language=es&addressdetails=1'
    const r = await fetch(url, { headers: { 'Accept-Language': 'es' } })
    if (r.ok) {
      const data = await r.json()
      data.forEach(p => {
        if (!p.name) return
        const addr   = p.address || {}
        const cc     = (addr.country_code || '').toUpperCase()
        const cObj   = store.countryList.find(c => c.code === cc)
        const cName  = cObj ? nameES(cObj.name) : (addr.country || cc)
        const region = addr.state || addr.county || addr.municipality || ''
        list.push({
          type: 'city', label: p.name,
          sub: [region, cName].filter(Boolean).join(', '),
          code: cc, name: cName, city: p.name,
          lat: parseFloat(p.lat), lng: parseFloat(p.lon),
          flag: cObj?.flag || '🌍',
        })
      })
    }
  } catch {}

  // 3) Respaldo: base de datos local de ciudades
  if (list.filter(r => r.type === 'city').length < 2) {
    outer: for (const [code, cities] of Object.entries(CITIES)) {
      for (const city of cities) {
        if (!city.toLowerCase().includes(q)) continue
        if (list.some(r => r.city?.toLowerCase() === city.toLowerCase())) continue
        const c = store.countryList.find(x => x.code === code)
        const cName = c ? nameES(c.name) : code
        list.push({
          type: 'city', label: city, sub: cName,
          code, name: cName, city,
          lat: c?.latlng[0] || 0, lng: c?.latlng[1] || 0, flag: c?.flag || '🌍',
        })
        if (list.filter(r => r.type === 'city').length >= 5) break outer
      }
    }
  }

  // Si el usuario ya ha borrado el texto, no abrimos nada
  if (query.value.trim().toLowerCase() !== q) return
  results.value = list.slice(0, 8)
  open.value = results.value.length > 0
}

// Click en un resultado
function choose(r) {
  open.value = false
  query.value = r.label || r.name
  selectCountry({ code: r.code, name: r.name })

  if (r.type === 'city') {
    flyTo(r.lat, r.lng, 10)
    selectCity(r.city)
  } else {
    const area = r.area || 0
    const zoom = area > 2000000 ? 3 : area > 500000 ? 4 : area > 50000 ? 5 : 6
    flyTo(r.lat, r.lng, zoom)
  }
}

// Click fuera del buscador → cerrar desplegable
function onDocClick(e) {
  if (wrapEl.value && !wrapEl.value.contains(e.target)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div class="nav-search" ref="wrapEl">
    <i class="bi bi-search"></i>
    <input
      type="search"
      id="globalSearch"
      v-model="query"
      placeholder="Busca un país o ciudad…"
      autocomplete="off"
      name="country-search"
      @focus="loadCountryList"
      @input="onInput"
    />
    <ul id="searchDropdown" class="search-dropdown" :class="{ open }">
      <li v-for="(r, i) in results" :key="i" @click="choose(r)">
        <span>{{ r.flag || '🌍' }}</span>
        <div style="flex:1;min-width:0;overflow:hidden">
          <div style="font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.label }}</div>
          <div style="font-size:.72rem;color:var(--mid)">{{ r.sub }}</div>
        </div>
      </li>
    </ul>
  </div>
</template>
