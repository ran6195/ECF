<script setup>
import { computed } from 'vue'
import { DEFAULT_THEME, FONT_OPTIONS, COLOR_FIELDS, ALIGN_OPTIONS } from '../theme'

// Mutiamo direttamente l'oggetto style reattivo del parent.
const props = defineProps({
  style: { type: Object, required: true },
})

// Raggio bordo gestito come numero (px) ma memorizzato come stringa "8px".
const radiusPx = computed({
  get() {
    return parseInt(props.style.theme.radius, 10) || 0
  },
  set(v) {
    const n = Math.max(0, Math.min(40, Number(v) || 0))
    props.style.theme.radius = `${n}px`
  },
})

// Larghezza: "100%" = piena (nessun limite), altrimenti cap di leggibilità in px.
const isFullWidth = computed({
  get() {
    return props.style.theme.maxWidth === '100%'
  },
  set(v) {
    props.style.theme.maxWidth = v ? '100%' : `${maxWidthPx.value}px`
  },
})

const maxWidthPx = computed({
  get() {
    return parseInt(props.style.theme.maxWidth, 10) || 720
  },
  set(v) {
    const n = Math.max(360, Math.min(1200, Number(v) || 720))
    props.style.theme.maxWidth = `${n}px`
  },
})

function resetTheme() {
  Object.assign(props.style.theme, { ...DEFAULT_THEME })
}
</script>

<template>
  <div>
    <div class="flex between" style="margin-bottom:12px">
      <span class="muted small">Tema (anteprima live a destra)</span>
      <button class="btn small ghost" type="button" @click="resetTheme">Ripristina default</button>
    </div>

    <div class="color-grid">
      <div v-for="c in COLOR_FIELDS" :key="c.key" class="color-item">
        <label class="field-label small">{{ c.label }}</label>
        <div class="color-row">
          <input type="color" v-model="style.theme[c.key]" class="color-swatch" />
          <input type="text" v-model="style.theme[c.key]" class="color-hex" />
        </div>
      </div>
    </div>

    <div class="grid-2" style="margin-top:14px">
      <div>
        <label class="field-label small">Font</label>
        <select v-model="style.theme.fontFamily">
          <option v-for="f in FONT_OPTIONS" :key="f.value" :value="f.value">{{ f.label }}</option>
        </select>
      </div>
      <div>
        <label class="field-label small">Raggio bordi: {{ radiusPx }}px</label>
        <input type="range" min="0" max="40" v-model.number="radiusPx" />
      </div>
    </div>

    <div class="grid-2" style="margin-top:14px; align-items:end">
      <div>
        <label class="field-label small">
          Larghezza form: {{ isFullWidth ? 'piena (100%)' : `max ${maxWidthPx}px` }}
        </label>
        <input
          type="range"
          min="360"
          max="1200"
          step="20"
          v-model.number="maxWidthPx"
          :disabled="isFullWidth"
        />
      </div>
      <div>
        <label class="checkbox-row small">
          <input type="checkbox" v-model="isFullWidth" />
          Piena larghezza (nessun limite)
        </label>
        <p class="muted small" style="margin:4px 0 0">
          Il form occupa sempre il 100% del contenitore; il limite migliora la leggibilità su schermi larghi.
        </p>
      </div>
    </div>

    <div class="form-row" style="margin-top:14px">
      <label class="field-label small">Posizione nel contenitore</label>
      <div class="align-group">
        <button
          v-for="a in ALIGN_OPTIONS"
          :key="a.value"
          type="button"
          :class="['align-btn', { active: style.theme.align === a.value }]"
          @click="style.theme.align = a.value"
        >{{ a.label }}</button>
      </div>
      <p class="muted small" style="margin:4px 0 0">
        Visibile quando il form non è a piena larghezza.
      </p>
    </div>

    <div class="form-row" style="margin-top:14px">
      <label class="field-label small">CSS avanzato (opzionale)</label>
      <textarea
        v-model="style.customCss"
        rows="5"
        class="css-area"
        spellcheck="false"
        placeholder=".ecf-submit { text-transform: uppercase; }
.ecf-title { letter-spacing: .5px; }"
      ></textarea>
      <p class="muted small">
        Applicato dentro lo Shadow DOM del form. Classi utili:
        <code>.ecf-form</code>, <code>.ecf-title</code>, <code>.ecf-input</code>,
        <code>.ecf-submit</code>, <code>.ecf-label</code>.
      </p>
    </div>
  </div>
</template>

<style scoped>
.color-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.color-item { display: flex; flex-direction: column; gap: 4px; }
.color-row { display: flex; gap: 8px; align-items: center; }
.color-swatch { width: 42px; height: 38px; padding: 2px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; background: #fff; }
.color-hex { flex: 1; font-family: monospace; text-transform: lowercase; }
.css-area { font-family: monospace; font-size: .82rem; }
.checkbox-row { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.checkbox-row input { margin: 0; }
.align-group { display: inline-flex; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.align-btn { padding: 7px 16px; font-size: .9rem; background: #fff; border: 0; border-right: 1px solid var(--border); cursor: pointer; color: var(--text, #1f2937); }
.align-btn:last-child { border-right: 0; }
.align-btn.active { background: var(--primary, #4f46e5); color: #fff; }
@media (max-width: 600px) { .color-grid { grid-template-columns: 1fr; } }
</style>
