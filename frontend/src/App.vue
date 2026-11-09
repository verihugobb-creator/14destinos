<script setup>
// Componente raíz: junta todas las piezas de la app
import { onMounted } from 'vue'
import { restoreSession, loadCountryList } from './composables/useStore'

import NavBar          from './components/NavBar.vue'
import MapView         from './components/MapView.vue'
import SidePanel       from './components/SidePanel.vue'
import MemoriesPage    from './components/MemoriesPage.vue'
import AuthModal       from './components/AuthModal.vue'
import MemoryModal     from './components/MemoryModal.vue'
import PlanModal       from './components/PlanModal.vue'
import MemoryViewModal from './components/MemoryViewModal.vue'
import DeleteModal     from './components/DeleteModal.vue'
import ToastContainer  from './components/ToastContainer.vue'
import InstallBanner   from './components/InstallBanner.vue'

onMounted(() => {
  restoreSession()    // si hay token guardado, recupera el usuario
  loadCountryList()   // lista de países (buscador + marcadores del mapa)
})
</script>

<template>
  <NavBar />
  <MapView />
  <SidePanel />
  <MemoriesPage />

  <AuthModal />
  <MemoryModal />
  <PlanModal />
  <MemoryViewModal />
  <DeleteModal />

  <ToastContainer />
  <InstallBanner />
</template>
