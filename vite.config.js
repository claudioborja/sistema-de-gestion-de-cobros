import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({base: '/assets/', publicDir: false, plugins: [tailwindcss()], build: {outDir: 'public/assets', emptyOutDir: true, manifest: true, rollupOptions: {input: 'resources/js/app.js'}}});
