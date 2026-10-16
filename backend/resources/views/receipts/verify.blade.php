<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verificar comprobante · Crece Impulso</title>
<style>body{font-family:system-ui,sans-serif;background:#f3f4f7;color:#222735;margin:0;padding:24px}main{max-width:520px;margin:30px auto;background:white;border:1px solid #e3e4e8;border-radius:18px;padding:28px}h1{font-size:24px;color:#811335}.amount{font-size:36px;font-weight:700;color:#811335}dt{font-size:13px;color:#626b7b;margin-top:18px}dd{margin:4px 0;overflow-wrap:anywhere}.notice{background:#f6f0f2;padding:16px;border-radius:10px;line-height:1.5}footer{font-size:13px;color:#626b7b;line-height:1.5}</style></head>
<body><main><p>CRECE IMPULSO · VERIFICACIÓN</p>
@if($receipt)
<h1>{{ $receipt['status'] === 'reversed' ? 'Pago revertido' : 'Pago registrado' }}</h1>@if($receipt['status'] === 'reversed')<p class="notice">Este pago fue revertido el {{ $receipt['reversed_on'] }}. Ya no abona al saldo del crédito. Se conserva el comprobante original para consulta.</p>@endif<p class="amount">${{ number_format((float) $receipt['amount'], 2) }} MXN</p>
<dl><dt>Comprobante</dt><dd>{{ $receipt['folio'] }}</dd><dt>Crédito</dt><dd>{{ $receipt['credit_folio'] }}</dd><dt>Fecha de recepción</dt><dd>{{ $receipt['received_on'] }}</dd><dt>Medio de pago</dt><dd>{{ $receipt['method'] === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal' }}</dd></dl>
<p class="notice">Compara el folio, el importe y la fecha de esta página con el comprobante que recibiste. Si no coinciden, consulta en tu sucursal.</p>
<footer>Este resultado muestra el estado actual del pago. No representa un nuevo pago ni acredita la liquidación de todo el crédito. Una copia del QR conduce al mismo registro.</footer>
@else
<h1>No fue posible verificar este comprobante</h1><p>El enlace no es válido o no corresponde a un pago registrado. Consulta directamente en tu sucursal.</p>
@endif
</main></body></html>
