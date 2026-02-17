/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.{twig,html}',
    './src/**/*.{js,jsx,ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        'dark': '#162C26',
        'primary': '#288760',
        'primary-pine': '#1A5140',
        'primary-mint': '#5CA87C',
        'primary-lightest-green': '#B7E5BA',
        'grey': '#666666',
        'white': '#ffffff',
        'secondary': '#25BC7A',
        'card-bg': '#FBFEFC',
        'card-border': '#DEEEE5',
        'schema-bg': '#DEEEE5',
        'schema-line': '#E9F3EF',
        
      },
      spacing: {
        '0.5': '2px',
      },
      fontFamily: {
        'sans': ['Stack Sans Text', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        'stack-headline': ['Stack Sans Headline', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        'DEFAULT': '10px',
      },
    },
  },
  plugins: [],
}
