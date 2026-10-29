<section style="break-before:page;page-break-before:always;font-family:Arial,sans-serif">
<h1 style="color:#7e1838;font-size:23px">Acceso personal a Crece Impulso</h1>
<p>Hoja de entrega confidencial. Sucursal debe entregar a cada titular únicamente sus credenciales después de verificar su identidad. No entregar la tabla completa a las integrantes.</p>
<table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr><th>Cliente</th><th>Usuario</th><th>Contraseña temporal</th><th>Vence (Cancún)</th></tr></thead><tbody>
@foreach($rows as $row)
<tr>@foreach(['name','folio'] as $key)<td style="border:1px solid #bbb;padding:10px">{{ $row[$key] }}</td>@endforeach
<td style="border:1px solid #bbb;padding:10px;font-family:monospace">{{ $row['password'] ?? 'Cuenta existente: conserva su contraseña' }}</td><td style="border:1px solid #bbb;padding:10px">{{ $row['expires'] ?? '—' }}</td></tr>
@endforeach
</tbody></table>
<p>La contraseña temporal vence en siete días. Al primer acceso deberá cambiarla por una nueva. Las claves aparecen únicamente en esta impresión y no se guardan en el contrato. Reimprimir no cambia contraseñas ni permite recuperarlas; solicita asistencia presencial si no se entregó la hoja o se extravió.</p>
</section>
