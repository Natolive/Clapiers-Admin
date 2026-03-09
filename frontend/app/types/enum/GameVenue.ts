export enum GameVenue {
    HOME = 'home',
    AWAY = 'away',
}

export const GameVenueLabels: Record<GameVenue, string> = {
    [GameVenue.HOME]: 'Domicile',
    [GameVenue.AWAY]: 'Extérieur',
};

export const GameVenueOptions = Object.values(GameVenue).map(value => ({
    value,
    label: GameVenueLabels[value],
}));
