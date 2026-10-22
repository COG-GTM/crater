import { defineConfig, Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { existsSync, rmSync, writeFileSync } from 'fs'

// Writes public/hot while the dev server runs so Laravel's @vite directive serves from it.
function laravelHotFile(): Plugin {
    const hotFile = resolve(__dirname, 'public/hot')
    const clean = () => existsSync(hotFile) && rmSync(hotFile)

    return {
        name: 'laravel-hot-file',
        apply: 'serve',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const { host, port, https } = server.config.server
                const hostname = typeof host === 'string' ? host : 'localhost'
                writeFileSync(hotFile, `${https ? 'https' : 'http'}://${hostname}:${port ?? 3000}`)
            })
            process.on('exit', clean)
            process.on('SIGINT', () => process.exit())
            process.on('SIGTERM', () => process.exit())
        },
    }
}

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
    plugins: [vue(), laravelHotFile()],
})
