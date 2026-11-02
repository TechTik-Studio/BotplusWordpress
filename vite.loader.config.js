import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  base: './',
  build: {
    target: 'es2020',
    outDir: 'build',
    emptyOutDir: false,
    assetsDir: '',
    minify: 'esbuild',
    rollupOptions: {
      input: {
        loader: resolve(__dirname, 'assets/js/loader.js'),
      },
      output: {
        entryFileNames: 'botplus-loader.min.js',
        chunkFileNames: 'botplus-loader-[name].min.js',
      },
    },
  },
});

