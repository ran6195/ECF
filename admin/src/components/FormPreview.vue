<script setup>
// Anteprima live "estetica" del form basata sulla definizione corrente dei campi.
// È una rappresentazione client; il render autoritativo resta lato server.
defineProps({
  name: { type: String, default: '' },
  description: { type: String, default: '' },
  fields: { type: Array, default: () => [] },
})

function options(field) {
  return Array.isArray(field.options) ? field.options : []
}
</script>

<template>
  <div class="preview">
    <h3 class="preview-title">{{ name || 'Senza nome' }}</h3>
    <p v-if="description" class="muted small">{{ description }}</p>

    <div v-for="(field, i) in fields" :key="i" class="preview-field" v-show="field.type !== 'hidden'">
      <label class="field-label">
        {{ field.label || '(senza label)' }}
        <span v-if="field.required" style="color:var(--danger)">*</span>
      </label>

      <template v-if="['text','email','number','date'].includes(field.type)">
        <input :type="field.type" :placeholder="field.placeholder" disabled />
      </template>

      <textarea v-else-if="field.type === 'textarea'" :placeholder="field.placeholder" rows="3" disabled></textarea>

      <select v-else-if="field.type === 'select'" disabled>
        <option value="">— Seleziona —</option>
        <option v-for="(o, j) in options(field)" :key="j" :value="o.value">{{ o.label }}</option>
      </select>

      <div v-else-if="field.type === 'radio'" class="opt-list">
        <label v-for="(o, j) in options(field)" :key="j" class="opt"><input type="radio" disabled /> {{ o.label }}</label>
      </div>

      <div v-else-if="field.type === 'checkbox'" class="opt-list">
        <template v-if="options(field).length">
          <label v-for="(o, j) in options(field)" :key="j" class="opt"><input type="checkbox" disabled /> {{ o.label }}</label>
        </template>
        <label v-else class="opt"><input type="checkbox" disabled /> {{ field.label }}</label>
      </div>
    </div>

    <button class="btn" disabled style="margin-top:12px">Invia</button>
  </div>
</template>

<style scoped>
.preview { border: 1px dashed var(--border); border-radius: 10px; padding: 18px; background: #fafafa; }
.preview-title { margin: 0 0 4px; font-size: 1.1rem; }
.preview-field { margin-top: 14px; }
.opt-list { display: flex; flex-direction: column; gap: 6px; }
.opt { font-weight: 400; display: flex; align-items: center; gap: 6px; }
.opt input { width: auto; }
</style>
