const brazilianCurrencyAmount = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function parseMoneyInput(value: string): number | null {
    const trimmed = value.trim();
    const normalized = trimmed.includes(',')
        ? trimmed.replace(/\./g, '').replace(',', '.')
        : /^\d{1,3}(?:\.\d{3})+$/.test(trimmed)
          ? trimmed.replace(/\./g, '')
          : trimmed;

    if (!/^\d+(?:\.\d{0,2})?$/.test(normalized)) {
        return null;
    }

    const amount = Number(normalized);

    return Number.isFinite(amount) ? amount : null;
}

export function formatMoneyInput(value: string): {
    display: string;
    decimal: string;
} | null {
    const amount = parseMoneyInput(value);

    if (amount === null) {
        return null;
    }

    return {
        display: brazilianCurrencyAmount.format(amount),
        decimal: amount.toFixed(2),
    };
}
