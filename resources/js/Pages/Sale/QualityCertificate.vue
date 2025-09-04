<template>
<Head title="Certificado de calidad" />
  <div class="bg-gray-100 min-h-screen p-4 sm:p-8 font-sans">
    <!-- Botón de Imprimir Flotante que se oculta al imprimir -->
    <div class="print-hide text-center">
        <button @click="printCertificate" title="Imprimir Certificado"
            class="fixed bottom-7 right-7 bg-blue-600 text-white rounded-full size-16 shadow-lg hover:bg-blue-700 transition-all z-50 flex items-center justify-center transform hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <!-- Contenedor del Certificado con posición relativa para las decoraciones -->
    <div id="certificateContent" class="bg-white max-w-4xl mx-auto p-8 sm:p-12 shadow-2xl border-t-8 border-blue-600 border-b-8 rounded-lg relative overflow-hidden">
        <!-- Contenedor del contenido para estar sobre la marca de agua -->
        <div class="relative z-10">
            <!-- Encabezado del Certificado -->
            <header class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center pb-6 border-b-2 border-gray-200">
                <figure class="w-48 sm:w-60 mx-auto sm:mx-0">
                    <img class="mx-auto" src="@/../../public/images/logo.png" alt="Logo">
                </figure>
                <div class="sm:col-span-2 text-center sm:text-right">
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 tracking-wider">
                        Certificado de Calidad
                    </h1>
                    <p class="text-md text-gray-500 mt-1">Quality Assurance Certificate</p>
                </div>
            </header>

            <!-- Información de la Venta -->
            <section class="mt-6 text-sm text-gray-700">
                <div class="flex justify-between items-start">
                    <div>
                        <p><span class="font-bold">Cliente:</span> {{ sale.branch?.name ?? 'N/A' }}</p>
                        <p><span class="font-bold">Contacto:</span> {{ sale.contact?.name ?? 'N/A' }}</p>
                        <p><span class="font-bold">Orden de Compra:</span> {{ sale.oce_name ?? 'N/A' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">Folio: <span class="font-mono bg-gray-100 px-2 py-1 rounded">OV-{{ sale.id.toString().padStart(4, '0') }}</span></p>
                        <p class="font-bold mt-1">Fecha: <span class="font-normal">{{ formattedDate }}</span></p>
                    </div>
                </div>
            </section>

            <!-- Tabla de Productos -->
            <section class="mt-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">Productos Verificados</h2>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dimensiones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 text-sm">
                            <tr v-for="item in sale.sale_products" :key="item.id">
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.product?.name ?? 'N/A' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.quantity }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.product?.material ?? 'N/A' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">
                                  <span v-if="item.product?.diameter">
                                    {{ item.product?.width ?? '0' }} x {{ item.product?.diameter ?? '0' }} mm
                                  </span>
                                  <span v-else>
                                    {{ item.product?.large ?? '0' }} x {{ item.product?.height ?? '0' }} x {{ item.product?.diameter ?? '0' }} mm
                                  </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Sección de Instrucciones (Aplicación y Almacenamiento) -->
            <section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                <div>
                    <h3 class="font-bold text-gray-800 mb-2 text-sm">Instrucciones de Aplicación</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Limpie la superficie con alcohol y un paño que no deje pelusa.</li>
                        <li>La superficie debe estar completamente seca y libre de polvo o grasa.</li>
                        <li>No tocar el adhesivo, podría perder fuerza de adherencia.</li>
                        <li>Aplique cuidadosamente y presione sobre toda la pieza para fijar.</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 mb-2 text-sm">Indicaciones de Almacenamiento</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Apilar verticalmente los paquetes (máximo 4 cajas).</li>
                        <li>No colocar objetos pesados sobre las plantillas.</li>
                        <li>1 año de estabilidad en almacén en las siguientes condiciones:</li>
                        <li class="list-none ml-4">- Temp. 15-30 °C y 50% Humedad Relativa.</li>
                    </ul>
                </div>
            </section>

            <!-- Sección de Aprobación -->
            <section class="mt-6 text-center">
                <div class="inline-block mt-4">
                    <p class="mb-8 text-sm">Vo.Bo</p>
                    <span class="border-t-2 border-black pt-2 px-12 text-sm">
                        Ing. Gustavo Gómez
                    </span>
                    <p class="text-xs text-gray-500 mt-1">Encargado de Producción</p>
                </div>
            </section>

            <!-- Pie de Página -->
            <footer class="mt-8 pt-4 border-t-2 border-gray-200 text-xs text-gray-500 flex justify-between items-center">
                <p class="w-2/3">
                    Emblemas de alta calidad. Especialistas en ramo automotriz, electrodomésticos, y más.
                </p>
                <figure class="w-14 h-14">
                    <img src="/images/qrPagina.png" alt="QR a la página web">
                </figure>
                <figure>
                    <img class="mx-auto m-3 lg:m-0 w-full lg:w-full" src="../../../../public/images/escudoSherman.png" alt="">
                </figure>
            </footer>
        </div>
    </div>
  </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';

export default {
  components: {
    Head
  },
  props: {
    sale: {
      type: Object,
      required: true
    }
  },
  computed: {
    formattedDate() {
      if (!this.sale.created_at) return '';
      const date = new Date(this.sale.created_at);
      return date.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
  },
  methods: {
    printCertificate() {
      window.print();
    }
  }
}
</script>

<style>
#certificateContent::before,
#certificateContent::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  z-index: 0;
  opacity: 0.04; /* Opacidad muy baja para efecto de marca de agua */
  pointer-events: none; /* Asegura que no interfiera con el contenido */
}

/* Círculo decorativo azul */
#certificateContent::before {
  background-color: #3b82f6; /* Blue-500 */
  width: 500px;
  height: 500px;
  top: -200px;
  left: -200px;
}

/* Círculo decorativo rojo */
#certificateContent::after {
  background-color: #ef4444; /* Red-500 */
  width: 450px;
  height: 450px;
  bottom: -200px;
  right: -150px;
}


/* Estilos que solo se aplican al imprimir */
@media print {
  /* Oculta el botón y cualquier otro elemento que no deba imprimirse */
  .print-hide {
    display: none;
  }

  /* Asegura que el contenido del certificado use todo el espacio */
  body, .bg-gray-100 {
    background-color: white !important;
    margin: 0;
    padding: 0;
  }

  @page {
    size: A4;
    margin: 0;
  }

  #certificateContent {
    box-shadow: none;
    border: 2px solid #000;
    margin: 0;
    max-width: 100%;
    border-radius: 0;
  }
  
  /* Asegura que la marca de agua se imprima */
  #certificateContent::before,
  #certificateContent::after {
    opacity: 0.06;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>

