export enum MemberGender {
    MALE   = 'male',
    FEMALE = 'female',
    OTHER  = 'other',
}

export const MemberGenderLabels: Record<MemberGender, string> = {
    [MemberGender.MALE]:   'Homme',
    [MemberGender.FEMALE]: 'Femme',
    [MemberGender.OTHER]:  'Autre',
};

export const MemberGenderOptions = Object.values(MemberGender).map(v => ({
    value: v,
    label: MemberGenderLabels[v],
}));
