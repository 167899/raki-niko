import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Збірка теми: dist/ підключає inc/assets.php за маніфестом.
// @app — код застосунку (mobile/src), з якого беремо пошук, форматування, час приготування й карту зон.
export default defineConfig({
  base: './',
  resolve: { alias: { '@app': fileURLToPath(new URL('../../../../mobile/src', import.meta.url)) } },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    target: 'es2020',
    rollupOptions: { input: 'src/main.ts' },
  },
});
