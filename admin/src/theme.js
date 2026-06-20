// Default del tema: devono combaciare con Form::THEME_DEFAULTS lato backend.
export const DEFAULT_THEME = {
  primary: '#4f46e5',
  primaryHover: '#4338ca',
  text: '#1f2937',
  background: '#ffffff',
  border: '#e5e7eb',
  radius: '8px',
  fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
  buttonText: '#ffffff',
  maxWidth: '720px',
}

export const FONT_OPTIONS = [
  { value: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', label: 'Sistema (default)' },
  { value: 'Arial, Helvetica, sans-serif', label: 'Arial / Helvetica' },
  { value: 'Georgia, "Times New Roman", serif', label: 'Georgia (serif)' },
  { value: '"Times New Roman", Times, serif', label: 'Times New Roman' },
  { value: '"Courier New", monospace', label: 'Monospace' },
  { value: 'Verdana, Geneva, sans-serif', label: 'Verdana' },
]

// Etichette dei colori del tema per l'editor.
export const COLOR_FIELDS = [
  { key: 'primary', label: 'Colore primario' },
  { key: 'primaryHover', label: 'Primario (hover)' },
  { key: 'buttonText', label: 'Testo del bottone' },
  { key: 'text', label: 'Testo' },
  { key: 'background', label: 'Sfondo del form' },
  { key: 'border', label: 'Bordi' },
]

// Crea uno style completo a partire dai default (per nuovi form).
export function defaultStyle() {
  return { theme: { ...DEFAULT_THEME }, customCss: '' }
}

// Normalizza uno style caricato dal backend (merge sui default).
export function hydrateStyle(style) {
  const s = style && typeof style === 'object' ? style : {}
  return {
    theme: { ...DEFAULT_THEME, ...(s.theme || {}) },
    customCss: typeof s.customCss === 'string' ? s.customCss : '',
  }
}
