<template>
  <!--
    Este componente muestra un par de datos (etiqueta y valor).
    Es útil para mostrar detalles de un objeto de forma consistente.
  -->
  <div>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ label }}</p>
    <p class="font-medium text-gray-800 dark:text-gray-200" :class="valueClass">
      {{ value || 'N/A' }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'InfoItem',
  props: {
    // La etiqueta o título del dato a mostrar.
    label: {
      type: String,
      required: true,
    },
    // El valor del dato. Puede ser texto o número.
    value: {
      type: [String, Number],
      default: null,
    },
    // Clases de CSS adicionales para estilizar el valor (ej. cambiar color).
    valueClass: {
      type: String,
      default: '',
    },
  }
}
</script>
