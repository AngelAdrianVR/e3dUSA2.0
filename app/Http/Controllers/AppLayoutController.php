<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DesignOrder;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;

class AppLayoutController extends Controller
{
    /**
     * Módulos del buscador global. El orden define cómo se muestran las
     * secciones en el panel de resultados.
     */
    private const MODULES = [
        'products' => 'Productos',
        'branches' => 'Clientes / Sucursales',
        'sales' => 'Ventas',
        'design_orders' => 'Órdenes de diseño',
        'machines' => 'Máquinas',
        'users' => 'Usuarios',
    ];

    /** Resultados por módulo cuando la búsqueda no está filtrada. */
    private const LIMIT = 7;

    /** Resultados cuando el usuario elige un módulo específico. */
    private const FILTERED_LIMIT = 20;

    public function globalSearch(Request $request)
    {
        $term = trim((string) $request->input('term', ''));
        $module = (string) $request->input('module', '');

        if ($term === '') {
            return response()->json(['modules' => []]);
        }

        // Un módulo desconocido se trata como búsqueda sin filtro.
        if ($module !== '' && !array_key_exists($module, self::MODULES)) {
            $module = '';
        }

        $limit = $module !== '' ? self::FILTERED_LIMIT : self::LIMIT;

        $sections = match ($module) {
            'products' => ['products' => $this->searchProducts($term, $limit)],
            'branches' => ['branches' => $this->searchBranches($term, $limit)],
            'sales' => ['sales' => $this->searchSales($term, $limit)],
            'design_orders' => ['design_orders' => $this->searchDesignOrders($term, $limit)],
            'machines' => ['machines' => $this->searchMachines($term, $limit)],
            'users' => ['users' => $this->searchUsers($term, $limit)],
            default => [
                'products' => $this->searchProducts($term, $limit),
                'branches' => $this->searchBranches($term, $limit),
                'sales' => $this->searchSales($term, $limit),
                'design_orders' => $this->searchDesignOrders($term, $limit),
                'machines' => $this->searchMachines($term, $limit),
                'users' => $this->searchUsers($term, $limit),
            ],
        };

        $modules = [];

        foreach (self::MODULES as $key => $label) {
            if (empty($sections[$key])) {
                continue;
            }

            $modules[] = [
                'key' => $key,
                'label' => $label,
                'items' => $sections[$key],
            ];
        }

        return response()->json(['modules' => $modules]);
    }

    private function searchProducts(string $term, int $limit): array
    {
        return Product::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $this->like($term))
                    ->orWhere('code', 'like', $this->like($term));
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $this->prefix($term)])
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'code'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->name,
                'subtitle' => $product->code ? "Código: {$product->code}" : null,
                'url' => route('catalog-products.show', $product->id),
            ])
            ->all();
    }

    /**
     * Busca clientes y sucursales (incluidas las que pertenecen a un grupo o a una
     * matriz) por nombre, razón social, RFC, número de cliente o grupo.
     */
    private function searchBranches(string $term, int $limit): array
    {
        return Branch::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $this->like($term))
                    ->orWhere('business_name', 'like', $this->like($term))
                    ->orWhere('rfc', 'like', $this->like($term))
                    ->orWhere('client_number', 'like', $this->like($term))
                    ->orWhere('group_name', 'like', $this->like($term));
            })
            ->with('accountManager:id,name')
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $this->prefix($term)])
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'status', 'group_name', 'business_name', 'parent_branch_id', 'account_manager_id'])
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'title' => $branch->name,
                'subtitle' => $this->branchSubtitle($branch),
                'vendor' => $branch->accountManager?->name
                    ? "Vendedor: {$branch->accountManager->name}"
                    : 'Sin vendedor asignado',
                // Permite al frontend avisar cuando el cliente no pertenece al usuario logueado.
                'vendor_id' => $branch->account_manager_id,
                'badge' => $branch->status,
                'url' => route('branches.show', $branch->id),
            ])
            ->all();
    }

    private function searchSales(string $term, int $limit): array
    {
        // Las ventas solo se pueden localizar por su folio (id).
        if (!is_numeric($term)) {
            return [];
        }

        return Sale::query()
            ->where('id', $term)
            ->take($limit)
            ->get(['id', 'status', 'total_amount', 'type'])
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'title' => 'Orden ' . strtoupper((string) $sale->type) . ' #' . str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT),
                'subtitle' => 'Total: $' . number_format((float) $sale->total_amount, 2),
                'badge' => $sale->status,
                'url' => route('sales.show', $sale->id),
            ])
            ->all();
    }

    private function searchDesignOrders(string $term, int $limit): array
    {
        return DesignOrder::query()
            ->where('order_title', 'like', $this->like($term))
            ->latest()
            ->take($limit)
            ->get(['id', 'order_title', 'status'])
            ->map(fn ($order) => [
                'id' => $order->id,
                'title' => $order->order_title,
                'subtitle' => null,
                'badge' => $order->status,
                'url' => route('design-orders.show', $order->id),
            ])
            ->all();
    }

    private function searchMachines(string $term, int $limit): array
    {
        return Machine::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $this->like($term))
                    ->orWhere('serial_number', 'like', $this->like($term));
            })
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'serial_number'])
            ->map(fn ($machine) => [
                'id' => $machine->id,
                'title' => $machine->name,
                'subtitle' => $machine->serial_number ? "Serie: {$machine->serial_number}" : null,
                'url' => route('machines.show', $machine->id),
            ])
            ->all();
    }

    private function searchUsers(string $term, int $limit): array
    {
        return User::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $this->like($term))
                    ->orWhere('email', 'like', $this->like($term));
            })
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'title' => $user->name,
                'subtitle' => $user->email,
                'badge' => $user->is_active ? null : 'Inactivo',
                'url' => route('users.show', $user->id),
            ])
            ->all();
    }

    /**
     * Descripción secundaria de un cliente: grupo, tipo (matriz/sucursal) y razón social.
     */
    private function branchSubtitle(Branch $branch): string
    {
        $parts = [];

        if ($branch->group_name) {
            $parts[] = "Grupo: {$branch->group_name}";
        }

        $parts[] = $branch->parent_branch_id ? 'Sucursal' : 'Matriz';

        if ($branch->business_name && $branch->business_name !== $branch->name) {
            $parts[] = $branch->business_name;
        }

        return implode(' · ', $parts);
    }

    private function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    private function prefix(string $term): string
    {
        return addcslashes($term, '%_\\') . '%';
    }
}
