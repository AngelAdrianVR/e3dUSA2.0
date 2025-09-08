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
     * Display the sales analysis dashboard using Inertia.
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
     * Helper function to apply base filters for currency and sale type.
     */
    private function applyBaseFiltersToQuery($query, Request $request)
    {
        // Always filter for 'venta' type as requested.
        $query->where('sales.type', 'venta');

        // Filter by currency if provided.
        if ($request->filled('currency')) {
            $query->where('sales.currency', $request->input('currency'));
        }
    }


    /**
     * Get the top-selling products for a given period.
     */
    public function getTopProducts(Request $request)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $topProductsData = $query->get();

        if ($topProductsData->isEmpty()) {
            return response()->json([]);
        }

        $productIds = $topProductsData->pluck('product_id')->toArray();
        
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
     * Get sales history for a specific product.
     */
    public function getProductSales(Request $request, Product $product)
    {
        $query = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->where('sale_products.product_id', $product->id)
            ->select(
                'sales.id as sale_id',
                'sales.created_at',
                'branches.name as branch_name',
                'sale_products.quantity',
                'sale_products.price'
            );

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $sales = $query->orderBy('sales.created_at', 'desc')->get();

        return response()->json($sales);
    }

    /**
     * Get the top customers (branches) by purchase amount.
     */
    public function getTopCustomers(Request $request)
    {
        $query = Sale::query()
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select('branches.id', 'branches.name', DB::raw('SUM(sales.total_amount) as total_purchased'))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('total_purchased', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    /**
     * Get global sales metrics (sales, costs, profit, etc.).
     */
    public function getSalesMetrics(Request $request)
    {
        $query = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as daily_sales'),
                DB::raw('SUM(sale_products.quantity * products.cost) as daily_costs')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc');
        
        $this->applyBaseFiltersToQuery($query, $request);
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
            'currency' => $request->input('currency', 'MXN'), // Added currency to response
        ]);
    }
    
    /**
     * Get sales details for a specific customer.
     */
    public function getCustomerSalesDetails(Request $request, Branch $branch)
    {
        $query = Sale::query()
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->where('sales.branch_id', $branch->id)
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity) as total_quantity'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_amount')
            )
            ->groupBy('date', 'family_name')
            ->orderBy('date', 'asc');

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    /**
     * Get total sales by product family.
     */
    public function getProductFamiliesSales(Request $request)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->select(
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_family_sales')
            )
            ->groupBy('family_name')
            ->orderBy('total_family_sales', 'desc');

        $this->applyBaseFiltersToQuery($query, $request);
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

    /**
     * Get the top sellers by sales amount.
     */
    public function getTopSellers(Request $request)
    {
        $query = Sale::query()
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', DB::raw('SUM(sales.total_amount) as total_sold'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_sold', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        return response()->json($query->get());
    }

    /**
     * Get sales details for a specific seller, broken down by product family.
     */
    public function getSellerSalesDetails(Request $request, User $user)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->leftJoin('product_families', 'products.product_family_id', '=', 'product_families.id')
            ->where('sales.user_id', $user->id)
            ->select(
                DB::raw('COALESCE(product_families.name, "Sin Familia") as family_name'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_family_sales')
            )
            ->groupBy('family_name');

        $this->applyBaseFiltersToQuery($query, $request);
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
