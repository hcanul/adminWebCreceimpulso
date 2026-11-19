import type { PaymentReceipt } from './receipts.service';
import { money } from '../domain/credit-calculation';

export type ReceiptPrintFormat = 'letter' | 'ticket80' | 'ticket58';
export const receiptPrintFormats = [
  { label: 'Hoja carta · dos copias', value: 'letter' },
  { label: 'Ticket · 80 mm', value: 'ticket80' },
  { label: 'Ticket · 58 mm', value: 'ticket58' },
];
const escape = (value: string | number | null | undefined) =>
  String(value ?? '').replace(
    /[&<>"']/g,
    (char) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char,
  );

/** Uses only receipt data already authorized by the API. Printing never registers a payment. */
export function receiptPrintDocument(
  receipt: PaymentReceipt,
  qr: string,
  logo: string,
  format: ReceiptPrintFormat,
  rowsPerPart = 6,
): string {
  const letter = format === 'letter';
  const parts: PaymentReceipt['allocations'][] = [];
  if (letter) {
    for (let i = 0; i < receipt.allocations.length; i += rowsPerPart)
      parts.push(receipt.allocations.slice(i, i + rowsPerPart));
  } else parts.push(receipt.allocations);
  if (!parts.length) parts.push([]);
  function copy(rows: PaymentReceipt['allocations'], index: number, recipient: string) {
    return `<article class="copy"><header><img class="brand" src="${escape(logo)}" alt="Crece Impulso"><div><strong>COMPROBANTE DE PAGO RECIBIDO</strong><p class="folio">${escape(receipt.folio)}</p><p>${escape(recipient)}${parts.length > 1 ? ` · Parte ${index + 1} de ${parts.length}` : ''}</p></div></header>
      <div class="amount">${escape(money(receipt.amount))} MXN <small>Total del comprobante</small></div>
      <div class="facts"><div><b>Crédito:</b> ${escape(receipt.credit_folio)}</div><div><b>Recibido:</b> ${escape(receipt.received_on)}</div><div><b>Medio:</b> ${receipt.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal'}</div><div><b>Recibió:</b> ${escape(receipt.actor_name)}</div>
      ${receipt.bank_reference ? `<div class="wide"><b>Banco / folio:</b> ${escape(receipt.bank_name)} · ${escape(receipt.bank_reference)} <b>Fecha depósito:</b> ${escape(receipt.deposited_on)}</div>` : ''}</div>
      <p class="breakdown">Cuotas: ${escape(money(receipt.installment_amount ?? receipt.amount))} · Mora: ${escape(money(receipt.late_fee_amount ?? '0.00'))}</p>
      ${receipt.status === 'reversed' ? `<div class="reversed"><strong>PAGO REVERTIDO · ${escape(receipt.reversed_on)}</strong><p>Este pago ya no abona al crédito. Se conserva su aplicación original.</p>${receipt.reversal ? `<p>${escape(receipt.reversal.actor_name)}: ${escape(receipt.reversal.reason)}</p>` : ''}</div>` : ''}
      ${letter ? `<table><thead><tr><th>Aporta</th><th>Se aplica a</th><th>Cuota / vencimiento</th><th>Importe</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${escape(row.payer)}</td><td>${escape(row.beneficiary)}</td><td>${row.kind === 'late_fee' ? 'Mora de cuota' : 'Cuota'} ${escape(row.number)} · ${escape(row.due_on)}${row.charged_on ? `<br>Cargo ${escape(row.charged_on)}` : ''}</td><td>${escape(money(row.amount))}</td></tr>`).join('')}</tbody></table>` : rows.map((row) => `<div class="ticket-line"><b>${escape(row.beneficiary)}</b><br>Aporta: ${escape(row.payer)}<br>${row.kind === 'late_fee' ? 'Mora de cuota' : 'Cuota'} ${escape(row.number)} · ${escape(row.due_on)}${row.charged_on ? `<br>Cargo ${escape(row.charged_on)}` : ''}<strong class="line-amount">${escape(money(row.amount))}</strong></div>`).join('')}
      <footer><img class="qr" src="${escape(qr)}" alt="QR para verificar este pago"><div><b>Verifica folio, importe y fecha al escanear.</b><p>Requiere internet. Reimprimir no registra otro pago ni acredita la liquidación del crédito.</p>${parts.length > 1 ? '<p>Cada parte repite el mismo total y QR; no son pagos adicionales. Conservar todas las partes.</p>' : ''}</div></footer></article>`;
  }
  const body = parts
    .map((rows, index) =>
      letter
        ? `<section class="sheet">${copy(rows, index, 'Copia para cliente')}${copy(rows, index, 'Copia para sucursal')}</section>`
        : copy(rows, index, 'Copia para cliente'),
    )
    .join('');
  const paper = letter ? 'letter portrait' : `${format === 'ticket58' ? 58 : 80}mm 297mm`;
  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${escape(receipt.folio)}</title><style>
    @page{size:${paper};margin:${letter ? '6mm' : '3mm'}}
    *{box-sizing:border-box}html,body{margin:0;padding:0;background:white;color:#171717;font-family:Arial,sans-serif;font-size:${letter ? '9px' : format === 'ticket58' ? '10px' : '11px'};-webkit-print-color-adjust:exact;print-color-adjust:exact}
    p{margin:2px 0}.folio{font-weight:700;overflow-wrap:anywhere}.copy{overflow-wrap:anywhere}header{display:flex;align-items:center;gap:5mm}header strong{font-size:10px}.brand{width:42mm;height:auto;max-height:15mm;object-fit:contain}.amount{font-size:18px;font-weight:700;margin:3mm 0}.amount small{font-size:8px;font-weight:400}.facts{display:grid;grid-template-columns:1fr 1fr;gap:1.5mm}.wide{grid-column:1/-1}.breakdown{margin:2mm 0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{text-align:left;vertical-align:top;padding:1mm;border-bottom:1px solid #bbb;overflow-wrap:anywhere}th{background:#f2f2f2}th:last-child,td:last-child{width:23mm;text-align:right}th:nth-child(3){width:47mm}thead{display:table-header-group}tr{break-inside:avoid}footer{display:flex;align-items:center;gap:3mm;margin-top:2mm;border-top:1px solid #bbb;padding-top:1mm;font-size:8px}footer .qr{width:22mm;height:22mm;flex:none}.reversed{border:1px solid #222;padding:1mm;font-size:8px}
    ${letter ? '.sheet{width:203.9mm;height:267.4mm;break-after:page;display:grid;grid-template-rows:1fr 1fr}.sheet:last-child{break-after:auto}.copy{height:133.7mm;padding:3mm}.copy:first-child{border-bottom:1px dashed #777}' : `body{width:${format === 'ticket58' ? '52' : '74'}mm;margin:0 auto}header{display:block;text-align:center}.brand{max-width:100%;width:42mm}.facts{display:block}.facts>div{margin-bottom:1mm}.amount{text-align:center}.amount small{display:block}.ticket-line{border-top:1px dashed #aaa;padding:2mm 0;break-inside:avoid}.line-amount{display:block;text-align:right}footer{display:block;text-align:center}footer .qr{width:30mm;height:30mm}.copy{width:100%}`}
    </style></head><body>${body}</body></html>`;
}
