<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra OC-{{ str_pad($purchase->id, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 2.5cm 1.5cm;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
        }
        .header, .footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #888;
            text-align: center;
        }
        .header {
            top: -2cm;
            height: 1.5cm;
            line-height: 1.5cm;
        }
        .footer {
            bottom: -2cm;
            height: 1.5cm;
            line-height: 1.5cm;
        }
        .footer .page:after {
            content: counter(page);
        }
        .logo {
            width: 150px;
        }
        h1 {
            font-size: 24px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            margin-top: 20px;
            float: right;
            width: 40%;
        }
        .total-section td {
            border: none;
            padding: 4px 10px;
        }
        .total-section .total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            color: #0056b3;
        }
        .info-table {
            margin-top: 25px;
        }
        .info-table td {
            border: none;
            padding: 2px 0;
        }
        .info-table strong {
            display: inline-block;
            width: 120px; /* Ancho fijo para las etiquetas */
        }
        .signatures {
            margin-top: 80px;
            width: 100%;
            text-align: center;
        }
        .signatures td {
            border: none;
            padding-top: 40px;
            border-top: 1px solid #333;
            width: 50%;
        }
        .watermark {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 100px;
            color: #000;
            opacity: 0.08;
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    @if(!$purchase->authorizer_id)
        <div class="watermark">{{ $purchase->is_spanish_template ? 'NO AUTORIZADO' : 'UNAUTHORIZED' }}</div>
    @endif

    <div class="header">
        OC-{{ str_pad($purchase->id, 4, '0', STR_PAD_LEFT) }}
    </div>

    <div class="footer">
        EMBLEMS 3D USA | <span class="page">Página </span>
    </div>

    <table style="width:100%; border: none;">
        <tr>
            <td style="width:40%; border: none;">
                {{-- Asumiendo que tienes tu logo en public/images/logo.png --}}
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo">
                <p style="font-size: 9px; margin-top: 10px; line-height: 1.4;">
                    Av. Aurelio Ortega 518, Seattle,<br>
                    45150 Zapopan, Jal.<br>
                    EDU211206DC9 | 33 38338209
                </p>
            </td>
            <td style="width:60%; text-align: right; border: none; vertical-align: top;">
                <h1>{{ $purchase->is_spanish_template ? 'Orden de Compra' : 'Purchase Order' }}</h1>
                <table class="info-table" style="float: right; text-align: right; margin-top: 10px;">
                    <tr><td><strong>Folio:</strong> OC-{{ str_pad($purchase->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                    <tr><td><strong>{{ $purchase->is_spanish_template ? 'Fecha Emisión:' : 'Issue Date:' }}</strong> {{ \Carbon\Carbon::parse($purchase->emited_at)->format('d/m/Y') }}</td></tr>
                    <tr><td><strong>{{ $purchase->is_spanish_template ? 'Fecha Entrega:' : 'Delivery Date:' }}</strong> {{ \Carbon\Carbon::parse($purchase->expected_delivery_date)->format('d/m/Y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width:100%; border: none; margin-top: 30px; font-size: 11px;">
        <tr>
            <td style="width:50%; border: none; background-color: #f5f5f5; padding: 10px; border-radius: 5px;">
                <strong>{{ $purchase->is_spanish_template ? 'PROVEEDOR' : 'SUPPLIER' }}</strong><br>
                <p style="margin: 5px 0 0 0; font-size: 12px; font-weight: bold; color: #0056b3;">{{ $purchase->supplier->name }}</p>
                <p style="margin: 5px 0 0 0;">{{ $purchase->supplier->address }}</p>
                <p style="margin: 5px 0 0 0;">{{ $purchase->supplier->phone }}</p>
            </td>
            <td style="width:50%; border: none; background-color: #f5f5f5; padding: 10px; border-radius: 5px;">
                <strong>{{ $purchase->is_spanish_template ? 'DATOS BANCARIOS' : 'BANKING DETAILS' }}</strong><br>
                @if($purchase->bankAccount)
                    <p style="margin: 5px 0 0 0;"><strong>Banco:</strong> {{ $purchase->bankAccount->bank_name }}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Cuenta:</strong> {{ $purchase->bankAccount->account_number }}</p>
                    <p style="margin: 5px 0 0 0;"><strong>CLABE:</strong> {{ $purchase->bankAccount->clabe }}</p>
                @else
                    <p style="margin: 5px 0 0 0;">No especificados</p>
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ $purchase->is_spanish_template ? 'Código' : 'Code' }}</th>
                <th>{{ $purchase->is_spanish_template ? 'Descripción' : 'Description' }}</th>
                <th class="text-right">{{ $purchase->is_spanish_template ? 'Cantidad' : 'Quantity' }}</th>
                <th class="text-right">{{ $purchase->is_spanish_template ? 'P. Unitario' : 'Unit Price' }}</th>
                <th class="text-right">{{ $purchase->is_spanish_template ? 'Importe' : 'Total' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $item)
            <tr>
                <td>{{ $item->product->code }}</td>
                <td>
                    {{ $item->description }}
                    {{-- INICIO: Sección de Distribución --}}
                    @if($item->plane_stock > 0 || $item->ship_stock > 0 || $item->additional_stock > 0)
                        <div style="font-size: 8px; color: #555; margin-top: 5px; padding-left: 10px; border-left: 2px solid #eee;">
                            @if($item->plane_stock > 0)
                                <span style="display: block; margin-bottom: 2px;"><strong>Avión:</strong> {{ number_format($item->plane_stock, 2) }} {{ $item->product->measure_unit }}</span>
                            @endif
                            @if($item->ship_stock > 0)
                                <span style="display: block; margin-bottom: 2px;"><strong>Barco:</strong> {{ number_format($item->ship_stock, 2) }} {{ $item->product->measure_unit }}</span>
                            @endif
                            @if($item->additional_stock > 0)
                                <span style="display: block;"><strong>A Favor:</strong> {{ number_format($item->additional_stock, 2) }} {{ $item->product->measure_unit }}</span>
                            @endif
                        </div>
                    @endif
                    {{-- FIN: Sección de Distribución --}}
                </td>
                <td class="text-right">{{ number_format($item->quantity, 2) }} {{ $item->product->measure_unit }}</td>
                <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">${{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total-section">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">${{ number_format($purchase->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>{{ $purchase->is_spanish_template ? 'Impuestos' : 'Tax' }} ({{ $purchase->tax > 0 ? '16%' : '0%' }})</td>
            <td class="text-right">${{ number_format($purchase->tax, 2) }}</td>
        </tr>
        <tr class="total">
            <td>Total ({{$purchase->currency}})</td>
            <td class="text-right">${{ number_format($purchase->total, 2) }}</td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    @if($purchase->notes)
        <div style="margin-top: 30px;">
            <strong>{{ $purchase->is_spanish_template ? 'Notas:' : 'Notes:' }}</strong>
            <p style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">{{ $purchase->notes }}</p>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>{{ $purchase->user->name }}<br><strong>{{ $purchase->is_spanish_template ? 'Solicitado por' : 'Requested by' }}</strong></td>
            <td>@if($purchase->authorizer){{ $purchase->authorizer->name }}@else &nbsp; @endif<br><strong>{{ $purchase->is_spanish_template ? 'Autorizado por' : 'Authorized by' }}</strong></td>
        </tr>
    </table>
</body>
</html>

