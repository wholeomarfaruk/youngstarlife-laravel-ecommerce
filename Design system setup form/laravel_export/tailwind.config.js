/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./resources/views/**/*.blade.php', './app/Livewire/**/*.php', './app/View/**/*.php'],
  theme: {
    extend: {
      colors: {
        canvas: '#F6F5F1', panel: '#FBFAF7', line: '#E4E2DA', 'line-2': '#D6D3C9', soft: '#EFEEE9', zebra: '#FAFAF7',
        ink: { DEFAULT: '#1A1A17', 2: '#3A3A34', 3: '#5E5D57', muted: '#6B6A63', faint: '#B5B3AA' },
        brand: { DEFAULT: '#1F5F4A', hover: '#174A3A', soft: '#E3EEE8', tint: '#F4F8F5', mid: '#6FA58C', pale: '#C9DCD2' },
        side: { DEFAULT: '#1A1A17', hover: '#2E2E29', line: '#3A3A34', text: '#E9E7DF', muted: '#B9B7AE', dim: '#8F8D84', num: '#6F6D65' },
        ok: { DEFAULT: '#1F5F4A', bg: '#E3EEE8' },
        info: { DEFAULT: '#1D4E89', bg: '#E4ECF6' },
        warn: { DEFAULT: '#8A5600', bg: '#FBF1DC', bar: '#C08A2A' },
        bad: { DEFAULT: '#B42318', bg: '#FBE7E4' },
        mute: { DEFAULT: '#5E5D57', bg: '#EFEEE9' },
      },
      fontFamily: { sans: ['Geist', 'system-ui', 'sans-serif'], mono: ['"Geist Mono"', 'ui-monospace', 'monospace'] },
      fontSize: { '2xs': ['10px', '14px'], xs: ['11px', '16px'], sm: ['12px', '18px'], base: ['13px', '20px'], md: ['14px', '20px'], lg: ['15px', '22px'], xl: ['18px', '24px'], '2xl': ['20px', '26px'], '3xl': ['26px', '32px'] },
      borderRadius: { sm: '3px', DEFAULT: '4px', md: '6px', lg: '8px' },
      boxShadow: { pop: '0 8px 24px rgba(26,26,23,.14)', search: '0 12px 32px rgba(26,26,23,.16)', seg: '0 1px 2px rgba(26,26,23,.12)' },
      width: { sidebar: '216px' }, spacing: { sidebar: '216px' },
    },
  },
  // tones used dynamically: text-{{ $tone }}
  safelist: ['text-ok', 'text-info', 'text-warn', 'text-bad', 'text-mute'],
  plugins: [],
};
