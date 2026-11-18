<?php
/** Render-only synthetic examples. Never calls issue/print actions or queries business data. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory=$argv[1];
config(['view.compiled'=>$directory.'/cache-vistas']);
if(!is_dir($directory.'/cache-vistas')) mkdir($directory.'/cache-vistas',0700);
$snapshot=json_decode(file_get_contents($directory.'/ejemplo.json'),true,flags:JSON_THROW_ON_ERROR)['snapshot'];
foreach($snapshot['terms']['members'] as $i=>&$member) $member['client_id']=Ramsey\Uuid\Uuid::uuid5(Ramsey\Uuid\Uuid::NAMESPACE_URL,'creceimpulso/synthetic/member/'.$i)->toString();
unset($member);
$snapshot['reference_contract']=app(App\Domains\Documents\Services\ReferenceContract::class)->payload($snapshot);
$snapshot['requested_total']='30000.00';$snapshot['requested_at']='2026-11-01';
$snapshot['member_addresses']=array_fill_keys(array_column($snapshot['terms']['members'],'client_id'),'Calle Ficticia 100, Colonia DEMO, Carrillo');
$save=function($name,$html)use($directory){$html=preg_replace('/(<body[^>]*>)/','$1<div style="font:9px Arial;background:#fff4cd;padding:3px">EJEMPLO FICTICIO · NO EMITIDO · Datos de muestra</div>',$html,1);file_put_contents($directory.'/'.$name.'.html',$html);};
foreach(['dossier'=>'solicitud','installation'=>'instalacion','authorization'=>'autorizacion','contract_reference'=>'Contrato','promissory_reference'=>'Pagare'] as $kind=>$name){
 $view=match($kind){'dossier'=>'documents.reference.dossier','installation'=>'documents.reference.installation','authorization'=>'documents.reference.authorization',default=>'documents.reference.contract'};
 $save($name,view($view,['snapshot'=>$snapshot,'kind'=>$kind,'id'=>'DEMO-NO-EMITIDO','issued'=>'18/11/2026 09:00'])->render());
}
$members=collect($snapshot['terms']['members'])->map(fn($m)=>$m+['total_repayment'=>'12800.00','weekly_payment'=>'800.00','late_fee_balance'=>$m['role_in_group']==='presidenta'?'30.00':'0.00','guarantee'=>'1000.00','overdue'=>$m['role_in_group']==='presidenta'?'330.00':'0.00','balance'=>$m['role_in_group']==='presidenta'?'11530.00':'11200.00']);
$statement=['credit'=>['group'=>'Las Flores DEMO','folio'=>'CRE-DEMO-000001','branch'=>'Carrillo DEMO','first_due_on'=>'2026-11-11'],'members'=>$members];
$common=['as_of'=>'2026-11-25','statement'=>$statement,'promoter'=>'Sofía Asesora Ejemplo'];
$balance=3840000;$rows=[];
for($i=0;$i<16;$i++){$paid=[240000,210000][$i]??0;$balance-=$paid;$rows[]=['number'=>$i+1,'due_on'=>Carbon\CarbonImmutable::parse('2026-11-11')->addWeeks($i)->toDateString(),'paid_on'=>$i<2?($i===0?'2026-11-11':'2026-11-18'):null,'payment_numbers'=>$i<2?(string)($i+1):'','days'=>$i===1?7:0,'due'=>'2400.00','paid'=>App\Domains\Credit\Services\CreditCalculator::money($paid),'balance'=>App\Domains\Credit\Services\CreditCalculator::money($balance)];}
$save('EstadoCuenta',view('reports.reference',$common+['kind'=>'statement','rows'=>$rows,'selected_members'=>$members,'total'=>'38400.00'])->render());
$save('deposito',view('reports.reference',$common+['kind'=>'deposits','deposits'=>collect([(object)['number'=>2,'received_on'=>'2026-11-18','deposited_on'=>'2026-11-18','bank_name'=>'Banco DEMO','bank_reference'=>'FICTICIO-018','amount'=>'2100.00']])])->render());
$save('cobranza',view('reports.reference',$common+['kind'=>'collection_log','first_overdue_on'=>'2026-11-18','member'=>$members->first(),'client'=>(object)['address'=>'Calle Ficticia 100 DEMO','phone'=>'0000000000']])->render());
$save('agenda',view('reports.reference',['kind'=>'agenda','as_of'=>'2026-11-25','week_on'=>'2026-11-23','week_until'=>'2026-11-27','actor_name'=>'Asesora Ficticia'])->render());
$loan=new App\Models\LoanApplication;$loan->forceFill(['id'=>'00000000-0000-4000-8000-000000000001','promoter_id'=>'demo','approved_snapshot'=>$snapshot['terms']]);
$row=(object)['id'=>$loan->id,'branch'=>'Carrillo DEMO','group'=>'Las Flores DEMO','folio'=>'CRE-DEMO-000001','installment_balance'=>'33900.00','balance'=>'33930.00','due'=>'2730.00','overdue'=>'330.00','late_fee_balance'=>'30.00','days_overdue'=>7];
$report=['as_of'=>'2026-11-25','data'=>collect([$row]),'total'=>1,'summary'=>['balance'=>'33930.00','installment_balance'=>'33900.00','late_fee_balance'=>'30.00','due'=>'2730.00']];
$save('ReporteCartera',view('reports.reference',['kind'=>'portfolio','as_of'=>'2026-11-25','report'=>$report,'loans'=>collect([$loan->id=>$loan]),'available_guarantees'=>collect([$loan->id=>'3000.00']),'promoters'=>collect(['demo'=>'Asesora Ficticia'])])->render());
echo "Diez formatos sintéticos renderizados sin emitir documentos ni credenciales.\n";
