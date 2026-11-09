// Tema claro / oscuro compartido por toda la app.
// El script de index.html ya pone data-theme="light" antes de pintar (si toca).
import { ref } from 'vue'

const html = document.documentElement
export const theme = ref(html.getAttribute('data-theme') === 'light' ? 'light' : 'dark')

function applyMeta() {
  const meta = document.getElementById('metaThemeColor')
  if (meta) meta.setAttribute('content', theme.value === 'light' ? '#f5f7fb' : '#06080e')
}
applyMeta()

export function setTheme(t) {
  theme.value = t
  if (t === 'light') html.setAttribute('data-theme', 'light')
  else html.removeAttribute('data-theme')
  try { localStorage.setItem('wl_theme', t) } catch {}
  applyMeta()
}

export function toggleTheme() {
  setTheme(theme.value === 'light' ? 'dark' : 'light')
}

// ── Mapa de fondo ("tiles") ──────────────────────────────────────────────
// CARTO exige una API key (gratis): https://carto.com/basemaps/apikey/
// La key se pone en el archivo frontend/.env  →  VITE_CARTO_KEY=tu_key
// Vite mete en el código las variables que empiezan por VITE_ al compilar.
const CARTO_KEY = import.meta.env.VITE_CARTO_KEY || ''

// Si no hay key de CARTO se usa Esri (gratis y sin key).
export const USING_CARTO = CARTO_KEY !== ''

export function tileUrlForTheme() {
  if (USING_CARTO) {
    const style = theme.value === 'light' ? 'light_nolabels' : 'dark_nolabels'
    return `https://{s}.basemaps.cartocdn.com/rastertiles/${style}/{z}/{x}/{y}{r}.png?key=${CARTO_KEY}`
  }
  return theme.value === 'light'
    ? 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}'
    : 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}'
}

// Opciones de la capa de tiles para Leaflet (cada proveedor tiene las suyas)
export function tileOptions() {
  if (USING_CARTO) {
    return {
      // CARTO obliga a que este texto se vea siempre en el mapa
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
      subdomains: 'abcd',
      maxZoom: 19,
    }
  }
  return {
    attribution: 'Tiles &copy; <a href="https://www.esri.com/">Esri</a> &mdash; Esri, HERE, Garmin, &copy; OpenStreetMap',
    maxNativeZoom: 16,   // Esri tiene imágenes hasta el zoom 16
    maxZoom: 19,
  }
}
