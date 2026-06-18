<script setup>
import { computed } from 'vue'

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
]

const hasOptions = computed(() => ['select', 'radio', 'checkbox'].includes(props.field.type))
const hasValidation = computed(() => ['text', 'email', 'textarea', 'number'].includes(props.field.type))
const isNumber = computed(() => props.field.type === 'number')

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
      <label class="flex" style="font-weight:600;font-size:.85rem">
        <input type="checkbox" v-model="field.required" style="width:auto" /> Obbligatorio
      </label>
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
