<script setup>
// Mapa del mundo con Leaflet: países, provincias y marcadores de recuerdos
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import L from 'leaflet'
import { store, countryCounts, nameES, selectCountry, selectCity } from '../composables/useStore'
import { theme, tileUrlForTheme, tileOptions } from '../composables/useTheme'
import { setMap } from '../composables/useMap'

const mapEl = ref(null)   // el <div> donde se dibuja el mapa

// Variables "normales" (no reactivas): Leaflet las gestiona él solo
let map, tileLayer, countriesLayer, provincesLayer
let selectedLayer = null
let selectedProvinceLayer = null
let memoryMarkers = []

// ── Estilos ───────────────────────────────────────────────────────────────
const styleDefault  = { fillColor: '#ffffff', weight: 0.6, color: 'rgba(255,255,255,0.15)', fillOpacity: 0.0, opacity: 0.4 }
const styleHover    = { fillColor: '#4d9fff', fillOpacity: 0.22, weight: 1.2, color: 'rgba(77,159,255,0.5)' }
const styleSelected = { fillColor: '#1a3a6b', fillOpacity: 0.85, weight: 1.5, color: '#4d9fff', opacity: 0.9 }
const styleMemory   = { fillColor: '#1a3a6b', fillOpacity: 0.85, weight: 1.5, color: '#4d9fff', opacity: 0.7 }

const provinceDefault  = { fillColor: '#ffffff', weight: 0.5, color: 'rgba(255,255,255,0.08)', fillOpacity: 0.0, opacity: 0.25 }
const provinceHover    = { fillColor: '#4d9fff', fillOpacity: 0.32, weight: 1.3, color: '#4d9fff', opacity: 0.85 }
const provinceSelected = { fillColor: '#2d7fe0', fillOpacity: 0.55, weight: 1.5, color: '#9fc6ff', opacity: 0.95 }

// Código ISO de un país del GeoJSON ("-99" = sin código)
function countryCode(layer) {
  const c = layer.feature?.properties?.ISO_A2 || ''
  return c !== '-99' ? c : ''
}

// Estilo "en reposo": azul si tiene recuerdos, transparente si no
function baseStyle(layer) {
  return countryCounts.value[countryCode(layer)] ? styleMemory : styleDefault
}

// ── Países ────────────────────────────────────────────────────────────────
function onCountryHover(e) {
  const layer = e.target
  if (layer === selectedLayer) return
  layer.setStyle(styleHover)
  layer.bringToFront()
}

function onCountryHoverOut(e) {
  const layer = e.target
  if (layer === selectedLayer) return
  layer.setStyle(baseStyle(layer))
}

function selectCountryLayer(layer) {
  if (selectedLayer && selectedLayer !== layer) selectedLayer.setStyle(baseStyle(selectedLayer))
  selectedLayer = layer
  layer.setStyle(styleSelected)
}

function onCountryClick(e) {
  L.DomEvent.stopPropagation(e)
  const layer = e.target
  const p = layer.feature.properties

  selectCountryLayer(layer)
  layer.bringToFront()
  if (selectedProvinceLayer) {
    selectedProvinceLayer.setStyle(provinceDefault)
    selectedProvinceLayer = null
  }
  provincesLayer?.bringToFront()
  map.fitBounds(layer.getBounds(), { padding: [60, 60], maxZoom: 6 })

  const name = nameES(p.ADMIN || p.NAME || '')
  selectCountry({ code: countryCode(layer), name, nameES: name })
}

// ── Provincias ────────────────────────────────────────────────────────────
function onProvinceHover(e) {
  const layer = e.target
  if (layer === selectedProvinceLayer) return
  layer.setStyle(provinceHover)
  layer.bringToFront()
}

function onProvinceHoverOut(e) {
  const layer = e.target
  if (layer === selectedProvinceLayer) return
  layer.setStyle(provinceDefault)
}

function onProvinceClick(e) {
  L.DomEvent.stopPropagation(e)
  const layer = e.target
  const p = layer.feature.properties

  if (selectedProvinceLayer && selectedProvinceLayer !== layer) selectedProvinceLayer.setStyle(provinceDefault)
  selectedProvinceLayer = layer
  layer.setStyle(provinceSelected)
  layer.bringToFront()
  map.fitBounds(layer.getBounds(), { padding: [50, 50], maxZoom: 8 })

  // País al que pertenece la provincia
  const code = (p.iso_a2 && p.iso_a2 !== '-99') ? p.iso_a2.toUpperCase() : ''
  const name = nameES(p.admin || '')

  // Marcamos también el país en la capa de países
  countriesLayer?.eachLayer(cLayer => {
    if (code && countryCode(cLayer) === code) selectCountryLayer(cLayer)
  })

  selectCountry({ code, name, nameES: name })
  // La provincia hace de "ciudad" seleccionada en el panel
  if (p.name) selectCity(p.name)
}

// ── Marcadores con el nº de recuerdos por país ────────────────────────────
function updateMemoryMarkers() {
  if (!map) return

  // 1) Repintar países según si tienen recuerdos o no
  countriesLayer?.eachLayer(layer => {
    if (layer !== selectedLayer) layer.setStyle(baseStyle(layer))
  })

  // 2) Borrar marcadores viejos y crear los nuevos
  memoryMarkers.forEach(m => m.remove())
  memoryMarkers = []

  Object.entries(countryCounts.value).forEach(([code, count]) => {
    const c = store.countryList.find(x => x.code === code)
    if (!c || !c.latlng || !c.latlng[0]) return

    const icon = L.divIcon({
      html: `<div class="memory-country-marker"><div class="mcm-inner"><div class="mcm-dot"></div><span class="mcm-count">${count}</span></div></div>`,
      className: '',
      iconSize: [null, null],
      iconAnchor: [0, 0],
    })
    memoryMarkers.push(
      L.marker([c.latlng[0], c.latlng[1]], { icon, interactive: false, zIndexOffset: -100 }).addTo(map)
    )
  })
}

// ── Altura del mapa = ventana - barra de navegación ───────────────────────
function setMapHeight() {
  if (mapEl.value) mapEl.value.style.height = (window.innerHeight - 60) + 'px'
}
function onResize() {
  setMapHeight()
  map?.invalidateSize()
}

const tooltipOpts = { sticky: true, className: 'leaflet-tooltip-dark', offset: [10, 0] }

// ── Crear el mapa cuando el <div> ya existe en la página ─────────────────
onMounted(() => {
  setMapHeight()
  window.addEventListener('resize', onResize)

  map = L.map(mapEl.value, {
    center: [25, 10],
    zoom: 2,
    minZoom: 2,
    maxZoom: 10,
    zoomControl: true,
    preferCanvas: true,
    worldCopyJump: false,
    maxBounds: [[-85, -210], [85, 210]],
    maxBoundsViscosity: 0.9,
  })
  setMap(map)   // lo compartimos con el resto de componentes

  tileLayer = L.tileLayer(tileUrlForTheme(), tileOptions()).addTo(map)

  // Capa de países
  fetch('js/countries.geojson')
    .then(r => r.json())
    .then(data => {
      countriesLayer = L.geoJSON(data, {
        style: () => ({ ...styleDefault }),
        onEachFeature: (feature, layer) => {
          const p = feature.properties
          layer.bindTooltip(p.ADMIN || p.NAME || '', tooltipOpts)
          layer.on({ mouseover: onCountryHover, mouseout: onCountryHoverOut, click: onCountryClick })
        },
      }).addTo(map)
      provincesLayer?.bringToFront()
      updateMemoryMarkers()
    })
    .catch(e => console.error('GeoJSON error:', e))

  // Capa de provincias / estados (por encima de los países)
  fetch('js/provinces.geojson')
    .then(r => r.json())
    .then(data => {
      provincesLayer = L.geoJSON(data, {
        style: () => ({ ...provinceDefault }),
        onEachFeature: (feature, layer) => {
          layer.bindTooltip(feature.properties.name || '', tooltipOpts)
          layer.on({ mouseover: onProvinceHover, mouseout: onProvinceHoverOut, click: onProvinceClick })
        },
      }).addTo(map)
      provincesLayer.bringToFront()
    })
    .catch(e => console.warn('Provinces GeoJSON not loaded:', e))
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  map?.remove()
})

// ── Reacciones a cambios del estado ───────────────────────────────────────
// Cambia el tema → cambian las imágenes del mapa
watch(theme, () => tileLayer?.setUrl(tileUrlForTheme()))

// Cambian los recuerdos o llega la lista de países → redibujar marcadores
watch([countryCounts, () => store.countryList.length], updateMemoryMarkers)
</script>

<template>
  <div id="map" ref="mapEl"></div>
</template>
