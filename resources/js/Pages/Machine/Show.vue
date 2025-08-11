<template>
  <AppLayout title="Detalles de Maquinaria">
    <div class="p-4 sm:p-6 lg:p-8 dark:text-white font-sans">
      <!-- Encabezado y Acciones -->
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Detalles de Maquinaria</h1>
          <p class="text-sm text-gray-500 dark:text-gray-300 mt-1">Visualiza y gestiona la información de tus máquinas.</p>
        </div>
        <Link :href="route('machines.index')"
          class="mt-4 sm:mt-0 flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-slate-700 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
          <i class="fa-solid fa-xmark"></i>
        </Link>
      </header>

      <!-- Selector de Máquina y Botones de Acción -->
      <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
        <div class="w-full md:w-1/3">
          <el-select v-model="selectedMachineId" clearable filterable placeholder="Buscar una máquina…"
            class="w-full"
            no-data-text="No hay maquinaria registrada" no-match-text="No se encontraron coincidencias">
            <el-option v-for="item in machines" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </div>
        <div v-if="machine" class="flex items-center space-x-2">
          <el-tooltip v-if="canEdit" content="Editar Máquina" placement="top">
            <Link :href="route('machines.edit', selectedMachineId)">
              <button class="action-button">
                <i class="fa-solid fa-pencil"></i>
              </button>
            </Link>
          </el-tooltip>
          <Dropdown v-if="canDoMoreActions" align="right" width="48">
            <template #trigger>
              <button class="action-button-primary">
                Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
              </button>
            </template>
            <template #content>
              <DropdownLink v-if="canCreate" :href="route('machines.create')">Agregar nueva máquina</DropdownLink>
              <DropdownLink v-if="canCreateMaintenance" :href="route('maintenances.create', selectedMachineId)">Registrar mantenimiento</DropdownLink>
              <DropdownLink v-if="canCreateSparePart" :href="route('spare-parts.create', selectedMachineId)">Registrar refacción</DropdownLink>
              <DropdownLink v-if="canCreate" @click="uploadFilesModal = true" as="button">Subir archivos</DropdownLink>
              <div class="border-t border-gray-200 dark:border-slate-700" />
              <DropdownLink v-if="canDelete" @click="showConfirmModal = true" as="button" class="text-red-600 dark:text-red-500 hover:!bg-red-50 dark:hover:!bg-red-500/10">
                Eliminar Máquina
              </DropdownLink>
            </template>
          </Dropdown>
        </div>
      </div>

      <!-- Contenido Principal -->
      <div v-if="machine" class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        
        <!-- Columna Izquierda: Carrusel de Imágenes -->
        <div class="lg:col-span-2">
          <h2 class="text-xl font-bold text-center mb-4">{{ machine.name }}</h2>
          <div class="relative group">
            <!-- Carrusel -->
            <div class="relative w-full h-72 rounded-xl bg-slate-200 dark:bg-slate-800 flex items-center justify-center overflow-hidden shadow-lg">
              <figure v-if="hasImages">
                <img :src="currentImageUrl" :key="currentImageUrl" class="object-contain h-full w-full transition-opacity duration-500" alt="Imagen de la máquina">
              </figure>
              <div v-else class="text-center text-gray-500">
                <i class="fa-solid fa-image text-6xl"></i>
                <p class="mt-2 text-sm">Sin imágenes</p>
              </div>
               <!-- Overlay para ampliar imagen -->
              <div v-if="hasImages" @click="openImage(currentImageUrl)" class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center cursor-pointer transition-opacity duration-300">
                <i class="fa-solid fa-magnifying-glass-plus text-white text-4xl"></i>
              </div>
            </div>
            <!-- Controles de Navegación del Carrusel -->
            <template v-if="hasMultipleImages">
              <button @click="prevImage" class="carousel-nav-button left-0 -translate-x-1/2">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button @click="nextImage" class="carousel-nav-button right-0 translate-x-1/2">
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </template>
          </div>
           <!-- Indicadores de Puntos del Carrusel -->
          <div v-if="hasMultipleImages" class="flex justify-center space-x-2 mt-4">
            <button v-for="(image, index) in machine.media" :key="image.id" @click="currentImageIndex = index"
              :class="['h-2 w-2 rounded-full transition-colors', currentImageIndex === index ? 'bg-primary' : 'bg-gray-300 dark:bg-slate-600 hover:bg-primary/70']">
            </button>
          </div>
        </div>

        <!-- Columna Derecha: Panel de Información -->
        <div class="lg:col-span-3 bg-white dark:bg-slate-800/50 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
          <!-- Pestañas -->
          <div class="border-b border-gray-200 dark:border-slate-700 px-4">
            <nav class="-mb-px flex space-x-6">
              <button @click="activeTab = 'info'" :class="getTabClass('info')">Información General</button>
              <button @click="activeTab = 'maintenance'" :class="getTabClass('maintenance')">Mantenimiento</button>
              <button @click="activeTab = 'spares'" :class="getTabClass('spares')">Refacciones</button>
            </nav>
          </div>

          <!-- Contenido de las Pestañas -->
          <div class="p-6 text-sm min-h-[400px]">
            <!-- Tab 1: Información General -->
            <div v-if="activeTab === 'info'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <InfoItem label="Fecha de adquisición" :value="machine.aquisition_date" />
                    <InfoItem label="Número de serie" :value="machine.serial_number" />
                    <InfoItem label="Proveedor" :value="machine.supplier" />
                    <InfoItem label="Costo" :value="`$${machine.cost}`" value-class="text-green-600 font-semibold" />
                    <InfoItem label="Peso" :value="`${machine.weight} kg`" />
                    <InfoItem label="Ancho" :value="`${machine.width} cm`" />
                    <InfoItem label="Largo" :value="`${machine.large} cm`" />
                    <InfoItem label="Alto" :value="`${machine.height} cm`" />
                </div>
                <h3 class="text-base font-semibold mt-8 mb-3 border-t border-gray-200 dark:border-slate-700 pt-6">Archivos Adjuntos</h3>
                <div v-if="machine.media?.filter(m => m.collection_name == 'files')?.length" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    <FileView v-for="file in machine.media?.filter(m => m.collection_name == 'files')" 
                      :key="file" :file="file" :deletable="false" />
                </div>
                <div v-else>
                  <Empty />
                  <p class="text-center text-base">Registra <button @click="uploadFilesModal = true" class="hover:underline text-secondary dark:text-blue-300">aquí</button></p>
                </div>
            </div>

            <!-- Tab 2: Mantenimiento -->
            <div v-if="activeTab === 'maintenance'" class="overflow-x-auto">
                <table v-if="machine.maintenances?.length" class="w-full text-left">
                    <thead>
                        <tr class="border-b dark:border-slate-700">
                            <th class="py-2 pr-4">Tipo</th>
                            <th class="py-2 px-4">Fecha</th>
                            <th class="py-2 px-4">Costo</th>
                            <th class="py-2 px-4">Responsable</th>
                            <th class="py-2 pl-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(maintenance, index) in machine.maintenances" :key="maintenance.id" @click="openMaintenanceModal(maintenance, index)"
                            class="border-b dark:border-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/50 cursor-pointer">
                            <td class="py-3 pr-4">
                                <div class="flex items-center space-x-2">
                                  <el-tooltip v-if="maintenance.validated_at" placement="top">
                                    <template #content>
                                      <div>
                                        <p class="text-sm">Validado el: <strong class="ml-2">{{ maintenance.validated_at }}</strong></p>
                                        <p class="text-sm">Validado por: <strong class="ml-2">{{ maintenance.validated_by }}</strong></p>
                                      </div>
                                    </template>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mx-2 text-green-500">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                  </el-tooltip>
                                  <el-tooltip v-else content="Esperando validación" placement="top">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-orange-400 mx-2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                  </el-tooltip>
                                    <StatusIndicator :item="maintenance" type="maintenance" :types="maintenanceTypes" />
                                    <span>{{ maintenance.maintenance_type }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ formatDate(maintenance.maintenance_date) }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">${{ maintenance.cost}}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ maintenance.responsible }}</td>
                            <td class="py-3 pl-4 text-right">
                                <el-popconfirm title="¿Eliminar este registro?" @confirm.stop="deleteRow(maintenance.id, 'maintenance')">
                                    <template #reference>
                                        <button @click.stop class="delete-icon-button">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </template>
                                </el-popconfirm>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else>
                  <Empty />
                  <p class="text-center text-base">Registra <button @click="$inertia.visit(route('maintenances.create', selectedMachineId))" class="hover:underline text-secondary dark:text-blue-300">aquí</button></p>
                </div>
            </div>

            <!-- Tab 3: Refacciones -->
            <div v-if="activeTab === 'spares'" class="overflow-x-auto">
                <table v-if="machine.spare_parts?.length" class="w-full text-left">
                    <thead>
                        <tr class="border-b dark:border-slate-700">
                            <th class="py-2 pr-4">Refacción</th>
                            <th class="py-2 px-4">Cantidad</th>
                            <th class="py-2 px-4">Costo Unit.</th>
                            <th class="py-2 px-4">Ubicación</th>
                            <th class="py-2 pl-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(spare, index) in machine.spare_parts" :key="spare.id" @click="openSparePartModal(spare, index)"
                            class="border-b dark:border-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/50 cursor-pointer">
                            <td class="py-3 pr-4 font-medium">{{ spare.name }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ spare.quantity }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">${{ spare.cost }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ spare.location }}</td>
                            <td class="py-3 pl-4 text-right">
                                <el-popconfirm title="¿Eliminar este registro?" @confirm.stop="deleteRow(spare.id, 'spare-part')">
                                    <template #reference>
                                        <button @click.stop class="delete-icon-button">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </template>
                                </el-popconfirm>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else>
                  <Empty />
                  <p class="text-center text-base">Registra una <button @click="$inertia.visit(route('spare-parts.create', selectedMachineId))" class="hover:underline text-secondary dark:text-blue-300">aquí</button></p>
                </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Placeholder si no hay máquina seleccionada -->
      <div v-else class="text-center py-20 bg-white dark:bg-slate-800/50 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700">
          <i class="fa-solid fa-robot text-5xl text-gray-400"></i>
          <h3 class="mt-4 text-xl font-semibold">Selecciona una máquina</h3>
          <p class="text-gray-500">Usa el buscador para ver los detalles de una máquina.</p>
      </div>
    </div>

    <!-- Modales -->
    <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
      <template #title>Eliminar Máquina</template>
      <template #content>¿Estás seguro de que deseas eliminar '{{ machine?.name }}'? Esta acción no se puede deshacer.</template>
      <template #footer>
        <CancelButton @click="showConfirmModal = false" class="mr-2">Cancelar</CancelButton>
        <PrimaryButton @click="deleteMachine" :disabled="form.processing">Eliminar</PrimaryButton>
      </template>
    </ConfirmationModal>

    <!-- Implementar modales para Mantenimiento y Refacciones aquí si se desea -->
    <!-- Modal Mantenimientos -->
    <DialogModal :show="maintenanceModal" @close="maintenanceModal = false">
      <template #title>
        <h1 class="font-bold flex items-center justify-between mt-5">
          <span>Registro de mantenimiento</span>
          <Link v-if="!selectedMaintenance.validated_at" :href="route('maintenances.edit', selectedMaintenance)">
            <button class="action-button">
              <i class="fa-solid fa-pencil"></i>
            </button>
          </Link>
        </h1>
      </template>
      <template #content>
        <section class="mt-3">
          <div class="grid grid-cols-3 gap-2">
            <p class="text-[#373737] dark:text-gray-400">Máquina:</p>
            <p class=" col-span-2 dark:text-white">{{ machine.name }}</p>
            <p class="text-[#373737] dark:text-gray-400">No. Mantenimiento:</p>
            <p class=" col-span-2 dark:text-white">{{ maintenanceIndex }}</p>
            <p class="text-[#373737] dark:text-gray-400">Tipo de mantenimiento:</p>
            <p class="col-span-2 dark:text-white"> {{ selectedMaintenance.maintenance_type }}</p>
            <p class="text-[#373737] dark:text-gray-400">Fecha:</p>
            <p class=" col-span-2 dark:text-white"> {{ formatDate(selectedMaintenance.maintenance_date) }}</p>
            <p class="text-[#373737] dark:text-gray-400">Costo:</p>
            <p class=" col-span-2 dark:text-white"> ${{ selectedMaintenance.cost }}</p>
            <p class="text-[#373737] dark:text-gray-400">Realizado por:</p>
            <p class=" col-span-2 dark:text-white">{{ selectedMaintenance.responsible }}</p>
            <p class="text-[#373737] dark:text-gray-400">Descripción de acciones:</p>
            <p class=" col-span-2 dark:text-white">{{ selectedMaintenance.actions }}</p>
            <p class="text-[#373737] dark:text-gray-400">Validado por:</p>
            <div v-if="selectedMaintenance.validated_by" class=" col-span-2">
              <div v-if="!selectedMaintenance.validated_by" class="flex items-center space-x-2">
                <el-tooltip content="Esperando validación" placement="top">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                    stroke="currentColor" class="size-4 text-[#BE6E04]">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                  </svg>
                </el-tooltip>
                <span>-</span>
              </div>
              <div v-else class="flex items-center space-x-2">
                <el-tooltip :content="`Validado el ${selectedMaintenance?.validated_at}`" placement="top">
                  <i class="fa-solid fa-check text-[#0FA430]"></i>
                </el-tooltip>
                <span>{{ selectedMaintenance.validated_by }}</span>
              </div>
            </div>
            <p v-else>Sin validar aún</p>
            <p class="text-[#373737] dark:text-white col-span-full mt-2">Evidencia:</p>
            <template v-if="selectedMaintenance.media?.length">
              <div 
                v-for="(media, index) in selectedMaintenance.media" 
                :key="index"
                class="relative group rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
              >
                <a :href="media.original_url" target="_blank" rel="noopener noreferrer">
                  <img 
                    :src="media.original_url" 
                    alt="Imagen de mantenimiento"
                    class="w-full h-48 object-cover"
                  />
                  <!-- Overlay con efecto hover -->
                  <div 
                    class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 flex items-center justify-center transition-all duration-300"
                  >
                    <span class="text-white text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity">
                      Ver imagen
                    </span>
                  </div>
                </a>
              </div>
            </template>
            <p v-else class="text-center dark:text-gray-300 italic"> No hay archivos de evidencia</p>
          </div>
        </section>
      </template>

      <template #footer>
        <div>
          <SecondaryButton
            v-if="this.$page.props.auth.user.permissions.includes('Validar mantenimiento de maquinas') && !selectedMaintenance?.validated_at || true"
            @click="validateMaintenance" :loading="form.processing">
            Validar mantenimiento
          </SecondaryButton>
          <div v-else-if="selectedMaintenance?.validated_at" 
            class="flex items-center text-green-800 bg-green-200 dark:bg-green-700 dark:text-green-100 py-1 px-3 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Validado
          </div>
        </div>
      </template>
    </DialogModal>

    <!-- Refacciones -->
      <DialogModal :show="sparePartModal" @close="sparePartModal = false">
        <template #title>
          <h1 class="font-bold flex items-center justify-between mt-7">
            <span>Registro de refacción</span>
            <Link :href="route('spare-parts.edit', selectedSparePart)">
              <button class="action-button">
                <i class="fa-solid fa-pencil"></i>
              </button>
          </Link>
          </h1>
        </template>
        <template #content>
          <section class="mt-3">
            <div class="grid grid-cols-3 gap-2">
              <p class="text-[#373737] dark:text-gray-500">Máquina:</p>
              <p class="col-span-2 dark:text-white">{{ machine.name }}</p>
              <p class="text-[#373737] dark:text-gray-500">Refacción:</p>
              <p class="col-span-2 dark:text-white"> {{ selectedSparePart.name }}</p>
              <p class="text-[#373737] dark:text-gray-500">Adquirida el:</p>
              <p class="col-span-2 dark:text-white"> {{ formatDate(selectedSparePart.created_at) }}</p>
              <p class="text-[#373737] dark:text-gray-500">Costo unitario $MXN:</p>
              <p class="col-span-2 dark:text-white"> ${{ selectedSparePart.cost?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}</p>
              <p class="text-[#373737] dark:text-gray-500">Cantidad:</p>
              <p class="col-span-2 dark:text-white">{{ selectedSparePart.quantity }}</p>
              <p class="text-[#373737] dark:text-gray-500">Proveedor:</p>
              <p class="col-span-2 dark:text-white">{{ selectedSparePart.supplier }}</p>
              <p class="text-[#373737] dark:text-gray-500">Ubicación:</p>
              <p class="col-span-2 dark:text-white">{{ selectedSparePart.location }}</p>
              <p class="text-[#373737] dark:text-gray-500">Descripción:</p>
              <p class="col-span-2 dark:text-white">{{ selectedSparePart.description }}</p>
              <p class="text-[#373737] dark:text-white col-span-full mt-2">Evidencia:</p>
              <template v-if="selectedSparePart.media?.length">
                <div 
                  v-for="(media, index) in selectedSparePart.media" 
                  :key="index"
                  class="relative group rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                >
                  <a :href="media.original_url" target="_blank" rel="noopener noreferrer">
                    <img 
                      :src="media.original_url" 
                      alt="Imagen de mantenimiento"
                      class="w-full h-48 object-cover"
                    />
                    <!-- Overlay con efecto hover -->
                    <div 
                      class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 flex items-center justify-center transition-all duration-300"
                    >
                      <span class="text-white text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity">
                        Ver imagen
                      </span>
                    </div>
                  </a>
                </div>
              </template>
              <p v-else class="text-center dark:text-gray-300 italic"> No hay archivos de evidencia</p>
            </div>
          </section>
        </template>
      </DialogModal>

    <!-- //Modal para subir archivo -->
    <DialogModal :show="uploadFilesModal" @close="uploadFilesModal = false">
      <template #title>
        <p class="text-center font-bold"> Subir archivos a {{ machine.name }}</p>
      </template>
      <template #content>
        <div>
          <form @submit.prevent="uploadFiles" ref="myUploadFilesForm">
            <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="imagen" :max-files="4" />
          </form>
        </div>
      </template>
      <template #footer>
        <div class="flex space-x-3">
          <CancelButton @click="uploadFilesModal = false; form.reset()" :disabled="form.processing">Cancelar
          </CancelButton>
          <SecondaryButton @click="uploadFiles" :disabled="!form.media.length" :loading="form.processing">Subir archivos
          </SecondaryButton>
        </div>
      </template>
    </DialogModal>

  </AppLayout>
</template>

<script>
// Importaciones de componentes
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Empty from "@/Components/MyComponents/Empty.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { ElMessage, ElMessageBox } from 'element-plus'; // Para notificaciones
// Importa `router` para la navegación y `useForm`
import { Link, useForm, router } from "@inertiajs/vue3"; 
// Componentes locales (si los creas)
import InfoItem from './InfoItem.vue'; 
import StatusIndicator from './StatusIndicator.vue'; 

export default {
  components: {
    AppLayout, PrimaryButton, FileView, FileUploader, Empty, SecondaryButton, CancelButton, Link, DropdownLink, Dropdown, ConfirmationModal, DialogModal,
    InfoItem, StatusIndicator
  },

  props: {
    machine: Object, // Máquina actual con todos los detalles
    machines: Array, // Lista de todas las máquinas (solo id y name)
  },

  data() {
    return {
      form: useForm({ media: [] }),
      // El v-model del selector ahora se basa en el ID de la prop 'machine'
      selectedMachineId: this.machine?.id || null, 
      currentImageIndex: 0,
      activeTab: 'info', // 'info', 'maintenance', 'spares'
      showConfirmModal: false,
      uploadFilesModal: false,
      maintenanceTypes: ['Preventivo', 'Correctivo', 'Limpieza'],

      //mantenimiento
      maintenanceModal: false,
      maintenanceIndex: null,
      selectedMaintenance: null,

      // refacciones
      sparePartModal: false,
      sparePartIndex: null,
      selectedSparePart: null,


    };
  },

  computed: {
    // Todas las computadas ahora usan directamente la prop 'machine'
    hasImages() {
      return this.machine?.media && this.machine.media.length > 0;
    },
    hasMultipleImages() {
        return this.machine?.media?.length > 1;
    },
    currentImageUrl() {
      if (!this.hasImages) return null;
      return this.machine.media[this.currentImageIndex]?.original_url;
    },
    // Permisos
    userPermissions() {
        return this.$page.props.auth.user.permissions || [];
    },
    canEdit() {
        return this.userPermissions.includes('Editar maquinas');
    },
    canCreate() {
        return this.userPermissions.includes('Crear maquinas');
    },
    canDelete() {
        return this.userPermissions.includes('Eliminar maquinas');
    },
    canCreateMaintenance() {
        return this.userPermissions.includes('Crear mantenimientos');
    },
    canCreateSparePart() {
        return this.userPermissions.includes('Crear refacciones');
    },
    canDoMoreActions() {
        return this.canCreate || this.canCreateMaintenance || this.canCreateSparePart || this.canDelete;
    }
  },

  watch: {
    // ESTA ES LA CORRECCIÓN CLAVE
    selectedMachineId(newId) {
      // Si se selecciona un ID y es diferente al de la máquina actual,
      // visita la ruta 'show' para esa nueva máquina.
      if (newId && newId !== this.machine.id) {
        router.get(route('machines.show', newId), {}, {
          preserveState: true, // Mantiene el estado del componente (como el modal abierto)
          preserveScroll: true, // Mantiene la posición del scroll
          onSuccess: () => { // Resetea el estado visual al cambiar de máquina
              this.activeTab = 'info';
              this.currentImageIndex = 0;
          }
        });
      }
    },
  },

  methods: {
    // --- Lógica de UI ---
    getTabClass(tabName) {
      return this.activeTab === tabName
        ? 'py-3 px-1 border-b-2 border-primary text-primary'
        : 'py-3 px-1 border-b-2 border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300';
    },
    getFileTypeIcon(fileName) {
      const extension = fileName?.split('.').pop().toLowerCase() || '';
      const iconMap = {
        pdf: 'fa-regular fa-file-pdf text-red-500',
        jpg: 'fa-regular fa-image text-blue-500',
        jpeg: 'fa-regular fa-image text-blue-500',
        png: 'fa-regular fa-image text-blue-500',
        gif: 'fa-regular fa-image text-blue-500',
        webp: 'fa-regular fa-image text-blue-500',
        svg: 'fa-regular fa-image text-blue-500',
        doc: 'fa-regular fa-file-word text-blue-700',
        docx: 'fa-regular fa-file-word text-blue-700',
        xls: 'fa-regular fa-file-excel text-green-600',
        xlsx: 'fa-regular fa-file-excel text-green-600',
      };
      return iconMap[extension] || 'fa-regular fa-file-lines text-gray-400';
    },
    openImage(url) {
      window.open(url, "_blank");
    },
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    },
    // --- Lógica del Carrusel ---
    nextImage() {
      if (!this.hasMultipleImages) return;
      this.currentImageIndex = (this.currentImageIndex + 1) % this.machine.media.length;
    },
    prevImage() {
      if (!this.hasMultipleImages) return;
      this.currentImageIndex = (this.currentImageIndex - 1 + this.machine.media.length) % this.machine.media.length;
    },
    // --- Lógica de Modales ---
    openMaintenanceModal(maintenance, index) {
      this.selectedMaintenance = maintenance;
      this.maintenanceModal = true;
      this.maintenanceIndex = index + 1;
    },
    openSparePartModal(spare_part, index) {
      this.selectedSparePart = spare_part;
      this.sparePartModal = true;
      this.sparePartIndex = index + 1;
    },
    // --- Acciones CRUD ---
    deleteMachine() {
      // La acción ahora usa directamente this.machine.id
      this.$inertia.delete(route("machines.destroy", this.machine.id), {
        onSuccess: () => {
          ElMessage({
              type: 'success',
              message: 'Máquina Eliminada',
          });
          this.selectedMachineId = null; 
          this.showConfirmModal = false;
          // Inertia reemplazará la prop 'machine' con null si el controlador lo gestiona,
          // o puedes redirigir al index.
        },
        onError: () => {
          ElMessage({
            type: 'error',
            message: 'Hubo un problema con la elimincaión',
        });
        }
      });
    },
    deleteRow(id, type) {
        const routeName = type === 'maintenance' ? 'maintenances.destroy' : 'spare-parts.destroy';
        this.$inertia.delete(route(routeName, id), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage({
                    type: 'success',
                    message: 'Registro eliminado',
                });
                // Inertia actualizará la prop 'machine' automáticamente con los datos frescos.
            },
            onError: () => {
                ElMessage({
                    type: 'error',
                    message: 'No se pudo eliminar el registro',
                });
            }
        });
    },
    uploadFiles() {
      this.form.post(route("machines.upload-files", this.machine.id), {
        _method: 'put',
        onSuccess: () => {
          ElMessage({
              type: 'success',
              message: 'Archivos agregados a la máquina',
          });

          this.form.reset();
          this.uploadFilesModal = false;
        },
      });
    },
    validateMaintenance(){
      this.form.put(route('maintenances.validate', this.selectedMaintenance.id), {
        onSuccess: () => {
          ElMessage({
              type: 'success',
              message: 'Mantenimiento validado',
          });
          this.selectedMaintenance.validated_at = new Date().toISOString();
          this.selectedMaintenance.validated_by = this.$page.props.auth.user.name;
          this.maintenanceModal = false;
        },
        onError: error => {
          console.log(error)
        }
      })
    },
  },
};
</script>

<style scoped>
/* Estilos para botones de acción reutilizables */
.action-button {
  @apply size-9 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-secondary transition-all duration-200;
}
.action-button-primary {
  @apply h-9 px-4 flex items-center justify-center rounded-lg bg-secondary text-white text-sm font-semibold hover:bg-secondary/90 transition-all duration-200;
}
.carousel-nav-button {
    @apply absolute top-1/2 -translate-y-1/2 size-8 flex items-center justify-center rounded-full bg-white/70 dark:bg-black/50 text-gray-800 dark:text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 hover:bg-white dark:hover:bg-black;
}
.delete-icon-button {
    @apply size-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-red-100 dark:hover:bg-red-500/20 hover:text-red-600 dark:hover:text-red-500 transition-colors;
}
</style>
