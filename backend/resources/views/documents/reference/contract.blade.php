<!doctype html><html lang="es"><meta charset="utf-8"><title>Contrato {{ $snapshot['folio'] }}</title>
@include('documents.reference.style')
<body>
<div class="notice">VERSIÓN PARA REVISIÓN · NO UTILIZAR PARA FIRMA. Texto de la referencia conservado literalmente. Las condiciones del texto requieren conciliación con la operación; esta emisión no modifica intereses, cargos ni pagos.</div>
@if($kind === 'contract_reference')
@include('documents.reference.note')
<div class="contract page">
@foreach($snapshot['reference_contract']['contract_paragraphs'] as $paragraph)
@if(str_starts_with($paragraph, 'PRESIDENTE(A) DEL COMITE'))
<div class="signatures"><div class="signature">PRESIDENTE(A) DEL COMITE</div><div class="signature">TESORERO(A) DEL COMITE</div></div>
@else
<p>{{ $paragraph }}</p>
@endif
@endforeach
</div>
<section class="page"><h1>A N E X O I</h1><p>{{ preg_replace('/^A N E X O I\s*/u', '', $snapshot['reference_contract']['annex_text']) }}</p>
<table><thead><tr><th>NOMBRE</th><th>PRESTAMO</th><th>CARGO</th><th>FIRMA</th></tr></thead><tbody class="stripes">@foreach($snapshot['terms']['members'] as $m)<tr><td>{{ $m['name'] }}</td><td>${{ $m['estimate']['total_repayment'] }}</td><td>{{ ['presidenta'=>'PRESIDENTE (A)','tesorera'=>'TESORERO (A)','integrante'=>'ELEMENTO'][$m['role_in_group']] }}</td><td></td></tr>@endforeach</tbody></table></section>
<section class="page"><div class="legal">{{ $snapshot['reference_contract']['rules_text'] }}</div><div class="signatures"><div class="signature">PRESIDENTE(A) DEL COMITE</div><div class="signature">TESORERO(A) DEL COMITE</div></div></section>
<section class="page"><div class="legal">{{ $snapshot['reference_contract']['payment_conditions_text'] }}</div><table><thead><tr><th>NOMBRE</th><th>CARGO</th><th>HUELLA</th><th>FIRMA</th></tr></thead><tbody>@foreach($snapshot['terms']['members'] as $m)<tr style="height:22mm"><td>{{ $m['name'] }}</td><td>{{ ['presidenta'=>'PRESIDENTE (A)','tesorera'=>'TESORERO (A)','integrante'=>'ELEMENTO'][$m['role_in_group']] }}</td><td></td><td></td></tr>@endforeach</tbody></table></section>
@else
@include('documents.reference.note')
@endif
<p class="footer">{{ $snapshot['folio'] }} · Documento {{ $id }} · Versión {{ $snapshot['source_version'] }} · Referencia {{ $snapshot['reference_contract']['source_sha256'] }} · Emitido {{ $issued }} (Cancún)</p>
</body></html>
