<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- Panel Flotante de Notas -->
        <BranchNotes :branch-id="branch.id" />

        <!-- === ENCABEZADO === -->
        <h1 class="dark:text-white font-bold text-2xl mb-4">{{ branch.name }} {{ branch.parent?.id ? '' : '(SUCURSAL MATRIZ)' }}</h1>
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-gray-500">
            <div class="w-full lg:w-1/3">
                <el-select @change="$inertia.get(route('branches.show', selectedBranch))"
                    v-model="selectedBranch" filterable placeholder="Buscar otro cliente..."
                    class="!w-full"
                    no-data-text="No hay clientes registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in branches" :key="item.id"
                        :label="item.name" :value="item.id" />
                </el-select>
            </div>
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar clientes')" content="Editar Cliente" placement="top">
                    <Link :href="route('branches.edit', branch.id)">
                        <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                            <i class="fa-solid fa-pencil text-sm"></i>
                        </button>
                    </Link>
                </el-tooltip>
                
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Crear clientes')" :href="route('branches.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo Cliente
                        </DropdownLink>
                        <DropdownLink @click="showAddProductsModal = true" as="button">
                            <i class="fa-solid fa-tags w-4 mr-2"></i> Agregar Productos
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Eliminar clientes')" @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('branches.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">ID:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ branch.id }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">No. Cliente:</span>
                            <span class="text-gray-800 dark:text-gray-200">{{ branch.client_number ?? 'No asignado' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="branch.status === 'Cliente' ? 'success' : 'info'" size="small">{{ branch.status }}</el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Vendedor:</span>
                            <span>{{ branch.account_manager?.name ?? 'No asignado' }}</span>
                        </li>
                        <li class="flex flex-col">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Grupo:</span>
                            <div class="flex items-center gap-2 mt-1">
                                <el-select
                                    v-model="selectedGroup"
                                    filterable
                                    clearable
                                    size="small"
                                    placeholder="Sin grupo"
                                    class="!w-full"
                                    :teleported="false"
                                    @change="assignGroup"
                                >
                                    <el-option v-for="g in groupsList" :key="g" :label="g" :value="g" />
                                </el-select>
                                <el-button
                                    v-if="selectedGroup"
                                    size="small"
                                    type="danger"
                                    plain
                                    :loading="savingGroup"
                                    title="Quitar del grupo"
                                    @click="removeGroup"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </el-button>
                            </div>
                            <span v-if="groupBranchIsChild" class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                Esta sucursal se gestiona desde la matriz «{{ groupBranchName }}».
                            </span>
                        </li>
                        <li class="flex flex-col">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Razón Social:</span>
                                <el-tooltip v-if="branch.parent_branch_id && $page.props.auth.user.permissions.includes('Editar clientes')" content="Editar los datos fiscales propios de esta sucursal" placement="top">
                                    <button @click="openFiscalModal" type="button" class="text-gray-400 hover:text-primary transition-colors">
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                </el-tooltip>
                            </div>
                            <span class="text-gray-800 dark:text-gray-200 leading-tight mt-1">{{ branch.business_name || branch.parent?.business_name || 'No especificada' }}</span>
                            <span v-if="!branch.business_name && branch.parent?.business_name" class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                                <i class="fa-solid fa-circle-info mr-1"></i> Heredado de la matriz «{{ branch.parent.name }}»
                            </span>
                        </li>
                        <li class="flex flex-col">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                            <span class="mt-1">{{ branch.rfc || branch.parent?.rfc || 'No especificado' }}</span>
                            <span v-if="!branch.rfc && branch.parent?.rfc" class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                                <i class="fa-solid fa-circle-info mr-1"></i> Heredado de la matriz «{{ branch.parent.name }}»
                            </span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Matriz:</span>
                            <span @click="branch.parent?.id ? $inertia.visit(route('branches.show', branch.parent.id)) : null" :class="branch.parent?.id ? 'text-blue-500 hover:underline cursor-pointer' : ''">{{ branch.parent?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Ultima compra:</span>
                            <span>{{ formatRelative(branch.last_purchase_date) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Sucursales Hijas Mejorada -->
                <div v-if="!branch.parent_branch_id" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <div class="flex items-center justify-between border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold flex items-center">
                            <span>Sucursales</span>
                            <el-tag type="info" size="small" effect="plain" class="!rounded-full ml-2">{{ branch.children?.length || 0 }}</el-tag>
                        </h3>
                        <button @click="showAddChild = !showAddChild" class="text-primary hover:underline text-sm font-bold">
                            <i class="fa-solid fa-plus mr-1"></i> Agregar
                        </button>
                    </div>

                    <!-- Buscador para agregar sucursales hijas -->
                    <div v-if="showAddChild" class="mb-4 p-3 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-900/30">
                        <el-select
                            v-model="childToAdd"
                            filterable
                            remote
                            reserve-keyword
                            clearable
                            :remote-method="remoteChildSearch"
                            :loading="searchingChildren"
                            size="small"
                            placeholder="Buscar cliente/matriz para agregar como sucursal"
                            class="!w-full"
                            :teleported="false"
                            @change="addChild"
                        >
                            <el-option v-for="item in childCandidates" :key="item.id"
                                :label="childCandidateLabel(item)" :value="item.id" />
                        </el-select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Solo clientes que no pertenezcan a otra matriz. Si eliges una matriz, sus sucursales también se integran.
                        </p>
                    </div>

                    <div v-if="branch.children && branch.children.length" class="space-y-2 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                        <Link v-for="child in branch.children" :key="child.id" :href="route('branches.show', child.id)"
                              class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700/50 bg-gray-50/50 dark:bg-slate-900/30 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors group">
                            
                            <div class="flex items-center space-x-3 overflow-hidden">
                                <div class="shrink-0 size-9 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-xs border border-blue-200 dark:border-blue-800">
                                    {{ child.id }}
                                </div>
                                <div class="truncate">
                                    <p class="font-semibold text-gray-700 dark:text-gray-200 group-hover:text-primary transition-colors text-sm truncate">
                                        {{ child.name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                        <i class="fa-solid fa-location-dot mr-1 opacity-70"></i>
                                        {{ child.address || 'Sin dirección' }}
                                    </p>
                                </div>
                            </div>
                            
                            <div class="shrink-0 pl-2">
                                <i class="fa-solid fa-chevron-right text-gray-400 group-hover:text-primary transition-colors text-xs"></i>
                            </div>
                        </Link>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic text-center">
                        Esta matriz aún no tiene sucursales.
                    </p>
                </div>

                <!-- Card de Contactos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold">Contactos</h3>
                        <button @click="openContactModal()" class="text-primary hover:underline text-sm font-bold">
                            <i class="fa-solid fa-plus mr-1"></i> Nuevo
                        </button>
                    </div>
                    <div v-if="branch.contacts?.length" class="space-y-4">
                        <div v-for="contact in branch.contacts" :key="contact.id" class="relative">
                            <div class="absolute top-0 right-0 flex space-x-1 transition-opacity duration-200">
                                <el-tooltip content="Editar" placement="top">
                                    <button @click="openContactModal(contact)" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-blue-200 dark:hover:bg-blue-900 transition-colors">
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                </el-tooltip>
                                <el-tooltip content="Eliminar" placement="top">
                                    <button @click="showConfirmDeleteContact = { show: true, contactId: contact.id }" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-red-200 dark:hover:bg-red-900 transition-colors">
                                        <i class="fa-regular fa-trash-can text-xs"></i>
                                    </button>
                                </el-tooltip>
                            </div>

                            <p class="font-semibold">
                                <el-tag v-if="contact.area" size="small" class="mr-2">{{ contact.area }}</el-tag>
                                <el-tag v-else size="small" type="info" class="mr-2">Por definir</el-tag>
                                {{ contact.prefix }} {{ contact.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ contact.charge }}</p>
                            <div class="text-sm mt-1 space-y-1">
                                <p v-if="getPrimaryDetail(contact, 'Correo')"><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Correo') }}</p>
                                
                                <!-- Mostrar todos los teléfonos -->
                                <template v-if="getContactDetails(contact, 'Teléfono').length">
                                    <p v-for="phone in getContactDetails(contact, 'Teléfono')" :key="phone.id" class="flex items-center">
                                        <i class="fa-solid fa-phone mr-2 text-gray-400"></i>
                                        {{ formatPhone(phone.value) }}
                                        <el-tag v-if="phone.is_primary" type="success" size="small" class="ml-2 !h-5 !px-1.5 text-[10px]">Principal</el-tag>
                                    </p>
                                </template>
                                
                                <p v-if="contact.birthdate">
                                    <i class="fa-solid fa-cake-candles mr-2 text-gray-400"></i> {{ formatBirthday(contact.birthdate) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay contactos registrados.</p>
                </div>

                <!-- Card de Grupo: clientes/sucursales que comparten el grupo -->
                <div v-if="selectedGroup" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <button type="button" @click="showGroupMembers = !showGroupMembers"
                        class="w-full flex items-center justify-between border-b dark:border-gray-600 pb-3">
                        <h3 class="text-lg font-semibold flex items-center">
                            Clientes en el grupo: {{ selectedGroup }}
                            <el-tag type="info" size="small" effect="plain" class="!rounded-full ml-2">{{ groupMembersTotal }}</el-tag>
                        </h3>
                        <i class="fa-solid text-gray-400" :class="showGroupMembers ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>

                    <div v-show="showGroupMembers" class="mt-4 space-y-3">
                        <!-- Buscador para agregar clientes al grupo -->
                        <el-select
                            v-model="branchToAdd"
                            filterable
                            remote
                            reserve-keyword
                            clearable
                            :remote-method="remoteGroupSearch"
                            :loading="groupSearching"
                            size="small"
                            placeholder="Buscar cliente para agregar al grupo"
                            class="!w-full"
                            :teleported="false"
                            @change="addGroupMember"
                        >
                            <el-option v-for="item in groupSearchResults" :key="item.id"
                                :label="item.rfc ? `${item.name} — ${item.rfc}` : item.name" :value="item.id" />
                        </el-select>

                        <!-- Lista de miembros del grupo -->
                        <div v-if="groupMembers.length" class="space-y-2 max-h-[280px] overflow-y-auto pr-2 custom-scrollbar">
                            <div v-for="member in groupMembers" :key="member.id"
                                class="rounded-lg border border-gray-100 dark:border-slate-700/50 bg-gray-50/50 dark:bg-slate-900/30 overflow-hidden">
                                <div class="flex items-center gap-2 p-3">
                                    <!-- Toggle para desplegar sucursales hijas -->
                                    <button v-if="member.children && member.children.length" type="button"
                                        @click="toggleMember(member)"
                                        class="shrink-0 size-6 flex items-center justify-center rounded text-gray-400 hover:text-primary hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors"
                                        :title="isMemberExpanded(member.id) ? 'Ocultar sucursales' : 'Ver sucursales'">
                                        <i class="fa-solid text-xs" :class="isMemberExpanded(member.id) ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                    </button>
                                    <span v-else class="shrink-0 size-6"></span>

                                    <Link :href="route('branches.show', member.id)" class="group min-w-0 flex items-center gap-3 flex-1">
                                        <span class="shrink-0 size-9 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-xs border border-blue-200 dark:border-blue-800">
                                            {{ member.id }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary truncate flex items-center">
                                                <el-tooltip v-if="!member.parent_branch_id" content="Sucursal matriz" placement="top">
                                                    <i class="fa-solid fa-crown text-yellow-500 mr-1"></i>
                                                </el-tooltip>
                                                {{ member.name }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                <i class="fa-solid fa-user-tie mr-1 opacity-70"></i>
                                                {{ member.account_manager?.name ?? 'Sin vendedor' }}
                                            </p>
                                        </div>
                                    </Link>
                                    <el-button size="small" type="danger" plain
                                        :loading="removingGroupId === member.id"
                                        @click="removeGroupMember(member)">
                                        Sacar
                                    </el-button>
                                </div>

                                <!-- Sucursales hijas de la matriz -->
                                <div v-if="member.children && member.children.length && isMemberExpanded(member.id)"
                                    class="border-t border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-900/40">
                                    <div v-for="child in member.children" :key="child.id"
                                        class="flex items-center gap-3 pl-11 pr-3 py-2">
                                        <Link :href="route('branches.show', child.id)" class="group min-w-0 flex items-center gap-3 flex-1">
                                            <span class="shrink-0 size-7 rounded-full bg-gray-100 dark:bg-slate-700/60 flex items-center justify-center text-gray-500 dark:text-gray-300 font-bold text-[10px] border border-gray-200 dark:border-slate-600">
                                                {{ child.id }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary truncate">
                                                    {{ child.name }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    <i class="fa-solid fa-user-tie mr-1 opacity-70"></i>
                                                    {{ child.account_manager?.name ?? 'Sin vendedor' }}
                                                </p>
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic text-center">
                            Este grupo aún no tiene otros clientes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg max-h-[70vh]">
                        <el-tabs v-model="activeTab" class="p-5">
                        <el-tab-pane label="Info. General" name="general">
                            <ul class="space-y-4 text-sm mt-2">
                                <li><strong class="font-semibold w-40 inline-block">Cuenta Bancaria:</strong> {{ branch.bank_account ?? 'No especificada' }}</li>
                                <li><strong class="font-semibold w-40 inline-block">Dirección:</strong> {{ branch.address ?? 'No especificada' }}</li>
                                <li><strong class="font-semibold w-40 inline-block">Código Postal:</strong> {{ branch.post_code ?? 'N/A' }}</li>
                                <li><strong class="font-semibold w-40 inline-block">Nos conoció por:</strong> {{ branch.meet_way ?? 'No especificado' }}</li>
                                <li>
                                    <strong class="font-semibold w-40 inline-block">Método de Pago:</strong>
                                    {{ branch.payment_method ?? 'No especificado' }}
                                    <span v-if="branch.payment_submethod" class="text-gray-500 dark:text-gray-400">({{ branch.payment_submethod }})</span>
                                </li>
                                <li>
                                    <strong class="font-semibold w-40 inline-block">Uso de CFDI:</strong> {{ branch.cfdi_use ?? 'No especificado' }}
                                </li>
                                <li>
                                    <strong class="font-semibold w-40 inline-block">CSF:</strong>
                                    <template v-if="csfMedia">
                                        <el-image
                                            v-if="isCsfImage"
                                            :src="csfMedia.original_url"
                                            :preview-src-list="[csfMedia.original_url]"
                                            :preview-teleported="true"
                                            fit="cover"
                                            class="size-24 rounded-md border border-gray-200 dark:border-slate-700 align-middle cursor-pointer"
                                        >
                                            <template #error>
                                                <div class="flex items-center justify-center size-24 text-gray-400">
                                                    <i class="fa-solid fa-file-lines text-2xl"></i>
                                                </div>
                                            </template>
                                        </el-image>
                                        <a v-else :href="csfMedia.original_url" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <i class="fa-regular fa-file-pdf mr-1"></i> Ver documento
                                        </a>
                                        <button @click="deleteCsf" type="button" class="ml-3 text-red-500 hover:text-red-700 text-sm" title="Eliminar CSF">
                                            <i class="fa-regular fa-trash-can"></i> Eliminar
                                        </button>
                                    </template>
                                    <template v-else>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">No cargada. Sube la CSF actualizada (PDF o imagen).</p>
                                        <FileUploader
                                            @files-selected="uploadCsf"
                                            :multiple="false"
                                            format="Documento"
                                            :max-files="1"
                                            :max-file-size="10"
                                            class="max-w-xs"
                                        />
                                    </template>
                                </li>
                                <li>
                                    <strong class="font-semibold inline-block mb-1">Notas:</strong>
                                    <p class="text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-slate-900/50 p-3 rounded-md border border-gray-100 dark:border-slate-700 mt-1">
                                        {{ branch.important_notes ?? 'No hay notas registradas para este cliente.' }}
                                    </p>
                                </li>
                            </ul>
                        </el-tab-pane>
                        
                        <el-tab-pane name="products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-tags mr-2"></i>
                                    <span>Prod. Asignados ({{ branch.products?.length ?? 0 }})</span>
                                </div>
                            </template>
                            <div v-if="branch.products.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <Products :products="branch.products" :branchId="branch.id" />
                            </div>
                             <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Aún no hay productos asignados a este cliente.</p>
                        </el-tab-pane>
                        
                        <el-tab-pane name="suggested_products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-lightbulb mr-2"></i>
                                    <span>Sugerencias</span>
                                </div>
                            </template>
                            <div v-if="branch.suggested_products?.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <SuggestedProducts :products="branch.suggested_products" />
                            </div>
                             <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">No hay productos sugeridos para este cliente.</p>
                        </el-tab-pane>

                        <!-- PESTAÑA COMPONENTIZADA: Análisis de Consumo -->
                        <el-tab-pane name="analytics">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-chart-bar mr-2"></i>
                                    <span>Análisis de Consumo</span>
                                </div>
                            </template>
                            <ConsumptionAnalytics :consumption-data="consumptionData" />
                        </el-tab-pane>

                        <el-tab-pane label="Cotizaciones" name="quotes">
                            <LoadingIsoLogo v-if="loadingQuotes" />
                            <Quotes v-else :quotes="quotes" />
                        </el-tab-pane>
                        <el-tab-pane label="Ventas" name="sales">
                            <LoadingIsoLogo v-if="loadingSales" />
                            <Sales v-else :sales="sales" />
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modals -->
        <ModalCrearEditarContacto :show="showContactModal" :contactable-id="branch.id" :contact="contactToEdit"
    :contactable-type="'App\\Models\\Branch'" @close="showContactModal = false" />
        <AddProductsModal :show="showAddProductsModal" :branch="branch" :catalog_products="catalog_products" @close="showAddProductsModal = false" />

        <ConfirmationModal :show="showConfirmDeleteContact.show" @close="showConfirmDeleteContact.show = false">
            <template #title>
                Eliminar Contacto
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar este contacto? Esta acción es irreversible.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmDeleteContact.show = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteContact" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Cliente
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este cliente? Todos los datos relacionados (contactos, precios, etc.) se perderán. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Modal para editar los datos fiscales propios del cliente -->
        <DialogModal :show="showFiscalModal" maxWidth="md" @close="closeFiscalModal">
            <template #title>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa-solid fa-file-invoice mr-2"></i> Datos fiscales
                </h2>
            </template>
            <template #content>
                <div class="space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Si dejas un campo vacío, este cliente usará el dato de su sucursal matriz.
                    </p>
                    <TextInput label="Razón Social" v-model="fiscalForm.business_name"
                        :error="fiscalForm.errors.business_name" placeholder="Razón social propia (opcional)" />
                    <TextInput label="RFC" v-model="fiscalForm.rfc"
                        :error="fiscalForm.errors.rfc" placeholder="RFC propio (opcional)" />
                </div>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="closeFiscalModal">Cancelar</CancelButton>
                    <PrimaryButton @click="saveFiscal" :disabled="fiscalForm.processing">
                        <span v-if="fiscalForm.processing">Guardando...</span>
                        <span v-else>Guardar</span>
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
// Pestañas
import Products from "@/Pages/Branch/Tabs/Products.vue";
import Quotes from "@/Pages/Branch/Tabs/Quotes.vue";
import Sales from "@/Pages/Branch/Tabs/Sales.vue";
import SuggestedProducts from "@/Pages/Branch/Tabs/SuggestedProducts.vue";
import ConsumptionAnalytics from "@/Pages/Branch/Tabs/ConsumptionAnalytics.vue"; // <- El nuevo componente

// Modals
import AddProductsModal from "@/Pages/Branch/Modals/AddProductsModal.vue";
import ModalCrearEditarContacto from "@/Pages/Branch/Modals/ModalCrearEditarContacto.vue";

// Componentes
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import DialogModal from "@/Components/DialogModal.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        const form = useForm({
            products: [],
        });

        const fiscalForm = useForm({
            business_name: '',
            rfc: '',
        });

        // Recuperar la pestaña activa de la URL si existe
        const queryParams = new URLSearchParams(window.location.search);
        const tab = queryParams.get('tab');

        return {
            form,
            fiscalForm,
            showFiscalModal: false,
            activeTab: tab || 'general', // Inicializar con el valor de la URL o por defecto
            selectedBranch: this.branch.id,
            showConfirmModal: false,
            showAddProductsModal: false,
            showContactModal: false,
            showConfirmDeleteContact: { show: false, contactId: null },
            quotes: [],
            loadingQuotes: false,
            sales: [],
            loadingSales: false,
            contactToEdit: null,
            currentProduct: {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            },
            loadingProductMedia: false,
            // --- Grupos ---
            selectedGroup: this.groupName ?? null,
            showGroupMembers: false,
            branchToAdd: null,
            groupSearchResults: [],
            groupSearching: false,
            removingGroupId: null,
            savingGroup: false,
            expandedMembers: {},
            // --- Sucursales hijas ---
            showAddChild: false,
            childToAdd: null,
            childCandidates: [],
            searchingChildren: false,
        };
    },
    components: {
        Link,
        Dropdown,
        AppLayout,
        BranchNotes,
        DropdownLink,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
        DialogModal,
        TextInput,
        InputError,
        FileUploader,
        Products,
        Quotes,
        Sales,
        SuggestedProducts, 
        ConsumptionAnalytics,
        AddProductsModal,
        ModalCrearEditarContacto,
    },
    props: {
        branch: Object,
        branches: Array,
        catalog_products: Array,
        consumptionData: Object,
        groups: {
            type: Array,
            default: () => [],
        },
        groupName: {
            type: String,
            default: null,
        },
        groupBranchId: {
            type: Number,
            default: null,
        },
        groupBranchName: {
            type: String,
            default: '',
        },
        groupBranchIsChild: {
            type: Boolean,
            default: false,
        },
        groupMembers: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        groupsList() {
            return this.groups ?? [];
        },
        // Total de clientes/sucursales en el grupo (matrices + sus sucursales hijas).
        groupMembersTotal() {
            return (this.groupMembers || []).reduce(
                (total, member) => total + 1 + (member.children?.length || 0),
                0
            );
        },
        availableProducts() {
            const assignedProductIds = this.branch.products.map(p => p.id);
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        },
        csfMedia() {
            return (this.branch.media || []).find(m => m.collection_name === 'csf') || null;
        },
        isCsfImage() {
            const media = this.csfMedia;
            if (!media) return false;
            if (media.mime_type) return String(media.mime_type).startsWith('image/');
            const name = media.file_name || '';
            const ext = name.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        },
    },
    methods: {
        formatPhone(number) {
            if (!number) return '';
            // Eliminamos todo lo que no sea dígito
            const digits = number.toString().replace(/\D/g, '');
            // Agrupamos cada 2 dígitos y unimos con guiones
            return digits.match(/.{1,2}/g)?.join('-') || '';
        },
        openContactModal(contact = null) {
            this.contactToEdit = contact;
            this.showContactModal = true;
        },
        openFiscalModal() {
            this.fiscalForm.clearErrors();
            this.fiscalForm.business_name = this.branch.business_name ?? '';
            this.fiscalForm.rfc = this.branch.rfc ?? '';
            this.showFiscalModal = true;
        },
        closeFiscalModal() {
            this.showFiscalModal = false;
        },
        saveFiscal() {
            this.fiscalForm.patch(route('branches.fiscal.update', this.branch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Datos fiscales actualizados correctamente.');
                    this.showFiscalModal = false;
                },
                onError: () => {
                    ElMessage.error('Revisa los campos e inténtalo de nuevo.');
                },
            });
        },
        deleteContact() {
            const contactId = this.showConfirmDeleteContact.contactId;
            router.delete(route('contacts.destroy', contactId), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Contacto eliminado correctamente');
                    this.showConfirmDeleteContact = { show: false, contactId: null };
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar el contacto');
                }
            });
        },
        getContactDetails(contact, type) {
            if (!contact.details) return [];
            return contact.details.filter(d => d.type === type);
        },
        getPrimaryDetail(contact, type) {
            if (!contact.details) return 'No disponible';

            // Buscar detalle primario
            const primary = contact.details.find(d => d.type === type && d.is_primary);
            if (primary) return primary.value;

            // Si no hay primario, tomar el primero que coincida con el tipo
            const first = contact.details.find(d => d.type === type);
            return first ? first.value : 'No disponible';
        },
        saveProducts() {
            this.form.post(route('branches.add-products', this.branch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Productos asignados correctamente.');
                    this.showAddProductsModal = false;
                    this.form.reset();
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al asignar los productos.');
                }
            });
        },
        async getProductMedia() {
            if (!this.currentProduct.product_id) return;
            
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.product_id));
                if (response.status === 200) {
                    const product = response.data.product;
                    this.currentProduct.media = product.media;
                    this.currentProduct.base_price = product.base_price;
                    this.currentProduct.current_stock = product.current_stock;
                    this.currentProduct.location = product.location;
                }
            } catch (error) {
                console.error("Error al cargar detalles del producto:", error);
                ElMessage.error('No se pudo cargar la información del producto.');
                this.resetCurrentProduct();
            } finally {
                this.loadingProductMedia = false;
            }
        },
        addProduct() {
            if (!this.currentProduct.product_id) {
                ElMessage.warning('Debes seleccionar un producto.');
                return;
            }
            if (this.isProductInForm(this.currentProduct.product_id)) {
                ElMessage.warning('Este producto ya está en la lista.');
                return;
            }
            this.form.products.push({ ...this.currentProduct });
            this.resetCurrentProduct();
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        resetCurrentProduct() {
            this.currentProduct = {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            };
        },
        getProductName(productId) {
            const product = this.catalog_products.find(p => p.id === productId);
            return product ? product.name : `ID: ${productId}`;
        },
        isProductInForm(productId) {
            return this.form.products.some(p => p.product_id === productId);
        },
        formatBirthday(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString + 'T00:00:00'); 
            return format(date, "d 'de' MMMM", { locale: es });
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            if (diffMs < 0) return "En el futuro";
            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);
            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
        },
        async deleteItem() {
            try {
                const response = await axios.delete(route('branches.destroy', this.branch.id));
                ElMessage.success(response.data?.message || 'Cliente eliminado con éxito.');
                this.showConfirmModal = false;
                this.$inertia.visit(route('branches.index'));
            } catch (err) {
                ElMessage.error(err.response?.data?.message || 'Ocurrió un error al eliminar el cliente.');
                console.error(err);
                this.showConfirmModal = false;
            }
        },
        async uploadCsf(files) {
            if (!files || !files.length) return;
            const formData = new FormData();
            formData.append('csf', files[0]);
            try {
                const response = await axios.post(route('branches.csf.store', this.branch.id), formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                ElMessage.success(response.data?.message || 'CSF actualizada correctamente.');
                this.$inertia.reload({ only: ['branch'], preserveScroll: true });
            } catch (err) {
                ElMessage.error(err.response?.data?.message || 'No se pudo subir la CSF.');
            }
        },
        async deleteCsf() {
            if (!this.csfMedia) return;
            try {
                await ElMessageBox.confirm('¿Deseas eliminar la CSF actual? Esta acción no se puede deshacer.', 'Eliminar CSF', {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                });
            } catch {
                return;
            }
            try {
                await axios.delete(route('media.delete-file', this.csfMedia.id));
                ElMessage.success('CSF eliminada correctamente.');
                this.$inertia.reload({ only: ['branch'], preserveScroll: true });
            } catch (err) {
                ElMessage.error('No se pudo eliminar la CSF.');
            }
        },
        // --- Grupo del cliente ---
        isMemberExpanded(memberId) {
            return !!this.expandedMembers[memberId];
        },
        toggleMember(member) {
            this.expandedMembers[member.id] = !this.expandedMembers[member.id];
        },
        // --- Sucursales hijas (matriz) ---
        childCandidateLabel(item) {
            const extras = [];
            if (item.children_count) extras.push(`${item.children_count} sucursal(es)`);
            if (item.rfc) extras.push(item.rfc);
            return extras.length ? `${item.name} — ${extras.join(' · ')}` : item.name;
        },
        async remoteChildSearch(query) {
            if (!query || query.length < 2) {
                this.childCandidates = [];
                return;
            }

            this.searchingChildren = true;
            try {
                const response = await axios.get(route('branches.children.candidates', this.branch.id), {
                    params: { query },
                });
                this.childCandidates = response.data.items ?? [];
            } catch (error) {
                console.error(error);
            } finally {
                this.searchingChildren = false;
            }
        },
        async addChild(childId) {
            if (!childId) return;

            try {
                await axios.post(route('branches.children.add', this.branch.id), { child_ids: [childId] });
                ElMessage.success('Sucursal agregada correctamente.');
                this.childToAdd = null;
                this.childCandidates = [];
                this.showAddChild = false;
                this.$inertia.reload({ only: ['branch'], preserveScroll: true });
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo agregar la sucursal');
                this.childToAdd = null;
            }
        },
        reloadGroupData() {
            this.$inertia.reload({
                only: ['branch', 'groups', 'groupName', 'groupBranchId', 'groupBranchName', 'groupBranchIsChild', 'groupMembers'],
                preserveScroll: true,
            });
        },
        async assignGroup(value) {
            if (!value) {
                return this.removeGroup();
            }

            this.savingGroup = true;
            try {
                await axios.post(route('branches.groups.add', this.groupBranchId), { group_name: value });
                ElMessage.success('Grupo asignado correctamente.');
                this.reloadGroupData();
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo asignar el grupo');
                this.selectedGroup = this.groupName ?? null;
            } finally {
                this.savingGroup = false;
            }
        },
        async removeGroup() {
            if (!this.groupName) {
                this.selectedGroup = null;
                return;
            }

            this.savingGroup = true;
            try {
                await axios.delete(route('branches.groups.remove', this.groupBranchId));
                ElMessage.success('Cliente removido del grupo.');
                this.selectedGroup = null;
                this.showGroupMembers = false;
                this.reloadGroupData();
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo quitar del grupo');
            } finally {
                this.savingGroup = false;
            }
        },
        async remoteGroupSearch(query) {
            if (!query || query.length < 2) {
                this.groupSearchResults = [];
                return;
            }

            this.groupSearching = true;
            try {
                const response = await axios.get(route('branches.groups.search'), {
                    params: { query, group: this.groupName },
                });
                this.groupSearchResults = response.data.items ?? [];
            } catch (error) {
                console.error(error);
            } finally {
                this.groupSearching = false;
            }
        },
        async addGroupMember(branchId) {
            if (!branchId || !this.groupName) return;

            try {
                await axios.post(route('branches.groups.add', branchId), { group_name: this.groupName });
                ElMessage.success('Cliente agregado al grupo.');
                this.branchToAdd = null;
                this.groupSearchResults = [];
                this.reloadGroupData();
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo agregar el cliente al grupo');
            }
        },
        async removeGroupMember(member) {
            this.removingGroupId = member.id;
            try {
                await axios.delete(route('branches.groups.remove', member.id));
                ElMessage.success('Cliente removido del grupo.');
                this.reloadGroupData();
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo remover el cliente del grupo');
            } finally {
                this.removingGroupId = null;
            }
        },
        async fetchQuotes() {
            try {
                this.loadingQuotes = true;
                const response = await axios.get(route('quotes.branch-quotes', this.branch.id));
                if ( response.status === 200 ) {
                    this.quotes = response.data
                }
            } catch (error) {
                console.error("Error al cargar cotizaciones:", error);
                ElMessage.error('Ocurrió un error al cargar cotizaciones.');
            } finally {
                this.loadingQuotes = false;
            }
        },
        async fetchSales() {
            try {
                this.loadingSales = true;
                const response = await axios.get(route('sales.branch-sales', this.branch.id));
                this.sales = response.data;
            } catch (error) {
                console.error("Error al cargar las ventas:", error);
                ElMessage.error('Ocurrió un error al cargar las ventas.');
            } finally {
                this.loadingSales = false;
            }
        },
    },
    watch: {
        activeTab(newTab) {
            // Actualizar la URL sin recargar la página cuando cambia la pestaña
            const url = new URL(window.location.href);
            url.searchParams.set('tab', newTab);
            window.history.replaceState({}, '', url);
        },
        'branch.id'(newId, oldId) {
            if (newId !== oldId) {
                this.selectedBranch = newId;
                this.activeTab = 'general';
                this.showGroupMembers = false;
                this.branchToAdd = null;
                this.groupSearchResults = [];
                this.expandedMembers = {};
                this.showAddChild = false;
                this.childToAdd = null;
                this.childCandidates = [];
                this.fetchQuotes();
                this.fetchSales();
            }
        },
        groupName(newName) {
            this.selectedGroup = newName ?? null;
        },
    },
    mounted() {
        this.fetchQuotes();
        this.fetchSales();
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}

/* Ocultar barra de scroll principal de tarjetas pero mantener funcionamiento */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 20px;
}
</style>