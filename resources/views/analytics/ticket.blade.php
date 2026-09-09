<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ticket {{ $document->reference }}</title><style>body{font-family:system-ui,sans-serif;max-width:320px;margin:0 auto;padding:24px;color:#111}h1{font-size:18px}p,li,td{font-size:13px}table{width:100%;border-collapse:collapse}td{padding:4px 0;border-top:1px dashed #ccc}.total{font-size:16px;font-weight:700}@media print{.noprint{display:none}}</style></head>
<body>
<h1>{{ $document->snapshot['restaurant']['name'] }}</h1>
<p>Ticket {{ $document->reference }} · {{ $document->issued_at?->format('d/m/Y H:i') }}</p>
<table><tbody>@foreach ($document->snapshot['lines'] as $line)<tr><td>{{ $line['active_quantity'] }} × {{ $line['product_name'] }}</td><td style="text-align:right">{{ \App\CatalogMoney::format($line['net_minor']) }} €</td></tr>@endforeach</tbody></table>
<p>Subtotal: {{ \App\CatalogMoney::format($document->subtotal_minor) }} € · Descuento: -{{ \App\CatalogMoney::format($document->discount_minor) }} € · Cargos: {{ \App\CatalogMoney::format($document->charges_minor) }} €</p>
<p class="total">Total: {{ \App\CatalogMoney::format($document->total_minor) }} {{ $document->currency }}</p>
<p>Pagos: @foreach ($document->snapshot['payments'] as $payment)@foreach ($payment['tenders'] as $tender){{ $tender['method'] }} {{ \App\CatalogMoney::format($tender['amount_minor']) }} € · @endforeach@endforeach</p>
@if ($document->snapshot['restaurant']['ticket_footer'])<p>{{ $document->snapshot['restaurant']['ticket_footer'] }}</p>@endif
<p class="noprint"><button onclick="window.print()">Imprimir</button></p>
</body>
</html>
