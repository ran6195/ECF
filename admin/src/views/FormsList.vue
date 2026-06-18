<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api/client'
import SnippetBox from '../components/SnippetBox.vue'

const forms = ref([])
const loading = ref(true)
const error = ref('')
const expanded = ref(null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/api/forms')
    forms.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function remove(form) {
  if (!confirm(`Eliminare il form "${form.name}" e tutte le sue submission?`)) return
  try {
    await api.del(`/api/forms/${form.id}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
}

function toggleSnippet(id) {
  expanded.value = expanded.value === id ? null : id
}

onMounted(load)
</script>

<template>
  <div class="container">
    <div class="flex between" style="margin-bottom:18px">
      <h1 style="margin:0">I tuoi form</h1>
      <router-link class="btn" :to="{ name: 'form-new' }">+ Nuovo form</router-link>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="muted">Caricamento...</div>

    <div v-else-if="!forms.length" class="card muted">Nessun form ancora. Creane uno!</div>

    <div v-for="form in forms" :key="form.id" class="card">
      <div class="flex between">
        <div>
          <div class="flex" style="gap:10px">
            <strong style="font-size:1.05rem">{{ form.name }}</strong>
            <span class="badge" :class="form.status">{{ form.status }}</span>
          </div>
          <div class="muted small" style="margin-top:4px">
            UUID: <code>{{ form.uuid }}</code> · {{ form.submissions_count }} submission
          </div>
        </div>
        <div class="flex">
          <button class="btn small secondary" @click="toggleSnippet(form.id)">Snippet</button>
          <router-link class="btn small secondary" :to="{ name: 'submissions', params: { id: form.id } }">
            Submission
          </router-link>
          <router-link class="btn small" :to="{ name: 'form-edit', params: { id: form.id } }">Modifica</router-link>
          <button class="btn small danger" @click="remove(form)">Elimina</button>
        </div>
      </div>

      <div v-if="expanded === form.id" style="margin-top:14px">
        <SnippetBox :uuid="form.uuid" />
      </div>
    </div>
  </div>
</template>
