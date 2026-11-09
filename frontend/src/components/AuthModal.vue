<script setup>
// Modal de login / registro
import { ref, reactive, onMounted } from 'vue'
import BaseModal from './BaseModal.vue'
import { API } from '../services/api'
import { store, applyLogin, selectCountry } from '../composables/useStore'
import { registerModal } from '../composables/useModals'
import { toast } from '../composables/useToast'

const modal = ref(null)
const tab   = ref('login')      // 'login' | 'register'
const error = ref('')
const loading = ref(false)

const loginForm    = reactive({ email: '', password: '' })
const registerForm = reactive({ name: '', email: '', password: '' })

function setTab(t) {
  tab.value = t
  error.value = ''
}

function open() {
  error.value = ''
  modal.value.show()
}

// Lo común tras entrar o registrarse
function afterAuth(res, msg) {
  API.setToken(res.token)
  applyLogin(res.user)
  modal.value.hide()
  toast(msg, 'success')
  // Si había un país abierto, lo recargamos para ver ciudades y recuerdos
  if (store.country) selectCountry(store.country)
}

async function login() {
  error.value = ''
  loading.value = true
  try {
    const res = await API.login({ email: loginForm.email.trim(), password: loginForm.password })
    afterAuth(res, `¡Bienvenida, ${res.user.display_name.split(' ')[0]}! 💖`)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function register() {
  error.value = ''
  loading.value = true
  try {
    const res = await API.register({
      display_name: registerForm.name.trim(),
      email:        registerForm.email.trim(),
      password:     registerForm.password,
    })
    afterAuth(res, '¡Cuenta creada! 🌍')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(() => registerModal('auth', { open, close: () => modal.value.hide() }))
</script>

<template>
  <BaseModal id="authModal" ref="modal">
    <template #title>Bienvenida 💖</template>

    <div class="wl-tabs mb-4">
      <button class="wl-tab" :class="{ active: tab === 'login' }" @click="setTab('login')">Entrar</button>
      <button class="wl-tab" :class="{ active: tab === 'register' }" @click="setTab('register')">Registrarse</button>
    </div>

    <!-- @submit.prevent: el Enter también envía, sin recargar la página -->
    <form v-if="tab === 'login'" @submit.prevent="login">
      <input type="email" class="wl-input mb-3" v-model="loginForm.email" placeholder="Email" autocomplete="email" />
      <input type="password" class="wl-input mb-3" v-model="loginForm.password" placeholder="Contraseña" autocomplete="current-password" />
      <button type="submit" class="btn-primary-sm w-100" :disabled="loading">Entrar <i class="bi bi-arrow-right ms-1"></i></button>
    </form>

    <form v-else @submit.prevent="register">
      <input type="text" class="wl-input mb-3" v-model="registerForm.name" placeholder="Tu nombre" autocomplete="name" />
      <input type="email" class="wl-input mb-3" v-model="registerForm.email" placeholder="Email" autocomplete="email" />
      <input type="password" class="wl-input mb-3" v-model="registerForm.password" placeholder="Contraseña (mín. 8 caracteres)" autocomplete="new-password" />
      <button type="submit" class="btn-primary-sm w-100" :disabled="loading">Crear cuenta</button>
    </form>

    <div v-if="error" class="wl-error mt-3">{{ error }}</div>
  </BaseModal>
</template>
