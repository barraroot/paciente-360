/**
 * T224 — Vite config para bundle do widget de chat web.
 *
 * Formato: IIFE (Immediately Invoked Function Expression).
 * Output: public/widget/v1/_bundle/widget.iife.js
 * Target: ES2020 (suporta Chrome 85+, Firefox 78+, Safari 14+).
 * Minificação: esbuild (mais rápido que terser, suficiente para ~30 KB gzip).
 *
 * pusher-js NÃO é incluído no bundle — é carregado do próprio pacote node_modules
 * e atribuído ao window.Pusher pelo transport.js via import inline dentro do IIFE.
 * Se o consumidor já tiver pusher no window, o bundle usa o existente.
 */

import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  root: resolve(__dirname),
  build: {
    outDir: resolve(__dirname, '../../public/widget/v1/_bundle'),
    emptyOutDir: true,
    target: 'es2020',
    minify: true,
    lib: {
      entry: resolve(__dirname, 'src/widget.js'),
      name: 'P360Widget',
      fileName: () => 'widget.iife.js',
      formats: ['iife'],
    },
    rollupOptions: {
      // pusher-js is bundled inline (small subset used via window.Pusher pattern)
      // No external dependencies — fully self-contained IIFE
      output: {
        // Ensure the IIFE is self-executing with no global leakage
        exports: 'none',
        inlineDynamicImports: true,
      },
    },
  },
  define: {
    'process.env.NODE_ENV': '"production"',
  },
});
