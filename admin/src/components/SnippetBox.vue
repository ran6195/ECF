<script setup>
import { ref, computed } from 'vue'
import { api } from '../api/client'

const props = defineProps({
  uuid: { type: String, required: true },
})

const copied = ref(false)

const snippet = computed(() =>
  `<div data-ecf-form="${props.uuid}"></div>\n` +
  `<script src="${api.base}/embed.js" data-api="${api.base}" async><\/script>`
)

async function copy() {
  try {
    await navigator.clipboard.writeText(snippet.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1800)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex between" style="margin-bottom:8px">
      <span class="field-label" style="margin:0">Snippet da incollare sul sito</span>
      <button class="btn small secondary" @click="copy">{{ copied ? 'Copiato!' : 'Copia' }}</button>
    </div>
    <code class="snippet">{{ snippet }}</code>
  </div>
</template>
