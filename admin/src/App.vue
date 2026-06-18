<script setup>
import { useRouter } from 'vue-router'
import { useAuth } from './api/auth'

const { isAuthenticated, user, logout } = useAuth()
const router = useRouter()

function doLogout() {
  logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="isAuthenticated" class="app-header">
    <div class="brand">ECF · Admin</div>
    <nav>
      <router-link :to="{ name: 'forms' }">Form</router-link>
    </nav>
    <div class="flex">
      <span class="muted small">{{ user?.email }}</span>
      <button class="btn ghost small" @click="doLogout">Esci</button>
    </div>
  </header>

  <router-view />
</template>
