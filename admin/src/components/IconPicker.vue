<script setup>
import { ref } from 'vue'
import { FIELD_ICONS } from '../fieldIcons'

// Selettore a griglia per l'icona di un campo: a differenza dei font Google
// (ricerca testuale su ~100 nomi) qui le opzioni sono poche e visive, quindi
// si mostrano tutte insieme invece di cercarle.
const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const names = Object.keys(FIELD_ICONS)
const open = ref(false)

function select(name) {
  open.value = false
  emit('update:modelValue', name)
}

function onBlur() {
  setTimeout(() => { open.value = false }, 150)
}
</script>

<template>
  <div class="icon-picker">
    <button type="button" class="icon-picker-trigger" @click="open = !open" @blur="onBlur">
      <svg v-if="modelValue && FIELD_ICONS[modelValue]" class="icon-preview" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" v-html="FIELD_ICONS[modelValue].svg"></svg>
      <span>{{ modelValue && FIELD_ICONS[modelValue] ? FIELD_ICONS[modelValue].label : 'Nessuna icona' }}</span>
      <span class="muted small" style="margin-left:auto">▾</span>
    </button>
    <div v-if="open" class="icon-picker-grid">
      <button type="button" class="icon-picker-item" :class="{ active: !modelValue }" @mousedown.prevent="select('')" title="Nessuna icona">
        <span class="icon-none">✕</span>
      </button>
      <button
        v-for="name in names"
        :key="name"
        type="button"
        class="icon-picker-item"
        :class="{ active: name === modelValue }"
        :title="FIELD_ICONS[name].label"
        @mousedown.prevent="select(name)"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" v-html="FIELD_ICONS[name].svg"></svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.icon-picker { position: relative; }
.icon-picker-trigger {
  display: flex; align-items: center; gap: 8px; width: 100%;
  padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
  background: #fff; cursor: pointer; font-size: .9rem; color: var(--text);
}
.icon-preview { width: 18px; height: 18px; flex-shrink: 0; }
.icon-picker-grid {
  position: absolute; z-index: 20; top: calc(100% + 4px); left: 0;
  display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px;
  width: 280px; max-height: 220px; overflow-y: auto;
  background: #fff; border: 1px solid var(--border); border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .12); padding: 8px;
}
.icon-picker-item {
  display: flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; border: 1px solid transparent; border-radius: 6px;
  background: none; cursor: pointer; color: var(--text);
}
.icon-picker-item svg { width: 18px; height: 18px; }
.icon-picker-item:hover { background: #f1f5f9; }
.icon-picker-item.active { border-color: var(--primary); background: #eef2ff; color: var(--primary); }
.icon-none { font-size: .85rem; color: var(--muted); }
</style>
