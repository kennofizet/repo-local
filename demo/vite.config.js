import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@kennofizet/repo-local-frontend': path.resolve(__dirname, '../packages/frontend/src'),
    },
    dedupe: ['vue', 'vue-router', 'axios'],
  },
  server: {
    host: '127.0.0.1',
    port: 5178,
    strictPort: true,
    proxy: {
      '/api/repo-local': {
        target: 'http://127.0.0.1:8090',
        changeOrigin: true,
      },
    },
  },
})
