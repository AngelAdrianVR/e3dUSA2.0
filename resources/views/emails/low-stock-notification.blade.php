<x-mail::message>
# Alerta de Nivel de Stock

**Se acaba de crear/actualizar una orden de venta (Órden #{{ $sale->id }}) y los siguientes productos llegaron al limite permitido, favor de avisar a compras para reponer y tener un stock sano.**

Aquí está el resumen de los productos afectados:

<x-mail::table>
| Producto | Código | Stock Actual | Mínimo Permitido | ⚠️ |
|:---------|:-------|:-------------|:-----------------|:----|
@foreach($products as $product)
| {{ $product->name }} | {{ $product->code ?? 'N/A' }} | **{{ $product->current_stock }}** | {{ $product->min_quantity }} | {{ $product->requires_director_approval ? 'Consultar con Sherman antes de comprar' : '' }} |
@endforeach
</x-mail::table>

<br>
Saludos,<br>
{{ config('app.name') }}
</x-mail::message>