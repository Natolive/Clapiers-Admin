export enum GameHistoryAction {
    CREATED = 'created',
    UPDATED = 'updated',
    DELETED = 'deleted',
}

/** A single before/after change on an updated field. */
export type GameHistoryFieldChange = {
    old: string | number | null;
    new: string | number | null;
};

/**
 * One audit row for a transaction on a game.
 *
 * For CREATED/DELETED, `changes` is a full snapshot ({field: value}).
 * For UPDATED, `changes` holds only the changed fields ({field: {old, new}}).
 */
export type GameHistory = {
    id: number;
    action: GameHistoryAction;
    gameId: number | null;
    opponent: string;
    gameDate: string; // 'YYYY-MM-DD'
    teamId: number | null;
    teamName: string;
    changes: Record<string, unknown>;
    actorEmail: string | null;
    createdAt: string; // ISO 8601
};
