<script setup>
import { computed, watch } from 'vue'

// Il field è un oggetto reattivo del parent: lo mutiamo direttamente.
const props = defineProps({
  field: { type: Object, required: true },
  index: { type: Number, required: true },
})
const emit = defineEmits(['remove'])

const TYPES = [
  { value: 'text', label: 'Testo' },
  { value: 'email', label: 'Email' },
  { value: 'textarea', label: 'Testo lungo' },
  { value: 'number', label: 'Numero' },
  { value: 'select', label: 'Menu a tendina' },
  { value: 'radio', label: 'Scelta singola (radio)' },
  { value: 'checkbox', label: 'Caselle (checkbox)' },
  { value: 'date', label: 'Data' },
  { value: 'hidden', label: 'Nascosto' },
  { value: 'privacy_consent', label: 'Consenso privacy (obbligatorio)' },
  { value: 'time_slot', label: 'Fascia oraria' },
]

const TIME_SLOT_STEPS = [15, 30]
const TIME_SLOT_DEFAULTS = { start_time: '08:00', end_time: '17:00', step: 30 }

const hasOptions = computed(() => ['select', 'radio', 'checkbox'].includes(props.field.type))
const hasValidation = computed(() => ['text', 'email', 'textarea', 'number'].includes(props.field.type))
const isNumber = computed(() => props.field.type === 'number')
const isPrivacyConsent = computed(() => props.field.type === 'privacy_consent')
const isTimeSlot = computed(() => props.field.type === 'time_slot')
const isDate = computed(() => props.field.type === 'date')
const hasPlaceholderOption = computed(() => ['select', 'time_slot'].includes(props.field.type))

// La checkbox privacy è sempre obbligatoria: forza il flag in UI (l'autorità
// resta comunque il backend, che lo forza a prescindere da cosa arriva dal client).
// La fascia oraria parte precompilata (08:00-17:00, step 30') così l'anteprima
// mostra subito delle opzioni sensate.
watch(() => props.field.type, (type) => {
  if (type === 'privacy_consent') props.field.required = true
  if (type === 'time_slot') {
    ensureValidation()
    for (const k in TIME_SLOT_DEFAULTS) {
      if (!props.field.validation[k]) props.field.validation[k] = TIME_SLOT_DEFAULTS[k]
    }
  }
})

function ensureOptions() {
  if (!Array.isArray(props.field.options)) props.field.options = []
}
function addOption() {
  ensureOptions()
  props.field.options.push({ value: '', label: '' })
}
function removeOption(i) {
  props.field.options.splice(i, 1)
}
function ensureValidation() {
  if (!props.field.validation || typeof props.field.validation !== 'object') props.field.validation = {}
}

// Genera una key automatica dalla label se la key è vuota.
function autoKey() {
  if (!props.field.key && props.field.label) {
    props.field.key = props.field.label
      .toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9_]+/g, '_')
      .replace(/^_+|_+$/g, '')
  }
}
</script>

<template>
  <div class="field-editor card">
    <div class="flex between" style="margin-bottom:12px">
      <div class="flex">
        <span class="drag-handle" title="Trascina per riordinare">⠿</span>
        <strong>Campo #{{ index + 1 }}</strong>
        <span class="badge draft">{{ field.type }}</span>
      </div>
      <button class="btn small danger" type="button" @click="emit('remove')">Elimina</button>
    </div>

    <div class="grid-2">
      <div class="form-row">
        <label class="field-label">Label</label>
        <input v-model="field.label" @blur="autoKey" placeholder="Es. Nome completo" />
      </div>
      <div class="form-row">
        <label class="field-label">Key (chiave macchina)</label>
        <input v-model="field.key" placeholder="es. nome_completo" />
      </div>
    </div>

    <div class="grid-2">
      <div class="form-row">
        <label class="field-label">Tipo</label>
        <select v-model="field.type">
          <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>
      <div class="form-row">
        <label class="field-label">Placeholder</label>
        <input v-model="field.placeholder" placeholder="Testo segnaposto" />
      </div>
    </div>

    <div class="form-row">
      <label v-if="!isPrivacyConsent" class="flex" style="font-weight:600;font-size:.85rem">
        <input type="checkbox" v-model="field.required" style="width:auto" /> Obbligatorio
      </label>
      <span v-else class="muted small">Sempre obbligatoria.</span>
    </div>

    <!-- Link alla pagina privacy -->
    <div v-if="isPrivacyConsent" class="sub-section">
      <span class="field-label">Link informativa privacy</span>
      <div class="grid-2">
        <div>
          <label class="field-label small">URL pagina privacy</label>
          <input :value="field.validation?.link_url" @input="ensureValidation(); field.validation.link_url = $event.target.value" placeholder="https://www.miosito.it/privacy" />
        </div>
        <div>
          <label class="field-label small">Testo del link</label>
          <input :value="field.validation?.link_text" @input="ensureValidation(); field.validation.link_text = $event.target.value" placeholder="informativa sulla privacy" />
        </div>
      </div>
    </div>

    <!-- Prima opzione (placeholder) del <select>: comune a menu a tendina e fascia oraria -->
    <div v-if="hasPlaceholderOption" class="sub-section">
      <label class="field-label small">Testo prima opzione</label>
      <input :value="field.validation?.placeholder_label" @input="ensureValidation(); field.validation.placeholder_label = $event.target.value" placeholder="— Seleziona —" />
    </div>

    <!-- Fascia oraria: ora inizio/fine + step, le opzioni della select sono generate dal backend -->
    <div v-if="isTimeSlot" class="sub-section">
      <span class="field-label">Fascia oraria</span>
      <div class="grid-2">
        <div>
          <label class="field-label small">Ora inizio</label>
          <input type="time" :value="field.validation?.start_time" @input="ensureValidation(); field.validation.start_time = $event.target.value" />
        </div>
        <div>
          <label class="field-label small">Ora fine</label>
          <input type="time" :value="field.validation?.end_time" @input="ensureValidation(); field.validation.end_time = $event.target.value" />
        </div>
      </div>
      <div class="form-row" style="margin-top:10px">
        <label class="field-label small">Intervallo</label>
        <select :value="field.validation?.step" @change="ensureValidation(); field.validation.step = Number($event.target.value)">
          <option v-for="s in TIME_SLOT_STEPS" :key="s" :value="s">{{ s }} minuti</option>
        </select>
      </div>
    </div>

    <!-- Vincoli per il tipo data: le date escluse non sono selezionabili -->
    <div v-if="isDate" class="sub-section">
      <span class="field-label">Vincoli data</span>
      <label class="flex" style="font-weight:400;font-size:.9rem;margin-top:8px">
        <input type="checkbox" :checked="!!field.validation?.exclude_past" @change="ensureValidation(); field.validation.exclude_past = $event.target.checked" style="width:auto" />
        Escludi date passate
      </label>
      <label class="flex" style="font-weight:400;font-size:.9rem;margin-top:6px">
        <input type="checkbox" :checked="!!field.validation?.exclude_weekends" @change="ensureValidation(); field.validation.exclude_weekends = $event.target.checked" style="width:auto" />
        Escludi sabato e domenica
      </label>
      <p v-if="field.validation?.exclude_weekends" class="muted small" style="margin-top:8px">
        Il blocco del weekend non è visibile nell'anteprima qui a destra (è statica, non esegue lo script del form): verificalo su una pagina reale o su <code>test-embed</code>.
      </p>
    </div>

    <!-- Opzioni per select/radio/checkbox -->
    <div v-if="hasOptions" class="sub-section">
      <div class="flex between" style="margin-bottom:8px">
        <span class="field-label" style="margin:0">Opzioni</span>
        <button class="btn small secondary" type="button" @click="addOption">+ Opzione</button>
      </div>
      <p v-if="!field.options || !field.options.length" class="muted small">Nessuna opzione. (Per checkbox singolo lascia vuoto.)</p>
      <div v-for="(opt, i) in field.options" :key="i" class="flex" style="margin-bottom:6px">
        <input v-model="opt.label" placeholder="Etichetta" />
        <input v-model="opt.value" placeholder="Valore" />
        <button class="btn small ghost" type="button" @click="removeOption(i)">✕</button>
      </div>
    </div>

    <!-- Validazione -->
    <div v-if="hasValidation" class="sub-section">
      <span class="field-label">Validazione</span>
      <div class="grid-2">
        <template v-if="isNumber">
          <div>
            <label class="field-label small">Min</label>
            <input type="number" :value="field.validation?.min" @input="ensureValidation(); field.validation.min = $event.target.value" />
          </div>
          <div>
            <label class="field-label small">Max</label>
            <input type="number" :value="field.validation?.max" @input="ensureValidation(); field.validation.max = $event.target.value" />
          </div>
        </template>
        <template v-else>
          <div>
            <label class="field-label small">Lunghezza min</label>
            <input type="number" :value="field.validation?.minLength" @input="ensureValidation(); field.validation.minLength = $event.target.value" />
          </div>
          <div>
            <label class="field-label small">Lunghezza max</label>
            <input type="number" :value="field.validation?.maxLength" @input="ensureValidation(); field.validation.maxLength = $event.target.value" />
          </div>
        </template>
      </div>
      <div class="form-row" style="margin-top:10px">
        <label class="field-label small">Regex (opzionale)</label>
        <input :value="field.validation?.regex" @input="ensureValidation(); field.validation.regex = $event.target.value" placeholder="es. ^[0-9]{5}$" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.field-editor { background: #fcfcfd; }
.drag-handle { cursor: grab; color: var(--muted); font-size: 1.1rem; user-select: none; }
.sub-section { border-top: 1px solid var(--border); margin-top: 10px; padding-top: 12px; }
</style>
