<?php
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
$archive = $root.'/artifacts/creceimpulso-cpanel.zip';
$revision = trim(shell_exec('git rev-parse HEAD') ?? '');
$number = getenv('BUILD_NUMBER');
if (!preg_match('/^[a-f0-9]{40}$/', $revision) || !preg_match('/^[0-9]+$/', $number ?: '') || !is_file($archive)) {
    throw new RuntimeException('Falta una revisión, número de ejecución o paquete válido.');
}
if (!getenv('CPANEL_FTP_USER') || !getenv('CPANEL_FTP_PASSWORD')) throw new RuntimeException('Falta la credencial FTPS de Jenkins.');
$release = 'ci-'.$number.'-'.substr($revision, 0, 12);
$manifest = json_encode(['release' => $release, 'revision' => $revision, 'sha256' => hash_file('sha256', $archive)], JSON_THROW_ON_ERROR);
function transfer(string $name, $stream = null, bool $rename = false): string {
    $curl = curl_init('ftp://svgm374.serverneubox.com.mx:21/jenkins-creceimpulso-pruebas/'.$name.($rename ? '.tmp' : ''));
    curl_setopt_array($curl, [CURLOPT_USERPWD => getenv('CPANEL_FTP_USER').':'.getenv('CPANEL_FTP_PASSWORD'), CURLOPT_USE_SSL => CURLUSESSL_ALL, CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 300, CURLOPT_RETURNTRANSFER => true]);
    if ($stream !== null) {
        $stat = fstat($stream);
        curl_setopt_array($curl, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $stream, CURLOPT_INFILESIZE => $stat['size']]);
        if ($rename) curl_setopt($curl, CURLOPT_POSTQUOTE, ['RNFR '.$name.'.tmp', 'RNTO '.$name]);
    }
    $result = curl_exec($curl);
    $error = curl_errno($curl);
    curl_close($curl);
    if ($result === false) throw new RuntimeException('Falló FTPS (código '.$error.').');
    return $result;
}
$stream = fopen($archive, 'rb');
transfer('incoming.zip', $stream, true);
fclose($stream);
$stream = fopen('php://temp', 'w+'); fwrite($stream, $manifest); rewind($stream);
transfer('request.json', $stream, true); fclose($stream);
echo 'Entrega solicitada: '.$release."\n";
for ($attempt = 0; $attempt < 60; $attempt++) {
    sleep(10);
    try { $status = json_decode(transfer('status.json'), true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable) { continue; }
    if (($status['release'] ?? '') !== $release) continue;
    if (($status['state'] ?? '') === 'success') { echo "Publicación y comprobación local correctas.\n"; exit(0); }
    if (($status['state'] ?? '') === 'failure') throw new RuntimeException('El hosting detuvo la entrega en la fase '.($status['phase'] ?? 'desconocida').'. Revisar el registro privado y el respaldo.');
}
throw new RuntimeException('No llegó confirmación del cron en diez minutos. Revisar el hosting antes de reintentar.');
