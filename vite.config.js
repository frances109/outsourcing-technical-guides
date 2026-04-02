// vite.config.js
// ─────────────────────────────────────────────────────────────
//  npm run dev     → hot-reload dev server at http://localhost:3000
//  npm run build   → minifies src/ → plugin/dist/  (via build.mjs)
//  npm run preview → preview the production build locally
//
//  NOTE: Both JS entry points are plain IIFEs with no ES module
//  imports, so Rollup/Vite cannot bundle them as 'iife' with
//  multiple inputs (Rollup hard constraint). The build script
//  (build.mjs) uses esbuild + clean-css directly instead.
// ─────────────────────────────────────────────────────────────

import { defineConfig } from 'vite';
import { resolve }      from 'path';

export default defineConfig({
  base: './',

  server: {
    port: 3000,
    open: '/src/preview/technical-guides.html',
    host: true,
  },

  resolve: {
    alias: {
      '~bootstrap-icons': resolve(__dirname, 'node_modules/bootstrap-icons'),
    },
  },
});