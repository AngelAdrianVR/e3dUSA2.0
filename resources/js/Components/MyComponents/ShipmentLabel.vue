<template>
    <div>
        <!-- Salto de página para todas las etiquetas a partir de la segunda -->
        <div v-if="labelData.boxNumber > 1" class="page-break"></div>
        
        <div class="label-container relative bg-white p-6 flex flex-col justify-between" 
             :style="{ backgroundImage: `url('${imgFondo}')`, backgroundSize: 'contain', backgroundPosition: 'center', backgroundRepeat: 'no-repeat', opacity: 0.9 }">
            
            <div class="flex gap-4 flex-grow items-center">
                <!-- Columna de la Tabla Principal (80%) -->
                <div class="w-4/5">
                    <table class="w-full border-collapse border-[3px] border-black text-sm lg:text-base bg-white/70">
                        <tbody>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold w-[35%] text-center">Empresa</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ sale.branch?.name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Teléfono</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ primaryPhone }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Contacto</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ sale.contact?.name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Guía</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ shipment.tracking_guide ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Factura</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ sale.invoice_id ?? 'Pendiente' }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Orden de Compra</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ sale.type === 'stock' ? 'OS-' : 'OV-' }}{{ sale.id.toString().padStart(4, '0') }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Orden de Producción</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ sale.type === 'stock' ? 'OS-' : 'OP-' }}{{ sale.id.toString().padStart(4, '0') }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Número de Cajas</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ labelData.boxNumber }} de {{ labelData.totalBoxes }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center">Cantidad de Piezas</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ labelData.totalPieces }}</td>
                            </tr>
                            <tr>
                                <td class="border-[3px] border-black p-3 font-bold text-center leading-tight">Num. parte<br>/Descripción</td>
                                <td class="border-[3px] border-black p-3 font-semibold text-center text-gray-800">{{ labelData.contentDescription }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Columna de Íconos y Logo (20%) -->
                <div class="w-1/5 flex flex-col items-center justify-between py-2 z-10 h-full">
                    <!-- Logo superior -->
                    <img :src="imgLogo" alt="Logo" class="w-24 object-contain mb-2">
                    
                    <!-- Lista de Íconos -->
                    <div class="flex flex-col items-center justify-between flex-grow w-full space-y-4 py-4">
                        <img :src="imgIcon1" alt="No apilar" class="w-20 object-contain">
                        <img :src="imgIcon2" alt="Mantener seco" class="w-20 object-contain">
                        <img :src="imgIcon3" alt="Frágil" class="w-20 object-contain">
                        <img :src="imgIcon4" alt="Hacia arriba" class="w-20 object-contain">
                        <img :src="imgIcon5" alt="Manejar con cuidado" class="w-20 object-contain">
                    </div>
                </div>
            </div>

            <!-- Footer descriptivo -->
            <div class="mt-4 mb-2 text-center text-[13px] leading-tight italic font-semibold text-gray-800 bg-white/60 px-4 py-2 rounded">
                Emblemas, llaveros, carcasas para llave, portaplacas, placas de estireno, tapetes de uso rudo, mantas de develación, parasoles, banderines, alfombras de entrada, organizadores de cajuela, tapones para válvulas, portadocumentos, gafetes magnéticos, kits de emergencia.
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ShipmentLabel',
    props: {
        sale: {
            type: Object,
            required: true
        },
        shipment: {
            type: Object,
            required: true
        },
        labelData: {
            type: Object,
            required: true // Incluye { boxNumber, totalBoxes, totalPieces, contentDescription }
        },
        primaryPhone: {
            type: String,
            default: 'N/A'
        }
    },
    data() {
        // Usamos el origen de la ventana actual para asegurarnos de que la nueva ventana
        // donde se imprima no pierda las rutas de las imágenes si se ejecuta en modo "about:blank"
        const baseUrl = window.location.origin;
        return {
            imgFondo: `${baseUrl}/images/logo.png`,
            imgLogo: `${baseUrl}/images/escudoSherman.png`,
            imgIcon1: `${baseUrl}/images/no_apilar.png`,
            imgIcon2: `${baseUrl}/images/paraguas.png`,
            imgIcon3: `${baseUrl}/images/fragil.png`,
            imgIcon4: `${baseUrl}/images/flechas.png`,
            imgIcon5: `${baseUrl}/images/manos2.png`,
        };
    }
}
</script>