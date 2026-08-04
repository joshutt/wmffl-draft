import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Dev/integration: the PHP API runs separately (php -S localhost:8080
// new/api/public/index.php); the dev server proxies /api/* to it so the
// SPA can use same-origin paths, matching the production webroot layout
// (SPA at site root, API under /api/ via .htaccess).
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
})
