from pathlib import Path
from datetime import date,timedelta
from bs4 import BeautifulSoup,Tag
from docx import Document
from docx.shared import Inches,Pt,RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
import json,re,base64,shutil
root=Path(__file__).resolve().parents[3]
out=Path('/Users/hugocanulechazarreta/Downloads/CreceImpulso-Reportes-Revision')
source=BeautifulSoup((root/'docs/reportes/ejemplos-reportes.html').read_text(),'html.parser')
fixture=json.loads((out/'ejemplo.json').read_text())['snapshot']
# Navegación del catálogo ampliado. Las seis plantillas mantienen su HTML de origen.
style='''body{font:14px/1.5 Arial;background:#f3f4f7;color:#252936;margin:0}main{max-width:1100px;margin:auto;padding:24px}section,header{background:white;padding:28px;margin:20px 0;border:1px solid #ddd;border-radius:12px}h1{font-size:30px}h2{color:#811335}nav a{display:block;color:#811335;margin:6px 0}table{border-collapse:collapse;width:100%;font-size:12px}td,th{padding:8px;border:1px solid #ddd;text-align:left}th{background:#f8edf1}.notice{background:#fff6dd;padding:12px}button{background:#811335;color:white;padding:12px;border:0}img{max-width:100%}.scroll{overflow:auto}@media print{body{background:white}main{padding:0}section{break-before:page;border:0}nav,button{display:none}}'''
intro='''<h1>Guía de reportes y documentos de Crece Impulso</h1><p>Edición para verificación funcional · 18 de noviembre de 2026</p><p>Esta guía permite revisar qué reportes genera Admin Web, cómo obtenerlos y qué datos deben comprobarse. Incluye ejemplos completos con un mismo crédito ficticio y una propuesta separada para cartera vencida.</p><p class="notice"><b>DATOS FICTICIOS.</b> Los formatos documentales se renderizaron desde las plantillas actuales. Los reportes de cartera, estados, ticket y corte son ejemplos explicativos de contenido. Contrato y pagaré siguen en revisión. No se emitieron créditos, pagos, claves ni documentos operativos.</p>'''
items=[]
def custom(title,html):items.append((title,html))
def tab(headers,rows):
 from html import escape
 return '<table><thead><tr>'+''.join('<th>'+escape(str(x))+'</th>' for x in headers)+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+escape(str(x))+'</td>' for x in row)+'</tr>' for row in rows)+'</tbody></table>'
custom('Cómo revisar los ejemplos','<p>1. Verifica sucursal, grupo, folios y fecha de consulta. 2. Compara los importes aprobados con las condiciones del crédito. 3. Revisa las 16 fechas y los pagos aplicados. 4. Confirma que los totales individuales sumen el grupo. 5. Anota correcciones de campos, textos y presentación en la hoja final.</p><p>La impresión se realiza desde Admin Web. Para guardar PDF, abre el reporte, utiliza su botón de impresión y selecciona Guardar como PDF en el navegador. La cartera dispone de descarga CSV para abrirse en Excel; no cuenta con un botón propio de impresión.</p><p>Las consultas respetan los permisos y sucursales del usuario. Si una opción no aparece, revisa el rol y alcance. La generación de un formato no sustituye aprobación, entrega ni cobro.</p>')
custom('Inventario de salidas disponibles',tab(['Salida','Acceso en Admin Web','Formato'],[['Cartera y vencimientos','Menú Cartera','Consulta y CSV'],['Estado de cuenta grupal e individual','Crédito → Estado de cuenta','Imprimir o PDF'],['Ticket de cobro','Caja → Comprobante','Imprimir y verificar QR'],['Corte completo','Cortes → Reporte','Imprimir o PDF'],['Expediente','Cliente → Documentos','Documento versionado'],['Acta de instalación','Expediente → Documentos','Documento versionado'],['Acta de autorización','Crédito autorizado → Documentos','Documento versionado'],['Calendario','Crédito autorizado → Documentos','Documento versionado'],['Contrato para revisión','Crédito autorizado → Documentos','Borrador no apto para firma'],['Pagaré grupal para revisión','Crédito autorizado → Documentos','Borrador no apto para firma'],['Accesos temporales','Anexo al imprimir contrato','Hoja confidencial cuando corresponde']])+'<p>Dashboard, bandejas de solicitudes y consulta de garantía no son reportes exportables independientes verificados. Los ajustes por reversos se muestran en los reportes correspondientes.</p>')
custom('Crédito usado en los ejemplos','<p>Grupo Las Flores DEMO, sucursal Carrillo DEMO, tres integrantes. Folio CRE-DEMO-000001. Fecha de corte de estados de cuenta: 25/11/2026.</p>'+tab(['Concepto','Por integrante','Grupo'],[['Capital aprobado','10,000.00','30,000.00'],['Interés pactado','2,800.00','8,400.00'],['Total de cuotas','12,800.00','38,400.00'],['Cuota semanal','800.00','2,400.00'],['Garantía retenida','1,000.00','3,000.00'],['Neto entregable','9,000.00','27,000.00']])+'<p>Montos en MXN. Primer vencimiento 11/11/2026; último 24/02/2027. Factor de ejemplo 80 por cada mil de capital: (10,000 / 1,000) × 80 = 800 por semana; 800 × 16 = 12,800. Interés = 12,800 − 10,000. No confundir el factor con una tasa porcentual directa.</p><p>Semana 1 pagada completa el 11/11 por $2,400. Semana 2 pagada parcialmente el 18/11 por $2,100: María $500, Ana $800, Lucía $800. El faltante de María es $300; genera $30 de mora el 19/11. Al corte 25/11 no ha iniciado la segunda semana de atraso. No hay reversos ni aplicaciones de garantía en este caso.</p>')
portfolio=[['CRE-DEMO-000001','Las Flores DEMO','Carrillo','33,900.00','30.00','33,930.00','2,730.00','2,400.00','330.00','7'],['CRE-DEMO-000002','Horizonte DEMO','Tulum','12,800.00','0.00','12,800.00','0.00','0.00','0.00','0'],['TOTAL','','','46,700.00','30.00','46,730.00','2,730.00','2,400.00','330.00','']]
custom('Cartera y vencimientos','<p>Ruta: Menú → Cartera y vencimientos → Descargar CSV. Busca por folio, grupo o sucursal y selecciona Todos, Con atraso, Vence hoy o Sin atraso. El CSV incluye todos los resultados del filtro, no solo la página visible.</p>'+tab(['Crédito','Grupo','Sucursal','Cuotas','Mora','Saldo','Exigible','Hoy','Atraso','Días'],portfolio)+'<p>Corte 25/11/2026. El segundo crédito es un ejemplo adicional sin vencimiento exigible a la fecha; no forma parte de Las Flores. Próximo vencimiento de Las Flores: 25/11/2026; el del crédito adicional: 02/12/2026. El CSV real también incluye UUID, filtros y fecha operativa.</p><p>Verifica que saldo = cuotas + mora. Exigible, vencido y cuotas de hoy son componentes del saldo, no cantidades que se sumen de nuevo. Actualmente un crédito atrasado permanece en Todos; no existe traslado administrativo a cartera vencida.</p>')
calendar=[]
for i in range(16):
 due=date(2026,11,11)+timedelta(weeks=i)
 paid=[2400,2100]+[0]*14
 total=2400;p=paid[i];state='Pagado' if i==0 else 'Vencido con abono' if i==1 else 'Vence hoy' if i==2 else 'Futuro'
 calendar.append([i+1,due.isoformat(),f'{total:,.2f}',f'{p:,.2f}',f'{total-p:,.2f}',state])
custom('Estado de cuenta grupal completo','<p>Ruta: Crédito → Estado de cuenta → Grupo → Imprimir o guardar PDF. Grupo Las Flores DEMO. Corte 25/11/2026.</p>'+tab(['Integrante','Total pactado','Cuotas pagadas','Saldo cuotas','Mora'],[['María','12,800.00','1,300.00','11,500.00','30.00'],['Ana','12,800.00','1,600.00','11,200.00','0.00'],['Lucía','12,800.00','1,600.00','11,200.00','0.00'],['TOTAL','38,400.00','4,500.00','33,900.00','30.00']])+tab(['Semana','Vencimiento','Cuota','Pagado','Pendiente','Estado'],calendar)+'<p>Saldo total $33,930.00. Cuotas atrasadas $300.00 y mora $30.00: atraso $330.00. Cuota pendiente de hoy $2,400.00: exigible total $2,730.00. Pagos futuros pendientes $31,200.00. La mora no está sumada dentro de las filas de cuotas.</p>')
individual=[]
for i,r in enumerate(calendar):
 p=800 if i==0 else 500 if i==1 else 0
 individual.append([r[0],r[1],'800.00',f'{p:,.2f}',f'{800-p:,.2f}',r[-1]])
custom('Estado de cuenta individual completo','<p>Ruta: Estado de cuenta → Seleccionar integrante → María Elena Ejemplo Muestra. CLI-DEMO-000001. Corte 25/11/2026.</p>'+tab(['Semana','Vencimiento','Cuota','Pagado','Pendiente','Estado'],individual)+'<p>Total pactado $12,800.00 − cuotas pagadas $1,300.00 = saldo de cuotas $11,500.00. Mora pendiente $30.00. Saldo total $11,530.00. Atraso $330.00; exigible $1,130.00. El pago de $500 se aplicó a la segunda cuota; la garantía continúa retenida y no se considera un pago.</p><p>Movimientos: 11/11/2026, pago #1, efectivo, $800 a María; 18/11/2026, pago #2, depósito FICTICIO-018, $500 a María; 19/11/2026, cargo de mora por $30. No hay cambios por garantía ni reversos.</p>')
for n in [4,5]:
 s=source.select('section')[n-1]
 title=s.h2.get_text(' ',strip=True)
 body=''.join(str(x) for x in s.contents if not isinstance(x,Tag) or x.name!='h2')
 custom(title,body+'<p>Comprobar: referencia y fecha de recepción, desglose de cuotas/mora y autorización de consulta. Un depósito entra al corte de depósitos; no incrementa el efectivo físico contado. El corte de ejemplo corresponde únicamente al día 18/11.</p>')
# Seis documentos con texto íntegro de las plantillas actuales y fixture completa.
for kind,title,route in [('dossier','Expediente del cliente','Cliente → Documentos → Generar expediente'),('installation','Acta de instalación y domicilio','Expediente → Documentos → Acta de instalación'),('authorization','Acta de autorización','Crédito autorizado → Documentos → Acta de autorización'),('schedule','Calendario autorizado','Crédito autorizado → Documentos → Calendario'),('contract_review','Contrato para revisión','Crédito autorizado → Documentos → Contrato para revisión'),('promissory_review','Pagaré grupal para revisión','Crédito autorizado → Documentos → Pagaré grupal')]:
 s=BeautifulSoup((out/(kind+'.html')).read_text(),'html.parser')
 body=''.join(str(x) for x in s.body.contents)
 custom(title,'<p>Ruta: '+route+'. El ejemplo siguiente reproduce el contenido íntegro de la plantilla actual con datos ficticios; el diseño Word adapta la presentación.</p>'+body)
custom('Hoja de accesos temporales','<p>Al imprimir el contrato vigente, el flujo contempla entregar una hoja anexa a titulares sin cuenta creada. Si ya tienen cuenta, conservan su contraseña. Reimprimir no revela ni renueva claves. Esta muestra se renderizó sin ejecutar ese flujo y no creó usuarios.</p>'+''.join(str(x) for x in BeautifulSoup((out/'access.html').read_text(),'html.parser').body.contents))
custom('Propuesta de cartera vencida','<p>Esta sección describe una mejora pendiente. El sistema actual calcula atraso, pero no tiene un traslado auditado a una cartera separada. La muestra siguiente no corresponde a una pantalla implementada.</p>'+tab(['Vista propuesta','Crédito','Saldo','Tratamiento'],[['Ordinaria','CRE-DEMO-000002','12,800.00','Se mantiene en reporte ordinario'],['Vencida o recuperación','CRE-DEMO-000001','33,930.00','Se excluye solo del reporte ordinario'],['Consolidado','Ambos','46,730.00','Conserva conciliación total']])+'<p>El traslado se haría sobre el ciclo de crédito, con usuario autorizado, fecha y motivo. No cancela, liquida ni elimina la deuda; no modifica pagos o cortes. La consulta general consolidada debe conservar el saldo de ambas carteras.</p><p>Pendiente de definir antes de programar: quién autoriza el traslado y la reincorporación; si habrá un mínimo de atraso o cierre del ciclo; si un abono modifica la clasificación; qué reportes y métricas deben separar ambas carteras. No se ha aplicado una política automática.</p>')
custom('Hoja de verificación y observaciones',tab(['Comprobación','Resultado esperado','Observación'],[['Identidad y sucursal','Folio, grupo y sucursal correctos',''],['Condiciones','Solicitado y aprobado separados',''],['Cuotas','16 fechas; última 24/02/2027 en ejemplo',''],['Importes','Capital 30,000; interés 8,400; total 38,400',''],['Retención','Garantía 3,000; neto 27,000',''],['Abonos','4,500 a cuotas del grupo',''],['Mora','30; sin capitalización',''],['Saldo','33,930 al 25/11/2026',''],['Caja','2,400 efectivo y 2,100 depósitos el 18/11',''],['Legal','Contrato y pagaré aún en revisión',''],['Accesos','Entrega confidencial por titular',''],['Cartera vencida','Propuesta pendiente de implementación','']])+'<p>Revisado por: ____________________ Fecha: ____________________</p><p>Cambios solicitados y reporte al que corresponden:</p><p>______________________________________________________________________</p><p>______________________________________________________________________</p>')
# HTML navegable con estilos propios de cada plantilla conservados en páginas individuales.
links=''.join(f'<a href="#s{i}">{i:02d} {t}</a>' for i,(t,_) in enumerate(items,1))
html=f'<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Guía completa de reportes</title><style>{style}</style><main><header>{intro}<button onclick="window.print()">Imprimir guía</button><nav>{links}</nav></header>'+''.join(f'<section id="s{i}"><h2>{i:02d} {t}</h2>{b}</section>' for i,(t,b) in enumerate(items,1))+'</main></html>'
(out/'Guia-completa-reportes.html').write_text(html)
# Word editable con tabla de contenido estática y todas las tablas completas.
doc=Document(); sec=doc.sections[0];sec.page_width=Inches(8.5);sec.page_height=Inches(11);sec.top_margin=Inches(.65);sec.bottom_margin=Inches(.65);sec.left_margin=Inches(.65);sec.right_margin=Inches(.65)
normal=doc.styles['Normal'];normal.font.name='Calibri';normal.font.size=Pt(10);normal.paragraph_format.space_after=Pt(6)
for name,size in [('Title',28),('Heading 1',20),('Heading 2',13),('Heading 3',11)]:
 st=doc.styles[name];st.font.name='Calibri';st.font.size=Pt(size);st.font.color.rgb=RGBColor.from_string('000000' if name=='Title' else '811335')
 st.paragraph_format.space_before=Pt(10);st.paragraph_format.space_after=Pt(6)
footer=sec.footer.paragraphs[0];footer.alignment=2
footer.add_run('Crece Impulso  |  Ejemplos ficticios  |  ')
fld=OxmlElement('w:fldSimple');fld.set(qn('w:instr'),'PAGE');footer._p.append(fld)
doc.add_picture(str(root/'frontend/apps/admin-web/public/crece-impulso-logo.png'),width=Inches(2.5))
doc.add_paragraph('Guía de reportes y documentos de Crece Impulso','Title')
doc.add_paragraph('Ejemplos completos para revisión funcional')
doc.add_paragraph('18 de noviembre de 2026')
for p in BeautifulSoup(intro,'html.parser').find_all('p'): doc.add_paragraph(p.get_text(' ',strip=True))
doc.add_heading('Contenido',1)
for i,(t,_) in enumerate(items,1):
 p=doc.add_paragraph(f'{i:02d}  {t}');p.paragraph_format.space_after=Pt(2);p.paragraph_format.line_spacing=1
def convert(node):
 if not isinstance(node,Tag):return
 if node.name in ['style','script','img','meta']:return
 if node.name=='table':
  rows=node.find_all('tr');width=max([len(r.find_all(['th','td'],recursive=False)) for r in rows] or [1]);t=doc.add_table(rows=0,cols=width);t.style='Table Grid';t.autofit=False
  for i,r in enumerate(rows):
   cells=t.add_row().cells
   for j,c in enumerate(r.find_all(['th','td'],recursive=False)):
    cells[j].text=c.get_text(' ',strip=True)
    for p in cells[j].paragraphs:
     p.paragraph_format.space_after=Pt(3);p.paragraph_format.space_before=Pt(3)
     for run in p.runs:run.font.size=Pt(8);run.bold=(i==0)
   trPr=t.rows[-1]._tr.get_or_add_trPr();cant=OxmlElement('w:cantSplit');trPr.append(cant)
   if i==0:
    repeat=OxmlElement('w:tblHeader');trPr.append(repeat)
    for cell in cells:
     shade=OxmlElement('w:shd');shade.set(qn('w:fill'),'F6EAF0');cell._tc.get_or_add_tcPr().append(shade)
  doc.add_paragraph('');return
 if node.name in ['h1','h2','h3']:
  text=node.get_text(' ',strip=True)
  if text:doc.add_heading(re.sub(r'[^\w\sáéíóúüñÁÉÍÓÚÜÑ]',' ',text),2)
  return
 if node.name in ['p','footer']:
  text=node.get_text(' ',strip=True)
  if text:doc.add_paragraph(text)
  return
 if node.name=='div' and not node.find(['p','div','table','h1','h2','h3']):
  text=node.get_text(' ',strip=True)
  if text:doc.add_paragraph(text)
  return
 if node.name=='br':return
 classes=node.get('class',[])
 if node.name=='div' and any(c in classes for c in ['field','meta','notice','signature']):
  text=node.get_text(' ',strip=True)
  if text:doc.add_paragraph(text)
  return
 if node.name=='div' and 'blank' in classes:
  doc.add_paragraph('Espacio para croquis de la visita');t=doc.add_table(rows=1,cols=1);t.style='Table Grid';t.rows[0].height=Inches(1.2);return
 for c in node.children:convert(c)
for i,(title,body) in enumerate(items,1):
 h=doc.add_heading(f'{i:02d} {title}',1);h.paragraph_format.page_break_before=True
 for n in BeautifulSoup(body,'html.parser').contents:convert(n)
 # Preserve field labels and values in paragraphs; legal text remains complete.
doc.core_properties.title='Guía de reportes y documentos de Crece Impulso';doc.core_properties.author='Crece Impulso';doc.core_properties.subject='Ejemplos ficticios para revisión funcional'
for style in doc.styles:
 for border in list(style.element.iter(qn('w:pBdr'))): border.getparent().remove(border)
for border in list(doc.element.iter(qn('w:pBdr'))): border.getparent().remove(border)
doc.save(out/'Guia-completa-reportes.docx')
print('Guía creada con',len(items),'apartados. Word y HTML completos.')
