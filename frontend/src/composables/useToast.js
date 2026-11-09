// Avisos pequeños (toasts) que aparecen abajo a la derecha.
// "toasts" es una lista reactiva: ToastContainer.vue la pinta con v-for.
import { reactive } from 'vue'

export const toasts = reactive([])
let nextId = 1

export function toast(msg, type = 'info') {
  const id = nextId++
  toasts.push({ id, msg, type })
  // A los 4 segundos lo quitamos de la lista → desaparece de la pantalla
  setTimeout(() => {
    const i = toasts.findIndex(t => t.id === id)
    if (i !== -1) toasts.splice(i, 1)
  }, 4000)
}
