<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesAnalysisController extends Controller
{
    /**
     * Muestra el dashboard de análisis de ventas usando Inertia.
     */
    public function index()
    {
        return Inertia::render('SalesAnalysis/Index');
    }

    /**
     * Obtiene los 30 productos más vendidos según un período de tiempo.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopProducts(Request $request)
    {
        $period = $request->input('period', 'month'); // 'month', 'year', 'all'

        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->where('sales.type', 'venta')
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->limit(30);

        if ($period === 'month') {
            $query->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'year') {
            $query->whereYear('sales.created_at', now()->year);
        }

        $topProductsData = $query->get();

        $productIds = $topProductsData->pluck('product_id');

        // Usamos find() para mantener el orden de los IDs obtenidos en la consulta anterior
        $products = Product::with('media')->find($productIds);

        // Mapeamos los resultados para añadir la información del producto y la URL de la imagen
        $results = $topProductsData->map(function ($item) use ($products) {
            $product = $products->firstWhere('id', $item->product_id);

            if (!$product) {
                return null;
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'cost' => $product->cost,
                'base_price' => $product->base_price,
                'total_quantity' => (int) $item->total_quantity,
                // Asume que la colección de imágenes se llama 'images'. Cambia si es necesario.
                'image_url' => $product->getFirstMediaUrl('images') ?: null,
            ];
        })->filter()->values(); // filter() para eliminar nulos y values() para reindexar el array

        return response()->json($results);
    }

    /**
     * Obtiene el historial de ventas para un producto específico.
     *
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductSales(Request $request, Product $product)
    {
        $period = $request->input('period', 'month'); // 'month', 'year', 'all'

        $query = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->where('sale_products.product_id', $product->id)
            ->where('sales.type', 'venta')
            ->select(
                'sales.id as sale_id',
                'sales.created_at',
                'branches.name as branch_name',
                'sale_products.quantity',
                'sale_products.price'
            );

        if ($period === 'month') {
            $query->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'year') {
            $query->whereYear('sales.created_at', now()->year);
        }

        $sales = $query->orderBy('sales.created_at', 'desc')->get();

        return response()->json($sales);
    }

    /**
     * Obtiene los 10 clientes (sucursales) que más han comprado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopCustomers(Request $request)
    {
        $period = $request->input('period', 'month');

        $query = Sale::query()
            ->where('type', 'venta')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select('branches.id', 'branches.name', DB::raw('SUM(sales.total_amount) as total_purchased'))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('total_purchased', 'desc')
            ->limit(10);

        if ($period === 'month') {
            $query->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'year') {
            $query->whereYear('sales.created_at', now()->year);
        }

        return response()->json($query->get());
    }

    /**
     * Obtiene métricas de ventas globales (ventas, costos, utilidad, etc.).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesMetrics(Request $request)
    {
        $period = $request->input('period', 'month');

        // Query para datos de series de tiempo (ventas y costos diarios)
        $timeSeriesQuery = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->where('sales.type', 'venta')
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as daily_sales'),
                DB::raw('SUM(sale_products.quantity * products.cost) as daily_costs')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc');

        if ($period === 'month') {
            $timeSeriesQuery->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'year') {
            $timeSeriesQuery->whereYear('sales.created_at', now()->year);
        }
        
        $timeSeriesData = $timeSeriesQuery->get();

        // Calcular totales
        $totalSales = $timeSeriesData->sum('daily_sales');
        $totalCosts = $timeSeriesData->sum('daily_costs');
        $totalProfit = $totalSales - $totalCosts;
        $margin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;

        return response()->json([
            'total_sales' => $totalSales,
            'total_costs' => $totalCosts,
            'total_profit' => $totalProfit,
            'margin_percentage' => $margin,
            'time_series' => $timeSeriesData,
        ]);
    }

    
}
