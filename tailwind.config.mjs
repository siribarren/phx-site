/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        phx: {
          1: '#0B5FFF',
          2: '#1E3A8A',
          3: '#3B82F6',
          4: '#EAF2FF',
          5: '#F7FAFF',
          6: '#111827',
          7: '#6B7280',
          8: '#DCE7F9',
        },
      },
      fontFamily: {
        sans: ['Inter', 'Helvetica Neue', 'Helvetica', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        panel: '0 10px 30px rgba(15, 23, 42, 0.06)',
      },
      maxWidth: {
        content: '76rem',
      },
    },
  },
  plugins: [],
};
