import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],

    build: {
        // Aumentamos el límite de advertencia a 3000 porque los paquetes serán más grandes (esto es normal y deseado en tu caso)
        chunkSizeWarningLimit: 3000, 
        
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // 1. Agrupar las librerías de terceros (Vue, Inertia, Axios, Lodash, etc.)
                    if (id.includes('node_modules')) {
                        return 'vendor'; 
                    }
                    
                    // 2. Agrupar TODO el código de la aplicación en un solo bloque.
                    // Al juntar Pages, Components y Layouts evitamos el error "ReferenceError" 
                    // provocado por dependencias circulares, manteniendo las peticiones al mínimo.
                    if (id.includes('resources/js/')) {
                        return 'app-frontend'; 
                    }
                }
            }
        }
    }
});