import type { Estimate } from '../services/credit.service';
export function cents(value: string): bigint {
  if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(value))
    throw Error('Escribe un importe válido, sin comas y con hasta dos decimales.');
  const [whole, fraction = ''] = value.split('.');
  return BigInt(whole!) * 100n + BigInt(fraction.padEnd(2, '0'));
}
export function decimal(value: bigint): string {
  return `${value / 100n}.${String(value % 100n).padStart(2, '0')}`;
}
export function estimateCredit(amount: string, factor: string): Estimate {
  if (!/^\d{1,4}(?:\.\d{1,4})?$/.test(factor)) throw Error('Tasa no válida.');
  const capital = cents(amount),
    [whole, fraction = ''] = factor.split('.');
  const rate = BigInt(whole!) * 10000n + BigInt(fraction.padEnd(4, '0'));
  const weekly = (capital * rate + 5000000n) / 10000000n,
    total = weekly * 16n,
    guarantee = (capital + 5n) / 10n;
  if (capital <= 0n || total < capital) throw Error('Revisa el monto y la tasa.');
  return {
    principal: decimal(capital),
    weekly_payment: decimal(weekly),
    total_repayment: decimal(total),
    interest: decimal(total - capital),
    guarantee: decimal(guarantee),
    net_delivery: decimal(capital - guarantee),
  };
}
export function sumEstimates(rows: Estimate[]): Estimate {
  const keys = [
    'principal',
    'weekly_payment',
    'total_repayment',
    'interest',
    'guarantee',
    'net_delivery',
  ] as const;
  return Object.fromEntries(
    keys.map((key) => [key, decimal(rows.reduce((sum, row) => sum + cents(row[key]), 0n))]),
  ) as unknown as Estimate;
}
export const money = (value: string | null | undefined) =>
  value == null
    ? 'Pendiente'
    : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value));
