<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client'

const route = useRoute()
const formId = route.params.id

const items = ref([])
const fields = ref([])
const pagination = ref({ page: 1, per_page: 20, total: 0, last_page: 1 })
const loading = ref(true)
const error = ref('')
const detail = ref(null)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get(`/api/forms/${formId}/submissions?page=${page}&per_page=20`)
    items.value = res.data.items
    fields.value = res.data.fields
    pagination.value = res.data.pagination
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function cell(payload, key) {
  const v = payload?.[key]
  if (Array.isArray(v)) return v.join(', ')
  return v ?? ''
}

async function exportCsv() {
  try {
    const res = await api.get(`/api/forms/${formId}/submissions/export`, { raw: true })
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `submissions-${formId}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.message
  }
}

onMounted(() => load(1))
</script>

<template>
  <div class="container">
    <div class="flex between" style="margin-bottom:18px">
      <h1 style="margin:0">Submission</h1>
      <div class="flex">
        <button class="btn secondary" @click="exportCsv">Esporta CSV</button>
        <router-link class="btn secondary" :to="{ name: 'forms' }">← Indietro</router-link>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="muted">Caricamento...</div>

    <div v-else class="card">
      <p v-if="!items.length" class="muted">Nessuna submission ancora.</p>

      <table v-else>
        <thead>
          <tr>
            <th>#</th>
            <th v-for="f in fields" :key="f.key">{{ f.label }}</th>
            <th>Data</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in items" :key="s.id">
            <td>{{ s.id }}</td>
            <td v-for="f in fields" :key="f.key">{{ cell(s.payload, f.key) }}</td>
            <td class="small muted">{{ s.created_at }}</td>
            <td><button class="btn small ghost" @click="detail = s">Dettaglio</button></td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="flex" style="margin-top:14px">
        <button class="btn small secondary" :disabled="pagination.page <= 1" @click="load(pagination.page - 1)">← Prec</button>
        <span class="muted small">Pagina {{ pagination.page }} di {{ pagination.last_page }} ({{ pagination.total }} totali)</span>
        <button class="btn small secondary" :disabled="pagination.page >= pagination.last_page" @click="load(pagination.page + 1)">Succ →</button>
      </div>
    </div>

    <!-- Modale dettaglio payload -->
    <div v-if="detail" class="modal-backdrop" @click.self="detail = null">
      <div class="modal card">
        <div class="flex between" style="margin-bottom:12px">
          <h3 style="margin:0">Submission #{{ detail.id }}</h3>
          <button class="btn small ghost" @click="detail = null">✕</button>
        </div>
        <table>
          <tr v-for="(value, key) in detail.payload" :key="key">
            <th style="width:30%">{{ key }}</th>
            <td>{{ Array.isArray(value) ? value.join(', ') : value }}</td>
          </tr>
        </table>
        <div class="muted small" style="margin-top:12px">
          <div><strong>Origine:</strong> {{ detail.source_url || '—' }}</div>
          <div><strong>IP:</strong> {{ detail.ip || '—' }}</div>
          <div><strong>User agent:</strong> {{ detail.user_agent || '—' }}</div>
          <div><strong>Data:</strong> {{ detail.created_at }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 50; }
.modal { width: 100%; max-width: 560px; max-height: 85vh; overflow: auto; margin: 0; }
</style>
