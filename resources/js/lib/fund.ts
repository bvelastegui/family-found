export const usd = (cents: number): string =>
  new Intl.NumberFormat('es-EC', {
    style: 'currency',
    currency: 'USD',
  }).format(cents / 100);

export const operationKey = (): string => crypto.randomUUID();

const fundTimezone = 'America/Guayaquil';

export function fundDate(date: string): string {
  const day = date.slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return date;

  const [year, month, number] = day.split('-').map(Number);
  return new Intl.DateTimeFormat('es-EC', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(year, month - 1, number, 12)));
}

export function fundMonth(month: string): string {
  if (!/^\d{4}-\d{2}$/.test(month)) return month;

  return new Intl.DateTimeFormat('es-EC', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${month}-01T12:00:00Z`));
}

export function fundDateTime(timestamp: string): string {
  const iso = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(timestamp)
    ? `${timestamp.replace(' ', 'T')}Z`
    : timestamp;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return timestamp;

  return new Intl.DateTimeFormat('es-EC', {
    dateStyle: 'long',
    timeStyle: 'short',
    hour12: false,
    timeZone: fundTimezone,
  }).format(date);
}

export type FundPagination<T> = {
  data: T[];
  last_page: number;
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
};
