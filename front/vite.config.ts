import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        // Chunks por vendor (función: formato soportado por Rolldown en Vite 8).
        manualChunks(id: string) {
          if (id.includes('node_modules')) {
            if (id.includes('@mantine')) return 'mantine'
            if (id.includes('react') || id.includes('react-router')) return 'react-vendor'
            if (id.includes('@tabler')) return 'icons'
            if (id.includes('@tanstack') || id.includes('axios') || id.includes('zustand')) return 'data'
          }
          return undefined
        },
      },
    },
  },
})
