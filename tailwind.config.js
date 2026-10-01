/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/Views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        orbitron: ['Orbitron', 'ui-sans-serif', 'system-ui'],
        rajdhani: ['Rajdhani', 'ui-sans-serif', 'system-ui'],
      },
    },
  },
  safelist: [
    // Badges e alertas (admin/site)
    'border-emerald-400/25', 'bg-emerald-500/10', 'text-emerald-200', 'text-emerald-100', 'border-emerald-500/30', 'border-emerald-500/25',
    'border-amber-400/25', 'bg-amber-500/10', 'text-amber-200', 'text-amber-100', 'border-amber-500/30', 'border-amber-500/25',
    'border-rose-400/30', 'bg-rose-500/10', 'text-rose-200', 'text-rose-100', 'border-rose-500/30', 'border-rose-500/25',
    'border-cyan-400/25', 'bg-cyan-500/10', 'text-cyan-200', 'text-cyan-100', 'border-cyan-500/30', 'border-cyan-500/25',
    'border-slate-700', 'bg-slate-900/80', 'bg-slate-900/75', 'text-slate-300', 'text-slate-400',
    // Utilitários dinâmicos de posts e gradientes
    'from-cyan-600', 'to-blue-800', 'bg-cyan-500', 'text-slate-900', 'group-hover:text-cyan-400', 'text-cyan-400', 'hover:text-cyan-300',
    'from-purple-600', 'to-pink-800', 'bg-purple-500', 'text-white', 'group-hover:text-purple-400', 'text-purple-400', 'hover:text-purple-300',
    'from-green-600', 'to-teal-800', 'bg-green-500', 'group-hover:text-green-400', 'text-green-400', 'hover:text-green-300',
    // Badges legados/estáticos
    'status-badge', 'status-publicado', 'status-rascunho', 'status-agendado',
  ],
  plugins: [],
};
