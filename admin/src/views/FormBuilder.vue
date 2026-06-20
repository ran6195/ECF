<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { api, ApiError } from '../api/client'
import FieldEditor from '../components/FieldEditor.vue'
import FormPreview from '../components/FormPreview.vue'
import SnippetBox from '../components/SnippetBox.vue'
import StyleEditor from '../components/StyleEditor.vue'
import { defaultStyle, hydrateStyle } from '../theme'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => !!route.params.id)

const form = reactive({
  id: null,
  uuid: '',
  name: '',
  description: '',
  success_message: '',
  status: 'draft',
  style: defaultStyle(),
  fields: [],
})

// allowed_origins gestito come testo (una origine per riga).
const originsText = ref('')

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const success = ref('')

let keySeq = 1
function newField() {
  return { _k: keySeq++, id: null, key: '', label: '', type: 'text', required: false, placeholder: '', options: null, validation: null, sort_order: 0 }
}

function addField() {
  form.fields.push(newField())
}
function removeField(i) {
  form.fields.splice(i, 1)
}

async function load() {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await api.get(`/api/forms/${route.params.id}`)
    Object.assign(form, res.data)
    form.style = hydrateStyle(res.data.style)
    // Aggiunge una chiave locale univoca a ogni campo (per il drag & drop).
    form.fields = res.data.fields.map((f) => ({ ...f, _k: keySeq++ }))
    originsText.value = Array.isArray(res.data.allowed_origins) ? res.data.allowed_origins.join('\n') : ''
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function buildPayload() {
  const origins = originsText.value
    .split('\n')
    .map((o) => o.trim())
    .filter(Boolean)

  return {
    name: form.name,
    description: form.description,
    success_message: form.success_message,
    status: form.status,
    allowed_origins: origins.length ? origins : null,
    style: form.style,
    fields: form.fields.map((f, i) => ({
      id: f.id || undefined,
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

async function save() {
  error.value = ''
  success.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    const payload = buildPayload()
    let res
    if (isEdit.value) {
      res = await api.put(`/api/forms/${form.id}`, payload)
    } else {
      res = await api.post('/api/forms', payload)
    }
    Object.assign(form, res.data)
    form.style = hydrateStyle(res.data.style)
    form.fields = res.data.fields.map((f) => ({ ...f, _k: keySeq++ }))
    originsText.value = Array.isArray(res.data.allowed_origins) ? res.data.allowed_origins.join('\n') : ''
    success.value = isEdit.value ? 'Form aggiornato.' : 'Form creato.'
    if (!isEdit.value) {
      router.replace({ name: 'form-edit', params: { id: res.data.id } })
    }
  } catch (e) {
    error.value = e.message
    if (e instanceof ApiError && e.errors) fieldErrors.value = e.errors
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="container">
    <div class="flex between" style="margin-bottom:18px">
      <h1 style="margin:0">{{ isEdit ? 'Modifica form' : 'Nuovo form' }}</h1>
      <router-link class="btn secondary" :to="{ name: 'forms' }">← Indietro</router-link>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="muted">Caricamento...</div>

    <div v-else class="builder">
      <div class="builder-main">
        <!-- Impostazioni generali -->
        <div class="card">
          <h3 style="margin-top:0">Impostazioni</h3>
          <div class="form-row">
            <label class="field-label">Nome</label>
            <input v-model="form.name" placeholder="Es. Modulo contatti" />
            <p v-if="fieldErrors.name" class="alert error small" style="margin-top:6px">{{ fieldErrors.name[0] }}</p>
          </div>
          <div class="form-row">
            <label class="field-label">Descrizione</label>
            <textarea v-model="form.description" rows="2" placeholder="Mostrata sotto il titolo del form"></textarea>
          </div>
          <div class="grid-2">
            <div class="form-row">
              <label class="field-label">Messaggio di successo</label>
              <input v-model="form.success_message" placeholder="Grazie per averci contattato!" />
            </div>
            <div class="form-row">
              <label class="field-label">Stato</label>
              <select v-model="form.status">
                <option value="draft">Bozza</option>
                <option value="active">Attivo</option>
                <option value="disabled">Disabilitato</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <label class="field-label">Origini autorizzate (una per riga)</label>
            <textarea v-model="originsText" rows="2" placeholder="https://www.miosito.it&#10;Lascia vuoto per accettare da qualsiasi origine"></textarea>
            <p class="muted small">Vuoto = modalità aperta (qualsiasi dominio).</p>
          </div>
        </div>

        <!-- Campi -->
        <div class="card">
          <div class="flex between" style="margin-bottom:14px">
            <h3 style="margin:0">Campi</h3>
            <button class="btn small" @click="addField">+ Aggiungi campo</button>
          </div>

          <p v-if="!form.fields.length" class="muted">Nessun campo. Aggiungine uno per iniziare.</p>

          <draggable v-model="form.fields" :item-key="(el) => el._k" handle=".drag-handle" :animation="150">
            <template #item="{ element, index }">
              <FieldEditor :field="element" :index="index" @remove="removeField(index)" />
            </template>
          </draggable>
        </div>

        <!-- Stile -->
        <div class="card">
          <h3 style="margin-top:0">Stile</h3>
          <StyleEditor :style="form.style" />
        </div>

        <div class="flex" style="margin-bottom:40px">
          <button class="btn" :disabled="saving" @click="save">
            {{ saving ? 'Salvataggio...' : (isEdit ? 'Salva modifiche' : 'Crea form') }}
          </button>
        </div>
      </div>

      <!-- Anteprima + snippet -->
      <aside class="builder-side">
        <div class="card">
          <h4 style="margin-top:0">Anteprima</h4>
          <FormPreview :name="form.name" :description="form.description" :fields="form.fields" :style="form.style" />
        </div>
        <div v-if="form.uuid" class="card">
          <SnippetBox :uuid="form.uuid" />
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.builder { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
.builder-side { position: sticky; top: 20px; }
@media (max-width: 900px) {
  .builder { grid-template-columns: 1fr; }
}
</style>
