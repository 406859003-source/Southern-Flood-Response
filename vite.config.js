import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
  plugins: [react()],
  base: command === 'build' ? '/Southern-Flood-Response/' : '/',
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
        rewrite: path => path.replace(/^\/api/, '/Southern-Flood-Response/api'),
      },
    },
  },
}));
