// ═════════════════════════════════════════════════════════════════════════
// Estado global de la app.
// Es un reactive(): si cambia algo aquí, todos los componentes que lo usan
// se actualizan solos. No hace falta Pinia para una app de este tamaño.
// ═════════════════════════════════════════════════════════════════════════
import { reactive, computed } from 'vue'
import { API } from '../services/api'
import { CITIES } from '../data/cities'
import { NAMES_ES } from '../data/names'

export const store = reactive({
  user: null,             // usuario logueado (o null)
  country: null,          // { code, name, nameES } país seleccionado
  city: null,             // ciudad / provincia seleccionada
  memories: [],           // TODOS los recuerdos del usuario
  countryMemories: [],    // recuerdos del país abierto en el panel
  cities: [],             // destinos del país abierto
  citiesLoading: false,
  stats: [],              // [['Población', '47.000.000'], ...]
  countryList: [],        // lista de países (para buscador y marcadores)
  panelOpen: false,
  memoriesPageOpen: false,
})

// Nombre en español a partir del nombre en inglés
export function nameES(name) {
  return NAMES_ES[name] || name
}

// Cuántos recuerdos hay por país → { ES: 3, FR: 1 }
export const countryCounts = computed(() => {
  const counts = {}
  store.memories.forEach(m => {
    if (m.country_code) counts[m.country_code] = (counts[m.country_code] || 0) + 1
  })
  return counts
})

// ── Panel lateral ─────────────────────────────────────────────────────────
export function openPanel()  { store.panelOpen = true }
export function closePanel() { store.panelOpen = false }

// ── País seleccionado ─────────────────────────────────────────────────────
export function selectCountry(data) {
  store.country = data
  store.city = null
  store.stats = []
  store.cities = []
  store.countryMemories = []
  openPanel()

  if (data.code) fetchStats(data.code)
  if (!store.user) return          // sin sesión: el panel muestra el aviso de login
  loadCities(data.code)
  loadCountryMemories(data.code)
}

async function fetchStats(code) {
  if (!code || code.length !== 2) return
  try {
    const r = await fetch(`https://restcountries.com/v3.1/alpha/${code}?fields=population,capital,languages,currencies`)
    if (!r.ok) return
    const raw = await r.json()
    const c = Array.isArray(raw) ? raw[0] : raw
    // Si mientras tanto el usuario ha cambiado de país, ignoramos esta respuesta
    if (store.country?.code !== code) return
    store.stats = [
      ['Población', c?.population?.toLocaleString('es') ?? '—'],
      ['Capital',   c?.capital?.[0] ?? '—'],
      ['Idioma',    Object.values(c?.languages ?? {}).slice(0, 2).join(', ') || '—'],
      ['Moneda',    Object.values(c?.currencies ?? {}).map(x => x.symbol).join(', ') || '—'],
    ]
  } catch {}
}

// ── Ciudades ──────────────────────────────────────────────────────────────
async function loadCities(code) {
  store.cities = []
  if (!code || code.length !== 2) return
  store.citiesLoading = true

  try {
    // 1) Nominatim (OpenStreetMap): ciudades, pueblos, aldeas...
    try {
      const url = 'https://nominatim.openstreetmap.org/search?' +
        'countrycodes=' + code.toLowerCase() + '&' +
        'featureClass=P&format=json&limit=500&accept-language=es&addressdetails=0'
      const r = await fetch(url, { headers: { 'Accept-Language': 'es' } })
      if (r.ok) {
        const data = await r.json()
        if (data && data.length > 0) {
          const seen = new Set()
          const list = data
            .map(p => p.name || (p.display_name || '').split(',')[0].trim())
            .filter(n => n && !seen.has(n.toLowerCase()) && seen.add(n.toLowerCase()))
          if (store.country?.code === code) store.cities = list
          return
        }
      }
    } catch {}

    // 2) Respaldo: nuestra base de datos local
    if (CITIES[code]) {
      if (store.country?.code === code) store.cities = [...CITIES[code]]
      return
    }

    // 3) Último recurso: solo la capital
    try {
      const r = await fetch('https://restcountries.com/v3.1/alpha/' + code + '?fields=capital')
      if (r.ok) {
        const raw = await r.json()
        const c = Array.isArray(raw) ? raw[0] : raw
        if (c?.capital?.length && store.country?.code === code) store.cities = c.capital
      }
    } catch {}
  } finally {
    store.citiesLoading = false
  }
}

export function selectCity(city) {
  store.city = city
  loadCountryMemories(store.country?.code)
}

export function clearCity() {
  store.city = null
}

// ── Sesión ────────────────────────────────────────────────────────────────
export async function restoreSession() {
  if (!API.hasToken()) return
  try {
    applyLogin(await API.me())
  } catch {
    API.clearToken()
  }
}

export function applyLogin(user) {
  store.user = user
  loadMemories()
}

export function applyLogout() {
  API.clearToken()
  store.user = null
  store.memories = []
  store.countryMemories = []
  store.memoriesPageOpen = false
}

export function firstName() {
  return store.user?.display_name?.split(' ')[0] ?? ''
}

// ── Recuerdos ─────────────────────────────────────────────────────────────
export async function loadMemories() {
  try { store.memories = await API.getMemories() } catch {}
}

export async function loadCountryMemories(code) {
  if (!store.user || !code) { store.countryMemories = []; return }
  try { store.countryMemories = await API.getMemoriesByCountry(code) } catch {}
}

export function findMemory(id) {
  return store.memories.find(m => String(m.id) === String(id))
}

// Tras crear / editar / borrar: refresca la lista del panel
export function refreshCountryMemories() {
  loadCountryMemories(store.country?.code)
}

// ── Lista de países (buscador + marcadores del mapa) ──────────────────────
export async function loadCountryList() {
  if (store.countryList.length) return
  try {
    const r = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2,flag,latlng,area')
    if (!r.ok) throw new Error('fetch failed')
    const d = await r.json()
    store.countryList = d
      .filter(c => c.cca2 && c.name?.common)
      .map(c => ({ code: c.cca2, name: c.name.common, flag: c.flag || '🌍', latlng: c.latlng || [0, 0], area: c.area || 0 }))
      .sort((a, b) => a.name.localeCompare(b.name, 'es'))
  } catch {
    store.countryList = [
      { code: 'ES', name: 'España',         flag: '🇪🇸', latlng: [40, -4],    area: 505990  },
      { code: 'FR', name: 'Francia',        flag: '🇫🇷', latlng: [46, 2],     area: 551695  },
      { code: 'IT', name: 'Italia',         flag: '🇮🇹', latlng: [42, 12],    area: 301336  },
      { code: 'DE', name: 'Alemania',       flag: '🇩🇪', latlng: [51, 9],     area: 357114  },
      { code: 'PT', name: 'Portugal',       flag: '🇵🇹', latlng: [39, -8],    area: 92090   },
      { code: 'GB', name: 'Reino Unido',    flag: '🇬🇧', latlng: [54, -2],    area: 242495  },
      { code: 'US', name: 'Estados Unidos', flag: '🇺🇸', latlng: [38, -97],   area: 9372610 },
      { code: 'MX', name: 'México',         flag: '🇲🇽', latlng: [23, -102],  area: 1964375 },
      { code: 'JP', name: 'Japón',          flag: '🇯🇵', latlng: [35, 135],   area: 377930  },
      { code: 'CN', name: 'China',          flag: '🇨🇳', latlng: [35, 105],   area: 9596960 },
      { code: 'AR', name: 'Argentina',      flag: '🇦🇷', latlng: [-34, -64],  area: 2780400 },
      { code: 'BR', name: 'Brasil',         flag: '🇧🇷', latlng: [-10, -55],  area: 8515767 },
      { code: 'MA', name: 'Marruecos',      flag: '🇲🇦', latlng: [32, -5],    area: 446550  },
      { code: 'GR', name: 'Grecia',         flag: '🇬🇷', latlng: [39, 22],    area: 131957  },
      { code: 'TR', name: 'Turquía',        flag: '🇹🇷', latlng: [39, 35],    area: 783562  },
      { code: 'TH', name: 'Tailandia',      flag: '🇹🇭', latlng: [15, 100],   area: 513120  },
      { code: 'AU', name: 'Australia',      flag: '🇦🇺', latlng: [-27, 133],  area: 7686850 },
      { code: 'IN', name: 'India',          flag: '🇮🇳', latlng: [20, 77],    area: 3287263 },
      { code: 'NL', name: 'Países Bajos',   flag: '🇳🇱', latlng: [52.3, 5.3], area: 41543   },
      { code: 'BE', name: 'Bélgica',        flag: '🇧🇪', latlng: [50.8, 4.5], area: 30528   },
    ]
  }
}
