/**
 * Club scheduling rules, shared between the calendar grid (badges,
 * highlighted columns) and the game form warnings.
 */

/** Maximum number of home games allowed on the same day */
export const MAX_HOME_GAMES_PER_DAY = 3;

/** Days of week (Date.getDay()) on which home games are played: Monday & Friday */
export const HOME_DAYS: readonly number[] = [1, 5];

export const isHomeDay = (date: Date): boolean => HOME_DAYS.includes(date.getDay());

/** Local-timezone YYYY-MM-DD key, matching the backend date format */
export const toDateKey = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

/**
 * Returns the first closure covering the given YYYY-MM-DD key, or null.
 * ISO date strings compare lexicographically, so plain string comparison
 * is correct here (range inclusive on both ends).
 */
export const findClosureForDateKey = <T extends { startDate: string; endDate: string }>(
    closures: readonly T[],
    dateKey: string,
): T | null =>
    closures.find(c => dateKey >= c.startDate && dateKey <= c.endDate) ?? null;
