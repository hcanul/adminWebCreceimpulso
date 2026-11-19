<?php
// Crea un ZIP de entrega; no extrae archivos ni accede al hosting.
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Uso: php zip-package.php origen destino\n");
    exit(1);
}
$root = realpath($argv[1]);
if ($root === false || !is_dir($root)) {
    throw new RuntimeException('Carpeta de paquete inexistente.');
}
$zip = new ZipArchive();
if ($zip->open($argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('No se pudo crear el ZIP.');
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('El paquete no admite enlaces simbólicos.');
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
        continue;
    }
    if (!$file->isFile()) continue;
    if (preg_match('~(^|/)\.env(?:\.|$)~', $relative)) {
        throw new RuntimeException('El paquete contiene un archivo de entorno.');
    }
    if (!$zip->addFile($file->getPathname(), $relative)) {
        throw new RuntimeException('No se pudo añadir un archivo al ZIP.');
    }
}
if (!$zip->close()) throw new RuntimeException('No se pudo finalizar el ZIP.');
echo "Paquete cPanel creado.\n";
