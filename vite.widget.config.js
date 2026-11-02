import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  base: './',
  plugins: [vue()],
  build: {
    target: 'es2020',
    outDir: 'build',
    emptyOutDir: false,
    assetsDir: '',
    minify: 'esbuild',
    rollupOptions: {
      input: {
        widget: resolve(__dirname, 'assets/js/widget/app.js'),
      },
      output: {
        entryFileNames: 'botplus-widget.min.js',
        chunkFileNames: 'botplus-widget-[name].min.js',
        assetFileNames: 'botplus-widget.min.[ext]',
      },
    },
  },
  define: {
    'process.env.NODE_ENV': '"production"',
  },
});