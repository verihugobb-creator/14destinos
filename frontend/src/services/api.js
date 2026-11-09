/**
 * api.js — Cliente de la API centralizado.
 * Todas las llamadas fetch al backend PHP pasan por aquí; añade el token automáticamente.
 */
// Ruta relativa: en /14dest/ pide a /14dest/api, en la raíz pide a /api
const BASE = 'api'

function token() {
  return localStorage.getItem('wl_token')
}

function headers(json = true) {
  const h = json ? { 'Content-Type': 'application/json' } : {}
  const t = token()
  if (t) h['Authorization'] = `Bearer ${t}`
  return h
}

async function request(method, path, body = null, isMultipart = false) {
  // Algunos Apache eliminan la cabecera Authorization → también mandamos el token por URL
  const t   = token()
  const sep = path.includes('?') ? '&' : '?'
  const url = t ? BASE + path + sep + '_t=' + encodeURIComponent(t) : BASE + path

  const opts = { method, headers: headers(!isMultipart) }
  if (isMultipart) {
    opts.body = body                 // FormData: el navegador pone el Content-Type
  } else if (body) {
    opts.body = JSON.stringify(body)
  }

  let res
  try {
    res = await fetch(url, opts)
  } catch {
    throw new Error('No se pudo conectar al servidor.')
  }

  let data, isJson = true
  try { data = await res.json() } catch { data = {}; isJson = false }

  if (!res.ok) {
    // Respuesta vacía / no JSON con error 5xx → casi siempre es que el backend PHP no está arrancado
    const fallback = (!isJson && res.status >= 500)
      ? 'No hay conexión con el backend. ¿Está arrancado Docker (docker compose up -d)?'
      : `Error ${res.status}`
    const err = new Error(data.message || fallback)
    err.errors = data.errors ?? {}
    err.status = res.status
    throw err
  }
  return data.data ?? data
}

export const API = {
  // Auth
  register: (payload) => request('POST', '/auth/register', payload),
  login:    (payload) => request('POST', '/auth/login', payload),
  me:       ()        => request('GET',  '/auth/me'),

  // Recuerdos
  getMemories:          ()       => request('GET', '/memories'),
  getMemoriesByCountry: (code)   => request('GET', `/memories/country/${code}`),
  saveMemory:           (fd)     => request('POST', '/memories', fd, true),
  updateMemory:         (id, fd) => request('POST', `/memories/${id}`, fd, true),
  deleteMemory:         (id)     => request('DELETE', `/memories/${id}`),

  // Planes
  generatePlan: (p) => request('POST', '/plans', p),
  getPlans:     ()  => request('GET', '/plans'),

  // Token
  setToken:   (t) => localStorage.setItem('wl_token', t),
  clearToken: ()  => localStorage.removeItem('wl_token'),
  hasToken:   ()  => !!localStorage.getItem('wl_token'),
}
