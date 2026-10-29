import { cents, decimal } from './credit-calculation';
import type { AuthorizationTerms } from '../services/authorization.service';
import type { Installment } from '../services/disbursement.service';
export function weeklyDates(firstDue: string): string[] {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(firstDue)) throw Error('Selecciona la fecha del primer pago.');
  const first = new Date(`${firstDue}T00:00:00Z`);
  if (!Number.isFinite(first.getTime()) || first.toISOString().slice(0, 10) !== firstDue)
    throw Error('La fecha del primer pago no es válida.');
  return Array.from({ length: 16 }, (_, index) => {
    const date = new Date(first);
    date.setUTCDate(date.getUTCDate() + 7 * index);
    return date.toISOString().slice(0, 10);
  });
}
export function previewSchedule(terms: AuthorizationTerms, firstDue: string): Installment[] {
  return weeklyDates(firstDue).flatMap((due_on, index) =>
    terms.members.map((member) => ({
      client_id: member.client_id,
      number: index + 1,
      due_on,
      amount_due: member.estimate.weekly_payment,
      amount_paid: '0.00',
    })),
  );
}
export function groupSchedule(rows: Installment[]) {
  return Array.from(new Set(rows.map((row) => row.number)))
    .sort((a, b) => a - b)
    .map((number) => {
      const members = rows.filter((row) => row.number === number);
      return {
        number,
        due_on: members[0]!.due_on,
        amount_due: decimal(members.reduce((sum, row) => sum + cents(row.amount_due), 0n)),
      };
    });
}
