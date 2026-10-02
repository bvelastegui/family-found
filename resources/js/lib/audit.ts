import { show as transactionShow } from '@/routes/fund/audit/transactions';
import { show as loanShow } from '@/routes/fund/audit/loans';
import { show as entryShow } from '@/routes/fund/audit/entries';
import type { RouteDefinition } from '@/wayfinder';

export type AuditEvent = {
  id: number;
  title: string;
  subject_type: string;
  subject_id: number;
  actor_name: string;
  created_at: string;
  data: Record<
    string,
    string | number | boolean | null | Record<string, string | boolean>
  >;
};
export type AuditEntry = {
  id: number;
  created_at: string;
  actor_name: string;
  reversal_of_id: number | null;
  transaction_id: number | null;
  loan_id: number | null;
  lines: {
    id: number;
    account: string;
    side: string;
    amount_cents: number;
    participant_name: string | null;
    loan_id: number | null;
  }[];
};
export const accountLabels: Record<string, string> = {
  cash: 'Efectivo',
  contributions: 'Aportes',
  loan_principal: 'Capital prestado',
  interest: 'Intereses',
};
export function auditRecordRoute(
  type: string,
  id: number,
  returnTo?: string,
): RouteDefinition<'get'> | null {
  const options = { query: returnTo ? { return_to: returnTo } : {} };
  if (type === 'transaction') return transactionShow(id, options);
  if (type === 'loan') return loanShow(id, options);
  if (type === 'entry') return entryShow(id, options);
  return null;
}
