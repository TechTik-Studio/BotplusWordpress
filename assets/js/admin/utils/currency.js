/**
 * Currency sanitization and formatting utility for BotPlus WordPress Plugin.
 * Decodes and prevents HTML entity leakage (e.g. &#36;0.00 -> $0.00).
 */

export function sanitizeCurrencySymbol(symbol) {
    if (!symbol || typeof symbol !== 'string') return '$';
    return symbol
        .replace(/&#36;/g, '$')
        .replace(/&dollar;/g, '$')
        .replace(/&pound;|&#163;/g, '£')
        .replace(/&euro;|&#8364;/g, '€')
        .replace(/&yen;|&#165;/g, '¥')
        .replace(/&amp;/g, '&')
        .trim() || '$';
}

export function formatCurrencyDisplay(value, defaultSymbol = '$') {
    if (value === null || value === undefined || value === '') {
        return `${sanitizeCurrencySymbol(defaultSymbol)}0.00`;
    }

    if (typeof value === 'number') {
        const symbol = sanitizeCurrencySymbol(defaultSymbol);
        return `${symbol}${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    if (typeof value === 'string') {
        let cleaned = value
            .replace(/&#36;/g, '$')
            .replace(/&dollar;/g, '$')
            .replace(/&pound;|&#163;/g, '£')
            .replace(/&euro;|&#8364;/g, '€')
            .replace(/&yen;|&#165;/g, '¥')
            .replace(/&amp;/g, '&')
            .trim();

        // If string already has a currency symbol prefix (e.g. "$120.00" or "₨ 5,000")
        if (/^[\$\£\€\¥\₨\₹]/.test(cleaned)) {
            return cleaned;
        }

        // If pure numeric string
        const parsed = parseFloat(cleaned.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(parsed)) {
            const symbol = sanitizeCurrencySymbol(defaultSymbol);
            return `${symbol}${parsed.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        return cleaned;
    }

    return `${sanitizeCurrencySymbol(defaultSymbol)}0.00`;
}

