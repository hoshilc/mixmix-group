// Grupo MixMix — preset de Tailwind. tailwind.config.js: { presets: [require('./mixmix-brand/tailwind.preset.js')] }
module.exports = {
  theme: {
    extend: {
      colors: {
        mm: {
          black: '#111110', bone: '#F0EFF4', gold: '#C6A15B', goldInk: '#8C6B33',
          goldSoft: '#E3CB9A', stone: '#7A7978', stoneInk: '#5C5B5A',
          stoneOnBlack: '#A8A7A6', wine: '#820934'
        }
      },
      fontFamily: {
        logo: ['"Bebas Neue"', 'Impact', 'sans-serif'],
        display: ['Archivo', 'system-ui', 'sans-serif'],
        body: ['"Source Serif 4"', 'Georgia', 'serif'],
        script: ['Moontime', 'cursive']
      },
      letterSpacing: { logo: '0.14em', label: '0.2em', display: '-0.02em' },
      borderRadius: { none: '0', DEFAULT: '0' },
      borderWidth: { rule: '2px' },
      maxWidth: { page: '1180px' }
    }
  }
};
