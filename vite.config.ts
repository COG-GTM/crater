import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    base: '/build/',
    publicDir: 'resources/static',
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: 'resources/scripts/main.js',
        },
    },
    server: {
        watch: {
            ignored: ['**/.env/**'],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources'),
            'vue-i18n': 'vue-i18n/dist/vue-i18n.cjs.js',
        },
    },
    plugins: [vue()],
})
