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
     * Extrae la data de la proyección basada en los filtros
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
        
        // Calculamos los meses de diferencia, mínimo 1 para evitar división por 0
        $monthsDiff = $startDate->diffInMonths($endDate);
        if ($monthsDiff < 1) {
            $monthsDiff = 1;
        }

        // 1. Query Base
        $query = DB::table('sale_products')
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->whereIn('sales.status', ['Completada', 'Autorizada', 'Enviada', 'En Proceso', 'Preparando Envío', 'En Producción'])
            ->where('products.is_purchasable', true);

        // Si se seleccionaron productos específicos, filtramos
        if ($request->product_mode === 'selected' && !empty($request->product_ids)) {
            $query->whereIn('products.id', $request->product_ids);
        }

        // 2. Obtener la tabla estratégica sumada por producto
        $salesData = clone $query;
        $tableData = $salesData->select(
                'products.id',
                'products.code',
                'products.name',
                'products.min_quantity',
                DB::raw('SUM(sale_products.quantity) as total_sold')
            )
            ->groupBy('products.id', 'products.code', 'products.name', 'products.min_quantity')
            ->orderByDesc('total_sold')
            ->get();

        // 3. Obtener el Stock Actual y las Imágenes (con Eager Loading para optimizar)
        $productIds = $tableData->pluck('id')->toArray();
        $productsDetails = Product::with(['storages', 'media'])
                                  ->whereIn('id', $productIds)
                                  ->get()
                                  ->keyBy('id');

        // 4. Formatear Tabla, Calcular Promedios y Sugerencias
        $criticalAlerts = 0;
        $totalUnitsSold = 0;

        $formattedTable = $tableData->map(function ($item) use ($monthsDiff, &$criticalAlerts, &$totalUnitsSold, $productsDetails) {
            // Obtenemos el modelo cargado con sus relaciones
            $productModel = $productsDetails->get($item->id);

            // Sumamos el stock de todos los almacenes para este producto
            $currentStock = $productModel ? $productModel->storages->sum('quantity') : 0;
            
            // Obtenemos la imagen y aplicamos el reemplazo del dominio
            $imageUrl = $productModel ? $productModel->getFirstMediaUrl('images') : null;
            if ($imageUrl) {
                $imageUrl = str_replace('http://127.0.0.1:8000', 'https://www.intranetemblems3d.dtw.com.mx', $imageUrl);
            }

            // Cálculos de proyección
            $monthlyAvg = $item->total_sold / $monthsDiff;
            $projection3Months = round($monthlyAvg * 3); // 3 Meses sugeridos de stock en total
            
            // Sugerencia real de compra (Lo que falta) descontando el stock actual
            $toOrder = max(0, $projection3Months - $currentStock);

            $totalUnitsSold += $item->total_sold;

            // Determinar estado de la sugerencia
            $status = 'Óptimo';
            if ($toOrder > 0) {
                $status = 'Pedir Pronto';
                // Si hay que pedir y además el stock actual está por debajo del mínimo, es crítico
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
                'projection_3_months' => $projection3Months, // La proyección bruta de 3 meses
                'to_order' => $toOrder,                      // Faltante (Sugerencia)
                'status' => $status
            ];
        });

        // 5. Preparar Datos para Gráfica (Tendencia mensual del Top 5 productos)
        $top5Ids = $tableData->take(5)->pluck('id')->toArray();
        $chartCategories = [];
        $chartSeries = [];

        if (count($top5Ids) > 0) {
            $trendQuery = clone $query;
            $trendData = $trendQuery->whereIn('products.id', $top5Ids)
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('DATE_FORMAT(sales.created_at, "%Y-%m") as month'),
                    DB::raw('SUM(sale_products.quantity) as sold')
                )
                ->groupBy('products.id', 'products.name', 'month')
                ->orderBy('month')
                ->get();

            // Extraer meses únicos ordenados
            $chartCategories = $trendData->pluck('month')->unique()->values()->toArray();
            
            // Construir las series para ApexCharts
            foreach ($top5Ids as $pid) {
                $prodData = $trendData->where('id', $pid);
                if ($prodData->isEmpty()) continue;

                $dataPoints = [];
                foreach ($chartCategories as $catMonth) {
                    $monthRecord = $prodData->firstWhere('month', $catMonth);
                    $dataPoints[] = $monthRecord ? (int)$monthRecord->sold : 0;
                }

                $chartSeries[] = [
                    'name' => $prodData->first()->name,
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