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

    // AÑADE ESTE BLOQUE BUILD (sirve para hacer paquetes mas grandes de recursos y no haya tantas peticiones)
    // Al haber menos peticiones hay menos latencia. (en emblemas 3d esta muy lento por su internet). 
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // 1. Agrupar las librerías de terceros (Vue, Inertia, Lodash)
                    if (id.includes('node_modules')) {
                        return 'vendor'; // Todo lo de node_modules se irá a un archivo vendor.js
                    }
                    
                    // 2. Agrupar tus componentes visuales compartidos
                    // (Ajusta la ruta si tus botones/modales están en otra carpeta diferente a Components)
                    if (id.includes('resources/js/Components/')) {
                        return 'ui-components'; // Modales, Botones, Inputs se irán a ui-components.js
                    }
                    
                    // 3. Agrupar los layouts (opcional pero recomendado)
                    if (id.includes('resources/js/Layouts/')) {
                        return 'layouts';
                    }
                }
            }
        }
    }
});
