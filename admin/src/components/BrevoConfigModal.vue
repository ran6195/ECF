<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { api, ApiError } from '../api/client'

// Wizard a 2 step per configurare la sincronizzazione Brevo di un form.
// Step 1 (Connessione): api key + list id, con test esplicito contro Brevo.
// Il test, se riuscito, ricarica anche la cache degli attributi contatto sul
// backend (tabella brevo_attributes): lo step 2 li legge da lì, senza
// richiamare Brevo ad ogni apertura della modale.
const props = defineProps({
  open: { type: Boolean, default: false },
  formId: { type: [Number, String], required: true },
  apiKey: { type: String, default: '' },
  listId: { type: [Number, String, null], default: null },
  mapping: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'save'])

const step = ref(1)
const apiKeyLocal = ref('')
const listIdLocal = ref('')
const mappingLocal = reactive({})

const testing = ref(false)
const testError = ref('')
const testSuccess = ref('')
const loadingCache = ref(false)
const attributes = ref([]) // [{name, type}], da un test riuscito o dalla cache

const emailFields = computed(() => props.fields.filter((f) => f.type === 'email'))
const canAdvance = computed(() => attributes.value.length > 0 || Object.keys(mappingLocal).length > 0)
const canSave = computed(() => !!mappingLocal.EMAIL)

async function loadCache() {
  loadingCache.value = true
  try {
    const res = await api.get(`/api/forms/${props.formId}/brevo/attributes`)
    attributes.value = res.data.attributes || []
  } catch (e) {
    // Nessuna cache ancora salvata (o errore di rete): non è bloccante, si
    // può comunque testare la connessione dallo step 1.
    attributes.value = []
  } finally {
    loadingCache.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    step.value = 1
    apiKeyLocal.value = props.apiKey || ''
    listIdLocal.value = props.listId || ''
    Object.keys(mappingLocal).forEach((k) => delete mappingLocal[k])
    Object.assign(mappingLocal, props.mapping || {})
    testError.value = ''
    testSuccess.value = ''
    attributes.value = []
    if (props.apiKey) loadCache()
  },
  { immediate: true }
)

async function testConnection() {
  testError.value = ''
  testSuccess.value = ''
  testing.value = true
  try {
    const res = await api.post(`/api/forms/${props.formId}/brevo/test-connection`, {
      api_key: apiKeyLocal.value.trim(),
      list_id: Number(listIdLocal.value),
    })
    attributes.value = res.data.attributes || []
    testSuccess.value = `Connessione riuscita — lista: ${res.data.list_name || listIdLocal.value}`
  } catch (e) {
    testError.value = e instanceof ApiError ? e.message : 'Errore imprevisto durante il test.'
  } finally {
    testing.value = false
  }
}

function goToStep2() {
  step.value = 2
}
function goToStep1() {
  step.value = 1
}

function setMapping(attrName, fieldKey) {
  if (fieldKey) mappingLocal[attrName] = fieldKey
  else delete mappingLocal[attrName]
}

function save() {
  emit('save', {
    api_key: apiKeyLocal.value.trim(),
    list_id: listIdLocal.value ? Number(listIdLocal.value) : null,
    mapping: { ...mappingLocal },
  })
}
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal card">
      <div class="flex between" style="margin-bottom:6px">
        <h3 style="margin:0">Integrazione Brevo</h3>
        <button class="btn small ghost" @click="emit('close')">✕</button>
      </div>
      <p class="muted small" style="margin-bottom:16px">
        Passo {{ step }} di 2 — {{ step === 1 ? 'Connessione' : 'Mappatura campi' }}
      </p>

      <!-- Step 1: connessione -->
      <div v-if="step === 1">
        <div class="grid-2">
          <div class="form-row">
            <label class="field-label small">Chiave API Brevo</label>
            <input v-model="apiKeyLocal" type="password" placeholder="xkeysib-..." />
          </div>
          <div class="form-row">
            <label class="field-label small">ID lista</label>
            <input v-model="listIdLocal" type="number" min="1" placeholder="es. 4" />
          </div>
        </div>

        <div v-if="testError" class="alert error small">{{ testError }}</div>
        <div v-if="testSuccess" class="alert success small">{{ testSuccess }}</div>

        <div class="flex" style="margin-top:6px">
          <button class="btn secondary small" :disabled="testing || !apiKeyLocal || !listIdLocal" @click="testConnection">
            {{ testing ? 'Verifica in corso...' : 'Testa connessione' }}
          </button>
          <span v-if="loadingCache" class="muted small">Carico configurazione salvata...</span>
        </div>

        <div class="flex" style="margin-top:20px; justify-content:flex-end">
          <button class="btn secondary" @click="emit('close')">Annulla</button>
          <button class="btn" :disabled="!canAdvance" @click="goToStep2">Avanti →</button>
        </div>
      </div>

      <!-- Step 2: mappatura campi -->
      <div v-else>
        <p v-if="!emailFields.length" class="alert error small">
          Il form non ha nessun campo di tipo Email: aggiungine uno prima di poter usare l'integrazione Brevo.
        </p>

        <div class="form-row">
          <label class="field-label small">Email del contatto <span style="color:#dc2626">*</span></label>
          <select :value="mappingLocal.EMAIL || ''" @change="setMapping('EMAIL', $event.target.value)">
            <option value="">— Seleziona campo email —</option>
            <option v-for="f in emailFields" :key="f.key" :value="f.key">{{ f.label }} ({{ f.key }})</option>
          </select>
        </div>

        <div v-if="attributes.length" style="margin-top:14px">
          <span class="field-label">Altri attributi Brevo (opzionale)</span>
          <div v-for="attr in attributes" :key="attr.name" class="form-row" style="margin-top:8px">
            <label class="field-label small">{{ attr.name }} <span class="muted">({{ attr.type || 'text' }})</span></label>
            <select :value="mappingLocal[attr.name] || ''" @change="setMapping(attr.name, $event.target.value)">
              <option value="">Nessuno</option>
              <option v-for="f in fields" :key="f.key" :value="f.key">{{ f.label }} ({{ f.key }})</option>
            </select>
          </div>
        </div>
        <p v-else class="muted small" style="margin-top:14px">
          Nessun altro attributo trovato sull'account Brevo: verrà sincronizzata solo l'email.
        </p>

        <div class="flex" style="margin-top:20px; justify-content:space-between">
          <button class="btn secondary" @click="goToStep1">← Indietro</button>
          <button class="btn" :disabled="!canSave" @click="save">Salva configurazione</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 60; }
.modal { width: 100%; max-width: 560px; max-height: 85vh; overflow: auto; margin: 0; }
</style>
