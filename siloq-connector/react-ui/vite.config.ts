import path from 'path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const isWordPress = mode === 'wordpress';
  
  return {
    server: {
      port: 3000,
      host: '0.0.0.0',
    },
    plugins: [react()],
    build: isWordPress ? {
      outDir: 'dist',
      emptyOutDir: true,
      rollupOptions: {
        input: 'src/index.tsx',
        output: {
          entryFileNames: 'index.js',
          chunkFileNames: '[name].js',
          assetFileNames: (assetInfo) => {
            if (assetInfo.name?.endsWith('.css')) {
              return 'index.css';
            }
            return '[name][extname]';
          },
        },
      },
    } : undefined,
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
      },
    },
  };
});
