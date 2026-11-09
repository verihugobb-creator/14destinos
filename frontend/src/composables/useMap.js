// Guarda la instancia del mapa de Leaflet para que otros componentes
// (buscador, botón "Estoy aquí"...) puedan moverlo sin tocar MapView.vue.
// El mapa NO se guarda en un ref() a propósito: Leaflet no se lleva bien
// con los proxies reactivos de Vue.
import L from 'leaflet'

let map = null
let locationMarker = null

export function setMap(m) { map = m }
export function getMap() { return map }

export function flyTo(lat, lng, zoom, duration = 1.5) {
  if (map && !isNaN(lat) && !isNaN(lng)) map.flyTo([lat, lng], zoom, { duration })
}

// Chincheta azul animada de "mi ubicación"
export function setLocationMarker(lat, lng, onClick) {
  if (!map) return
  if (locationMarker) locationMarker.remove()
  locationMarker = L.marker([lat, lng], {
    icon: L.divIcon({
      html: '<div class="location-pin-icon"><div class="location-pin-dot"></div><div class="location-pin-ring"></div></div>',
      className: '', iconSize: [40, 40], iconAnchor: [20, 20],
    }),
  }).addTo(map)
  if (onClick) locationMarker.on('click', onClick)
}
