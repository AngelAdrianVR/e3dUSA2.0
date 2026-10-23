<template>
    <div>
        <!-- Salto de página: se imprimen 2 etiquetas por hoja; la tercera (y las impares siguientes) inicia hoja -->
        <div class="label-container relative bg-white p-5 flex flex-col justify-between"
             :class="{ 'label-page-break': startsNewPage }"
             :style="{ backgroundImage: `url('${imgFondo}')`, backgroundSize: 'contain', backgroundPosition: 'center', backgroundRepeat: 'no-repeat', opacity: 0.9 }">
            
            <div class="flex gap-3 flex-grow items-center">
                <!-- Columna de la Tabla Principal (80%) -->
                <div class="w-4/5">
                    <table class="w-full border-collapse border-2 border-black text-xs leading-tight bg-white/70">
                        <tbody>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold w-[35%] text-center">Empresa</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ sale.branch?.name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Teléfono</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ primaryPhone }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Contacto</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ sale.contact?.name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Guía</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ shipment.tracking_guide ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Factura</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ sale.invoice_id ?? 'Pendiente' }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Orden de Compra</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ sale.type === 'stock' ? 'OS-' : (sale.type === 'muestra' ? 'OM-' : 'OV-') }}{{ sale.id.toString().padStart(4, '0') }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Orden de Producción</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ sale.type === 'stock' ? 'OS-' : 'OP-' }}{{ sale.id.toString().padStart(4, '0') }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Número de Cajas</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ labelData.boxNumber }} de {{ labelData.totalBoxes }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center">Cantidad de Piezas</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800">{{ labelData.totalPieces }}</td>
                            </tr>
                            <tr>
                                <td class="border-2 border-black px-2 py-2 font-bold text-center leading-tight">Num. parte<br>/Descripción</td>
                                <td class="border-2 border-black px-2 py-2 font-semibold text-center text-gray-800 break-words">{{ labelData.contentDescription }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Columna de Íconos y Logo (20%) -->
                <div class="w-1/5 flex flex-col items-center justify-between py-1 z-10 h-full">
                    <!-- Logo superior -->
                    <img :src="imgLogo" alt="Logo" class="w-16 object-contain mb-1">
                    
                    <!-- Lista de Íconos -->
                    <div class="flex flex-col items-center justify-between flex-grow w-full space-y-1 py-1">
                        <img :src="imgIcon1" alt="No apilar" class="w-11 object-contain">
                        <img :src="imgIcon2" alt="Mantener seco" class="w-11 object-contain">
                        <img :src="imgIcon3" alt="Frágil" class="w-11 object-contain">
                        <img :src="imgIcon4" alt="Hacia arriba" class="w-11 object-contain">
                        <img :src="imgIcon5" alt="Manejar con cuidado" class="w-11 object-contain">
                    </div>
                </div>
            </div>

            <!-- Footer descriptivo -->
            <div class="mt-1 mb-0.5 text-center text-[8px] leading-tight italic font-semibold text-gray-800 bg-white/60 px-2 py-1 rounded">
                Emblemas, llaveros, carcasas para llave, portaplacas, placas de estireno, tapetes de uso rudo, mantas de develación, parasoles, banderines, alfombras de entrada, organizadores de cajuela, tapones para válvulas, portadocumentos, gafetes magnéticos, kits de emergencia.
            </div>
        </div>
    </div>
</template>

<script>
// Etiquetas por hoja (hoja carta vertical dividida en dos mitades)
const LABELS_PER_PAGE = 2;

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
            required: true // Incluye { labelIndex, boxNumber, totalBoxes, totalPieces, contentDescription }
        },
        primaryPhone: {
            type: String,
            default: 'N/A'
        }
    },
    computed: {
        startsNewPage() {
            const labelIndex = this.labelData.labelIndex;

            // Si no se recibe la posición de la etiqueta, se conserva el comportamiento anterior.
            if (typeof labelIndex !== 'number') {
                return this.labelData.boxNumber > 1;
            }

            return labelIndex > 0 && labelIndex % LABELS_PER_PAGE === 0;
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