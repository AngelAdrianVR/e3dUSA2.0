<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza la lógica de "grupos" de clientes/sucursales.
 *
 * Un grupo (campo `group_name` en branches) comparte productos e historial de
 * precios entre todas sus sucursales, sin importar si son matrices o hijas.
 * Los productos se consolidan en una sucursal "líder" del grupo, que es la
 * sucursal desde donde todos los miembros leen y escriben sus productos.
 */
class BranchGroupService
{
    /**
     * Nombre de grupo efectivo de una sucursal.
     *
     * Prioriza el grupo propio de la sucursal; si no tiene, hereda el de su
     * sucursal matriz.
     */
    public function resolveGroupName(Branch $branch): ?string
    {
        if (!empty($branch->group_name)) {
            return $branch->group_name;
        }

        if ($branch->parent_branch_id) {
            $branch->loadMissing('parent');

            if ($branch->parent && !empty($branch->parent->group_name)) {
                return $branch->parent->group_name;
            }
        }

        return null;
    }

    /**
     * Sucursal líder del grupo, que es donde se consolidan los productos.
     *
     * Se prefiere una matriz (parent_branch_id null) y, en su defecto, la de
     * menor id para que el líder sea determinista.
     */
    public function getGroupLeader(?string $groupName): ?Branch
    {
        if (empty($groupName)) {
            return null;
        }

        return Branch::where('group_name', $groupName)
            ->orderByRaw('CASE WHEN parent_branch_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();
    }

    /**
     * Sucursal desde donde se leen/escriben los productos de una sucursal.
     *
     * Si la sucursal pertenece a un grupo (propio o heredado de su matriz),
     * devuelve el líder del grupo. De lo contrario, conserva el comportamiento
     * anterior: la matriz si es hija, o ella misma.
     */
    public function getProductTargetBranch(Branch $branch): Branch
    {
        $groupName = $this->resolveGroupName($branch);

        if ($groupName) {
            $leader = $this->getGroupLeader($groupName);

            if ($leader) {
                return $leader;
            }
        }

        if ($branch->parent_branch_id) {
            $branch->loadMissing('parent');

            return $branch->parent ?? $branch;
        }

        return $branch;
    }

    /**
     * Consolida en el líder del grupo la unión de los productos (e historial de
     * precios vigente) de todos los miembros, sin duplicar productos.
     *
     * Importante: los productos NO se eliminan de cada miembro, de modo que si
     * una sucursal sale del grupo conserva su catálogo propio.
     */
    public function rebalance(?string $groupName): void
    {
        if (empty($groupName)) {
            return;
        }

        $leader = $this->getGroupLeader($groupName);

        if (!$leader) {
            return;
        }

        $members = Branch::where('group_name', $groupName)->get();

        foreach ($members as $member) {
            if ($member->id === $leader->id) {
                continue;
            }

            $this->mergeProductsInto($member, $leader);
        }
    }

    /**
     * Combina la unión de los productos (con su precio vigente) de `$source`
     * dentro de `$target`, sin duplicar productos ni sobrescribir precios.
     *
     * Los productos del origen NO se eliminan: conserva su catálogo propio para
     * el caso en que se separe del grupo o de su matriz.
     */
    public function mergeProductsInto(Branch $source, Branch $target): void
    {
        if ($source->id === $target->id) {
            return;
        }

        foreach ($source->products()->get() as $product) {
            $sourceActivePrice = DB::table('branch_price_history')
                ->where('branch_id', $source->id)
                ->where('product_id', $product->id)
                ->whereNull('valid_to')
                ->orderByDesc('valid_from')
                ->first();

            $targetHasProduct = $target->products()->where('products.id', $product->id)->exists();

            if (!$targetHasProduct) {
                $target->products()->attach($product->id);

                if ($sourceActivePrice) {
                    $this->pushActivePrice($target, $sourceActivePrice);
                }

                continue;
            }

            // El destino ya tiene el producto: solo asegura que tenga un precio
            // vigente si aún no cuenta con uno.
            $targetHasActivePrice = DB::table('branch_price_history')
                ->where('branch_id', $target->id)
                ->where('product_id', $product->id)
                ->whereNull('valid_to')
                ->exists();

            if (!$targetHasActivePrice && $sourceActivePrice) {
                $this->pushActivePrice($target, $sourceActivePrice);
            }
        }
    }

    /**
     * Reconsolida todos los grupos existentes. Útil para poblar/actualizar los
     * grupos ya registrados (por ejemplo, tras desplegar esta funcionalidad).
     */
    public function rebalanceAll(): int
    {
        $groupNames = Branch::whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->pluck('group_name');

        foreach ($groupNames as $groupName) {
            $this->rebalance($groupName);
        }

        return $groupNames->count();
    }

    /**
     * Registra un precio vigente en la sucursal destino a partir de un registro
     * de historial de precios existente.
     */
    private function pushActivePrice(Branch $target, object $sourcePrice): void
    {
        DB::table('branch_price_history')->insert([
            'branch_id' => $target->id,
            'product_id' => $sourcePrice->product_id,
            'user_id' => $sourcePrice->user_id,
            'price' => $sourcePrice->price,
            'currency' => $sourcePrice->currency,
            'valid_from' => now(),
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
