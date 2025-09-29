<?php

namespace App\Http\Controllers;

use App\Models\FavoredProduct;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
// use App\Models\Storage;
use Illuminate\Support\Facades\Storage;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Mail\EmailSupplierTemplateMarkdownMail;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = auth()->user();

        // Inicializar la consulta de compras
        $query = Purchase::with(['supplier:id,name', 'user:id,name', 'authorizer:id,name', 'items.product:id,name,measure_unit', 'items.product.media'])
                         ->latest();

        // Filtrar por "mis compras" o "todas las compras"
        $view = $request->input('view', 'my'); // 'my' es el valor por defecto

        if ($view === 'my') {
            $query->where('user_id', $user->id);
        }

        // Paginar los resultados
        $purchases = $query->paginate(15)->withQueryString();

        // return $purchases;
        // Devolver la vista de Inertia con los datos
        return Inertia::render('Purchase/Index', [
            'purchases' => $purchases,
            'filters' => ['view' => $view],
        ]);
    }

    public function create()
    {
        // Se envían solo los proveedores activos.
        $suppliers = Supplier::select('id', 'name')->get();
        
        // Se podrían enviar también los productos, pero es mejor cargarlos dinámicamente
        // al seleccionar un proveedor para mejorar el rendimiento.
        return Inertia::render('Purchase/Create', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        // Validación de los datos del formulario, incluyendo los nuevos campos
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_contact_id' => 'nullable|exists:contacts,id',
            'supplier_bank_account_id' => 'nullable|exists:supplier_bank_accounts,id',
            'expected_delivery_date' => 'required|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string|max:1000',
            'is_spanish_template' => 'required|boolean',
            // 'type' => 'required|string|in:Venta,Muestra',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.additional_stock' => 'nullable|numeric|min:0',
            'items.*.plane_stock' => 'nullable|numeric|min:0',
            'items.*.ship_stock' => 'nullable|numeric|min:0',
            'items.*.type' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear la orden de compra principal con todos los datos
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => auth()->id(),
                'supplier_contact_id' => $request->supplier_contact_id,
                'supplier_bank_account_id' => $request->supplier_bank_account_id,
                'expected_delivery_date' => $request->expected_delivery_date,
                'currency' => $request->currency,
                'notes' => $request->notes,
                'is_spanish_template' => $request->is_spanish_template,
                // 'type' => $request->type,
                'subtotal' => $request->subtotal,
                'tax' => $request->tax,
                'total' => $request->total,
                'status' => 'Pendiente', // Estatus inicial
                'emited_at' => now(),
            ]);

            // 2. Crear los items de la orden de compra
            foreach ($request->items as $itemData) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'description' => $itemData['product_name'], // Se toma del form
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    'additional_stock' => $itemData['additional_stock'] ?? 0,
                    'plane_stock' => $itemData['plane_stock'] ?? 0,
                    'ship_stock' => $itemData['ship_stock'] ?? 0,
                    'type' => $itemData['type'] ?? 'Venta',
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // 3. Lógica para sumar el stock a favor
                $favoredQuantity = $itemData['additional_stock'] ?? 0;
                if ($favoredQuantity > 0) {
                    $favoredProduct = FavoredProduct::firstOrNew([
                        'supplier_id' => $request->supplier_id,
                        'product_id' => $itemData['product_id'],
                    ]);

                    $favoredProduct->quantity += $favoredQuantity;
                    $favoredProduct->save();
                }
            }

            // manejo de media (archivos extra)
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $purchase->addMedia($file)->toMediaCollection();
                }
            }
            
            DB::commit();
            
            // Redirigir al index con un mensaje de éxito
            return redirect()->route('purchases.index')->with('success', 'Órden de compra creada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear la orden de compra: " . $e->getMessage());
            // Redirigir de vuelta con un mensaje de error
            return back()->withErrors(['general' => 'Ocurrió un error inesperado. Por favor, inténtelo de nuevo.'])->withInput();
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'user:id,name', 
            'authorizer:id,name',
            'supplier:id,name,address,phone',
            'contact:id,name',
            'contact.details',
            'items.product.media',
            'bankAccount',
            'media' // Cargar los archivos adjuntos (evidencia)
        ]);

        return Inertia::render('Purchase/Show', [
            'purchase' => $purchase,
        ]);
    }

    public function edit(Purchase $purchase)
    {
        // Cargar las relaciones necesarias para la vista de edición
        $purchase->load(['items.product', 'supplier', 'media']);

        // Se envían todos los proveedores para el selector
        $suppliers = Supplier::select('id', 'name')->get();
        
        return Inertia::render('Purchase/Edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        // Validación de los datos del formulario
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_contact_id' => 'nullable|exists:contacts,id',
            'supplier_bank_account_id' => 'nullable|exists:supplier_bank_accounts,id',
            'expected_delivery_date' => 'required|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string|max:1000',
            'is_spanish_template' => 'required|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.additional_stock' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
            'media.*' => 'nullable|file',
            'current_media_ids' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // 1. RESPALDAR Y REVERTIR CANTIDADES A FAVOR ANTERIORES
            // Obtenemos los items originales antes de cualquier cambio.
            $originalItems = $purchase->items()->get();
            foreach ($originalItems as $item) {
                if ($item->additional_stock > 0) {
                    $favoredProduct = FavoredProduct::where('supplier_id', $purchase->supplier_id)
                                                    ->where('product_id', $item->product_id)
                                                    ->first();
                    if ($favoredProduct) {
                        // Descontamos la cantidad que se había agregado previamente.
                        $favoredProduct->quantity = max(0, $favoredProduct->quantity - $item->additional_stock);
                        $favoredProduct->save();
                    }
                }
            }

            // 2. Actualizar la orden de compra principal
            $purchase->update($request->except(['items', 'media', 'current_media_ids']));

            // 3. Eliminar los items antiguos y crear los nuevos
            $purchase->items()->delete();
            foreach ($request->items as $itemData) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'description' => $itemData['product_name'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    'additional_stock' => $itemData['additional_stock'] ?? 0,
                    'plane_stock' => $itemData['plane_stock'] ?? 0,
                    'ship_stock' => $itemData['ship_stock'] ?? 0,
                    'type' => $itemData['type'] ?? 'Venta',
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // 4. AGREGAR LAS NUEVAS CANTIDADES A FAVOR
                $newFavoredQuantity = $itemData['additional_stock'] ?? 0;
                if ($newFavoredQuantity > 0) {
                    // Usamos firstOrNew para encontrar el registro o prepararlo si no existe.
                    $favoredProduct = FavoredProduct::firstOrNew([
                        'supplier_id' => $purchase->supplier_id,
                        'product_id' => $itemData['product_id'],
                    ]);
                    // Sumamos la nueva cantidad.
                    $favoredProduct->quantity += $newFavoredQuantity;
                    $favoredProduct->save();
                }
            }

            // 5. Manejo de media
            if ($request->hasFile('media')) {
                $purchase->addMediaFromRequest('media')->toMediaCollection();
            }

            DB::commit();

            return redirect()->route('purchases.show', $purchase->id)->with('success', 'Órden de compra actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar la orden de compra: " . $e->getMessage());
            return back()->withErrors(['general' => 'Ocurrió un error inesperado. Por favor, inténtelo de nuevo.'])->withInput();
        }
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $purchase = Purchase::find($id);
            $purchase->delete();
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $purchases = Purchase::with(['supplier:id,name', 'user:id,name', 'authorizer:id,name', 'items.product:id,name,measure_unit', 'items.product.media'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('supplier', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $purchases], 200);
    }

    public function authorizePurchase(Purchase $purchase)
    {
        $purchase->update([
            'authorizer_id' => auth()->id(),
            'authorized_at' => now(),
            'status' => 'Autorizada',
        ]);

        $purchase->load(['user', 'authorizer']);

        // return response()->json(['message' => 'Orden autorizada', 'item' => $purchase]);
    }

    public function updateStatus(Request $request, Purchase $purchase)
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(['Compra realizada', 'Compra recibida', 'Cancelada'])],
            'rating' => 'nullable|array',
            'rating.q1' => 'required_with:rating|string',
            'rating.q2' => 'required_with:rating|string',
            'rating.q3_1' => 'required_with:rating|string',
            'rating.q4' => 'required_with:rating|string',
            'rating.q5' => 'required_with:rating|string',
            'rating.notes' => 'nullable|string',
            'evidence_files' => 'nullable|array|max:4',
            'evidence_files.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $newStatus = $request->input('status');
        $currentStatus = $purchase->status;

        try {
            DB::transaction(function () use ($request, $newStatus, $currentStatus, $purchase) {
                switch ($newStatus) {
                    case 'Compra realizada':
                        if ($currentStatus !== 'Autorizada') {
                            throw new \Exception('La compra debe estar "Autorizada" para marcarla como realizada.');
                        }
                        $purchase->update(['status' => $newStatus]);
                        break;

                    case 'Compra recibida':
                        if ($currentStatus !== 'Compra realizada') {
                            throw new \Exception('La compra debe estar marcada como "Realizada" para poder recibirla.');
                        }

                        $updateData = [
                            'status' => $newStatus,
                            'recieved_at' => now(),
                        ];

                        if ($request->has('rating')) {
                            $ratingData = $request->input('rating');
                            $user = auth()->user();
                            $pointsMap = [
                                'q1' => ['Si' => 30],
                                'q2' => ['Sí, cumplió con todo' => 15, 'No, no se cumplieron las especificaciones' => 0],
                                'q3' => ['No se requirió soporte' => 15, 'Atención inmediata' => 10, 'Atención tardía (2 o más días para atender)' => 5, 'No brindó soporte' => 0],
                                'q4' => ['No se presentó ninguna urgencia' => 15, '1 día de atraso' => 10, '2 a 3 días de atraso' => 8, '4 a 5 días de atraso' => 5, 'Más de 6 días de atraso' => 0],
                                'q5' => ['0 avisos de rechazo' => 15, '1 aviso de rechazo' => 5, '2 o más avisos de rechazo' => 0]
                            ];
                            $q3_answer = $ratingData['q3_1'];
                             if ($ratingData['q3_1'] !== 'No se requirió soporte' && !empty($ratingData['q3_2'])) {
                                $q3_answer .= ' (' . $ratingData['q3_2'] . ')';
                            }
                            $questions = [
                                ['answer' => $ratingData['q1'], 'points' => $pointsMap['q1'][$ratingData['q1']] ?? 0],
                                ['answer' => $ratingData['q2'], 'points' => $pointsMap['q2'][$ratingData['q2']] ?? 0],
                                ['answer' => $q3_answer, 'points' => $pointsMap['q3'][$ratingData['q3_2'] ?? $ratingData['q3_1']] ?? 0],
                                ['answer' => $ratingData['q4'], 'points' => $pointsMap['q4'][$ratingData['q4']] ?? 0],
                                ['answer' => $ratingData['q5'], 'points' => $pointsMap['q5'][$ratingData['q5']] ?? 0],
                                ['answer' => $ratingData['notes'], 'points' => 0],
                            ];
                             $updateData['rating'] = [
                                'questions' => $questions,
                                'created_at' => now()->format('Y-m-d H:i:s'),
                                'created_by' => $user->name,
                            ];
                        }
                        
                        $purchase->update($updateData);

                        if ($request->hasFile('evidence_files')) {
                            foreach ($request->file('evidence_files') as $file) {
                                $purchase->addMedia($file)->toMediaCollection('evidence_files');
                            }
                        }

                        $purchase->load('items.product');

                        // --- INICIO DE LA MODIFICACIÓN ---
                        foreach ($purchase->items as $item) {
                            if ($item->product) {
                                // Se calcula la cantidad a agregar sumando lo que llega por avión y barco.
                                $quantityToAdd = ($item->plane_stock ?? 0) + ($item->ship_stock ?? 0);

                                // Solo se ejecuta la actualización si la cantidad es mayor a cero.
                                if ($quantityToAdd > 0) {
                                    $storage = $item->product->storages()->firstOrCreate([], ['quantity' => 0]);
                                    $storage->increment('quantity', $quantityToAdd);
                                    
                                    StockMovement::create([
                                        'product_id' => $item->product->id,
                                        'storage_id' => $storage->id,
                                        'quantity_change' => $quantityToAdd, // Se usa la nueva cantidad calculada.
                                        'type' => 'Entrada',
                                        'notes' => 'Entrada por orden de compra recibida OC-' . $purchase->id
                                    ]);
                                }
                            }
                        }
                        // --- FIN DE LA MODIFICACIÓN ---
                        break;

                    case 'Cancelada':
                        if (in_array($currentStatus, ['Compra recibida', 'Cancelada'])) {
                            throw new \Exception('No se puede cancelar una compra que ya ha sido recibida o que ya está cancelada.');
                        }
                        $purchase->update(['status' => $newStatus]);
                        break;

                    default:
                        throw new \Exception('Estatus no válido o transición no permitida.');
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "El estatus de la compra se actualizó a \"{$newStatus}\".");
    }

    public function print(Purchase $purchase)
    {
        // Carga las relaciones necesarias para la orden de compra específica
        $purchase->load([
            'supplier.contacts',
            'supplier.bankAccounts',
            'bankAccount',
            'contact',
            'authorizer',
            'items.product.media',
            'user',
        ]);

        // Carga todos los proveedores con sus contactos y cuentas bancarias para el modal
        $allSuppliers = Supplier::with(['contacts', 'bankAccounts'])->get();

        // Renderiza la vista de Inertia pasando ambos conjuntos de datos
        return Inertia::render('Purchase/Print', [
            'purchase' => $purchase,
            'allSuppliers' => $allSuppliers, // Nueva prop con todos los proveedores
        ]);
    }

    /**
     * Envía la orden de compra por correo al proveedor.
     * Esta función también marca la orden como autorizada.
     */
    public function sendEmail(Request $request, Purchase $purchase)
    {
        $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'supplier_bank_account_id' => 'required|exists:supplier_bank_accounts,id',
            'subject' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        // 1. Actualizar la orden de compra con la información del formulario
        // y marcarla como autorizada.
        $purchase->update([
            'contact_id' => $request->contact_id,
            'supplier_bank_account_id' => $request->supplier_bank_account_id,
            'authorizer_id' => auth()->id(),
            'authorized_at' => now(),
            'status' => 'Autorizada', // Actualizamos el estado
        ]);

        // 2. Cargar las relaciones necesarias para generar el PDF
        $purchase->load(['supplier', 'items.product.media', 'bankAccount']);

        // 3. Generar el PDF usando la nueva plantilla moderna
        $pdf = Pdf::loadView('pdfTemplates.purchase-order', ['purchase' => $purchase]);
        $fileName = 'OC-' . str_pad($purchase->id, 4, "0", STR_PAD_LEFT) . '.pdf';
        $content = $pdf->download()->getOriginalContent();

        // 4. Guardar el PDF en el almacenamiento
        $path = "public/purchase-orders/{$fileName}";
        Storage::put($path, $content);

        // 5. Enviar el correo electrónico
        $attachment = [
            'path' => Storage::path($path),
            'name' => $fileName,
        ];

        $contact = $purchase->supplier->contacts()->find($request->contact_id);

        try {
            Mail::to($contact->email) // correo real
            // Mail::to('angelvazquez470@gmail.com') // correo de prueba
                ->bcc(auth()->user()->email) // Opcional: enviar copia al usuario autenticado
                ->send(new EmailSupplierTemplateMarkdownMail($request->subject, $request->content, $attachment));
        } catch (\Exception $e) {
            // Manejo de errores en caso de que el envío falle
            return response()->json(['message' => 'Error al enviar el correo: ' . $e->getMessage()], 500);
        }

        // 6. Eliminar el archivo después de enviarlo (opcional)
        Storage::delete($path);

        // return response()->json(['message' => 'Correo enviado exitosamente']);
    }
}
