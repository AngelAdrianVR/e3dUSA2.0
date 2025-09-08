<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

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
     * Helper function to apply date filters to a query.
     */
    private function applyDateFilterToQuery($query, Request $request, $dateColumn = 'sales.created_at')
    {
        $period = $request->input('period');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate && $endDate) {
            $query->whereBetween($dateColumn, [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
        } elseif ($period === 'month') {
            $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'year') {
            $query->whereYear($dateColumn, now()->year);
        }
        // If 'all', no date filter is applied.
    }

    /**
     * Obtiene el top productos más vendidos según un período de tiempo.
     */
    public function getTopProducts(Request $request)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->where('sales.type', 'venta')
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->limit(20);

        $this->applyDateFilterToQuery($query, $request);

        $topProductsData = $query->get();

        // Si no hay productos, devolvemos un array vacío para evitar errores.
        if ($topProductsData->isEmpty()) {
            return response()->json([]);
        }

        $productIds = $topProductsData->pluck('product_id')->toArray();
        
        // CORRECCIÓN: Se eliminó el DB::raw() redundante que causaba el error.
        // También se asegura que el orden de los IDs se mantenga.
        $products = Product::with('media')->whereIn('id', $productIds)
                    ->orderByRaw("FIELD(id, " . implode(',', $productIds) . ")")
                    ->get();

        $results = $topProductsData->map(function ($item) use ($products) {
            $product = $products->firstWhere('id', $item->product_id);

            if (!$product) return null;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'cost' => $product->cost,
                'base_price' => $product->base_price,
                'total_quantity' => (int) $item->total_quantity,
                'image_url' => $product->getFirstMediaUrl('images') ?: null,
            ];
        })->filter()->values();

        return response()->json($results);
    }

    /**
     * Obtiene el historial de ventas para un producto específico.
     */
    public function getProductSales(Request $request, Product $product)
    {
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

        $this->applyDateFilterToQuery($query, $request);

        $sales = $query->orderBy('sales.created_at', 'desc')->get();

        return response()->json($sales);
    }

    /**
     * Obtiene el top clientes (sucursales) que más han comprado.
     */
    public function getTopCustomers(Request $request)
    {
        $query = Sale::query()
            ->where('type', 'venta')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select('branches.id', 'branches.name', DB::raw('SUM(sales.total_amount) as total_purchased'))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('total_purchased', 'desc')
            ->limit(20);

        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    /**
     * Obtiene métricas de ventas globales (ventas, costos, utilidad, etc.).
     */
    public function getSalesMetrics(Request $request)
    {
        $query = Sale::query()
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

        $this->applyDateFilterToQuery($query, $request);
        
        $timeSeriesData = $query->get();

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
    
    /**
     * Obtiene los detalles de ventas para un cliente específico.
     * Asegúrate de agregar la ruta en tu archivo de rutas (ej. routes/api.php):
     * Route::get('/sales-analysis/customer-sales/{branch}', [SalesAnalysisController::class, 'getCustomerSalesDetails'])->name('api.sales-analysis.customer-sales');
     */
    public function getCustomerSalesDetails(Request $request, Branch $branch)
    {
        $query = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.type', 'venta')
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity) as total_quantity'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_amount')
            )
            ->groupBy('date', 'family_name')
            ->orderBy('date', 'asc');

        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    /**
     * Obtiene el total de ventas por familia de producto.
     * Asegúrate de agregar la ruta en tu archivo de rutas (ej. routes/api.php):
     * Route::get('/sales-analysis/product-families-sales', [SalesAnalysisController::class, 'getProductFamiliesSales'])->name('api.sales-analysis.product-families-sales');
     */
    public function getProductFamiliesSales(Request $request)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->where('sales.type', 'venta')
            ->select(
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_family_sales')
            )
            ->groupBy('family_name')
            ->orderBy('total_family_sales', 'desc');

        $this->applyDateFilterToQuery($query, $request);

        $data = $query->get();
        $totalSalesAllFamilies = $data->sum('total_family_sales');

        $results = $data->map(function ($item) use ($totalSalesAllFamilies) {
            return [
                'name' => $item->family_name,
                'total' => (float) $item->total_family_sales,
                'percentage' => $totalSalesAllFamilies > 0 ? ((float) $item->total_family_sales / $totalSalesAllFamilies) * 100 : 0,
            ];
        });

        return response()->json($results);
    }

    public function getTopSellers(Request $request)
    {
        $query = Sale::query()
            ->where('type', 'venta')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', DB::raw('SUM(sales.total_amount) as total_sold'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_sold', 'desc')
            ->limit(20);

        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    public function getSellerSalesDetails(Request $request, User $user)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->where('sales.type', 'venta')
            ->where('sales.user_id', $user->id)
            ->select(
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_family_sales')
            )
            ->groupBy('family_name');

        $this->applyDateFilterToQuery($query, $request, 'sales.created_at');

        $data = $query->get();
        $totalSalesAllFamilies = $data->sum('total_family_sales');

        $results = $data->map(function ($item) use ($totalSalesAllFamilies) {
            return [
                'name' => $item->family_name,
                'total' => (float) $item->total_family_sales,
                'percentage' => $totalSalesAllFamilies > 0 ? ((float) $item->total_family_sales / $totalSalesAllFamilies) * 100 : 0,
            ];
        });

        return response()->json($results);
    }
}

