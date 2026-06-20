<script setup>
import { ref, watch, onMounted } from 'vue'
import { api } from '../api/client'

// Anteprima FEDELE: chiede al backend l'HTML del form (stesso FormRenderer della
// produzione) e lo inietta in uno Shadow DOM, così tema e CSS personalizzato
// si vedono esattamente come sui siti terzi.
const props = defineProps({
  name: { type: String, default: '' },
  description: { type: String, default: '' },
  fields: { type: Array, default: () => [] },
  style: { type: Object, default: () => ({}) },
})

const host = ref(null)
const error = ref('')
let shadow = null
let timer = null

function payload() {
  return {
    name: props.name,
    description: props.description,
    style: props.style,
    fields: props.fields.map((f, i) => ({
      key: f.key,
      label: f.label,
      type: f.type,
      required: !!f.required,
      placeholder: f.placeholder || null,
      options: Array.isArray(f.options) && f.options.length ? f.options : null,
      validation: f.validation && Object.keys(f.validation).length ? f.validation : null,
      sort_order: i,
    })),
  }
}

async function refresh() {
  if (!shadow) return
  try {
    const res = await api.post('/api/forms/preview', payload())
    shadow.innerHTML = res.data.html
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Anteprima non disponibile.'
  }
}

// Debounce per non chiamare l'API a ogni tasto.
function scheduleRefresh() {
  clearTimeout(timer)
  timer = setTimeout(refresh, 350)
}

onMounted(() => {
  shadow = host.value.attachShadow({ mode: 'open' })
  refresh()
})

watch(() => props, scheduleRefresh, { deep: true })
</script>

<template>
  <div>
    <div ref="host" class="preview-host"></div>
    <p v-if="error" class="muted small" style="margin-top:8px">⚠ {{ error }}</p>
  </div>
</template>

<style scoped>
.preview-host { min-height: 80px; }
</style>
