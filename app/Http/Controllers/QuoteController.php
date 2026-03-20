<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteProduct;
use App\Models\User;
use App\Notifications\ApprovalQuoteNotification;
use App\Notifications\NewQuoteForApprovalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $showAll = $request->query('view') === 'all';
        $query = Quote::query();

        $query->where('is_active', true);

        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        $query->withCount('allVersions');

        // NOTA IMPORTANTE: Cambiamos 'products' por 'quoteProducts.product' para que traiga
        // la relación correcta (incluso los custom) y no ignore los productos "al vuelo".
        // Recuerda ajustar la vista Index si accede directo a 'products'.
        $quotes = $query->with(['branch:id,name,status', 'user:id,name', 'sale:id', 'authorizedBy:id,name', 'quoteProducts.product:id,name,cost'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Quote/Index', [
            'quotes' => $quotes,
            'filters' => $request->only(['view']),
        ]);
    }

    public function create()
    {
        $catalogProducts = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name', 'code')->get();
        $branches = Branch::select('id', 'name', 'status')->get();

        return Inertia::render('Quote/Create', [
            'catalogProducts' => $catalogProducts,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        // 1. Verificamos si en la lista de productos viene alguno marcado como "custom" (nuevo)
        $hasCustomProduct = collect($request->products)->contains(function ($product) {
            return filter_var($product['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
        });

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'receiver' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            // El costo de herramental es dinámicamente requerido si hay productos custom
            'tooling_cost' => [$hasCustomProduct ? 'required' : 'nullable', 'string'],
            'freight_cost' => 'required_unless:freight_option,El cliente manda la guia,Client sends the shipping label,Por cuenta del cliente,Paid by the client|nullable|numeric|min:0',
            'freight_option' => 'required|string',
            'first_production_days' => 'required|string',
            'validity' => 'nullable|string|max:255', // NUEVA VALIDACIÓN AGREGADA
            'has_early_payment_discount' => 'nullable|boolean',
            'early_payment_discount_amount' => ['exclude_unless:has_early_payment_discount,true', 'required', 'numeric', 'min:1', 'max:100'],
            'products' => 'required|array|min:1',
            // Validaciones combinadas para soporte de catálogos y productos nuevos
            'products.*.id' => 'nullable|exists:products,id',
            'products.*.is_custom' => 'required|boolean',
            'products.*.custom_name' => 'nullable|string',
            'products.*.custom_cost' => 'nullable|numeric|min:0',
            'products.*.image' => 'nullable|file|image|max:2048', // Hasta 2MB
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
        ]);

        $quote = null;

        DB::transaction(function () use ($request, &$quote) {
            $quote = new Quote();
            $quote->user_id = auth()->id();
            $quote->branch_id = $request->branch_id;
            $quote->receiver = $request->receiver;
            $quote->department = $request->department;
            $quote->currency = $request->currency;
            $quote->tooling_cost = $request->tooling_cost;
            $quote->is_tooling_cost_stroked = filter_var($request->is_tooling_cost_stroked, FILTER_VALIDATE_BOOLEAN);
            $quote->freight_cost = $request->freight_cost;
            $quote->is_freight_cost_stroked = filter_var($request->is_freight_cost_stroked, FILTER_VALIDATE_BOOLEAN);
            $quote->freight_option = $request->freight_option;
            $quote->first_production_days = $request->first_production_days;
            $quote->validity = $request->validity; // GUARDADO DEL NUEVO CAMPO
            $quote->notes = $request->notes;
            $quote->is_spanish_template = filter_var($request->is_spanish_template, FILTER_VALIDATE_BOOLEAN);
            $quote->show_breakdown = filter_var($request->show_breakdown, FILTER_VALIDATE_BOOLEAN);
            $quote->has_early_payment_discount = filter_var($request->has_early_payment_discount, FILTER_VALIDATE_BOOLEAN);
            $quote->early_payment_discount_amount = $request->early_payment_discount_amount ?? 0;
            
            $quote->version = 1;
            $quote->is_active = true;
            $quote->status = 'Esperando respuesta';

            $quote->save();
            $quote->root_quote_id = $quote->id;
            $quote->save();

            // 3. Adjuntar productos (Usando la relación quoteProducts HasMany en vez de attach)
            foreach ($request->products as $product) {
                $isCustom = filter_var($product['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $quoteProduct = $quote->quoteProducts()->create([
                    'product_id' => $isCustom ? null : $product['id'],
                    'custom_name' => $isCustom ? ($product['custom_name'] ?? null) : null,
                    'custom_cost' => $isCustom ? ($product['custom_cost'] ?? 0) : null,
                    'custom_measure_unit' => $isCustom ? ($product['custom_measure_unit'] ?? null) : null,
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'notes' => $product['notes'] ?? null,
                    'show_image' => filter_var($product['show_image'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'customization_details' => !empty($product['customization_details']) ? $product['customization_details'] : null,
                    'customer_approval_status' => 'Aprobado',
                ]);

                // 4. Si es un producto custom y se adjuntó una imagen, la guardamos
                if ($isCustom && isset($product['image']) && $product['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $quoteProduct->addMedia($product['image'])->toMediaCollection('custom_product_images');
                }
            }
            
            if ($quote) {
                $usersToNotify = User::permission('Autorizar ordenes de venta')->get();
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new NewQuoteForApprovalNotification($quote));
                }
            }
        });

        return Redirect::route('quotes.index')->with('success', 'Cotización (v1) creada exitosamente.');
    }

    public function show(Quote $quote)
    {
        $quote->load('branch');
        $productSourceBranch = $this->getProductTargetBranch($quote->branch);

        // Actualizado para cargar quoteProducts en lugar de products
        $quote->load([
            'user', 
            'authorizedBy',
            'quoteProducts.media', // Para imágenes de productos al vuelo
            'quoteProducts.product.media', // Para imágenes de catálogo
            'quoteProducts.product.priceHistory' => function ($query) use ($productSourceBranch) {
                $query->where('branch_id', $productSourceBranch->id)
                      ->orderBy('valid_from', 'desc');
            }
        ]);
        
        $allVersions = $quote->allVersions()
                            ->select('id', 'version', 'created_at', 'root_quote_id', 'status') 
                            ->get();

        $currentIndex = $allVersions->search(function ($v) use ($quote) {
            return $v->id == $quote->id;
        });

        $prev_version_id = $allVersions->get($currentIndex - 1)?->id; 
        $next_version_id = $allVersions->get($currentIndex + 1)?->id; 

        $quotes = Quote::orderBy('id')->get();
        $currentQuoteIndex = $quotes->search(function ($q) use ($quote) {
            return $q->id == $quote->id;
        });
        
        $nextQuote = $quotes->get(($currentQuoteIndex + 1) % $quotes->count());
        $prevQuote = $quotes->get(($currentQuoteIndex - 1 + $quotes->count()) % $quotes->count());

        return Inertia::render('Quote/Show', [
            'quote' => $quote,
            'allVersions' => $allVersions,
            'next_version_id' => $next_version_id,
            'prev_version_id' => $prev_version_id,
            'next_quote' => $nextQuote->id,
            'prev_quote' => $prevQuote->id,
        ]);
    }

    private function getProductTargetBranch($branch)
    {
        return $branch->parent ?? $branch;
    }

    public function edit(Quote $quote)
    {
        // Eager load completo para la edición
        $quote->load('quoteProducts.media', 'quoteProducts.product.media');

        $catalogProducts = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name', 'code')->get();
        $branches = Branch::select('id', 'name', 'status')->get();

        return Inertia::render('Quote/Edit', [
            'catalogProducts' => $catalogProducts,
            'branches' => $branches,
            'quote' => $quote,
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $hasCustomProduct = collect($request->products)->contains(function ($product) {
            return filter_var($product['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
        });

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'receiver' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'tooling_cost' => [$hasCustomProduct ? 'required' : 'nullable', 'string'],
            'freight_cost' => 'required_unless:freight_option,El cliente manda la guia,Client sends the shipping label,Por cuenta del cliente,Paid by the client|nullable|numeric|min:0',
            'freight_option' => 'required|string',
            'first_production_days' => 'required|string',
            'validity' => 'nullable|string|max:255', // NUEVA VALIDACIÓN EN UPDATE
            'has_early_payment_discount' => 'nullable|boolean',
            'early_payment_discount_amount' => ['exclude_unless:has_early_payment_discount,true', 'required', 'numeric', 'min:1', 'max:100'],
            'products' => 'required|array|min:1',
            'products.*.id' => 'nullable|exists:products,id',
            'products.*.is_custom' => 'required|boolean',
            'products.*.custom_name' => 'nullable|string',
            'products.*.custom_cost' => 'nullable|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        $newQuote = null;

        DB::transaction(function () use ($request, $quote, &$newQuote) {
            
            $rootId = $quote->root_quote_id ?? $quote->id;
            $latestVersionNum = Quote::where('root_quote_id', $rootId)->max('version');

            Quote::where('root_quote_id', $rootId)->update(['is_active' => false]);

            $newQuote = $quote->replicate();

            $newQuote->branch_id = $request->branch_id;
            $newQuote->receiver = $request->receiver;
            $newQuote->department = $request->department;
            $newQuote->currency = $request->currency;
            $newQuote->tooling_cost = $request->tooling_cost;
            $newQuote->is_tooling_cost_stroked = filter_var($request->is_tooling_cost_stroked, FILTER_VALIDATE_BOOLEAN);
            $newQuote->freight_cost = $request->freight_cost;
            $newQuote->is_freight_cost_stroked = filter_var($request->is_freight_cost_stroked, FILTER_VALIDATE_BOOLEAN);
            $newQuote->freight_option = $request->freight_option;
            $newQuote->first_production_days = $request->first_production_days;
            $newQuote->validity = $request->validity; // GUARDADO EN UPDATE
            $newQuote->notes = $request->notes;
            $newQuote->is_spanish_template = filter_var($request->is_spanish_template, FILTER_VALIDATE_BOOLEAN);
            $newQuote->show_breakdown = filter_var($request->show_breakdown, FILTER_VALIDATE_BOOLEAN);
            $newQuote->has_early_payment_discount = filter_var($request->has_early_payment_discount, FILTER_VALIDATE_BOOLEAN);
            $newQuote->early_payment_discount_amount = $request->early_payment_discount_amount ?? 0;

            $newQuote->version = $latestVersionNum + 1;
            $newQuote->is_active = true;
            $newQuote->root_quote_id = $rootId;
            $newQuote->status = 'Esperando respuesta';
            $newQuote->sale_id = null;
            $newQuote->customer_responded_at = null;
            $newQuote->rejection_reason = null;
            $newQuote->created_at = now();
            
            $newQuote->save();

            foreach ($request->products as $product) {
                $isCustom = filter_var($product['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $quoteProduct = $newQuote->quoteProducts()->create([
                    'product_id' => $isCustom ? null : $product['id'],
                    'custom_name' => $isCustom ? ($product['custom_name'] ?? null) : null,
                    'custom_cost' => $isCustom ? ($product['custom_cost'] ?? 0) : null,
                    'custom_measure_unit' => $isCustom ? ($product['custom_measure_unit'] ?? null) : null,
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'notes' => $product['notes'] ?? null,
                    'show_image' => filter_var($product['show_image'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'customization_details' => !empty($product['customization_details']) ? $product['customization_details'] : null,
                    'customer_approval_status' => 'Aprobado',
                ]);

                // 4. Si es un producto custom manejamos la imagen
                if ($isCustom) {
                    if (isset($product['image']) && $product['image'] instanceof \Illuminate\Http\UploadedFile) {
                        // Si el usuario subió una NUEVA imagen al editar, la guardamos
                        $quoteProduct->addMedia($product['image'])->toMediaCollection('custom_product_images');
                    } elseif (!empty($product['quote_product_id'])) {
                        // Si no hay imagen nueva, buscamos el producto original de la versión anterior y clonamos su imagen
                        $oldQp = QuoteProduct::find($product['quote_product_id']);
                        if ($oldQp && $oldQp->hasMedia('custom_product_images')) {
                            $oldQp->getFirstMedia('custom_product_images')->copy($quoteProduct, 'custom_product_images');
                        }
                    }
                }
            }

            if ($newQuote && is_null($newQuote->authorized_at)) {
                $usersToNotify = User::permission('Autorizar ordenes de venta')->get();
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new NewQuoteForApprovalNotification($newQuote));
                }
            }
        });

        return Redirect::route('quotes.index')->with('success', 'Cotización actualizada. Se ha creado la versión ' . $newQuote->version);
    }

    public function destroy(Quote $quote) { /* ... */ }

    public function massiveDelete(Request $request)
    {
        $affectedUserIds = [];

        foreach ($request->ids as $id) {
            $quote = Quote::find($id);
            if ($quote) {
                $affectedUserIds[] = $quote->user_id;
                $quote->delete();
            }
        }

        $uniqueUserIds = array_unique($affectedUserIds);
        $threeDaysAgo = Carbon::now()->subDays(3);

        foreach ($uniqueUserIds as $userId) {
            $user = User::find($userId);
            if (!$user) continue;

            $remainingPendingQuotes = Quote::where('user_id', $user->id)
                ->where('status', 'Esperando respuesta')
                ->where('created_at', '<=', $threeDaysAgo)
                ->where('is_active', true)
                ->whereNotIn('user_id', [2, 3])
                ->get();

            if ($remainingPendingQuotes->isNotEmpty()) {
                $alertContent = [
                    'type' => 'pending_quotations',
                    'title' => 'Cotizaciones Pendientes',
                    'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días.',
                    'quote_ids' => $remainingPendingQuotes->pluck('id')->toArray(),
                ];
                $user->addActiveAlert('pending_quotations', $alertContent);
            } else {
                $user->removeActiveAlert('pending_quotations');
            }
        }
    }

    public function authorizeQuote(Quote $quote)
    {
        $quote->update([
            'authorized_by_user_id' => auth()->id(),
            'authorized_at' => now(),
        ]);

        if (auth()->id() != $quote->user->id) {
            $quote_folio = 'COT-' . str_pad($quote->id, 4, "0", STR_PAD_LEFT);
            $quote->user->notify(new ApprovalQuoteNotification(
                'Cotización',
                $quote_folio,
                'quote',
                route('quotes.show', $quote->id)
            ));
        }

        return response()->json(['message' => 'Cotizacion autorizada', 'item' => $quote]);
    }

    public function clone(Quote $quote)
    {
        try {
            $originalQuote = Quote::with('quoteProducts')->findOrFail($quote->id);
            $newQuote = $originalQuote->replicate();

            $newQuote->status = 'Esperando respuesta';
            $newQuote->sale_id = null;
            $newQuote->authorized_at = null;
            $newQuote->authorized_by_user_id = null;
            $newQuote->customer_responded_at = null;
            $newQuote->rejection_reason = null;
            $newQuote->created_by_customer = false;
            $newQuote->user_id = auth()->id();
            $newQuote->save();

            // CLONACIÓN MODIFICADA PARA SOPORTAR PRODUCTOS CUSTOM Y SUS IMÁGENES
            foreach ($originalQuote->quoteProducts as $qp) {
                $newQp = $newQuote->quoteProducts()->create([
                    'product_id' => $qp->product_id,
                    'custom_name' => $qp->custom_name,
                    'custom_cost' => $qp->custom_cost,
                    'custom_measure_unit' => $qp->custom_measure_unit,
                    'quantity' => $qp->quantity,
                    'unit_price' => $qp->unit_price,
                    'notes' => $qp->notes,
                    'show_image' => $qp->show_image,
                    'customization_details' => $qp->customization_details,
                    'customer_approval_status' => $qp->customer_approval_status,
                ]);

                // Clonar la imagen si el producto clonado la posee (Spatie Media Library)
                if ($qp->is_custom && $qp->hasMedia('custom_product_images')) {
                    $qp->getFirstMedia('custom_product_images')->copy($newQp, 'custom_product_images');
                }
            }

            $newQuote->load('user', 'branch');

            return response()->json([
                'message' => 'Cotización clonada exitosamente',
                'newItem' => $newQuote,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al clonar la cotización: ' . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error al clonar la cotización. Por favor, inténtalo de nuevo.'], 500);
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');
        $quotes = Quote::with(['branch', 'user', 'sale'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $quotes], 200);
    }

    public function markEarlyPayment(Quote $quote) 
    {
        $quote->update(['early_paid_at' => now()]);
        return response()->json(['quote' => $quote]);
    }

    public function changeStatus(Quote $quote, Request $request)
    {
        $status = $request->input('new_status');
        $data = ['status' => $status];
        $rejection_reason = $request->input('rejection_reason');

        if ($status === 'Rechazada') {
            $data['rejection_reason'] = $rejection_reason;
            $data['customer_responded_at'] = now();
        }

        if ($status === 'Aceptada') {
            $data['customer_responded_at'] = now();
        }

        $quote->update($data);

        $user = $quote->user;
        $threeDaysAgo = Carbon::now()->subDays(3);
        $remainingPendingQuotes = Quote::where('user_id', $user->id)
            ->where('status', 'Esperando respuesta')
            ->where('is_active', true)
            ->where('created_at', '<=', $threeDaysAgo)
            ->get();

        if ($remainingPendingQuotes->isNotEmpty()) {
            $alertContent = [
                'type' => 'pending_quotations',
                'title' => 'Cotizaciones Pendientes',
                'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días.',
                'quote_ids' => $remainingPendingQuotes->pluck('id')->toArray(),
            ];
            $user->addActiveAlert('pending_quotations', $alertContent);
        } else {
            $user->removeActiveAlert('pending_quotations');
        }

        return response()->json(['message' => 'Estatus actualizado', 'quote' => $quote]);
    }

    public function fetchBranchQuotes(Branch $branch)
    {
        $quotes = Quote::with('user:id,name')->where('branch_id', $branch->id)
            ->latest()
            ->take(20)
            ->get(['id', 'user_id', 'authorized_at', 'sale_id', 'status', 'created_at', 'branch_id', 'currency', 'has_early_payment_discount', 'early_paid_at', 'customer_responded_at']);

        return response()->json($quotes);
    }

    public function getDetailsForSale(Quote $quote)
    {
        // Carga profunda usando las líneas de cotización independientes y sus relaciones
        $quote->load('branch', 'quoteProducts.media', 'quoteProducts.product.media');

        $approvedProducts = $quote->quoteProducts
            ->where('customer_approval_status', 'Aprobado')
            ->map(function ($qp) {
                // Usamos los Accessors creados en el modelo QuoteProduct (display_name, unit_cost, image_url, etc)
                return [
                    'id' => $qp->product_id ?? 'custom_' . $qp->id, // Pseudo ID para el frontend si es custom
                    'name' => $qp->display_name,
                    'code' => $qp->is_custom ? 'N/A' : $qp->product->code,
                    'cost' => $qp->unit_cost,
                    'quantity' => $qp->quantity,
                    'unit_price' => $qp->unit_price,
                    'customization_details' => $qp->customization_details,
                    'notes' => $qp->notes,
                    'image_url' => $qp->image_url, 
                ];
            });

        return response()->json([
            'branch_id' => $quote->branch_id,
            'contact_id' => $quote->branch->main_contact_id,
            'freight_option' => $quote->freight_option,
            'freight_cost' => $quote->freight_cost,
            'notes' => $quote->notes,
            'currency' => $quote->currency,
            'products' => $approvedProducts->values(),
        ]);
    }

    public function updateProductStatus(Request $request, QuoteProduct $quoteProduct)
    {
        $request->validate(['status' => 'required|in:Pendiente,Aprobado,Rechazado']);
        $quoteProduct->update(['customer_approval_status' => $request->status]);
        return response()->json(['message' => 'Estatus del producto actualizado con éxito.']);
    }
}