<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\StockProjectionExport;
use Maatwebsite\Excel\Facades\Excel;

class StockProjectionController extends Controller
{
    /**
     * Retorna la vista principal del dashboard usando Inertia
     */
    public function index()
    {
        return Inertia::render('StockProjection/Index');
    }

    /**
     * Obtiene los productos comprables de 100 en 100 para el buscador interactivo
     */
    public function fetchProducts(Request $request)
    {
        $query = Product::where('is_purchasable', true)
                        ->whereNull('archived_at');

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('code', 'like', $searchTerm);
            });
        }

        // Paginación de 100 en 100 para no saturar
        $products = $query->select('id', 'code', 'name', 'min_quantity')
                          ->orderBy('name')
                          ->paginate(100);

        // Agregamos la URL de la imagen principal usando Spatie Media Library con reemplazo de dominio
        $products->getCollection()->transform(function ($product) {
            $url = $product->getFirstMediaUrl('images');
            if ($url) {
                $url = str_replace('http://127.0.0.1:8000', 'https://www.intranetemblems3d.dtw.com.mx', $url);
            }
            $product->image_url = $url ?: null;
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Extrae la data de la proyección basada en los filtros.
     * 
     * LÓGICA CLAVE: Los productos ensamblados (compuestos) se "explotan" en sus
     * componentes comprables. Ejemplo: si se venden 100 "Llavero Toyota c/Medallón",
     * se contabilizan 100 "Llavero Toyota" + 100 "Medallón" para la proyección.
     * Las ventas directas de componentes también se suman normalmente.
     */
    private function getReportData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'product_mode' => 'required|in:all,selected',
            'product_ids' => 'array|required_if:product_mode,selected'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        
        $monthsDiff = $startDate->diffInMonths($endDate);
        if ($monthsDiff < 1) {
            $monthsDiff = 1;
        }

        // ==========================================
        // 1. CARGAR DATOS MAESTROS
        // ==========================================

        // Mapa de explosión: [product_id (int) => [ [component_id (int), quantity (float)], ... ]]
        // Se construye desde product_components y se hereda a variantes (parent_id)
        // igual que hace el accessor Product::actual_components
        $explosionMap = [];
        $rawComponents = DB::table('product_components')
            ->select('catalog_product_id', 'component_product_id', 'quantity')
            ->get();
        foreach ($rawComponents as $row) {
            $assemblyId = (int) $row->catalog_product_id;
            if (!isset($explosionMap[$assemblyId])) {
                $explosionMap[$assemblyId] = [];
            }
            $explosionMap[$assemblyId][] = [
                'component_id' => (int) $row->component_product_id,
                'quantity'     => (float) $row->quantity,
            ];
        }

        // Heredar componentes del padre a las variantes (productos con parent_id)
        // Esto replica la lógica de Product::getActualComponentsAttribute()
        $variantsWithoutOwnComponents = Product::whereNotNull('parent_id')
            ->whereNotIn('id', array_keys($explosionMap))
            ->select('id', 'parent_id')
            ->get();

        foreach ($variantsWithoutOwnComponents as $variant) {
            $parentId = (int) $variant->parent_id;
            if (isset($explosionMap[$parentId])) {
                $explosionMap[(int) $variant->id] = $explosionMap[$parentId];
            }
        }

        // Productos comprables (los que la empresa importa)
        $purchasableProducts = Product::where('is_purchasable', true)
            ->whereNull('archived_at')
            ->select('id', 'code', 'name', 'min_quantity')
            ->get()
            ->keyBy('id');
        $purchasableIds = $purchasableProducts->keys()->map(fn($id) => (int) $id)->toArray();

        // ==========================================
        // 2. QUERY BASE DE VENTAS AUTORIZADAS
        // ==========================================

        $baseQuery = DB::table('sale_products')
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->whereNotNull('sales.authorized_at')
            ->whereBetween('sales.authorized_at', [$startDate, $endDate])
            ->whereIn('sales.status', ['Completada', 'Autorizada', 'Enviada', 'En Proceso', 'Preparando Envío', 'En Producción']);

        // Modo "selected": incluir también los ensamblados que contienen los productos elegidos
        if ($request->product_mode === 'selected' && !empty($request->product_ids)) {
            $selectedIds = array_map('intval', $request->product_ids);
            $assemblyIds = DB::table('product_components')
                ->whereIn('component_product_id', $selectedIds)
                ->pluck('catalog_product_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
            $allRelevantIds = array_unique(array_merge($selectedIds, $assemblyIds));
            $baseQuery->whereIn('sale_products.product_id', $allRelevantIds);
        }

        // ==========================================
        // 3. EXPLOTAR ENSAMBLADOS Y AGREGAR
        // ==========================================

        $rawSales = (clone $baseQuery)
            ->select('sale_products.product_id', 'sale_products.quantity')
            ->get();

        // Debug: registrar si hay datos de explosión y ventas
        \Log::info('StockProjection :: Ensamblados en product_components: ' . count($explosionMap) 
            . ' (directos + ' . $variantsWithoutOwnComponents->count() . ' variantes heredados)'
            . ' | Ventas encontradas en periodo: ' . $rawSales->count()
            . ' | Productos comprables: ' . count($purchasableIds));

        $aggregatedSales = []; // (int) product_id => (int) total_quantity
        $explodedCount = 0;
        foreach ($rawSales as $sale) {
            $prodId = (int) $sale->product_id;
            $qty    = (int) $sale->quantity;

            // Si el producto vendido es un ensamblado, "explotar" en sus componentes
            if (isset($explosionMap[$prodId])) {
                $explodedCount++;
                foreach ($explosionMap[$prodId] as $comp) {
                    $compId = $comp['component_id'];
                    if (in_array($compId, $purchasableIds, true)) {
                        $derivedQty = (int) round($qty * $comp['quantity']);
                        $aggregatedSales[$compId] = ($aggregatedSales[$compId] ?? 0) + $derivedQty;
                    }
                }
            }

            // Siempre contar la venta directa si el producto en sí es comprable
            if (in_array($prodId, $purchasableIds, true)) {
                $aggregatedSales[$prodId] = ($aggregatedSales[$prodId] ?? 0) + $qty;
            }
        }

        // Modo "selected": filtrar resultado final solo a los productos seleccionados
        if ($request->product_mode === 'selected' && !empty($request->product_ids)) {
            $selectedIds = array_map('intval', $request->product_ids);
            $aggregatedSales = array_intersect_key(
                $aggregatedSales,
                array_flip($selectedIds)
            );
        }

        \Log::info('StockProjection :: Ensamblados explotados: ' . $explodedCount
            . ' | Productos resultantes en proyección: ' . count($aggregatedSales));

        // ==========================================
        // 4. CONSTRUIR TABLA DE PROYECCIÓN
        // ==========================================

        $tableData = collect();
        foreach ($aggregatedSales as $prodId => $totalSold) {
            $product = $purchasableProducts->get($prodId);
            if (!$product) continue;
            $tableData->push((object)[
                'id'         => $prodId,
                'code'       => $product->code,
                'name'       => $product->name,
                'min_quantity' => $product->min_quantity,
                'total_sold' => $totalSold,
            ]);
        }
        $tableData = $tableData->sortByDesc('total_sold')->values();

        // Obtener Stock Actual e Imágenes
        $productIds = $tableData->pluck('id')->toArray();
        $productsDetails = Product::with(['storages', 'media'])
                                  ->whereIn('id', $productIds)
                                  ->get()
                                  ->keyBy('id');

        // Formatear Tabla, Calcular Promedios y Sugerencias
        $criticalAlerts = 0;
        $totalUnitsSold = 0;

        $formattedTable = $tableData->map(function ($item) use ($monthsDiff, &$criticalAlerts, &$totalUnitsSold, $productsDetails) {
            $productModel = $productsDetails->get($item->id);
            $currentStock = $productModel ? $productModel->storages->sum('quantity') : 0;
            
            $imageUrl = $productModel ? $productModel->getFirstMediaUrl('images') : null;
            if ($imageUrl) {
                $imageUrl = str_replace('http://127.0.0.1:8000', 'https://www.intranetemblems3d.dtw.com.mx', $imageUrl);
            }

            $monthlyAvg = $item->total_sold / $monthsDiff;
            $projection3Months = round($monthlyAvg * 3);
            $toOrder = max(0, $projection3Months - $currentStock);
            $totalUnitsSold += $item->total_sold;

            $status = 'Óptimo';
            if ($toOrder > 0) {
                $status = 'Pedir Pronto';
                if ($currentStock <= $item->min_quantity) {
                    $criticalAlerts++;
                }
            }

            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'min_quantity' => $item->min_quantity,
                'current_stock' => $currentStock,
                'image_url' => $imageUrl,
                'total_sold' => $item->total_sold,
                'monthly_average' => round($monthlyAvg, 1),
                'projection_3_months' => $projection3Months,
                'to_order' => $toOrder,
                'status' => $status
            ];
        });

        // ==========================================
        // 5. GRÁFICA DE TENDENCIA MENSUAL (Top 5)
        // ==========================================

        $top5Ids = $tableData->take(5)->pluck('id')->map(fn($id) => (int) $id)->toArray();
        $chartCategories = [];
        $chartSeries = [];

        if (count($top5Ids) > 0) {
            $trendRaw = (clone $baseQuery)
                ->select(
                    'sale_products.product_id',
                    'sale_products.quantity',
                    DB::raw('DATE_FORMAT(sales.authorized_at, "%Y-%m") as month')
                )
                ->get();

            // Explotar tendencia por producto + mes
            $trendAgg = []; // "productId|month" => quantity
            foreach ($trendRaw as $row) {
                $month  = $row->month;
                $prodId = (int) $row->product_id;
                $qty    = (int) $row->quantity;

                if (isset($explosionMap[$prodId])) {
                    foreach ($explosionMap[$prodId] as $comp) {
                        $compId = $comp['component_id'];
                        if (in_array($compId, $top5Ids, true)) {
                            $derivedQty = (int) round($qty * $comp['quantity']);
                            $key = $compId . '|' . $month;
                            $trendAgg[$key] = ($trendAgg[$key] ?? 0) + $derivedQty;
                        }
                    }
                }
                if (in_array($prodId, $top5Ids, true)) {
                    $key = $prodId . '|' . $month;
                    $trendAgg[$key] = ($trendAgg[$key] ?? 0) + $qty;
                }
            }

            // Extraer meses únicos ordenados
            $allMonths = [];
            foreach ($trendAgg as $key => $val) {
                $parts = explode('|', $key);
                $allMonths[] = $parts[1];
            }
            $chartCategories = array_values(array_unique($allMonths));
            sort($chartCategories);

            // Construir series para ApexCharts
            foreach ($top5Ids as $pid) {
                $product = $purchasableProducts->get($pid);
                if (!$product) continue;

                $dataPoints = [];
                foreach ($chartCategories as $catMonth) {
                    $key = $pid . '|' . $catMonth;
                    $dataPoints[] = $trendAgg[$key] ?? 0;
                }
                $chartSeries[] = [
                    'name' => $product->name,
                    'data' => $dataPoints
                ];
            }
        }

        return [
            'kpis' => [
                'total_sold' => $totalUnitsSold,
                'monthly_average' => round($monthsDiff > 0 ? $totalUnitsSold / $monthsDiff : 0),
                'top_product' => $formattedTable->first() ? $formattedTable->first()['name'] : 'N/A',
                'critical_alerts' => $criticalAlerts
            ],
            'chart' => [
                'categories' => $chartCategories,
                'series' => $chartSeries
            ],
            'table' => $formattedTable
        ];
    }

    /**
     * Genera la proyección, KPIs y gráficas basados en filtros para la vista
     */
    public function generateReport(Request $request)
    {
        $data = $this->getReportData($request);
        return response()->json($data);
    }

    /**
     * Exporta el reporte generado a un archivo Excel
     */
    public function exportReport(Request $request)
    {
        $data = $this->getReportData($request);
        return Excel::download(new StockProjectionExport($data['table']), 'Proyeccion_Compras_' . now()->format('Ymd_His') . '.xlsx');
    }
}