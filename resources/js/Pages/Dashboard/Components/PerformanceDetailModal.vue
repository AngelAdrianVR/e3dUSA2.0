<template>
  <el-dialog
    :model-value="true"
    @close="$emit('close')"
    :title="`Detalles de puntuación: ${user.name}`"
    width="85%"
    top="5vh"
  >
    <!-- Main Details Table -->
    <h3 class="font-bold text-lg mb-2 dark:text-white">Resumen Semanal</h3>
    <el-table :data="user.details" stripe class="w-full" max-height="250px">
      <!-- Common Date Column -->
      <el-table-column prop="date" label="Día" width="140" />

      <!-- Production Columns -->
      <template v-if="user.type === 'production'">
        <el-table-column prop="punctuality" label="Puntualidad" align="center" />
        <el-table-column prop="time" label="Tiempo (min)" align="center" />
        <el-table-column prop="scrap" label="Merma" align="center" />
        <el-table-column prop="day_finished" label="Día terminado" align="center">
            <template #default="scope">
                <span :class="scope.row.day_finished === -50 ? 'text-red-500' : 'text-green-500'">
                    {{ scope.row.day_finished === -50 ? -50 : '✓' }}
                </span>
            </template>
        </el-table-column>
        <el-table-column prop="extra_time" label="T. Extra" align="center" />
      </template>

      <!-- Sales Columns -->
      <template v-if="user.type === 'sales'">
        <el-table-column label="Monto Vendido (MXN aprox.)" align="center">
            <template #default="scope">
                {{ scope.row.amount.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }) }}
            </template>
        </el-table-column>
      </template>

      <!-- Design Columns -->
      <template v-if="user.type === 'design'">
        <el-table-column prop="orders" label="Órdenes Terminadas" align="center" />
      </template>
    </el-table>

    <!-- Collapse Section for Weekly Tasks -->
    <el-collapse v-if="user.weekly_tasks && user.weekly_tasks.length" v-model="activeCollapse" class="mt-6">
      <el-collapse-item :title="collapseTitle" name="1">
         <el-table :data="user.weekly_tasks" stripe max-height="300px">
            <!-- Production Task Columns -->
            <template v-if="user.type === 'production'">
                <el-table-column prop="sale_folio" label="Orden Venta" width="120" />
                <el-table-column prop="folio" label="Orden Prod." width="120" />
                <el-table-column prop="name" label="Tarea" />
                <el-table-column prop="started_at" label="Inicio" width="180" />
                <el-table-column prop="finished_at" label="Fin" width="180" />
                <el-table-column prop="duration_minutes" label="Duración (min)" width="120" align="center" />
            </template>
           
            <!-- Sales Task Columns -->
            <template v-if="user.type === 'sales'">
                <el-table-column prop="folio" label="Folio Venta" width="150" />
                <el-table-column prop="date" label="Fecha de Autorización" width="200" />
                <el-table-column prop="amount" label="Monto" align="right" />
            </template>

            <!-- Design Task Columns -->
            <template v-if="user.type === 'design'">
                <el-table-column prop="folio" label="Folio Diseño" width="150" />
                <el-table-column prop="title" label="Título de la Orden" />
                <el-table-column prop="date" label="Fecha de Finalización" width="200" />
            </template>
         </el-table>
      </el-collapse-item>
    </el-collapse>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="$emit('close')">Cerrar</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script>
import { ref, computed } from 'vue';
// Asumiendo que Element Plus y sus componentes están registrados globalmente.
// Si no es así, deberías importarlos aquí:
// import { ElDialog, ElTable, ElTableColumn, ElCollapse, ElCollapseItem, ElButton } from 'element-plus';

export default {
    name: 'PerformanceDetailModal',
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    emits: ['close'],
    setup(props) {
        const activeCollapse = ref('');

        const collapseTitle = computed(() => {
            if (!props.user.type) return 'Ver detalles';
            const titles = {
                production: 'Ver detalle de tareas de producción realizadas',
                sales: 'Ver detalle de ventas de la semana',
                design: 'Ver detalle de diseños de la semana',
            };
            return titles[props.user.type];
        });

        return {
            activeCollapse,
            collapseTitle,
        };
    }
}
</script>

<style>
/* Scoped styles for modal adjustments if needed */
.el-dialog__header {
    padding-right: 50px; /* More space for the close button */
}
.el-dialog__body {
    padding-top: 10px;
    padding-bottom: 20px;
}
</style>
