<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuth } from '../api/auth'

const email = ref('admin@edysma.test')
const password = ref('')
const error = ref('')
const loading = ref(false)

const router = useRouter()
const { setSession } = useAuth()

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const res = await api.post('/api/auth/login', { email: email.value, password: password.value })
    setSession(res.data.token, res.data.user)
    router.push({ name: 'forms' })
  } catch (e) {
    error.value = e.message || 'Login fallito.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
    <form class="card login-card" @submit.prevent="submit">
      <h1>ECF · Admin</h1>
      <p class="muted small">Accedi per gestire i tuoi form.</p>

      <div v-if="error" class="alert error">{{ error }}</div>

      <div class="form-row">
        <label class="field-label">Email</label>
        <input v-model="email" type="email" required autocomplete="username" />
      </div>
      <div class="form-row">
        <label class="field-label">Password</label>
        <input v-model="password" type="password" required autocomplete="current-password" />
      </div>

      <button class="btn" type="submit" :disabled="loading" style="width:100%">
        {{ loading ? 'Accesso...' : 'Accedi' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-card { width: 100%; max-width: 380px; }
.login-card h1 { margin: 0 0 4px; font-size: 1.4rem; }
</style>
