<!doctype html><html lang="es"><meta charset="utf-8"><title>Solicitud {{ $snapshot['folio'] }}</title>
@include('documents.reference.style')
<style>@page{margin:9mm}body{font-size:8px;line-height:1.15}header{margin-bottom:2mm}header img{width:42mm}td{padding:.6mm}.label{font-size:6px}.value{min-height:3mm}.section-title{font-size:9px;padding:.6mm}.signatures{margin-top:9mm}</style><body>
@include('documents.reference.header',['title'=>'Solicitud de Credito'])
<table><colgroup>@for($i=0;$i<12;$i++)<col style="width:8.333%">@endfor</colgroup>
<tr>@foreach([['fecha','FECHA DE LLENADO',2],['city_id','OFICINA DE SERVICIOS',2],['ncliente','No. DEL CLIENTE',2],['nCredito','No. DEL CREDITO',2],['team_id','NOMBRE DEL GRUPO AL QUE PERTENECE',4]] as [$key,$label,$span])@include('documents.reference.field',compact('key','label','span'))@endforeach</tr>
<tr>@include('documents.reference.field',['key'=>'promoter_id','label'=>'PROMOTOR ASIGNADO','span'=>12])</tr>
<tr><td colspan="12" class="section-title">DATOS DEL SOLICITANTE</td></tr>
<tr>@foreach([['pNombre','PRIMER NOMBRE'],['sNombre','SEGUNDO NOMBRE'],['aPaterno','APELLIDO PATERNO'],['aMaterno','APELLIDO MATERNO']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>3])@endforeach</tr>
<tr>@foreach([['genero','SEXO'],['fNacimiento','FECHA DE NACIMIENTO'],['lNacimiento','LUGAR DE NACIMIENTO'],['pais','PAIS DE ORIGEN'],['nacionalidad','NACIONALIDAD'],['tidentificaion','TIPO IDENTIFICACION']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>2])@endforeach</tr>
<tr>@foreach([['rfc','R. F. C.'],['curp','C. U. R. P.'],['nIdentificacion','NUMERO DE IDENTIFICACION']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>4])@endforeach</tr>
<tr>@foreach([['ecivil','ESTADO CIVIL'],['regimen','REGIMEN'],['nescolar','NIVEL ESCOLAR'],['oficio','PROFESION U OFICIO']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>3])@endforeach</tr>
<tr><td colspan="12" class="section-title">DATOS DE UBICACION DEL DOMICILIO</td></tr>
<tr>@foreach([['dudcalle','CALLE',3],['dudne','No. EXT.',1],['dudni','No. INT.',1],['dudcolonia','COLONIA',2],['dudelegacion','DELEGACION O MUNICIPIO',3],['dudciudad','CIUDAD',2]] as [$key,$label,$span])@include('documents.reference.field',compact('key','label','span'))@endforeach</tr>
<tr>@foreach([['dudcp','C. P.',1],['dudreferencia','REFERENCIA UBICACION',4],['dudusotelefono','TELEFONO USO',2],['dudtelefono','NUM. TELEFONO',3],['dudrecidencia','LA RESIDENCIA ES',2]] as [$key,$label,$span])@include('documents.reference.field',compact('key','label','span'))@endforeach</tr>
<tr><td colspan="12" class="section-title">DATOS DE LA FAMILIA Y CONYUGUE</td></tr>
<tr>@foreach([['dfnombre','NOMBRE Y APELLIDO DEL CONYUGUE',5],['dftrtabaja','TRABAJA ACTUALMENTE',2],['dfcocupacion','OCUPACION',3],['dfchijos','NO. HIJOS',1],['dfcdeconomicos','DEPEN. ECONOMICOS',1]] as [$key,$label,$span])@include('documents.reference.field',compact('key','label','span'))@endforeach</tr>
<tr><td colspan="12" class="section-title">DATOS SITUACION ECONOMICA DEL SOLICITANTE</td></tr>
<tr>@foreach([['dseactividad','ACTIVIDAD ECONOMICA'],['dseofinggresos','OTRA FUENTE DE INGRESOS'],['dseihogar','INGRESOS DEL HOGAR']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>4])@endforeach</tr>
<tr>@foreach([['dsenphabitan','CUANTAS PERSONAS EN TOTAL VIVEN EN LA VIVIENDA'],['dseccvivienda','CUANTOS CUARTOS TIENE EN TOTAL ESTA VIVIENDA'],['desmaterial','DE QUE MATERIAL ES LA MAYOR PARTE DEL PISO DE SU VIVIENDA']] as [$key,$label])@include('documents.reference.field',['key'=>$key,'label'=>$label,'span'=>4])@endforeach</tr>
@foreach(['rf'=>'REFERENCIA FAMILIAR','rp'=>'REFERENCIA PERSONAL'] as $prefix=>$title)
<tr><td colspan="12" class="section-title">{{ $title }}</td></tr><tr>
@foreach([['Nombre','NOMBRE COMPLETO',5],['Parentesco','PARENTESCO',3],['Telefono','TELEFONO',4]] as [$suffix,$label,$span])@include('documents.reference.field',['key'=>$prefix.$suffix,'label'=>$label,'span'=>$span])@endforeach</tr><tr>
@foreach([['Domicilio','DOMICILIO',5],['cp','C. P.',1],['Ocupacion','OCUPACION',3],['LTrabajo','LUGAR DE TRABAJO',3]] as [$suffix,$label,$span])@include('documents.reference.field',['key'=>$prefix.$suffix,'label'=>$label,'span'=>$span])@endforeach</tr>
@endforeach
<tr><td colspan="12" class="section-title">ACTUALMENTE TIENE CREDITO VIGENTE?</td></tr>
<tr><th colspan="6">NOMBRE DE LA EMPRESA DE CREDITO</th><th colspan="2">FECHA INICIAL</th><th colspan="2">FECHA FINAL</th><th colspan="2">TOTAL ADEUDADO</th></tr>
@foreach(['','2'] as $suffix)<tr>@foreach([['nempresa','EMPRESA',6],['fechaini','INICIO',2],['fechafin','FIN',2],['total','TOTAL',2]] as [$key,$label,$span])@include('documents.reference.field',['key'=>$key.$suffix,'label'=>$label,'span'=>$span])@endforeach</tr>@endforeach
<tr><td colspan="12" class="section-title">* INFORMACION IMPORTANTE * AL SOLICITANTE</td></tr>
<tr>@include('documents.reference.field',['key'=>'tfdirectivos','label'=>'TIENE USTED FAMILIARES QUE OCUPEN PUESTOS DIRECTIVOS (1ER NIVEL) EN LA EMPRESA?','span'=>6])@include('documents.reference.field',['key'=>'dNombre','label'=>'OCUPA USTED O ALGUNOS DE SUS FAMILIARES PUESTOS DE ALTA RELEVANCIA EN EL SERVICIO PUBLICO?','span'=>6])</tr>
<tr>@include('documents.reference.field',['key'=>'ocupuesto','label'=>'SI NOMBRE','span'=>6])@include('documents.reference.field',['key'=>'dNombre2','label'=>'SI NOMBRE','span'=>6])</tr>
<tr><td colspan="12" class="section-title">OBSERVACIONES</td></tr><tr>@include('documents.reference.field',['key'=>'Observaciones','label'=>'','span'=>12])</tr>
<tr><td colspan="12">Declara el solicitante bajo protesta de decir verdad que: (1) Cumplirá las obligaciones de pago que contrae con el producto o servicio que en su caso llegue a recibir, únicamente con recursos provenientes de su propiedad y no actúa en nombre de terceros. (2) Ser el propietario real de los recursos que llegue a obtener en caso de que sea otorgado el crédito que solicita. (3) Se hace del conocimiento al solicitante que constituye un delito proporcionar datos y documentos falsos, así como actuar en nombre de terceros sin declararlo.</td></tr>
</table><div class="signatures"><div class="signature">{{ $snapshot['client'] }}<br>NOMBRE COMPLETO</div><div class="signature">FIRMA</div></div>
<p class="footer">Expediente {{ $snapshot['folio'] }} · Documento {{ $id }} · Versión {{ $snapshot['source_version'] }} · {{ $issued }}. El importe del nuevo crédito se captura en una solicitud independiente.</p>
</body></html>
