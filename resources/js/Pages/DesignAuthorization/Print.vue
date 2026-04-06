<template>
    <Head :title="'Formato de autorización de diseño FA-' + authorization.id" />

    <!-- Botón de Imprimir Flotante (Solo visible en pantalla) -->
    <button @click="print" title="Imprimir Formato"
        class="print:hidden fixed bottom-6 right-6 bg-[#005eb8] text-white rounded-full size-14 shadow-lg hover:bg-blue-800 transition-all z-50 flex items-center justify-center">
        <i class="fa-solid fa-print text-2xl"></i>
    </button>
  
    <!-- Contenedor Principal estilo Hoja Carta Horizontal -->
    <div class="max-w-[1100px] min-h-[650px] mx-auto my-8 bg-white text-black font-sans shadow-2xl p-6 print:shadow-none print:m-0 print:p-2 print:w-[100vw] print:h-[190mm] print:overflow-hidden flex flex-col box-border">
        
        <!-- Cabecera -->
        <header class="flex justify-between items-end mb-1 px-2 shrink-0">
            <!-- Logo y Título -->
            <div class="flex flex-col">
                <ApplicationLogo class="h-10 w-auto object-contain object-left" />
                <h1 class="text-[#005eb8] text-xl font-black uppercase tracking-wider">
                    Formato de Autorización
                </h1>
            </div>

            <!-- Versión y Checkboxes superiores -->
            <div class="flex flex-col items-end">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[#005eb8] text-lg">Versión</span>
                    <!-- Imprimiendo la variable de la versión de manera dinámica -->
                    <div class="min-w-[24px] px-1 h-6 border-[1.5px] border-[#e3000f] text-[#e3000f] flex items-center justify-center text-md font-bold">
                        {{ authorization.version || '1' }}
                    </div>
                    <!-- Cajas vacías -->
                    <div class="w-6 h-6 border-[1.5px] border-black rounded-sm"></div>
                    <div class="w-6 h-6 border-[1.5px] border-black rounded-sm"></div>
                    <div class="w-6 h-6 border-[1.5px] border-black rounded-sm"></div>
                    <div class="w-6 h-6 border-[1.5px] border-black rounded-sm"></div>
                    <div class="w-6 h-6 border-[1.5px] border-black rounded-sm"></div>
                </div>
                <p class="text-[#e3000f] text-xs">Marque el recuadro con una "X" si esta correcto</p>
            </div>
        </header>

        <!-- Cuerpo del Formato (Borde perimetral redondeado) -->
        <main class="border-[2px] border-gray-400 rounded-2xl flex flex-col flex-1 relative overflow-hidden">
            
            <!-- Zona Superior: Imagen (Izquierda) + Formularios (Derecha) -->
            <div class="flex flex-1 p-3 pb-1">
                
                <!-- Columna Izquierda: Imagen y Swatches -->
                <div class="w-[55%] pr-4 flex flex-col justify-between">
                    <!-- Contenedor de la Imagen principal -->
                    <div class="flex-grow flex items-center justify-center p-2 min-h-[220px]">
                        <div v-if="cover_image_url" class="w-full h-full flex justify-center items-center">
                            <!-- Altura máxima reducida para evitar desbordes -->
                            <img :src="cover_image_url" alt="Imagen del producto" class="max-w-full max-h-[300px] print:max-h-[270px] object-contain drop-shadow-xl">
                        </div>
                        <div v-else class="flex flex-col items-center justify-center text-gray-300">
                            <PhotoIcon class="h-16 w-16" />
                            <p class="mt-2 text-sm font-semibold">Sin visualización</p>
                        </div>
                    </div>

                    <!-- Muestras de Color Dinámicas (Swatches) -->
                    <div class="flex flex-wrap items-center gap-4 mt-1 mb-1 justify-center shrink-0">
                        
                        <!-- Swatch 1: Técnica de Impresión -->
                        <div v-if="authorization.production_methods?.length" class="flex items-center gap-3">
                            <div class="w-12 h-8 bg-[#4b5563] rounded-md shadow-sm border border-gray-500"></div>
                            <span class="font-bold text-[10px] uppercase text-gray-700 leading-tight max-w-[90px]">
                                {{ authorization.production_methods.join(', ') }}
                            </span>
                        </div>
                        
                        <!-- Swatch 2: Material / Color -->
                        <div v-if="authorization.material || authorization.color" class="flex items-center gap-3">
                            <div class="w-12 h-8 bg-[#d1d5db] rounded-md shadow-sm border border-gray-300"></div>
                            <span class="font-bold text-[10px] uppercase text-gray-700 leading-tight max-w-[100px]">
                                {{ [authorization.material, authorization.color].filter(Boolean).join(' ') }}
                            </span>
                        </div>

                        <!-- Swatches de Pantone Generados Dinámicamente -->
                        <template v-if="authorization.pantone && Array.isArray(authorization.pantone)">
                            <div v-for="(p, index) in authorization.pantone" :key="index" class="flex items-center gap-2">
                                <div class="w-12 h-8 rounded-md shadow-sm border border-gray-300" 
                                     :style="{ backgroundColor: p.color || '#ffffff' }"></div>
                                <span class="font-bold text-[10px] uppercase text-gray-700 leading-tight max-w-[100px]">
                                    PANTONE <br> {{ p.name }}
                                </span>
                            </div>
                        </template>

                        <!-- Por compatibilidad con datos guardados antes de esta actualización -->
                        <template v-else-if="authorization.pantone && typeof authorization.pantone === 'string'">
                             <div class="flex items-center gap-3">
                                <div class="w-12 h-8 rounded-md shadow-sm border border-gray-300" 
                                     :style="{ backgroundColor: authorization.pantone_color || '#ffffff' }"></div>
                                <span class="font-bold text-[10px] uppercase text-gray-700 leading-tight max-w-[100px]">
                                    PANTONE <br> {{ authorization.pantone }}
                                </span>
                            </div>
                        </template>

                        <!-- Imagen de Captura de Pantones Adjunta -->
                        <div v-if="pantone_capture_url" class="flex items-center gap-3 ml-2">
                            <img :src="procesarUrlImagen(pantone_capture_url)" alt="Captura Pantones" class="h-10 w-auto object-contain rounded-md shadow-sm border border-gray-300">
                            <!-- <span class="font-bold text-[10px] uppercase text-gray-700 leading-tight max-w-[100px]">
                                CAPTURA <br> ADJUNTA
                            </span> -->
                        </div>

                    </div>
                </div>

                <!-- Columna Derecha: Datos Técnicos y Comerciales -->
                <div class="w-[45%] pl-2 flex flex-col pt-1">
                    
                    <!-- NOMBRE DEL PRODUCTO -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1">
                            <div class="text-[#005eb8] text-[11px]">NOMBRE DEL PRODUCTO:</div>
                            <div class="border-b border-dashed border-gray-500 text-center text-[#e3000f] text-[11px] uppercase pb-0.5 mt-0.5">
                                {{ authorization.product_name }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3 mb-0.5"></div>
                    </div>

                    <!-- MATERIAL -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1">
                            <div class="text-[#005eb8] text-[11px]">MATERIAL:</div>
                            <div class="border-b border-dashed border-gray-500 text-center text-[#e3000f] text-[11px] uppercase pb-0.5 mt-0.5">
                                {{ authorization.material || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3 mb-0.5"></div>
                    </div>

                    <!-- MEDIDAS -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1">
                            <div class="text-[#005eb8] text-[11px]">MEDIDAS:</div>
                            <div class="border-b border-dashed border-gray-500 text-center text-[#e3000f] text-[11px] uppercase pb-0.5 mt-0.5">
                                {{ authorization.dimensions || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3 mb-0.5"></div>
                    </div>

                    <!-- TÉCNICA DE IMPRESIÓN -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1">
                            <div class="text-[#005eb8] text-[11px]">TÉCNICA DE IMPRESIÓN:</div>
                            <div class="border-b border-dashed border-gray-500 text-center text-[#e3000f] text-[11px] uppercase pb-0.5 mt-0.5">
                                {{ authorization.production_methods?.join(', ') || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3 mb-0.5"></div>
                    </div>

                    <!-- VENDEDOR (Sin Checkbox) -->
                    <div class="flex-1 mb-0.5 pr-[28px]">
                        <div class="text-[#005eb8] text-[11px]">VENDEDOR:</div>
                        <div class="border-b border-dashed border-gray-500 text-center text-[#e3000f] text-[11px] uppercase pb-0.5 mt-0.5">
                            {{ authorization.seller.name }}
                        </div>
                    </div>

                    <!-- FECHA DE AUTORIZACIÓN (Inline, Sin Checkbox) -->
                    <div class="flex-1 mb-1 pr-[28px] flex items-end">
                        <div class="text-[#005eb8] text-[11px] leading-tight mr-2">
                            FECHA DE <br> AUTORIZACIÓN:
                        </div>
                        <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] uppercase pb-0.5">
                            {{ authorization.authorized_at ? formatDate(authorization.authorized_at) : '---' }}
                        </div>
                    </div>

                    <!-- TIEMPO DE ENTREGA -->
                    <div class="flex items-end justify-between mb-0.5 mt-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[11px] uppercase mr-2">TIEMPO DE ENTREGA:</div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ authorization.delivery_time || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- VOLUMEN MÍNIMO -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[11px] uppercase mr-2">VOLUMEN MÍNIMO:</div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ authorization.minimum_volume ? authorization.minimum_volume + ' pzs' : '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- COSTO HERRAMENTAL DE IMPRESIÓN -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[10px] leading-tight mr-2 uppercase">
                                COSTO DEL HERRAMENTAL <br> DE IMPRESIÓN
                            </div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ formatCurrency(authorization.printing_tooling_cost) || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- COSTO HERRAMENTAL DE INYECCION -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[10px] leading-tight mr-2 uppercase">
                                COSTO DEL HERRAMENTAL <br> DE INYECCION DE PLASTICO
                            </div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ formatCurrency(authorization.injection_tooling_cost) || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- PRECIO POR UNIDAD -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[11px] uppercase mr-2">PRECIO POR UNIDAD:</div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ authorization.unit_price ? formatCurrency(authorization.unit_price) + ' + IVA' : '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- FLETE -->
                    <div class="flex items-end justify-between mb-0.5">
                        <div class="flex-1 flex items-end">
                            <div class="text-[#005eb8] text-[11px] uppercase mr-2">FLETE:</div>
                            <div class="border-b border-dashed border-gray-500 flex-1 text-center text-[#e3000f] text-[11px] pb-0.5">
                                {{ formatCurrency(authorization.freight_cost) || '---' }}
                            </div>
                        </div>
                        <div class="w-4 h-4 border-[1.5px] border-black rounded-sm ml-3"></div>
                    </div>

                    <!-- SECCIÓN DATOS DE CLIENTE -->
                    <div class="mt-1 pt-1 mb-0.5">
                        <div class="text-black text-[11px] font-semibold mb-1 ml-4 tracking-wide">DATOS DE CLIENTE:</div>
                        
                        <div class="flex items-end mb-0.5">
                            <div class="text-black text-[11px] w-[75px]">NOMBRE:</div>
                            <div class="border-b border-dashed border-black flex-1 text-center text-[11px] uppercase pb-0.5">
                                {{ authorization.contact?.name || '' }}
                            </div>
                        </div>
                        
                        <div class="flex items-end mb-0.5">
                            <div class="text-black text-[11px] w-[75px]">PUESTO:</div>
                            <div class="border-b border-dashed border-black flex-1 text-center text-[11px] uppercase pb-0.5">
                                {{ authorization.contact?.charge || '' }}
                            </div>
                        </div>

                        <div class="flex items-end mb-0.5">
                            <div class="text-black text-[11px] w-[75px]">EMPRESA:</div>
                            <div class="border-b border-dashed border-black flex-1 text-center text-[11px] uppercase pb-0.5">
                                {{ authorization.branch?.name || '' }}
                            </div>
                        </div>

                        <div class="flex items-end relative">
                            <div class="text-black text-[11px] w-[100px] leading-tight">FIRMA DE <br> AUTORIZACIÓN:</div>
                            <div class="border-b border-dashed border-black flex-1 text-center text-[11px] uppercase pb-0.5 min-h-[16px]"></div>
                            
                            <figure v-if="authorization.signature_media?.length" class="absolute right-0 bottom-1 w-28">
                                <img :src="procesarUrlImagen(authorization.signature_media[0]?.original_url)" alt="Firma" class="w-full h-auto">
                            </figure>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sección Inferior: Footer / Importante -->
            <div class="flex items-stretch mt-auto shrink-0">
                <div class="w-[25%] flex items-center justify-center p-2">
                    <span class="text-[#e3000f] text-[20px] font-normal tracking-wide">IMPORTANTE</span>
                </div>
                <!-- Caja de texto con borde gris superior e izquierdo -->
                <div class="w-[75%] border-t-[2px] border-l-[2px] border-gray-400 rounded-tl-3xl p-2 px-4 bg-white text-[9px] leading-tight flex flex-col gap-1">
                    <p class="text-black font-semibold">
                        Por favor leer y revisar cuidadosamente el diseño, colores y texto, ya que una vez que usted autoriza, cualquier error u omisión será responsabilidad de quien lo firma.
                    </p>
                    <p class="text-[#005eb8]">
                        *Los logotipos y marcas que se muestran en este formato son con fines exclusivamente ilustrativos, ya que los tonos de los grabados e impresión pueden variar dependiendo del producto o el lote.
                    </p>
                    <p class="text-[#005eb8]">
                        *Los colores mostrados en la pantalla pueden variar dependiendo del dispositivo donde se visualicen.
                    </p>
                </div>
            </div>

        </main>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';
import { PhotoIcon } from '@heroicons/vue/24/outline'; 

export default {
    components: {
        PhotoIcon,
        ApplicationLogo,
        Head
    },
    props: {
        authorization: Object,
        cover_image_url: String,
        pantone_capture_url: String,
        additional_files: Array,
    },
    methods: {
        // Formatea la fecha a DD/MM/YYYY
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        },
        // Formatea moneda (Ej. $1,900)
        formatCurrency(value) {
            if (value === null || value === undefined || value === '') return '';
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(value);
        },
        // Método para procesar la URL de la imagen (de tu código original)
        procesarUrlImagen(originalUrl) {
            if (!originalUrl) return '';
            return originalUrl.replace('https://intranetemblems3d.dtw.com.mx', 'https://clientes-emblems3d.dtw.com.mx');
        },
        // Ejecutar impresión del navegador
        print() {
          window.print();
        },
    },
};
</script>

<style>
/* Forzar impresión horizontal, ajustar márgenes y colores exactos (Rojos/Azules) */
@media print {
    @page {
        size: letter landscape;
        margin: 5mm; /* Pequeño margen para asegurar que no se desborde */
    }
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        margin: 0;
        padding: 0;
    }
}
</style>