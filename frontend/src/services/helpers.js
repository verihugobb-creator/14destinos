// Funciones pequeñas que se usan en varios sitios

// "ES" → 🇪🇸  (usa los símbolos de "letra regional" de Unicode)
export function codeToFlag(code) {
  if (!code || code.length !== 2) return '🌍'
  try {
    const pts = [...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65)
    return String.fromCodePoint(...pts)
  } catch {
    return '🌍'
  }
}

// Fecha de hoy en formato yyyy-mm-dd (para <input type="date">)
export function today() {
  return new Date().toISOString().split('T')[0]
}
