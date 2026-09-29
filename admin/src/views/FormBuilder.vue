<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { api, ApiError } from '../api/client'
import BrevoConfigModal from '../components/BrevoConfigModal.vue'
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
  redirect_url: '',
  status: 'draft',
  style: defaultStyle(),
  fields: [],
  recaptcha_enabled: false,
  recaptcha_site_key: '',
  recaptcha_secret_key: '',
  brevo_enabled: false,
  brevo_api_key: '',
  brevo_list_id: null,
  brevo_field_mapping: {},
})

const brevoModalOpen = ref(false)
const brevoConfigured = computed(() => !!(
  form.brevo_api_key && form.brevo_list_id && form.brevo_field_mapping && form.brevo_field_mapping.EMAIL
))

function onBrevoToggle(checked) {
  form.brevo_enabled = checked
  if (checked && !brevoConfigured.value) {
    brevoModalOpen.value = true
  }
}

function onBrevoSave({ api_key, list_id, mapping }) {
  form.brevo_api_key = api_key
  form.brevo_list_id = list_id
  form.brevo_field_mapping = mapping
  brevoModalOpen.value = false
}

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

// Applica lo style ricevuto dal backend MUTANDO l'oggetto form.style esistente,
// invece di sostituirlo con uno nuovo (`form.style = hydrateStyle(...)`).
// Bug osservato: dopo ogni salvataggio i campi della card "Stile" (StyleEditor,
// legato a :style="form.style") smettevano di rispondere a click/digitazione,
// mentre i campi legati direttamente a proprietà di primo livello di `form`
// (Nome, Descrizione, mai riassegnate) continuavano a funzionare — sintomo
// tipico di un binding v-model rimasto agganciato al riferimento oggetto
// precedente quando il prop viene sostituito anziché mutato. Riassegnando le
// chiavi sull'oggetto esistente l'identità dell'oggetto passato come prop a
// StyleEditor/FormPreview resta sempre la stessa.
function applyStyle(rawStyle) {
  const hydrated = hydrateStyle(rawStyle)
  Object.keys(form.style.theme).forEach((k) => {
    if (!(k in hydrated.theme)) delete form.style.theme[k]
  })
  Object.assign(form.style.theme, hydrated.theme)
  form.style.customCss = hydrated.customCss
}

async function load() {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await api.get(`/api/forms/${route.params.id}`)
    // style e fields sono esclusi dall'assign generico e gestiti a parte
    // (applyStyle/riga sotto): un Object.assign(form, res.data) qui
    // sovrascriverebbe comunque form.style con l'oggetto grezzo del server,
    // vanificando la mutazione in-place di applyStyle.
    const { style, fields, ...rest } = res.data
    Object.assign(form, rest)
    applyStyle(style)
    // Aggiunge una chiave locale univoca a ogni campo (per il drag & drop).
    form.fields = fields.map((f) => ({ ...f, _k: keySeq++ }))
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
    redirect_url: form.redirect_url || null,
    status: form.status,
    allowed_origins: origins.length ? origins : null,
    style: form.style,
    recaptcha_enabled: !!form.recaptcha_enabled,
    recaptcha_site_key: form.recaptcha_site_key || null,
    recaptcha_secret_key: form.recaptcha_secret_key || null,
    brevo_enabled: !!form.brevo_enabled,
    brevo_api_key: form.brevo_api_key || null,
    brevo_list_id: form.brevo_list_id || null,
    brevo_field_mapping: form.brevo_field_mapping && Object.keys(form.brevo_field_mapping).length ? form.brevo_field_mapping : null,
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
    const { style, fields, ...rest } = res.data
    Object.assign(form, rest)
    applyStyle(style)
    form.fields = fields.map((f) => ({ ...f, _k: keySeq++ }))
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
            <label class="field-label">Thank-you page (opzionale)</label>
            <input v-model="form.redirect_url" placeholder="https://www.miosito.it/grazie" />
            <p class="muted small">
              Se impostata, l'utente viene reindirizzato subito dopo l'invio (il messaggio di successo sopra non verrà mostrato).
            </p>
            <p v-if="fieldErrors.redirect_url" class="alert error small" style="margin-top:6px">{{ fieldErrors.redirect_url[0] }}</p>
          </div>
          <div class="form-row">
            <label class="field-label">
              Origini autorizzate (una per riga)<span v-if="form.recaptcha_enabled" style="color:#dc2626" aria-hidden="true"> *</span>
            </label>
            <textarea v-model="originsText" rows="2" placeholder="https://www.miosito.it&#10;Lascia vuoto per accettare da qualsiasi origine"></textarea>
            <p class="muted small">Vuoto = modalità aperta (qualsiasi dominio).</p>
            <p v-if="form.recaptcha_enabled && !originsText.trim()" class="alert error small" style="margin-top:6px">
              Obbligatorie perché reCAPTCHA è attivo: la site key è valida solo per i domini registrati su Google.
            </p>
            <p v-if="fieldErrors.allowed_origins" class="alert error small" style="margin-top:6px">{{ fieldErrors.allowed_origins[0] }}</p>
          </div>
        </div>

        <!-- Anti-spam -->
        <div class="card">
          <h3 style="margin-top:0">Anti-spam (reCAPTCHA v2)</h3>
          <label class="checkbox-row small">
            <input type="checkbox" v-model="form.recaptcha_enabled" />
            Attiva Google reCAPTCHA v2 (checkbox "Non sono un robot")
          </label>
          <div v-if="form.recaptcha_enabled" style="margin-top:12px">
            <p class="muted small">
              La site key è legata al dominio registrato nella
              <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">console reCAPTCHA di Google</a>:
              assicurati che coincida con le origini autorizzate sopra.
            </p>
            <div class="grid-2" style="margin-top:8px">
              <div class="form-row">
                <label class="field-label small">Site key</label>
                <input v-model="form.recaptcha_site_key" placeholder="6Lc..." />
                <p v-if="fieldErrors.recaptcha_site_key" class="alert error small" style="margin-top:6px">{{ fieldErrors.recaptcha_site_key[0] }}</p>
              </div>
              <div class="form-row">
                <label class="field-label small">Secret key</label>
                <input v-model="form.recaptcha_secret_key" type="password" placeholder="6Lc..." />
                <p v-if="fieldErrors.recaptcha_secret_key" class="alert error small" style="margin-top:6px">{{ fieldErrors.recaptcha_secret_key[0] }}</p>
              </div>
            </div>
            <p class="muted small" style="margin-top:8px">
              Il widget non è visibile nell'anteprima qui a destra (fallirebbe comunque per dominio non registrato): verificalo su una pagina reale o su <code>test-embed</code>.
            </p>
          </div>
        </div>

        <!-- Integrazioni -->
        <div class="card">
          <h3 style="margin-top:0">Integrazioni</h3>
          <label class="checkbox-row small">
            <input type="checkbox" :checked="form.brevo_enabled" :disabled="!form.id" @change="onBrevoToggle($event.target.checked)" />
            Sincronizza le submission su Brevo
          </label>
          <p v-if="!form.id" class="muted small" style="margin-top:8px">Salva il form per configurare l'integrazione Brevo.</p>
          <template v-else-if="form.brevo_enabled">
            <p v-if="brevoConfigured" class="muted small" style="margin-top:8px">
              Lista #{{ form.brevo_list_id }} · {{ Object.keys(form.brevo_field_mapping || {}).length }} campi mappati
            </p>
            <p v-else class="alert error small" style="margin-top:8px">Configurazione incompleta: completa la connessione a Brevo.</p>
            <button class="btn small secondary" type="button" style="margin-top:8px" @click="brevoModalOpen = true">
              {{ brevoConfigured ? 'Modifica configurazione' : 'Configura Brevo' }}
            </button>
          </template>
          <p v-if="fieldErrors.brevo_api_key" class="alert error small" style="margin-top:8px">{{ fieldErrors.brevo_api_key[0] }}</p>
          <p v-if="fieldErrors.brevo_list_id" class="alert error small" style="margin-top:6px">{{ fieldErrors.brevo_list_id[0] }}</p>
          <p v-if="fieldErrors['brevo_field_mapping.EMAIL']" class="alert error small" style="margin-top:6px">{{ fieldErrors['brevo_field_mapping.EMAIL'][0] }}</p>
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

    <BrevoConfigModal
      :open="brevoModalOpen"
      :form-id="form.id"
      :api-key="form.brevo_api_key"
      :list-id="form.brevo_list_id"
      :mapping="form.brevo_field_mapping || {}"
      :fields="form.fields"
      @close="brevoModalOpen = false"
      @save="onBrevoSave"
    />
  </div>
</template>

<style scoped>
.container { max-width: 1360px; }
.builder { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; }
.builder-side { position: sticky; top: 20px; }
.checkbox-row { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.checkbox-row input { margin: 0; }
@media (max-width: 900px) {
  .builder { grid-template-columns: 1fr; }
}
</style>
