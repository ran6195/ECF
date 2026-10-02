<?php

declare(strict_types=1);

namespace Ecf\Support;

/**
 * Elenco dei font Google selezionabili come tema dei form (ricerca inclusa
 * nell'admin) + generazione del link Google Fonts e del fallback CSS generico.
 *
 * Deve combaciare con admin/src/googleFonts.js (stessi nomi, stesse categorie):
 * non esiste un meccanismo di generazione condivisa in questo progetto, quindi
 * l'elenco è duplicato a mano nei due posti, come già FIELD_TYPES/THEME_DEFAULTS.
 *
 * Solo il nome è significativo per Google Fonts: il set di pesi caricati
 * (regular/semibold/bold) è fisso per tutti i font, per tenere semplice la
 * generazione dell'URL.
 */
final class GoogleFonts
{
    private const WEIGHTS = '400;600;700';

    /** Font mostrati per primi nel picker, prima di cercare. */
    public const POPULAR = [
        'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Inter',
        'Nunito', 'Raleway', 'Work Sans', 'Rubik', 'Playfair Display',
        'Merriweather', 'Lora', 'Oswald', 'Space Grotesk',
    ];

    /** @var array<string, string> nome font => categoria (sans-serif|serif|display|handwriting|monospace) */
    public const FONTS = [
        // --- sans-serif ---
        'Roboto' => 'sans-serif', 'Open Sans' => 'sans-serif', 'Lato' => 'sans-serif',
        'Montserrat' => 'sans-serif', 'Poppins' => 'sans-serif', 'Inter' => 'sans-serif',
        'Nunito' => 'sans-serif', 'Nunito Sans' => 'sans-serif', 'Raleway' => 'sans-serif',
        'Work Sans' => 'sans-serif', 'Rubik' => 'sans-serif', 'Quicksand' => 'sans-serif',
        'DM Sans' => 'sans-serif', 'Mulish' => 'sans-serif', 'Karla' => 'sans-serif',
        'Barlow' => 'sans-serif', 'Heebo' => 'sans-serif', 'Manrope' => 'sans-serif',
        'Hind' => 'sans-serif', 'Cabin' => 'sans-serif', 'Josefin Sans' => 'sans-serif',
        'Jost' => 'sans-serif', 'Assistant' => 'sans-serif', 'Titillium Web' => 'sans-serif',
        'PT Sans' => 'sans-serif', 'Noto Sans' => 'sans-serif', 'Source Sans 3' => 'sans-serif',
        'Fira Sans' => 'sans-serif', 'Oxygen' => 'sans-serif', 'Ubuntu' => 'sans-serif',
        'IBM Plex Sans' => 'sans-serif', 'Overpass' => 'sans-serif', 'Varela Round' => 'sans-serif',
        'Comfortaa' => 'sans-serif', 'Catamaran' => 'sans-serif', 'Exo 2' => 'sans-serif',
        'Archivo' => 'sans-serif', 'Saira' => 'sans-serif', 'Red Hat Display' => 'sans-serif',
        'Figtree' => 'sans-serif', 'Outfit' => 'sans-serif', 'Plus Jakarta Sans' => 'sans-serif',
        'Sora' => 'sans-serif', 'Urbanist' => 'sans-serif', 'Lexend' => 'sans-serif',
        'Albert Sans' => 'sans-serif', 'Space Grotesk' => 'sans-serif', 'Spline Sans' => 'sans-serif',
        'Public Sans' => 'sans-serif', 'Epilogue' => 'sans-serif', 'Be Vietnam Pro' => 'sans-serif',
        'Signika' => 'sans-serif', 'Kanit' => 'sans-serif', 'Prompt' => 'sans-serif',
        'Mukta' => 'sans-serif', 'Rajdhani' => 'sans-serif',
        // --- serif ---
        'Playfair Display' => 'serif', 'Merriweather' => 'serif', 'Lora' => 'serif',
        'PT Serif' => 'serif', 'Noto Serif' => 'serif', 'Libre Baskerville' => 'serif',
        'Roboto Slab' => 'serif', 'Bitter' => 'serif', 'Crimson Text' => 'serif',
        'Crimson Pro' => 'serif', 'EB Garamond' => 'serif', 'Cormorant' => 'serif',
        'Cormorant Garamond' => 'serif', 'Domine' => 'serif', 'Vollkorn' => 'serif',
        'Zilla Slab' => 'serif', 'Source Serif 4' => 'serif', 'Spectral' => 'serif',
        'Alegreya' => 'serif', 'Arvo' => 'serif', 'Rokkitt' => 'serif',
        'Frank Ruhl Libre' => 'serif', 'Cardo' => 'serif', 'Bree Serif' => 'serif',
        'DM Serif Display' => 'serif', 'DM Serif Text' => 'serif', 'Prata' => 'serif',
        'Fraunces' => 'serif',
        // --- display ---
        'Oswald' => 'display', 'Bebas Neue' => 'display', 'Anton' => 'display',
        'Abril Fatface' => 'display', 'Yanone Kaffeesatz' => 'display', 'Staatliches' => 'display',
        // --- handwriting ---
        'Pacifico' => 'handwriting', 'Dancing Script' => 'handwriting', 'Caveat' => 'handwriting',
        'Satisfy' => 'handwriting', 'Great Vibes' => 'handwriting', 'Shadows Into Light' => 'handwriting',
        'Indie Flower' => 'handwriting', 'Permanent Marker' => 'handwriting', 'Kalam' => 'handwriting',
        // --- monospace ---
        'Roboto Mono' => 'monospace', 'Space Mono' => 'monospace', 'JetBrains Mono' => 'monospace',
        'Fira Code' => 'monospace', 'IBM Plex Mono' => 'monospace', 'Source Code Pro' => 'monospace',
        'Inconsolata' => 'monospace', 'Courier Prime' => 'monospace',
    ];

    public static function isValid(string $name): bool
    {
        return array_key_exists($name, self::FONTS);
    }

    /** Fallback CSS generico in base alla categoria (display non è una keyword CSS valida → sans-serif). */
    public static function genericFallback(string $name): string
    {
        return match (self::FONTS[$name] ?? 'sans-serif') {
            'serif' => 'serif',
            'handwriting' => 'cursive',
            'monospace' => 'monospace',
            default => 'sans-serif',
        };
    }

    /** Valore da usare in CSS `font-family`, es. "'Poppins', sans-serif". */
    public static function cssStack(string $name): string
    {
        return "'" . $name . "', " . self::genericFallback($name);
    }

    /** URL del foglio di stile Google Fonts (CSS2) per il nome dato. Presuppone isValid(). */
    public static function cssUrl(string $name): string
    {
        $encoded = str_replace(' ', '+', $name);

        return "https://fonts.googleapis.com/css2?family={$encoded}:wght@" . self::WEIGHTS . '&display=swap';
    }
}
