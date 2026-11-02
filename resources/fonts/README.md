# Bundled fonts

The system fonts (docs/branding.md §3.5), downloaded from Google Fonts and shipped with the app so nothing loads from Google.

- `public/fonts/{key}/{400,500,600,700}.woff2`: screens and emails (latin subset)
- `resources/fonts/{key}/{400,700}.ttf`: PDFs (dompdf)

The list is `App\Support\Branding\Fonts::LIST`. All are free to bundle and embed: SIL Open Font License 1.1 (Inter, Open Sans, Lato, Poppins, Montserrat, Nunito, DM Sans, Work Sans, Source Serif 4, Merriweather, Playfair Display) or Apache License 2.0 (Roboto).

Lato has no 500/600 weights: the nearest file is used.
