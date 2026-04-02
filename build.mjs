#!/usr/bin/env node
// build.mjs
// ─────────────────────────────────────────────────────────────
//  Replaces `vite build` for this project.
//
//  Why not Vite/Rollup?
//  Both JS entry points are plain IIFEs (no ES module imports).
//  Rollup forbids multiple inputs when format is 'iife' because
//  inlineDynamicImports is implicitly true — a hard constraint
//  with no config workaround. esbuild has no such restriction.
//
//  What this does:
//   • Minifies src/js/*.js  →  plugin/dist/js/*.js   (esbuild)
//   • Minifies src/css/*.css →  plugin/dist/css/*.css  (clean-css)
//   • Cleans the output dir first
// ─────────────────────────────────────────────────────────────

import { build }             from 'esbuild';
import CleanCSS              from 'clean-css';
import { existsSync, mkdirSync, rmSync, readFileSync, writeFileSync } from 'fs';
import { resolve, dirname, basename } from 'path';
import { fileURLToPath }     from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const OUT_DIR = resolve(__dirname, 'plugin/outsourcing-technical-guides/dist');

// ── Helpers ───────────────────────────────────────────────────
function ensureDir(dir) {
  if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
}

function log(msg) {
  process.stdout.write(msg + '\n');
}

// ── 1. Clean output dir ───────────────────────────────────────
if (existsSync(OUT_DIR)) {
  rmSync(OUT_DIR, { recursive: true, force: true });
  log('✓ Cleaned ' + OUT_DIR);
}
ensureDir(resolve(OUT_DIR, 'js'));
ensureDir(resolve(OUT_DIR, 'css'));

// ── 2. Minify JS with esbuild ─────────────────────────────────
const jsEntries = [
  'src/js/technical-guides.js',
  'src/js/download-guides.js',
];

log('\nBuilding JS…');
for (const entry of jsEntries) {
  const name    = basename(entry);
  const outFile = resolve(OUT_DIR, 'js', name);

  await build({
    entryPoints: [resolve(__dirname, entry)],
    outfile:     outFile,
    bundle:      false,   // plain IIFE — nothing to bundle
    minify:      true,
    platform:    'browser',
    target:      ['es2017'],
    logLevel:    'warning',
  });

  log('  ✓ js/' + name);
}

// ── 3. Minify CSS with clean-css ──────────────────────────────
const cssEntries = [
  'src/css/base.css',
  'src/css/technical-guides.css',
  'src/css/download-guides.css',
];

const cc = new CleanCSS({ level: 2, returnPromise: true });

log('\nBuilding CSS…');
for (const entry of cssEntries) {
  const name    = basename(entry);
  const src     = readFileSync(resolve(__dirname, entry), 'utf8');
  const result  = await cc.minify(src);

  if (result.errors.length) {
    console.error('CSS errors in ' + entry + ':', result.errors);
    process.exit(1);
  }

  writeFileSync(resolve(OUT_DIR, 'css', name), result.styles, 'utf8');
  log('  ✓ css/' + name);
}

log('\n✓ Build complete → ' + OUT_DIR + '\n');
