<template>
<Head title="Certificado de calidad" />
  <div class="bg-gray-100 min-h-screen p-4 sm:p-8 font-sans">
    <!-- Botón de Imprimir Flotante que se oculta al imprimir -->
    <div class="print-hide text-center">
        <!-- Botón de Idioma (ES/EN) -->
        <button @click="toggleLang" :title="lang === 'es' ? 'English' : 'Español'"
            class="fixed bottom-28 right-7 bg-gray-700 text-white rounded-full size-16 shadow-lg hover:bg-gray-800 transition-all z-50 flex items-center justify-center transform hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.065 5.365 5.106 5.499 4.332 8.027zM5.68 5.063a7.962 7.962 0 012.332-1.397 5.956 5.956 0 00-1.392 3.05c.392-.334.834-.598 1.297-.799A7.968 7.968 0 0010 3.46c.666 0 1.302.098 1.906.279a7.04 7.04 0 00-.676 1.637c-.348-.075-.708-.124-1.08-.124-1.22 0-2.346.392-3.264 1.047A7.968 7.968 0 005.68 5.063zm6.28 13.467a8.01 8.01 0 003.708-10.91c-.092.114-.184.227-.279.338-.696.818-1.642 1.323-2.7 1.568a7.971 7.971 0 01-1.55 3.063c.279.212.609.35.977.413a7.06 7.06 0 00-.156 5.528zM5.98 9.305a7.98 7.98 0 002.02 3.304c-.054 1.89-.743 3.423-1.868 4.504a8.02 8.02 0 01-4.02-5.798c.932-.235 1.84-.677 2.618-1.322.598-.498 1.112-1.095 1.528-1.755a6.18 6.18 0 00-.278.067zm7.673 3.656a7.07 7.07 0 002.457-2.096 7.94 7.94 0 01-2.046 6.012c-.281-.977-.255-2.012-.412-3.916z" clip-rule="evenodd" />
            </svg>
        </button>
        <!-- Botón de Imprimir -->
        <button @click="printCertificate" :title="t.printTitle"
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
                        {{ t.certificateTitle }}
                    </h1>
                    <p class="text-md text-gray-500 mt-1">{{ t.certificateSubtitle }}</p>
                </div>
            </header>

            <!-- Información de la Venta -->
            <section class="mt-6 text-sm text-gray-700">
                <div class="flex justify-between items-start">
                    <div>
                        <p><span class="font-bold">{{ t.customer }}</span> {{ sale.branch?.name ?? 'N/A' }}</p>
                        <p><span class="font-bold">{{ t.contact }}</span> {{ sale.contact?.name ?? 'N/A' }}</p>
                        <p><span class="font-bold">{{ t.purchaseOrder }}</span> {{ sale.oce_name ?? 'N/A' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">{{ t.folio }}: <span class="font-mono bg-gray-100 px-2 py-1 rounded">OV-{{ sale.id.toString().padStart(4, '0') }}</span></p>
                        <p class="font-bold mt-1">{{ t.date }}: <span class="font-normal">{{ formattedDate }}</span></p>
                    </div>
                </div>
            </section>

            <!-- Tabla de Productos -->
            <section class="mt-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">{{ t.productsTitle }}</h2>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t.product }}</th>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t.quantity }}</th>
                                <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t.material }}</th>
                                <!-- <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dimensiones</th> -->
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 text-sm">
                            <tr v-for="item in sale.sale_products" :key="item.id">
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.product?.name ?? 'N/A' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.quantity }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ item.product?.material ?? 'N/A' }}</td>
                                <!-- <td class="px-6 py-3 whitespace-nowrap">
                                  <span v-if="item.product?.diameter">
                                    {{ item.product?.width ?? '0' }} x {{ item.product?.diameter ?? '0' }} mm
                                  </span>
                                  <span v-else>
                                    {{ item.product?.large ?? '0' }} x {{ item.product?.height ?? '0' }} x {{ item.product?.diameter ?? '0' }} mm
                                  </span>
                                </td> -->
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Sección de Instrucciones (Aplicación y Almacenamiento) -->
            <section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                <div>
                    <h3 class="font-bold text-gray-800 mb-2 text-sm">{{ t.appTitle }}</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>{{ t.app1 }}</li>
                        <li>{{ t.app2 }}</li>
                        <li>{{ t.app3 }}</li>
                        <li>{{ t.app4 }}</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 mb-2 text-sm">{{ t.storageTitle }}</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>{{ t.storage1 }}</li>
                        <li>{{ t.storage2 }}</li>
                        <li>{{ t.storage3 }}</li>
                        <li class="list-none ml-4">- {{ t.storage4 }}</li>
                    </ul>
                </div>
            </section>

            <!-- Sección de Aprobación -->
            <section class="mt-6 text-center">
                <div class="inline-block mt-4">
                    <p class="mb-1 text-sm">{{ t.approved }}</p>
                    <img src="/images/firma_gustavo.png" alt="Firma" class="mx-auto h-10 object-contain mb-1">
                    <span class="border-t-2 border-black pt-2 px-12 text-sm">
                        Ing. Gustavo Gómez
                    </span>
                    <p class="text-xs text-gray-500 mt-1">{{ t.role }}</p>
                </div>
            </section>

            <!-- Pie de Página -->
            <footer class="mt-8 pt-4 border-t-2 border-gray-200 text-xs text-gray-500 flex justify-between items-center">
                <p class="w-2/3">
                    {{ t.footer }}
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
  data() {
    return {
      lang: 'es',
      translations: {
        es: {
          printTitle: 'Imprimir Certificado',
          certificateTitle: 'Certificado de Calidad',
          certificateSubtitle: 'Quality Assurance Certificate',
          customer: 'Cliente:',
          contact: 'Contacto:',
          purchaseOrder: 'Orden de Compra:',
          folio: 'Folio',
          date: 'Fecha',
          productsTitle: 'Productos Verificados',
          product: 'Producto',
          quantity: 'Cantidad',
          material: 'Material',
          appTitle: 'Instrucciones de Aplicación',
          app1: 'Limpie la superficie con alcohol y un paño que no deje pelusa.',
          app2: 'La superficie debe estar completamente seca y libre de polvo o grasa.',
          app3: 'No tocar el adhesivo, podría perder fuerza de adherencia.',
          app4: 'Aplique cuidadosamente y presione sobre toda la pieza para fijar.',
          storageTitle: 'Indicaciones de Almacenamiento',
          storage1: 'Apilar verticalmente los paquetes (máximo 4 cajas).',
          storage2: 'No colocar objetos pesados sobre las plantillas.',
          storage3: '1 año de estabilidad en almacén en las siguientes condiciones:',
          storage4: 'Temp. 15-30 °C y 50% Humedad Relativa.',
          approved: 'Vo.Bo',
          role: 'Encargado de Producción',
          footer: 'Emblemas de alta calidad. Especialistas en ramo automotriz, electrodomésticos, y más.'
        },
        en: {
          printTitle: 'Print Certificate',
          certificateTitle: 'Quality Certificate',
          certificateSubtitle: 'Certificado de Calidad',
          customer: 'Customer:',
          contact: 'Contact:',
          purchaseOrder: 'Purchase Order:',
          folio: 'Folio',
          date: 'Date',
          productsTitle: 'Verified Products',
          product: 'Product',
          quantity: 'Quantity',
          material: 'Material',
          appTitle: 'Application Instructions',
          app1: 'Clean the surface with alcohol and a lint-free cloth.',
          app2: 'The surface must be completely dry and free of dust or grease.',
          app3: 'Do not touch the adhesive, it may lose adhesion strength.',
          app4: 'Apply carefully and press over the entire piece to secure it.',
          storageTitle: 'Storage Instructions',
          storage1: 'Stack packages vertically (maximum 4 boxes).',
          storage2: 'Do not place heavy objects on the templates.',
          storage3: '1 year of stability in storage under the following conditions:',
          storage4: 'Temp. 15-30 °C and 50% Relative Humidity.',
          approved: 'Approved',
          role: 'Production Manager',
          footer: 'High quality emblems. Specialists in automotive, appliances, and more.'
        }
      }
    };
  },
  computed: {
    t() {
      return this.translations[this.lang];
    },
    formattedDate() {
      if (!this.sale.created_at) return '';
      const date = new Date(this.sale.created_at);
      return date.toLocaleDateString(this.lang === 'es' ? 'es-MX' : 'en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
  },
  methods: {
    toggleLang() {
      this.lang = this.lang === 'es' ? 'en' : 'es';
    },
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

  html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
  }

  body, .bg-gray-100 {
    background-color: white !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .min-h-screen {
    min-height: auto !important;
    height: 100% !important;
    padding: 0 !important;
  }

  @page {
    size: letter;
    margin: 10mm;
  }

  /* Contenedor del certificado: ocupa toda la página */
  #certificateContent {
    position: fixed !important;
    top: 5mm !important;
    left: 5mm !important;
    right: 5mm !important;
    bottom: 5mm !important;
    box-shadow: none !important;
    border: none !important;
    border-top: none !important;
    border-bottom: none !important;
    border-radius: 0 !important;
    margin: 0 !important;
    max-width: none !important;
    width: auto !important;
    padding: 1rem 1.5rem !important;
    overflow: hidden !important;
  }

  /* Contenedor interno usa flex para empujar el footer al fondo */
  #certificateContent > .relative.z-10 {
    display: flex !important;
    flex-direction: column !important;
    height: 100% !important;
  }

  /* El footer se pega al final de la página */
  #certificateContent > .relative.z-10 > footer {
    margin-top: auto !important;
    padding-top: 0.5rem !important;
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

