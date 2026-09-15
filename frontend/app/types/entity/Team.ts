export type TeamCoach = {
    id: number;
    email: string;
};

export type Team = {
    id: number;
    name: string;
    /** Coachs (users) gérant l'équipe — édités depuis la page Équipes. */
    coaches?: TeamCoach[];
    /** Saison des compteurs ci-dessous (renvoyée par GET /api/team). */
    season?: string;
    /** Licenciés comptés pour la saison (licence validée, en paiement ou payée). */
    memberCount?: number;
    /** Parmi eux, ceux dont la licence est payée. */
    paidCount?: number;
    createdAt: string;
    updatedAt: string;
};
