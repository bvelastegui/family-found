export const usd = (cents: number): string =>
    new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);

export const operationKey = (): string => crypto.randomUUID();

export type FundPagination<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};
