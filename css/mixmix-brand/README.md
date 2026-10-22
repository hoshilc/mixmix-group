# Mix Mix Group — sistema de diseño v1

Consultoría para restaurantes: herramientas, procesos y controles. Estilo modernista
institucional — negro y dorado, rejilla visible, filetes de 2 px, cero esquinas redondeadas,
todo alineado a la izquierda.

## Instalación

1. Copia la carpeta `mixmix-brand/` (incluye `fonts/Moontime-Regular.ttf`) al proyecto.
2. `import './mixmix-brand/mixmix.css'` (o `<link rel="stylesheet" href="mixmix-brand/mixmix.css">`).
3. Tailwind (opcional): `presets: [require('./mixmix-brand/tailwind.preset.js')]`.
4. Tokens en JS/JSON: `tokens.json`.

Bebas Neue, Archivo y Source Serif 4 se cargan desde Google Fonts dentro del CSS.
Moontime es fuente licenciada por el cliente y viaja en `fonts/`.

## Tipografía

| Rol | Familia | Uso |
| --- | --- | --- |
| Logotipo y titulares | Bebas Neue | Caja alta, tracking 0.06–0.14em. Nunca condensada ni inclinada. |
| Subtítulos y datos | Archivo 500/600/800 | Jerarquía de interfaz, etiquetas, cifras. |
| Texto corrido | Source Serif 4 | Manuales, propuestas, reportes. |
| Acento | Moontime | Una palabra o frase corta por pieza. Solo dorado o negro. |

## Color y proporción

55% negro · 25% hueso · 12% dorado · 5% gris · 3% vino.

- El dorado `#C6A15B` no es color de texto pequeño: para párrafos usa `--mm-gold-ink` (#8C6B33).
- Sobre hueso, texto secundario en `--mm-stone-ink`; sobre negro, en `--mm-stone-onblack` (contraste AA).
- Vino `#820934` etiqueta y señaliza; nunca compite con el dorado en el mismo bloque.
- Sin degradados metálicos: el brillo se resuelve en el material (foil, barniz), no en el archivo.

## Clases

`.mm-container` `.mm-section` `.mm-rule` `.mm-grid` `.mm-cell` `.mm-cell-dark`
`.mm-logo` `.mm-display` `.mm-h2` `.mm-label` `.mm-label-accent` `.mm-body` `.mm-script`
`.mm-btn` `.mm-btn-secondary` `.mm-tag` `.mm-tag-gold` `.mm-input` `.mm-grayscale`

## Reglas duras

- Cero `border-radius`.
- Filetes siempre de 2 px, nunca hairlines.
- Nada centrado: titulares, copia y etiquetas de botón van a la izquierda.
- Fotografía siempre en blanco y negro (`.mm-grayscale`), nunca tintada.
- El manuscrito no se usa en caja alta, ni para datos, títulos de sección o textos de más de cuatro palabras.
