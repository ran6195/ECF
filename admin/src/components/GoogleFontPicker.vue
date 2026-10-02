<script setup>
import { ref, computed, watch } from 'vue'
import { GOOGLE_FONTS, POPULAR_GOOGLE_FONTS } from '../googleFonts'

// Campo di ricerca per i font Google: mostra i più usati a vuoto, filtra
// l'elenco completo mentre si digita. Seleziona il nome "grezzo" (es.
// "Poppins"): è lo StyleEditor a derivarne lo stack CSS e passarlo al tema.
const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const allNames = Object.keys(GOOGLE_FONTS)
const query = ref(props.modelValue || '')
const open = ref(false)

// Risincronizza il campo di ricerca quando il valore cambia dall'esterno
// (caricamento di un form salvato, "Ripristina default", ecc.).
watch(() => props.modelValue, (v) => {
  if (v !== query.value) query.value = v || ''
})

const results = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return POPULAR_GOOGLE_FONTS
  return allNames.filter((n) => n.toLowerCase().includes(q)).slice(0, 40)
})

function select(name) {
  query.value = name
  open.value = false
  emit('update:modelValue', name)
}

function clear() {
  query.value = ''
  open.value = false
  emit('update:modelValue', '')
}

// Chiusura ritardata per lasciare il tempo al click sulla lista di registrarsi
// prima che il blur dell'input nasconda il dropdown.
function onBlur() {
  setTimeout(() => { open.value = false }, 150)
}
</script>

<template>
  <div class="font-picker">
    <input
      type="text"
      v-model="query"
      placeholder="Cerca un font Google (es. Poppins, Playfair...)"
      @focus="open = true"
      @blur="onBlur"
    />
    <div v-if="open" class="font-picker-list">
      <button type="button" class="font-picker-item muted" @mousedown.prevent="clear">
        Nessuno (usa il font di sistema sopra)
      </button>
      <p v-if="!query.trim()" class="font-picker-hint">Più usati:</p>
      <button
        v-for="name in results"
        :key="name"
        type="button"
        class="font-picker-item"
        :class="{ active: name === modelValue }"
        @mousedown.prevent="select(name)"
      >{{ name }}</button>
      <p v-if="query.trim() && !results.length" class="font-picker-hint">Nessun font trovato.</p>
    </div>
  </div>
</template>

<style scoped>
.font-picker { position: relative; }
.font-picker-list {
  position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
  max-height: 260px; overflow-y: auto;
  background: #fff; border: 1px solid var(--border); border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
  padding: 6px;
}
.font-picker-item {
  display: block; width: 100%; text-align: left; background: none; border: 0;
  padding: 7px 10px; border-radius: 6px; cursor: pointer; font-size: .9rem; color: var(--text);
}
.font-picker-item:hover { background: #f1f5f9; }
.font-picker-item.active { background: #eef2ff; color: var(--primary); font-weight: 600; }
.font-picker-hint { margin: 4px 10px; font-size: .78rem; color: var(--muted); }
</style>
