<template>
    <el-dialog v-model="internalShow" title="Edición Masiva de Productos" width="95%" :before-close="handleClose" top="5vh">
        
        <div class="mb-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Ajusta rápidamente las propiedades principales, selecciona un producto padre (si aplica) o cambia la familia de los {{ editList.length }} productos seleccionados.
            </p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-2 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Img</th>
                        <th class="px-2 py-3 text-left font-medium text-gray-500 dark:text-gray-300 min-w-[180px]">Producto</th>
                        <th class="px-2 py-3 text-center font-medium text-gray-500 dark:text-gray-300"><i class="fa-solid fa-store" title="¿Se Vende?"></i> Venta</th>
                        <th class="px-2 py-3 text-center font-medium text-gray-500 dark:text-gray-300"><i class="fa-solid fa-truck" title="¿Se Compra?"></i> Compra</th>
                        <th class="px-2 py-3 text-center font-medium text-gray-500 dark:text-gray-300"><i class="fa-solid fa-puzzle-piece" title="¿Es Componente?"></i> Comp.</th>
                        <th class="px-2 py-3 text-left font-medium text-gray-500 dark:text-gray-300 min-w-[200px]">Producto Padre</th>
                        <th class="px-2 py-3 text-left font-medium text-gray-500 dark:text-gray-300 min-w-[150px]">Familia</th>
                        <th class="px-2 py-3 text-left font-medium text-gray-500 dark:text-gray-300 min-w-[120px]">Tipo</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(item, index) in form.products" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                        <!-- Imagen -->
                        <td class="px-2 py-2">
                            <img v-if="originalProducts[index]?.media?.length" 
                                 :src="originalProducts[index].media[0].original_url" 
                                 class="w-12 h-12 object-cover rounded cursor-pointer border border-gray-200 dark:border-gray-600 hover:opacity-80" 
                                 @click="openPreview(originalProducts[index].media[0].original_url)" />
                            <div v-else class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center text-gray-400">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        </td>
                        
                        <!-- Nombre (editable) -->
                        <td class="px-2 py-2">
                            <el-input v-model="item.name" placeholder="Nombre del producto" size="small" class="w-full" />
                            <div class="text-xs text-gray-400 mt-1">{{ originalProducts[index]?.code }}</div>
                        </td>

                        <!-- Checkbox Venta -->
                        <td class="px-2 py-2 text-center">
                            <el-checkbox v-model="item.is_sellable" />
                        </td>
                        
                        <!-- Checkbox Compra -->
                        <td class="px-2 py-2 text-center">
                            <el-checkbox v-model="item.is_purchasable" />
                        </td>

                        <!-- Checkbox Componente -->
                        <td class="px-2 py-2 text-center">
                            <el-checkbox v-model="item.is_used_as_component" />
                        </td>

                        <!-- Selector Padre -->
                        <td class="px-2 py-2">
                            <el-select 
                                v-model="item.parent_id" 
                                filterable 
                                remote 
                                clearable
                                reserve-keyword
                                placeholder="Buscar padre..."
                                :remote-method="searchParents"
                                :loading="loadingParents"
                                class="w-full">
                                <el-option
                                    v-for="parent in parentOptions"
                                    :key="parent.id"
                                    :label="parent.name"
                                    :value="parent.id"
                                    :disabled="parent.id === item.id"> <!-- Evita asignarse a sí mismo -->
                                </el-option>
                            </el-select>
                        </td>

                        <!-- Selector Familia -->
                        <td class="px-2 py-2">
                            <el-select v-model="item.product_family_id" filterable clearable placeholder="Familia" class="w-full" :disabled="item.product_type_key === 'I'">
                                <el-option v-for="fam in product_families" :key="fam.id" :label="fam.name" :value="fam.id" />
                            </el-select>
                        </td>

                        <!-- Selector Tipo -->
                        <td class="px-2 py-2">
                            <el-select v-model="item.product_type_key" placeholder="Tipo" class="w-full">
                                <el-option label="Producto" value="P" />
                                <el-option label="Insumo" value="I" />
                            </el-select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 mt-4">
                <el-button @click="handleClose" :disabled="form.processing">Cancelar</el-button>
                <el-button type="primary" @click="submit" :loading="form.processing">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar Cambios
                </el-button>
            </div>
        </template>

        <!-- Visor de imagen manual -->
        <el-image-viewer
            v-if="showImageViewer"
            :url-list="[previewImageUrl]"
            @close="showImageViewer = false"
            :hide-on-click-modal="true"
            teleported
        />
    </el-dialog>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import { ElMessage, ElImageViewer } from 'element-plus';
import axios from 'axios';

export default {
    components: {
        ElImageViewer
    },
    props: {
        show: Boolean,
        selected_products: Array,
        product_families: Array,
    },
    emits: ['close'],
    data() {
        return {
            internalShow: this.show,
            originalProducts: [],
            editList: [],
            form: useForm({
                products: []
            }),
            
            // Opciones dinámicas para el select de padres
            parentOptions: [],
            loadingParents: false,

            // Visor de imagenes
            showImageViewer: false,
            previewImageUrl: ''
        };
    },
    watch: {
        show(newVal) {
            this.internalShow = newVal;
            if (newVal) {
                this.initForm();
                this.searchParents(''); // Carga inicial de padres sugeridos
            }
        }
    },
    methods: {
        initForm() {
            // Guardamos referencias para la UI (nombres, imagenes, codigo)
            this.originalProducts = [...this.selected_products];
            
            // Construimos la estructura exacta para enviar al backend
            this.editList = this.selected_products.map(p => ({
                id: p.id,
                name: p.name ?? '',
                parent_id: p.parent_id ?? null,
                product_family_id: p.product_family_id ?? null,
                is_sellable: p.is_sellable == 1,
                is_purchasable: p.is_purchasable == 1,
                is_used_as_component: p.is_used_as_component == 1,
                product_type_key: p.product_type === 'Insumo' ? 'I' : 'P',
                material: p.material ? this.reverseMaterial(p.material) : null
            }));

            this.form.products = this.editList;
        },
        async searchParents(query) {
            this.loadingParents = true;
            try {
                // Se utiliza la nueva ruta optimizada
                const response = await axios.post(route('catalog-products.search-parents'), {
                    query: query 
                });
                
                if (response.data && response.data.items) {
                    this.parentOptions = response.data.items.map(i => ({
                        id: i.id,
                        // Concatenamos nombre y código para que el usuario identifique mejor al padre
                        name: `${i.name} (${i.code})`
                    }));
                }
            } catch (error) {
                console.error("Error buscando padres: ", error);
            } finally {
                this.loadingParents = false;
            }
        },
        openPreview(url) {
            if (url) {
                this.previewImageUrl = url;
                this.showImageViewer = true;
            }
        },
        submit() {
            // Corrección aquí: se cambia de catalog-products.massive-update a products.massive-update
            this.form.post(route('products.massive-update'), {
                onSuccess: () => {
                    ElMessage.success('Productos actualizados correctamente');
                    this.$emit('close');
                },
                onError: (err) => {
                    ElMessage.error('Ocurrió un error. Revisa la validación.');
                    console.error(err);
                }
            });
        },
        handleClose() {
            this.form.reset();
            this.$emit('close');
        },
        // Helper para invertir material a key
        reverseMaterial(materialName) {
            const map = {
                'METAL': 'M', 'PLASTICO': 'PLS', 'PIEL DE LUJO': 'PL', 'ORIGINAL': 'O',
                'LUJO': 'L', 'PIEL': 'P', 'ZAMAK': 'ZK', 'SOLIDCHROME': 'SCH',
                'MICROMETAL': 'MM', 'FLEXCHROME': 'FCH', 'ALUMINIO': 'AL', 'ESTIRENO': 'ES',
                'ABS': 'ABS', 'PVC': 'PVC', 'TELA': 'T', 'CAUCHO': 'CAU', 'VINILPIEL': 'VPL', 
                'FIBRA DE CARBONO': 'FC', 'OVERLAY': 'OV', 'ACERO': 'AC', 'FIBRA DSE CARBONO': 'FDC',
                'RESINA': 'RS', 'ENCAPSULADO': 'ENC', 'CORTE DIAMANTE': 'CDT' 
            };
            return map[materialName] || null;
        }
    },
    mounted() {
        if (this.show) {
            this.initForm();
            this.searchParents('');
        }
    }
};
</script>

<style scoped>
/* Asegurar visibilidad correcta de los dropdowns anidados si fuera necesario */
</style>