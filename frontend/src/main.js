// Punto de entrada: carga los estilos y monta la app de Vue en <div id="app">
import { createApp } from 'vue'

// Estilos de librerías
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import 'leaflet/dist/leaflet.css'
// Estilos propios
import './assets/style.css'

import App from './App.vue'

createApp(App).mount('#app')

// Service Worker para la PWA (solo en producción, en "npm run dev" molestaría)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js')
      .then(() => console.log('SW registered'))
      .catch(e => console.log('SW error:', e))
  })
}
