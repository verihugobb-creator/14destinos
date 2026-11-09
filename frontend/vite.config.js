import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// URL donde corre el backend PHP en desarrollo (Docker / XAMPP / Apache).
// Cámbiala si tu backend usa otro puerto.
const BACKEND = process.env.BACKEND_URL || 'http://localhost:8080'

export default defineConfig({
  plugins: [vue()],
  // Rutas relativas: la web funciona en la raíz del dominio o en una subcarpeta (/14dest/)
  base: './',
  server: {
    port: 5173,
    // En desarrollo, Vite reenvía /api y /uploads al backend PHP
    proxy: {
      '/api':     { target: BACKEND, changeOrigin: true },
      '/uploads': { target: BACKEND, changeOrigin: true },
    },
  },
  build: {
    // "npm run build" deja la web lista en frontend/dist
    outDir: 'dist',
    emptyOutDir: true,
  },
})
