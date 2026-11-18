@include('documents.reference.header',['title'=>'PAGARE'])
<table style="width:48mm;margin-left:auto"><tr><td>BUENO POR:</td></tr><tr><td style="font-size:18px;text-align:right">{{ $snapshot['reference_contract']['amount'] }}</td></tr></table>
<p style="margin:4mm 0;text-align:justify">{{ $snapshot['reference_contract']['promissory_text'] }}</p><h2>DEUDORES SOLIDARIOS</h2>
@foreach($snapshot['terms']['members'] as $m)
<div class="sign-block"><div class="details">{{ ['presidenta'=>'PRESIDENTE (A)','tesorera'=>'TESORERO(A)','integrante'=>'ELEMENTO'][$m['role_in_group']] }} ({{ $loop->iteration }}/{{ count($snapshot['terms']['members']) }})<p>CLIENTE: {{ $m['name'] }}</p><p>DIRECCION: {{ $snapshot['member_addresses'][$m['client_id']] ?? '___________________________' }}</p><p style="text-align:center">ACEPTO</p><div class="signature" style="margin-top:3mm">FIRMA</div></div><div class="finger">HUELLA</div></div>
@endforeach
<div class="sign-block"><div class="details">AVAL SOLIDARIO<p>CLIENTE: ___________________________</p><p>DIRECCION: ___________________________</p><p style="text-align:center">ACEPTO</p><div class="signature" style="margin-top:3mm">FIRMA</div></div><div class="finger">HUELLA</div></div>
