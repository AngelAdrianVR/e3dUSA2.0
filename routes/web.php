<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchNoteController;
use App\Http\Controllers\BranchPriceHistoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DesignAuthorizationController;
use App\Http\Controllers\DesignCategoryController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DesignOrderController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFamilyController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductionCostController;
use App\Http\Controllers\ProductionTaskController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesAnalysisController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\SupplierBankAccountController;
use App\Http\Controllers\SupplierContactController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierProductController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;



// Estado de carga de la aplicación
Route::get('/inicio', function () {
    return Inertia::render('Auth/Inicio');
})->name('inicio');

// Rutas de autenticación
Route::get('/', function () {
    return Inertia::render('Auth/Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

// importar archivos de ruta
Route::middleware('auth')->group(function () {
    require __DIR__ . '/web/payrolls.php';
    require __DIR__ . '/web/attendances.php';
});


// Rutas de Notificaciones
Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('auth')->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('auth')->name('notifications.read-all');
Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->middleware('auth')->name('notifications.destroy');


// ------- Products Routes ---------
Route::resource('catalog-products', ProductController::class)->middleware('auth');
Route::post('catalog-products/massive-delete', [ProductController::class, 'massiveDelete'])->middleware('auth')->name('catalog-products.massive-delete');
Route::post('catalog-products-get-matches', [ProductController::class, 'getMatches'])->middleware('auth')->name('catalog-products.get-matches');
Route::get('catalog-products-search-raw-materials', [ProductController::class, 'searchRawMaterial'])->middleware('auth')->name('catalog-products.search-raw-material');
Route::get('products-get-media/{product}', [ProductController::class, 'getProductMedia'])->middleware('auth')->name('products.get-media');
Route::get('products-mark-as-obsolet/{product}', [ProductController::class, 'markAsObsolet'])->middleware('auth')->name('catalog-products.obsolet');
Route::get('products-find-similar', [ProductController::class, 'findSimilar'])->middleware('auth')->name('catalog-products.find-similar');
Route::post('products/{product}/stock-movement', [ProductController::class, 'handleStockMovement'])->middleware('auth')->name('products.stock-movement');
Route::post('products-fetch-products', [ProductController::class, 'fetchProducts'])->middleware('auth')->name('products.fetch-products');
Route::post('catalog-products/QR-search-catalog-product', [ProductController::class, 'QRSearchCatalogProduct'])->middleware('auth')->name('catalog-products.QR-search-catalog-product');
Route::put('/products/{product}/simple-update', [ProductController::class, 'simpleUpdate'])->middleware('auth')->name('products.simple-update');
// Route::get('catalog-products/{catalog_product}/get-data', [ProductController::class, 'getCatalogProductData'])->name('catalog-products.get-data');
// Route::get('catalog-products-fetch-shipping-rates/{catalog_product}', [ProductController::class, 'fetchShippingRates'])->name('catalog-products.fetch-shipping-rates');
// Route::get('catalog-products-prices-report', [ProductController::class, 'pricesReport'])->name('catalog-products.prices-report');
// Route::post('catalog-products-get-by-ids', [ProductController::class, 'getByIds'])->name('catalog-products.get-by-ids');
// Route::get('catalog-products/{catalog_product}/get-info', [ProductController::class, 'getInfo'])->name('catalog-products.get-info');
// Route::get('export-catalog-products', [ProductController::class, 'exportExcel']);


// ------- product families Routes ---------
Route::resource('product-families', ProductFamilyController::class)->except('show')->middleware('auth');


//------------------ production-cost routes ----------------
Route::resource('production-costs', ProductionCostController::class)->except(['create', 'edit', 'show', 'destroy'])->middleware('auth');
Route::post('production-costs/massive-delete', [ProductionCostController::class, 'massiveDelete'])->middleware('auth')->name('production-costs.massive-delete');


// ------- brands Routes ---------
Route::resource('brands', BrandController::class)->except(['create', 'edit', 'show', 'destroy'])->middleware('auth');


// ------- CRM(branches sucursales Routes)  ---------
Route::resource('branches', BranchController::class)->middleware('auth');
Route::post('branches-get-matches', [BranchController::class, 'getMatches'])->middleware('auth')->name('branches.get-matches');
Route::post('branches/massive-delete', [BranchController::class, 'massiveDelete'])->middleware('auth')->name('branches.massive-delete');
Route::get('branches/{branch}/fetch-products', [BranchController::class, 'fetchBranchProducts'])->middleware('auth')->name('branches.fetch-products');
Route::post('/branches/{branch}/add-products', [BranchController::class, 'addProducts'])->middleware('auth')->name('branches.add-products');
Route::delete('/branches/{branch}/products/{product}', [BranchController::class, 'removeProduct'])->middleware('auth')->name('branches.products.remove');
// Route::put('branches/store-important-notes/{branch}', [BranchController::class, 'storeImportantNotes'])->name('branches.store-important-notes')->middleware('auth');
// Route::put('branches/update-product-price/{product_company}', [BranchController::class, 'updateProductPrice'])->name('branches.update-product-price')->middleware('auth');
// Route::get('branches/fetch-design-info/{branch}', [BranchController::class, 'fetchDesignInfo'])->name('branches.fetch-design-info')->middleware('auth');


// ------- CRM(Notas importantes de clientes Routes)  ---------
Route::get('/branches/{branch}/notes', [BranchNoteController::class, 'index'])->name('branch-notes.index');
Route::prefix('branch-notes')->name('branch-notes.')->group(function () {
    Route::post('/', [BranchNoteController::class, 'store'])->middleware('auth')->name('store');
    Route::put('/{branchNote}', [BranchNoteController::class, 'update'])->middleware('auth')->name('update');
    Route::delete('/{branchNote}', [BranchNoteController::class, 'destroy'])->middleware('auth')->name('destroy');
});


// --- Grupo de rutas para el Módulo de Análisis de Ventas ---
// Ruta para la vista principal del dashboard (Inertia)
Route::get('/sales-analysis', [SalesAnalysisController::class, 'index'])
    ->middleware('auth')
    ->name('sales-analysis.index');

// --- Grupo de rutas para la API del dashboard ---
Route::middleware(['auth'])->prefix('api/sales-analysis')->as('api.sales-analysis.')->group(function () {
    // Rutas que ya tenías (con URI corregida)
    Route::get('/top-products', [SalesAnalysisController::class, 'getTopProducts'])->name('top-products');
    Route::get('/product-sales/{product}', [SalesAnalysisController::class, 'getProductSales'])->name('product-sales');
    Route::get('/top-customers', [SalesAnalysisController::class, 'getTopCustomers'])->name('top-customers');
    Route::get('/sales-metrics', [SalesAnalysisController::class, 'getSalesMetrics'])->name('sales-metrics');
    // Para obtener los detalles de ventas de un cliente específico
    Route::get('/customer-sales/{branch}', [SalesAnalysisController::class, 'getCustomerSalesDetails'])->name('customer-sales');
    // Para obtener el resumen de ventas por familia de producto
    Route::get('/product-families-sales', [SalesAnalysisController::class, 'getProductFamiliesSales'])->name('product-families-sales');
    Route::get('/top-sellers', [SalesAnalysisController::class, 'getTopSellers'])->name('top-sellers');
    Route::get('/seller-sales/{user}', [SalesAnalysisController::class, 'getSellerSalesDetails'])->name('seller-sales');
});


// ------- (rutas de contactos de clientes)  ---------
Route::post('contacts', [ContactController::class, 'store'])->middleware('auth')->name('contacts.store');
Route::put('contacts/{contact}', [ContactController::class, 'update'])->middleware('auth')->name('contacts.update');
Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->middleware('auth')->name('contacts.destroy');


// ------- CRM(historial de precios de productos de cliente Routes)  ---------
Route::post('/branches/{branch}/products/{product}/price', [BranchPriceHistoryController::class, 'store'])->middleware('auth')->name('branches.products.price.store');
Route::patch('/branch-price-history/{priceHistory}/close', [BranchPriceHistoryController::class, 'close'])->middleware('auth')->name('branch-price-history.close');


// ------- CRM(cotizaciones Routes)  ---------
Route::resource('quotes', QuoteController::class)->middleware('auth');
Route::put('quotes/authorize/{quote}', [QuoteController::class, 'authorizeQuote'])->middleware('auth')->name('quotes.authorize');
Route::post('quotes-get-matches', [QuoteController::class, 'getMatches'])->middleware('auth')->name('quotes.get-matches');
Route::post('quotes-change-status/{quote}', [QuoteController::class, 'changeStatus'])->middleware('auth')->name('quotes.change-status');
Route::get('quotes-clone/{quote}', [QuoteController::class, 'clone'])->middleware('auth')->name('quotes.clone');
Route::post('quotes/massive-delete', [QuoteController::class, 'massiveDelete'])->middleware('auth')->name('quotes.massive-delete');
Route::get('quotes-fetch-branch-quotes/{branch}', [QuoteController::class, 'fetchBranchQuotes'])->middleware('auth')->name('quotes.branch-quotes');
Route::get('quotes/{quote}/details-for-sale', [QuoteController::class, 'getDetailsForSale'])->middleware(['auth'])->name('quotes.details-for-sale');
Route::put('/quotes/products/{quoteProduct}/updateStatus', [QuoteController::class, 'updateProductStatus'])->middleware('auth')->name('quotes.products.updateStatus');


// ------- CRM(Ordenes de venta Routes)  ---------
Route::resource('sales', SaleController::class)->middleware('auth');
Route::put('sales/authorize/{sale}', [SaleController::class, 'authorizeSale'])->middleware('auth')->name('sales.authorize');
Route::post('sales-get-matches', [SaleController::class, 'getMatches'])->middleware('auth')->name('sales.get-matches');
Route::post('sales/massive-delete', [SaleController::class, 'massiveDelete'])->middleware('auth')->name('sales.massive-delete');
Route::get('sales/print/{sale}', [SaleController::class, 'print'])->middleware('auth')->name('sales.print');
Route::get('sales-fetch-all', [SaleController::class, 'fetchAll'])->middleware('auth')->name('sales.fetch-all');
Route::get('sales/branch-sales/{branch}', [SaleController::class, 'branchSales'])->middleware('auth')->name('sales.branch-sales');
Route::get('sales-quality-certificate/{sale}', [SaleController::class, 'QualityCertificate'])->middleware('auth')->name('sales.quality-certificate');


// ------- (Produccion Routes)  ---------
Route::resource('productions', ProductionController::class)->except('show')->middleware('auth');
Route::get('/productions/{sale}', [ProductionController::class, 'show'])->middleware('auth')->name('productions.show');
Route::put('/productions/{production}/update-status', [ProductionController::class, 'updateStatus'])->middleware('auth')->name('productions.updateStatus');


// ------- (Rutas de envíos)  ---------
Route::resource('shipments', ShipmentController::class)->except(['create', 'show', 'edit', 'store'])->middleware('auth');
Route::get('/shipments/{sale}', [ShipmentController::class, 'show'])->middleware('auth')->name('shipments.show');
Route::post('shipments-get-matches', [ShipmentController::class, 'getMatches'])->middleware('auth')->name('shipments.get-matches');


// ------- (Rutas de proveedores)  ---------
Route::resource('suppliers', SupplierController::class)->middleware('auth');
Route::post('suppliers/massive-delete', [SupplierController::class, 'massiveDelete'])->middleware('auth')->name('suppliers.massive-delete');
Route::get('/suppliers/{supplier}/details', [SupplierController::class, 'getDetails'])->name('suppliers.get-details');


// ------- (rutas de contactos de proveedores)  ---------
Route::post('supplier-contacts', [SupplierContactController::class, 'store'])->middleware('auth')->name('supplier-contacts.store');
Route::put('supplier-contacts/{contact}', [SupplierContactController::class, 'update'])->middleware('auth')->name('supplier-contacts.update');
Route::delete('supplier-contacts/{contact}', [SupplierContactController::class, 'destroy'])->middleware('auth')->name('supplier-contacts.destroy');


// ------- (Rutas de Cuentas Bancarias de Proveedores) ---------
Route::post('supplier-bank-accounts', [SupplierBankAccountController::class, 'store'])->middleware('auth')->name('supplier-bank-accounts.store');
Route::put('supplier-bank-accounts/{bankAccount}', [SupplierBankAccountController::class, 'update'])->middleware('auth')->name('supplier-bank-accounts.update');
Route::delete('supplier-bank-accounts/{bankAccount}', [SupplierBankAccountController::class, 'destroy'])->middleware('auth')->name('supplier-bank-accounts.destroy');


// ------- (Rutas para la relación Productos <-> Proveedores) ---------
Route::post('suppliers/{supplier}/products', [SupplierProductController::class, 'store'])->middleware('auth')->name('suppliers.products.store');
Route::put('suppliers/{supplier}/products/{product}', [SupplierProductController::class, 'update'])->middleware('auth')->name('suppliers.products.update');
Route::delete('suppliers/{supplier}/products/{product}', [SupplierProductController::class, 'destroy'])->middleware('auth')->name('suppliers.products.destroy');


// ------- (Rutas de compras)  ---------
Route::resource('purchases', PurchaseController::class)->middleware('auth');
Route::post('purchases/massive-delete', [PurchaseController::class, 'massiveDelete'])->middleware('auth')->name('purchases.massive-delete');
Route::post('purchases-get-matches', [PurchaseController::class, 'getMatches'])->middleware('auth')->name('purchases.get-matches');
Route::put('purchases/authorize/{purchase}', [PurchaseController::class, 'authorizePurchase'])->middleware('auth')->name('purchases.authorize');
Route::get('purchases/print/{purchase}', [PurchaseController::class, 'print'])->middleware('auth')->name('purchases.print');
Route::put('/purchases/{purchase}/status', [PurchaseController::class, 'updateStatus'])->middleware('auth')->name('purchases.update-status');


// ------- (Rutas de diseño)  ---------
Route::resource('design-orders', DesignOrderController::class)->middleware('auth');
Route::put('design-orders/{designOrder}/start-work', [DesignOrderController::class, 'startWork'])->middleware('auth')->name('design-orders.start-work');
Route::post('design-orders/{designOrder}/finish-work', [DesignOrderController::class, 'finishWork'])->middleware('auth')->name('design-orders.finish-work');
Route::post('design-orders-get-matches', [DesignOrderController::class, 'getMatches'])->middleware('auth')->name('design-orders.get-matches');
Route::get('design-orders/authorize/{designOrder}', [DesignOrderController::class, 'authorizeDesignOrder'])->middleware('auth')->name('design-orders.authorize');
Route::get('design-orders-get-designers', [DesignOrderController::class, 'getDesigners'])->middleware('auth')->name('design-orders.get-designers');
Route::put('design-orders/{designOrder}/assign-designer', [DesignOrderController::class, 'assignDesigner'])->middleware('auth')->name('design-orders.assign-designer');
Route::post('/design-orders/check-similar', [DesignOrderController::class, 'checkSimilar'])->name('design-orders.check-similar');
Route::post('design-orders/massive-delete', [DesignOrderController::class, 'massiveDelete'])->middleware('auth')->name('design-orders.massive-delete');


// Ruta para almacenar las categorías de diseño creadas desde el modal
Route::post('design-categories', [DesignCategoryController::class, 'store'])->name('design-categories.store');


// ------- (Rutas de autorizacion de diseño)  ---------
Route::resource('design-authorizations', DesignAuthorizationController::class)->middleware('auth');
Route::get('design-authorizations/get-files/{designOrder}', [DesignAuthorizationController::class, 'getDesignOrderFiles'])->middleware('auth')->name('design-authorizations.get-files');
Route::put('design-authorizations/{designAuthorization}/client-response', [DesignAuthorizationController::class, 'updateClientResponse'])->middleware('auth')->name('design-authorizations.client-response');
Route::post('design-authorizations/{designAuthorization}/authorize-internal', [DesignAuthorizationController::class, 'authorizeInternal'])->middleware('auth')->name('design-authorizations.authorize-internal');
Route::get('design-authorizations/print/{designAuthorization}', [DesignAuthorizationController::class, 'print'])->middleware('auth')->name('design-authorizations.print');


// ------- (Tareas de produccion Routes)  ---------
Route::put('/production-tasks/{production_task}/status', [ProductionTaskController::class, 'updateStatus'])->middleware('auth')->name('production-tasks.updateStatus');
Route::get('/production-tasks/{task}/details', [ProductionTaskController::class, 'getTaskDetails'])->name('production-tasks.details')->middleware('auth');


// ------- Recursos humanos(users routes)  ---------
Route::resource('users', UserController::class)->middleware('auth');
Route::post('users-get-unseen-messages', [UserController::class, 'getUnseenMessages'])->middleware('auth')->name('users.get-unseen-messages');
Route::put('users-change-status/{user}', [UserController::class, 'changeStatus'])->middleware('auth')->name('users.change-status');
// Route::get('users-get-next-attendance', [UserController::class, 'getNextAttendance'])->middleware('auth')->name('users.get-next-attendance');
// Route::get('users-get-pause-status', [UserController::class, 'getPauseStatus'])->middleware('auth')->name('users.get-pause-status');
// Route::get('users-set-attendance', [UserController::class, 'setAttendance'])->middleware('auth')->name('users.set-attendance');
// Route::get('users-set-pause', [UserController::class, 'setPause'])->middleware('auth')->name('users.set-pause');
// Route::get('users-get-additional-time-requested-days/{user_id}/{payroll_id}', [UserController::class, 'getRequestedDays'])->middleware('auth')->name('users.get-additional-time-requested-days');
// Route::get('users-get-pendent-tasks', [UserController::class, 'getPendentTasks'])->middleware('auth')->name('users.get-pendent-tasks');
// Route::put('users-reset-pass/{user}', [UserController::class, 'resetPass'])->middleware('auth')->name('users.reset-pass');
// Route::put('users-update-pausas/{payroll_user}', [UserController::class, 'updatePausas'])->middleware('auth')->name('users.update-pausas');
// Route::put('users-update-vacations/{user}', [UserController::class, 'updateVacations'])->middleware('auth')->name('users.update-vacations');
// Route::post('users-get-notifications', [UserController::class, 'getNotifications'])->middleware('auth')->name('users.get-notifications');
// Route::post('users-read-notifications', [UserController::class, 'readNotifications'])->middleware('auth')->name('users.read-notifications');
// Route::post('users-delete-notifications', [UserController::class, 'deleteNotifications'])->middleware('auth')->name('users.delete-notifications');
// Route::get('users-get-all', [UserController::class, 'getAllUsers'])->middleware('auth')->name('users.get-all');
// Route::get('users-get-operators', [UserController::class, 'getOperators'])->middleware('auth')->name('users.get-operators');


// ------- Recursos humanos(Bonuses Routes)  ---------
Route::resource('bonuses', BonusController::class)->except(['create', 'edit', 'show', 'destroy'])->middleware('auth');
Route::post('bonuses/massive-delete', [BonusController::class, 'massiveDelete'])->middleware('auth')->name('bonuses.massive-delete');


// ------- Recursos humanos(Discounts Routes)  ---------
Route::resource('discounts', DiscountController::class)->except(['create', 'edit', 'show', 'destroy'])->middleware('auth');
Route::post('discounts/massive-delete', [DiscountController::class, 'massiveDelete'])->middleware('auth')->name('discounts.massive-delete');


// ------- Recursos humanos(Holidays Routes)  ---------
Route::resource('holidays', HolidayController::class)->except(['create', 'edit', 'show', 'destroy'])->middleware('auth');
Route::post('holidays/massive-delete', [HolidayController::class, 'massiveDelete'])->middleware('auth')->name('holidays.massive-delete');


// ------- Historial de acciones rutas  ---------
Route::get('/audits', [AuditController::class, 'index'])->middleware('auth')->name('audits.index');


// ------- Roles rutas  ---------
Route::resource('role-permissions', RolePermissionController::class)
->only(['index', 'store', 'update', 'destroy'])
->middleware(['auth', 'verified']);


// ------- Permisos rutas  ---------
Route::resource('permissions', PermissionController::class)
    ->only(['store', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);


// ------- tutorials & manuals routes  -------------
Route::resource('manuals', ManualController::class)->middleware('auth');
Route::put('manuals/increase-views/{manual}', [ManualController::class, 'increaseViews'])->name('manuals.increase-views')->middleware('auth');
Route::post('manuals/update-with-media/{manual}', [ManualController::class, 'updateWithMedia'])->name('manuals.update-with-media')->middleware('auth');


// ------- Machines Routes  ---------
Route::resource('machines', MachineController::class)->middleware('auth');
Route::post('machines/massive-delete', [MachineController::class, 'massiveDelete'])->middleware('auth')->name('machines.massive-delete');
Route::post('machines/upload-files/{machine}', [MachineController::class, 'uploadFiles'])->middleware('auth')->name('machines.upload-files');
// Route::post('machines/QR-search-machine', [MachineController::class, 'QRSearchMachine'])->name('machines.QR-search-machine');


// ------- Maintenances routes  -------------
Route::resource('maintenances', MaintenanceController::class)->except(['index', 'create', 'show'])->middleware('auth');
Route::get('maintenances/create/{selectedMachine}', [MaintenanceController::class, 'create'])->name('maintenances.create')->middleware('auth');
Route::put('maintenances/validate/{maintenance}', [MaintenanceController::class, 'validateWork'])->name('maintenances.validate')->middleware('auth');


// ---------- spare parts routes  ---------------
Route::resource('spare-parts', SparePartController::class)->except(['index', 'create', 'show'])->middleware('auth');
Route::get('spare-parts/create/{selectedMachine}', [SparePartController::class, 'create'])->name('spare-parts.create')->middleware('auth');
// Route::post('spare-parts/update-with-media/{spare_part}', [SparePartController::class, 'updateWithMedia'])->name('spare-parts.update-with-media')->middleware('auth');


// ---------- calendar (events/tasks) routes  ---------------
Route::prefix('calendar')->name('calendar.')->group(function () {
    // Vistas principales
    Route::get('/', [CalendarController::class, 'index'])->middleware('auth')->name('index');
    Route::get('/create', [CalendarController::class, 'create'])->middleware('auth')->name('create');
    Route::post('/', [CalendarController::class, 'store'])->middleware('auth')->name('store');

    // Acciones para Tareas
    Route::patch('/tasks/{task}/complete', [CalendarController::class, 'updateTaskStatus'])->middleware('auth')->name('tasks.complete');

    // Acciones para Eventos (invitaciones)
    Route::patch('/events/{event}/invitation', [CalendarController::class, 'updateInvitationStatus'])->middleware('auth')->name('events.invitation');
    
    // Acción para eliminar cualquier entrada
    Route::delete('/entries/{calendarEntry}', [CalendarController::class, 'destroy'])->middleware('auth')->name('entries.destroy');
});


// eliminacion de archivo desde componente FileView
Route::delete('/media/{media}', function (Media $media) {
    try {
        $media->delete(); // Elimina el archivo y su registro

        return response()->json(['message' => 'Archivo eliminado correctamente.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al eliminar el archivo.'], 500);
    }
})->name('media.delete-file');