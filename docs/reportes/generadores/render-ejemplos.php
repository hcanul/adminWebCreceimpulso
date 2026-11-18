<?php
// Renderiza plantillas con una fixture ficticia. No ejecuta emisión ni consultas de datos.
require dirname(__DIR__, 3).'/backend/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = $argv[1];
config(['view.compiled' => $directory.'/cache-vistas']);
if (!is_dir($directory.'/cache-vistas')) mkdir($directory.'/cache-vistas', 0700);
$fixture = json_decode(file_get_contents($directory.'/ejemplo.json'), true, flags: JSON_THROW_ON_ERROR);
foreach (['dossier', 'installation', 'authorization', 'schedule', 'contract_review', 'promissory_review'] as $kind) {
    $snapshot = $fixture['snapshot'];
    if (in_array($kind, ['dossier', 'installation'])) $snapshot['folio'] = 'CLI-DEMO-000001';
    $view = match($kind) {'contract_review' => 'documents.contract-review', 'promissory_review' => 'documents.promissory-review', default => 'documents.record'};
    $html = view($view, ['snapshot'=>$snapshot,'kind'=>$kind,'id'=>'DEMO-NO-EMITIDO-'.$kind,'issued'=>'18/11/2026 09:00'])->render();
    $notice = '<div style="background:#fff1cc;padding:12px;font:12px Arial;margin-bottom:15px"><b>MUESTRA FICTICIA PARA REVISIÓN</b> · Plantilla actual renderizada con datos de ejemplo. No se emitió un documento operativo. No válida para firma, pago ni identificación.</div>';
    $html = preg_replace('/(<body[^>]*>)/', '$1'.$notice, $html, 1);
    file_put_contents($directory.'/'.$kind.'.html', $html);
}
$rows = array_map(fn($m) => ['name'=>$m['name'],'folio'=>$m['folio'],'password'=>'DEMO-NO-VALIDA','expires'=>'25/11/2026 09:00'], $fixture['snapshot']['terms']['members']);
$sheet=view('documents.client-access-sheet',['rows'=>$rows])->render();
file_put_contents($directory.'/access.html','<!doctype html><html lang="es"><meta charset="utf-8"><body><p>MUESTRA FICTICIA · Ninguna clave es válida</p>'.$sheet.'</body></html>');
echo "Seis plantillas y hoja anexa renderizadas con datos ficticios.\n";
