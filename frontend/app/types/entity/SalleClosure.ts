export type SalleClosure = {
    id: number;
    startDate: string; // 'YYYY-MM-DD' (inclusive)
    endDate: string;   // 'YYYY-MM-DD' (inclusive)
    reason: string | null;
    createdAt: string;
    updatedAt: string;
};

export type CreateSalleClosureDto = {
    startDate: string; // 'YYYY-MM-DD'
    endDate: string;   // 'YYYY-MM-DD'
    reason?: string | null;
};
