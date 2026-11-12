<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\SaleProduct;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $query->where('sales.type', 'venta')->whereIn('sales.status', ['En Producción', 'Preparando Envío', 'Enviada']);

        // Filter by currency if provided.
        if ($request->filled('currency')) {
            $query->where('sales.currency', $request->input('currency'));
        }
    }

    /**
     * Filtros base para el análisis de productos más vendidos.
     *
     * Se usa el mismo universo de estatus que la proyección de stock
     * (venta de los últimos 12 meses) para que ambas cifras coincidan.
     */
    private function applyProductAnalysisBaseFiltersToQuery($query, Request $request)
    {
        $query->where('sales.type', 'venta')
            ->whereIn('sales.status', ['Completada', 'Autorizada', 'Enviada', 'En Proceso', 'Preparando Envío', 'En Producción']);

        if ($request->filled('currency')) {
            $query->where('sales.currency', $request->input('currency'));
        }
    }

    /**
     * ¿El usuario actual tiene permiso para ver cantidades de dinero?
     * Si no lo tiene, todas las cifras monetarias se devuelven en null.
     */
    private function canSeeMoney(): bool
    {
        return Gate::allows('Ver cantidades de dinero');
    }

    /**
     * Apply the product family filter using the "base" product
     * (parent product when the sold item is a variant).
     */
    private function applyFamilyFilterToQuery($query, Request $request)
    {
        if ($request->filled('family_id')) {
            $query->whereRaw(
                'COALESCE(products.parent_id, products.id) IN (SELECT id FROM products WHERE product_family_id = ?)',
                [$request->input('family_id')]
            );
        }
    }


    /**
     * Get the top-selling products for a given period.
     */
    public function getTopProducts(Request $request)
    {
        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->select(
                // Si el producto tiene un parent_id, lo agrupa bajo el padre. Si no, usa su propio id.
                DB::raw('COALESCE(products.parent_id, products.id) as base_product_id'),
                DB::raw('SUM(sale_products.quantity) as total_quantity')
            )
            ->groupBy('base_product_id')
            ->orderBy('total_quantity', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $topProductsData = $query->get();

        if ($topProductsData->isEmpty()) {
            return response()->json([]);
        }

        $productIds = $topProductsData->pluck('base_product_id')->toArray();
        
        $products = Product::with('media')->whereIn('id', $productIds)
                    ->orderByRaw("FIELD(id, " . implode(',', $productIds) . ")")
                    ->get();

        $canSeeMoney = $this->canSeeMoney();

        $results = $topProductsData->map(function ($item) use ($products, $canSeeMoney) {
            $product = $products->firstWhere('id', $item->base_product_id);
            if (!$product) return null;
            return [
                'id' => $product->id,
                'name' => $product->name,
                'archived_at' => $product->archived_at,
                'code' => $product->code,
                'cost' => $canSeeMoney ? $product->cost : null,
                'base_price' => $canSeeMoney ? $product->base_price : null,
                'total_quantity' => (int) $item->total_quantity,
                'image_url' => $this->getProductImageUrl($product),
            ];
        })->filter()->values();

        return response()->json($results);
    }

    /**
     * Get sales history for a specific product.
     */
    public function getProductSales(Request $request, Product $product)
    {
        // Obtener el ID del producto base y recolectar todos los IDs de sus variantes si tiene
        $productIds = Product::where('id', $product->id)
                            ->orWhere('parent_id', $product->id)
                            ->pluck('id');

        $query = DB::table('sales')
            ->join('sale_products', 'sales.id', '=', 'sale_products.sale_id')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->whereIn('sale_products.product_id', $productIds)
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

        if (! $this->canSeeMoney()) {
            $sales->each(function ($sale) {
                $sale->price = null;
            });
        }

        return response()->json($sales);
    }

    /**
     * Get the top customers (branches) by purchase amount.
     */
    public function getTopCustomers(Request $request)
    {
        $query = DB::table('sales')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->select('branches.id', 'branches.name', DB::raw('SUM(sales.total_amount) as total_purchased'))
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('total_purchased', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $customers = $query->get();

        if (! $this->canSeeMoney()) {
            $customers->each(function ($customer) {
                $customer->total_purchased = null;
            });
        }

        return response()->json($customers);
    }

    /**
     * Get global sales metrics (sales, costs, profit, etc.).
     */
    public function getSalesMetrics(Request $request)
    {
        $query = DB::table('sales')
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

        $canSeeMoney = $this->canSeeMoney();

        if (! $canSeeMoney) {
            // Sin permiso solo se devuelven las fechas (sin importes).
            $timeSeriesData = $timeSeriesData->map(function ($row) {
                return [
                    'date' => $row->date,
                    'daily_sales' => null,
                    'daily_costs' => null,
                ];
            });
        }

        return response()->json([
            'total_sales' => $canSeeMoney ? $totalSales : null,
            'total_costs' => $canSeeMoney ? $totalCosts : null,
            'total_profit' => $canSeeMoney ? $totalProfit : null,
            'margin_percentage' => $canSeeMoney ? $margin : null,
            'time_series' => $timeSeriesData,
            'currency' => $request->input('currency', 'MXN'), // Added currency to response
        ]);
    }
    
    /**
     * Get sales details for a specific customer.
     */
    public function getCustomerSalesDetails(Request $request, Branch $branch)
    {
        $query = DB::table('sales')
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

        $details = $query->get();

        if (! $this->canSeeMoney()) {
            $details->each(function ($row) {
                $row->total_amount = null;
            });
        }

        return response()->json($details);
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

        $canSeeMoney = $this->canSeeMoney();

        $results = $data->map(function ($item) use ($totalSalesAllFamilies, $canSeeMoney) {
            return [
                'name' => $item->family_name,
                'total' => $canSeeMoney ? (float) $item->total_family_sales : null,
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
        $query = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.profile_photo_path', DB::raw('SUM(sales.total_amount) as total_sold'))
            ->groupBy('users.id', 'users.name', 'users.profile_photo_path') // <-- Es buena práctica agrupar por todas las columnas no agregadas
            ->orderBy('total_sold', 'desc')
            ->limit(20);

        $this->applyBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        // 1. Obtenemos los resultados crudos de la base de datos
        $topSellersRaw = $query->get();

        // 2. Transformamos la colección para añadir la URL de la foto
        $topSellers = $topSellersRaw->map(function ($sellerData) {
            // Creamos una instancia temporal del modelo User para usar su lógica
            $user = new User();
            
            // El trait HasProfilePhoto busca este atributo para construir la URL
            $user->profile_photo_path = $sellerData->profile_photo_path;

            // Añadimos la URL calculada a nuestros datos
            $sellerData->profile_photo_url = $user->profile_photo_url;
            
            // El importe solo se expone si el usuario puede ver cantidades de dinero.
            if (! $this->canSeeMoney()) {
                $sellerData->total_sold = null;
            }

            return $sellerData;
        });

        return response()->json($topSellers);
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

        $canSeeMoney = $this->canSeeMoney();

        $results = $data->map(function ($item) use ($totalSalesAllFamilies, $canSeeMoney) {
            return [
                'name' => $item->family_name,
                'total' => $canSeeMoney ? (float) $item->total_family_sales : null,
                'percentage' => $totalSalesAllFamilies > 0 ? ((float) $item->total_family_sales / $totalSalesAllFamilies) * 100 : 0,
            ];
        });

        return response()->json($results);
    }

    /**
     * Obtiene los IDs del producto base junto con los de sus variantes.
     */
    private function getProductIdsWithVariants(Product $product)
    {
        return Product::query()
            ->where('id', $product->id)
            ->orWhere('parent_id', $product->id)
            ->pluck('id');
    }

    /**
     * URL de la imagen principal del producto.
     *
     * Los productos guardan sus imágenes en la colección "images" (catálogo nuevo)
     * o en "default" (registros heredados), por lo que se revisan ambas. Si es una
     * variante sin imagen propia, se usa la imagen del producto padre.
     */
    private function getProductImageUrl(Product $product): ?string
    {
        foreach (['images', 'default'] as $collection) {
            if ($product->hasMedia($collection)) {
                return $product->getFirstMediaUrl($collection) ?: null;
            }
        }

        if ($product->parent_id && $product->parent) {
            foreach (['images', 'default'] as $collection) {
                if ($product->parent->hasMedia($collection)) {
                    return $product->parent->getFirstMediaUrl($collection) ?: null;
                }
            }
        }

        return null;
    }

    /**
     * Listado de familias de producto para los filtros del análisis de productos.
     */
    public function getProductFamilies()
    {
        $families = ProductFamily::query()
            ->select('id', 'name', 'key')
            ->orderBy('name')
            ->get();

        return response()->json($families);
    }

    /**
     * Busca productos por nombre o código (para analizar sus ventas aunque
     * no aparezcan en el top de más vendidos).
     */
    public function searchProducts(Request $request)
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $products = Product::with(['media', 'productFamily:id,name'])
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderByRaw('CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 ELSE 2 END', [$term, "{$term}%"])
            ->orderBy('name')
            ->limit(25)
            ->get();

        $results = $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'archived_at' => $product->archived_at,
                'family_name' => $product->productFamily?->name ?? 'Sin Familia',
                'total_quantity' => null,
                'total_amount' => null,
                'image_url' => $this->getProductImageUrl($product),
            ];
        });

        return response()->json($results);
    }

    /**
     * Top 20 de productos más vendidos (por piezas o por dinero) con filtro de familia.
     */
    public function getTopSellingProducts(Request $request)
    {
        return response()->json($this->buildTopSellingProducts($request));
    }

    /**
     * Obtiene las opciones (métrica y cantidad) validadas del request.
     */
    private function resolveTopSellingOptions(Request $request): array
    {
        $metric = $request->input('metric') === 'amount' ? 'amount' : 'units';

        // Cantidad de productos a listar (20 por defecto, configurable a 30/40/50).
        $limit = (int) $request->input('limit', 20);
        if (! in_array($limit, [20, 30, 40, 50], true)) {
            $limit = 20;
        }

        return [$metric, $limit];
    }

    /**
     * Datos del top de productos más vendidos (compartido por el JSON y la impresión).
     */
    private function buildTopSellingProducts(Request $request): array
    {
        [$metric, $limit] = $this->resolveTopSellingOptions($request);

        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->join('products', 'sale_products.product_id', '=', 'products.id')
            ->select(
                DB::raw('COALESCE(products.parent_id, products.id) as base_product_id'),
                DB::raw('SUM(sale_products.quantity) as total_quantity'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_amount')
            )
            ->groupBy('base_product_id');

        $this->applyProductAnalysisBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);
        $this->applyFamilyFilterToQuery($query, $request);

        if ($metric === 'amount') {
            $query->orderBy('total_amount', 'desc');
        } else {
            $query->orderBy('total_quantity', 'desc');
        }

        $topProductsData = $query->limit($limit)->get();

        if ($topProductsData->isEmpty()) {
            return [];
        }

        $productIds = $topProductsData->pluck('base_product_id')->all();

        $products = Product::with(['media', 'productFamily:id,name'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $canSeeMoney = $this->canSeeMoney();

        return $topProductsData->map(function ($item) use ($products, $canSeeMoney) {
            $product = $products->get($item->base_product_id);

            if (! $product) {
                return null;
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'archived_at' => $product->archived_at,
                'family_name' => $product->productFamily?->name ?? 'Sin Familia',
                'total_quantity' => (float) $item->total_quantity,
                'total_amount' => $canSeeMoney ? (float) $item->total_amount : null,
                'image_url' => $this->getProductImageUrl($product),
            ];
        })->filter()->values()->all();
    }

    /**
     * Vista imprimible (sin layout) del top de productos más vendidos.
     */
    public function printTopSellingProducts(Request $request)
    {
        [$metric, $limit] = $this->resolveTopSellingOptions($request);

        $canSeeMoney = $this->canSeeMoney();

        // Sin permiso de dinero no se puede ordenar ni imprimir importes.
        if (! $canSeeMoney) {
            $metric = 'units';
        }

        $familyName = 'Todas las familias';
        if ($request->filled('family_id')) {
            $familyName = ProductFamily::find($request->input('family_id'))?->name ?? 'Todas las familias';
        }

        return Inertia::render('SalesAnalysis/ProductsPrint', [
            'products' => $this->buildTopSellingProducts($request),
            'metric' => $metric,
            'limit' => $limit,
            'canSeeMoney' => $canSeeMoney,
            'currency' => $canSeeMoney && $request->filled('currency') ? $request->input('currency') : null,
            'familyName' => $familyName,
            'periodLabel' => $this->resolvePeriodLabel($request),
        ]);
    }

    /**
     * Etiqueta legible del periodo seleccionado.
     */
    private function resolvePeriodLabel(Request $request): string
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate && $endDate) {
            return Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        }

        if ($request->input('period') === 'year') {
            return 'Año ' . now()->year;
        }

        return 'Mes en curso (' . now()->format('m/Y') . ')';
    }

    /**
     * Serie histórica (por fecha) de ventas de un producto: piezas y dinero.
     */
    public function getProductSalesTimeline(Request $request, Product $product)
    {
        $productIds = $this->getProductIdsWithVariants($product);

        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->whereIn('sale_products.product_id', $productIds)
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('SUM(sale_products.quantity) as total_quantity'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_amount'),
                DB::raw('COUNT(DISTINCT sales.id) as sales_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc');

        $this->applyProductAnalysisBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $timeline = $query->get();

        if (! $this->canSeeMoney()) {
            $timeline->each(function ($row) {
                $row->total_amount = null;
            });
        }

        return response()->json($timeline);
    }

    /**
     * Órdenes de venta (paginadas de 20 en 20) donde aparece un producto.
     */
    public function getProductSaleOrders(Request $request, Product $product)
    {
        $productIds = $this->getProductIdsWithVariants($product);

        $query = SaleProduct::query()
            ->join('sales', 'sale_products.sale_id', '=', 'sales.id')
            ->leftJoin('branches', 'sales.branch_id', '=', 'branches.id')
            ->whereIn('sale_products.product_id', $productIds)
            ->select(
                'sales.id as sale_id',
                'sales.created_at',
                'sales.currency',
                'sales.status',
                'branches.name as branch_name',
                DB::raw('SUM(sale_products.quantity) as total_quantity'),
                DB::raw('SUM(sale_products.quantity * sale_products.price) as total_amount'),
                DB::raw('AVG(sale_products.price) as avg_price')
            )
            ->groupBy('sales.id', 'sales.created_at', 'sales.currency', 'sales.status', 'branches.name')
            ->orderBy('sales.created_at', 'desc');

        $this->applyProductAnalysisBaseFiltersToQuery($query, $request);
        $this->applyDateFilterToQuery($query, $request);

        $orders = $query->paginate(20);

        if (! $this->canSeeMoney()) {
            $orders->through(function ($order) {
                $order->total_amount = null;
                $order->avg_price = null;

                return $order;
            });
        }

        return response()->json($orders);
    }
}
