// Registro de modales: cada modal se apunta aquí al montarse con su función open/close.
// Así cualquier componente puede hacer openModal('auth') sin tener referencias directas.
const registry = {}

export function registerModal(name, api) {
  registry[name] = api
}

export function openModal(name, ...args) {
  registry[name]?.open(...args)
}

export function closeModal(name) {
  registry[name]?.close()
}
