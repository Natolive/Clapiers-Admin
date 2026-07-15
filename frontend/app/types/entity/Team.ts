export type TeamCoach = {
    id: number;
    email: string;
};

export type Team = {
    id: number;
    name: string;
    /** Coachs (users) gérant l'équipe — édités depuis la page Équipes. */
    coaches?: TeamCoach[];
    createdAt: string;
    updatedAt: string;
};
