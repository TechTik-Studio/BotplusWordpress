import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  base: './',
  plugins: [vue()],
  build: {
    outDir: 'build',
    emptyOutDir: false,
    assetsDir: '',
    minify: 'esbuild',
    cssCodeSplit: false,
    modulePreload: false,
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'assets/js/admin/main.js'),
      },
      output: {
        entryFileNames: 'botplus-admin.min.js',
        assetFileNames: 'botplus-admin.min.[ext]',
        inlineDynamicImports: true,
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/js/admin'),
      '@css': resolve(__dirname, 'assets/css'),
    },
  },
});