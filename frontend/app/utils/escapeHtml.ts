const HTML_ENTITIES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/**
 * Escapes a string for safe interpolation into raw HTML
 * (e.g. FullCalendar's `eventContent` html option).
 */
export const escapeHtml = (value: string): string =>
    value.replace(/[&<>"']/g, (char) => HTML_ENTITIES[char] ?? char);
