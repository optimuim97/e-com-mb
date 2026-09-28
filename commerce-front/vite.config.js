import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

// Ports de dev dédiés à la marque blanche (5180 / 7010), pour tourner à côté
// d'une instance client sans conflit. Surchargeables : DEV_API_TARGET=… npm run dev
const API = process.env.DEV_API_TARGET || 'http://127.0.0.1:7010';

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5180,
    proxy: {
      // En dev : proxy les appels /api, /sanctum, /broadcasting et /storage vers Laravel
      '/api':          { target: API, changeOrigin: true },
      '/sanctum':      { target: API, changeOrigin: true },
      '/broadcasting': { target: API, changeOrigin: true },
      '/storage':      { target: API, changeOrigin: true },
    },
  },
});
