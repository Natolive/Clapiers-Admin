export interface LogEntry {
    id: number;
    level: string;
    channel: string;
    message: string;
    context: Record<string, unknown> | null;
    createdAt: string;
}
