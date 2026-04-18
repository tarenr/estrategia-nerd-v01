import { build } from 'esbuild';

await build({
  entryPoints: ['scripts/admin-html-editor-source.js'],
  bundle: true,
  format: 'iife',
  target: ['es2019'],
  minify: false,
  sourcemap: false,
  outfile: 'public/assets/js/admin-post-html-editor.js',
  logLevel: 'info',
});
