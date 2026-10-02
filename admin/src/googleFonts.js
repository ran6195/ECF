// Elenco dei font Google selezionabili nello stile dei form.
// Deve combaciare con backend/src/Support/GoogleFonts.php (stessi nomi, stesse
// categorie): nessun meccanismo di generazione condivisa in questo progetto,
// l'elenco è duplicato a mano nei due posti, come DEFAULT_THEME/THEME_DEFAULTS.

// Font mostrati per primi nel picker, prima di cercare.
export const POPULAR_GOOGLE_FONTS = [
  'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Inter',
  'Nunito', 'Raleway', 'Work Sans', 'Rubik', 'Playfair Display',
  'Merriweather', 'Lora', 'Oswald', 'Space Grotesk',
]

// nome font -> categoria (sans-serif|serif|display|handwriting|monospace)
export const GOOGLE_FONTS = {
  // --- sans-serif ---
  'Roboto': 'sans-serif', 'Open Sans': 'sans-serif', 'Lato': 'sans-serif',
  'Montserrat': 'sans-serif', 'Poppins': 'sans-serif', 'Inter': 'sans-serif',
  'Nunito': 'sans-serif', 'Nunito Sans': 'sans-serif', 'Raleway': 'sans-serif',
  'Work Sans': 'sans-serif', 'Rubik': 'sans-serif', 'Quicksand': 'sans-serif',
  'DM Sans': 'sans-serif', 'Mulish': 'sans-serif', 'Karla': 'sans-serif',
  'Barlow': 'sans-serif', 'Heebo': 'sans-serif', 'Manrope': 'sans-serif',
  'Hind': 'sans-serif', 'Cabin': 'sans-serif', 'Josefin Sans': 'sans-serif',
  'Jost': 'sans-serif', 'Assistant': 'sans-serif', 'Titillium Web': 'sans-serif',
  'PT Sans': 'sans-serif', 'Noto Sans': 'sans-serif', 'Source Sans 3': 'sans-serif',
  'Fira Sans': 'sans-serif', 'Oxygen': 'sans-serif', 'Ubuntu': 'sans-serif',
  'IBM Plex Sans': 'sans-serif', 'Overpass': 'sans-serif', 'Varela Round': 'sans-serif',
  'Comfortaa': 'sans-serif', 'Catamaran': 'sans-serif', 'Exo 2': 'sans-serif',
  'Archivo': 'sans-serif', 'Saira': 'sans-serif', 'Red Hat Display': 'sans-serif',
  'Figtree': 'sans-serif', 'Outfit': 'sans-serif', 'Plus Jakarta Sans': 'sans-serif',
  'Sora': 'sans-serif', 'Urbanist': 'sans-serif', 'Lexend': 'sans-serif',
  'Albert Sans': 'sans-serif', 'Space Grotesk': 'sans-serif', 'Spline Sans': 'sans-serif',
  'Public Sans': 'sans-serif', 'Epilogue': 'sans-serif', 'Be Vietnam Pro': 'sans-serif',
  'Signika': 'sans-serif', 'Kanit': 'sans-serif', 'Prompt': 'sans-serif',
  'Mukta': 'sans-serif', 'Rajdhani': 'sans-serif',
  // --- serif ---
  'Playfair Display': 'serif', 'Merriweather': 'serif', 'Lora': 'serif',
  'PT Serif': 'serif', 'Noto Serif': 'serif', 'Libre Baskerville': 'serif',
  'Roboto Slab': 'serif', 'Bitter': 'serif', 'Crimson Text': 'serif',
  'Crimson Pro': 'serif', 'EB Garamond': 'serif', 'Cormorant': 'serif',
  'Cormorant Garamond': 'serif', 'Domine': 'serif', 'Vollkorn': 'serif',
  'Zilla Slab': 'serif', 'Source Serif 4': 'serif', 'Spectral': 'serif',
  'Alegreya': 'serif', 'Arvo': 'serif', 'Rokkitt': 'serif',
  'Frank Ruhl Libre': 'serif', 'Cardo': 'serif', 'Bree Serif': 'serif',
  'DM Serif Display': 'serif', 'DM Serif Text': 'serif', 'Prata': 'serif',
  'Fraunces': 'serif',
  // --- display ---
  'Oswald': 'display', 'Bebas Neue': 'display', 'Anton': 'display',
  'Abril Fatface': 'display', 'Yanone Kaffeesatz': 'display', 'Staatliches': 'display',
  // --- handwriting ---
  'Pacifico': 'handwriting', 'Dancing Script': 'handwriting', 'Caveat': 'handwriting',
  'Satisfy': 'handwriting', 'Great Vibes': 'handwriting', 'Shadows Into Light': 'handwriting',
  'Indie Flower': 'handwriting', 'Permanent Marker': 'handwriting', 'Kalam': 'handwriting',
  // --- monospace ---
  'Roboto Mono': 'monospace', 'Space Mono': 'monospace', 'JetBrains Mono': 'monospace',
  'Fira Code': 'monospace', 'IBM Plex Mono': 'monospace', 'Source Code Pro': 'monospace',
  'Inconsolata': 'monospace', 'Courier Prime': 'monospace',
}

export function isValidGoogleFont(name) {
  return Object.prototype.hasOwnProperty.call(GOOGLE_FONTS, name)
}

// Fallback CSS generico in base alla categoria ("display" non è una keyword CSS valida).
export function genericFallback(name) {
  const category = GOOGLE_FONTS[name] || 'sans-serif'
  if (category === 'serif') return 'serif'
  if (category === 'handwriting') return 'cursive'
  if (category === 'monospace') return 'monospace'
  return 'sans-serif'
}

// Valore da usare in CSS font-family, es. "'Poppins', sans-serif".
export function googleFontCssStack(name) {
  return `'${name}', ${genericFallback(name)}`
}
