<template>
  <!--
    Este componente visual muestra un indicador de estado circular.
    El color del círculo se determina dinámicamente según las props.
  -->
  <span class="h-2.5 w-2.5 rounded-full shrink-0" :class="statusColor" :title="statusTitle"></span>
</template>

<script>
export default {
  name: 'StatusIndicator',
  props: {
    // El objeto que contiene la información del estado (ej. un mantenimiento).
    item: {
      type: Object,
      required: true,
    },
    // El tipo de indicador, para diferenciar la lógica (ej. 'maintenance').
    type: {
      type: String,
      required: true,
    },
    // El array o objeto que mapea los tipos a sus nombres.
    types: {
      type: [Array, Object],
      required: true,
    },
  },
  computed: {
    /**
     * Calcula la clase de color de Tailwind CSS basada en el tipo de mantenimiento.
     * @returns {string} La clase de CSS para el color de fondo.
     */
    statusColor() {
      if (this.type === 'maintenance') {
        const typeId = this.item.maintenance_type;
        // Mapeo de ID de tipo a color:
        // 0: Preventivo -> Azul
        // 1: Correctivo -> Rojo
        // 2: Limpieza -> Verde
        const colorMap = {
          'Preventivo': 'bg-blue-500',
          'Correctivo': 'bg-red-500',
          'Limpieza': 'bg-green-500',
        };
        return colorMap[typeId] || 'bg-gray-400'; // Color por defecto
      }
      // Se pueden agregar más lógicas para otros 'types' aquí.
      return 'bg-gray-400';
    },

    /**
     * Determina el texto para el tooltip (atributo title) del indicador.
     * @returns {string} El nombre del tipo de mantenimiento.
     */
    statusTitle() {
        if (this.type === 'maintenance') {
            return this.item.maintenance_type || 'Desconocido';
        }
        return 'Estado desconocido';
    }
  }
}
</script>
