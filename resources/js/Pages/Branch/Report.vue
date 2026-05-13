<template>
    <Head title="Reporte de Clientes y Sucursales" />

    <!-- Usamos clases 'print:' de TailwindCSS para optimizar el modo impresión -->
    <div class="min-h-screen bg-gray-100 py-8 print:py-0 print:bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0">
            <div class="bg-white shadow-sm sm:rounded-lg print:shadow-none print:rounded-none p-6 print:p-0">
                
                <!-- Cabecera web (oculta al imprimir) -->
                <div class="flex justify-between items-center mb-6 print:hidden">
                    <h2 class="text-2xl font-bold text-gray-800">Reporte de Clientes y Sucursales</h2>
                    <el-button type="primary" @click="printReport">
                        <i class="fa-solid fa-print mr-2"></i> Imprimir / Guardar PDF
                    </el-button>
                </div>

                <!-- Cabecera de impresión (visible sólo al imprimir) -->
                <div class="hidden print:block mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 text-center">Directorio de Clientes y Sucursales</h2>
                    <p class="text-center text-gray-500 mt-2">
                        Generado el: {{ new Date().toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </p>
                </div>

                <!-- Tabla de Datos -->
                <div class="overflow-x-auto">
                    <!-- Agregamos table-fixed para forzar a las columnas a respetar los anchos -->
                    <table class="min-w-full table-fixed divide-y divide-gray-200 border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <!-- Reducimos la columna Nombre a w-1/4 y Vendedor a w-1/4 para balancear -->
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300 w-1/4">
                                    Nombre / Sucursal
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300 w-1/6">
                                    RFC
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300 w-1/3">
                                    Dirección
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300 w-1/4">
                                    Vendedor Asignado
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="matriz in matrices" :key="'m-' + matriz.id">
                                <!-- FILA MATRIZ -->
                                <tr class="bg-gray-100">
                                    <!-- Se remueve whitespace-nowrap y se agrega break-words para que el texto haga saltos de línea al imprimir -->
                                    <td class="px-4 py-4 text-sm text-gray-900 border-r border-gray-200 font-semibold break-words">
                                        <i class="fa-solid fa-crown text-yellow-500 mr-2"></i> 
                                        {{ matriz.name }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 border-r border-gray-200 font-medium break-words">
                                        {{ matriz.rfc || 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 border-r border-gray-200 break-words">
                                        {{ matriz.address || 'Sin dirección registrada' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 font-medium break-words">
                                        {{ matriz.account_manager?.name || 'No asignado' }}
                                    </td>
                                </tr>

                                <!-- FILAS SUCURSALES (HIJAS) -->
                                <tr v-for="hija in matriz.children" :key="'h-' + hija.id" class="bg-white hover:bg-gray-50 transition-colors">
                                    <!-- Indentamos la celda para que se entienda la jerarquía -->
                                    <td class="px-4 py-3 text-sm text-gray-600 border-r border-gray-200 pl-10 break-words">
                                        <i class="fa-solid fa-arrow-turn-up fa-rotate-90 text-gray-400 mr-2"></i>
                                        {{ hija.name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 border-r border-gray-200 break-words">
                                        {{ hija.rfc || 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 border-r border-gray-200 break-words">
                                        {{ hija.address || 'Sin dirección registrada' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 break-words">
                                        {{ hija.account_manager?.name || 'No asignado' }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    
                    <!-- Estado vacío -->
                    <div v-if="matrices.length === 0" class="text-center py-8 text-gray-500">
                        No hay clientes registrados para mostrar en el reporte.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';

export default {
    components: {
        Head,
    },
    props: {
        matrices: {
            type: Array,
            required: true,
        }
    },
    methods: {
        printReport() {
            // Abre el diálogo de impresión nativo del navegador
            window.print();
        }
    }
};
</script>

<style scoped>
/* Aseguramos que la tabla al imprimirse mantenga los colores y formato adecuados */
@media print {
    @page {
        margin: 1cm;
        size: landscape; /* Opcional: imprimir en horizontal porque son muchas columnas */
    }
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>