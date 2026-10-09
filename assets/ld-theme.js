/* Limitless Dreams — Tailwind Play CDN config. Load immediately after cdn.tailwindcss.com. */
tailwind.config = {
  theme: {
    extend: {
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
      colors: {
        sand: {
          50: '#F6F3EB',
          100: '#F3EFE5',
          200: '#D8CDB8',
          500: '#756653',
          700: '#756653',
          900: '#35483C',
        },
        sage: {
          DEFAULT: '#9C9A7B',
          soft: '#B5B39A',
        }
      },
      boxShadow: {
        card: '0 1px 2px rgba(28,25,23,0.04), 0 8px 24px rgba(28,25,23,0.06)',
        'card-hover': '0 1px 2px rgba(28,25,23,0.04), 0 16px 40px rgba(28,25,23,0.10)',
      }
    }
  }
}
