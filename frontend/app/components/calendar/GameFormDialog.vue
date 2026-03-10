<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        :header="isEdit ? 'Modifier le match' : 'Nouveau match'"
        modal
        :style="{ width: '30rem' }"
    >
        <form @submit.prevent="handleSubmit" class="flex flex-column gap-4 pt-2">

            <!-- Team selector (super admin only) -->
            <div v-if="isSuperAdmin" class="flex flex-column gap-2">
                <label class="font-medium text-sm">Équipe <span class="text-red-500">*</span></label>
                <Select
                    v-model="form.teamId"
                    :options="teams"
                    option-label="name"
                    option-value="id"
                    placeholder="Sélectionner une équipe"
                    :invalid="!!errors.teamId"
                    :fluid="true"
                />
                <small v-if="errors.teamId" class="text-red-500">{{ errors.teamId }}</small>
            </div>

            <!-- Opponent -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">Équipe adverse <span class="text-red-500">*</span></label>
                <InputText
                    v-model="form.opponent"
                    placeholder="Ex: Montpellier VB"
                    :invalid="!!errors.opponent"
                    autofocus
                />
                <small v-if="errors.opponent" class="text-red-500">{{ errors.opponent }}</small>
            </div>

            <!-- Date -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">Date du match <span class="text-red-500">*</span></label>
                <DatePicker
                    v-model="form.date"
                    dateFormat="dd/mm/yy"
                    :invalid="!!errors.date"
                    :fluid="true"
                    showIcon
                />
                <small v-if="errors.date" class="text-red-500">{{ errors.date }}</small>
            </div>

            <!-- Venue -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">Lieu de réception</label>
                <SelectButton
                    v-model="form.venue"
                    :options="GameVenueOptions"
                    option-label="label"
                    option-value="value"
                />
            </div>

            <Message
                v-if="teamLimitWarning"
                severity="error"
                :closable="false"
                size="small"
            >
                {{ teamLimitWarning }}
            </Message>

            <Message
                v-if="homeCountWarning"
                :severity="homeCountWarning.startsWith('Journée') ? 'error' : 'warn'"
                :closable="false"
                size="small"
            >
                {{ homeCountWarning }}
            </Message>

            <!-- Meeting time (informative) -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">
                    Heure de rendez-vous
                    <span class="text-color-secondary font-normal">(informatif)</span>
                </label>
                <InputText
                    v-model="form.meetingTime"
                    placeholder="Ex: 14h30"
                    style="max-width: 10rem"
                />
            </div>

            <!-- Location -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">Lieu</label>
                <InputText v-model="form.location" placeholder="Ex: Gymnase Clapiers" />
            </div>

            <!-- Actions -->
            <div class="flex justify-content-end gap-2 pt-2 border-top-1 surface-border">
                <Button label="Annuler" severity="secondary" text type="button" @click="$emit('update:visible', false)" />
                <Button :label="isEdit ? 'Enregistrer' : 'Créer'" type="submit" :loading="loading" icon="pi pi-check" />
            </div>
        </form>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { GameRepository } from '~/repository/game-repository';
import type { Game, CreateUpdateGameDto } from '~/types/entity/Game';
import type { Team } from '~/types/entity/Team';
import { GameVenue, GameVenueOptions } from '~/types/enum/GameVenue';

const props = defineProps<{
    visible: boolean;
    game?: Game | null;
    initialDate?: Date | null;
    teams?: Team[];
    userTeamId?: number | null;
    homeCountByDate?: Record<string, number>;
    teamDateMap?: Record<string, number>;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [game: Game];
}>();

const { isSuperAdmin } = useUserRole();
const toast = usePVToastService();
const repository = new GameRepository();
const loading = ref(false);
const errors = ref<Record<string, string>>({});

const isEdit = computed(() => !!props.game?.id);

const homeCountWarning = computed(() => {
    if (form.value.venue !== GameVenue.HOME || !form.value.date) return null;
    const y = form.value.date.getFullYear();
    const m = String(form.value.date.getMonth() + 1).padStart(2, '0');
    const d = String(form.value.date.getDate()).padStart(2, '0');
    const dateKey = `${y}-${m}-${d}`;
    const total = props.homeCountByDate?.[dateKey] ?? 0;
    // When editing a home game on the same date, don't count itself
    const isSameDayHomeEdit = isEdit.value && props.game?.venue === GameVenue.HOME && props.game?.date === dateKey;
    const effective = isSameDayHomeEdit ? total - 1 : total;
    if (effective >= 3) return 'Journée complète — 3 matchs à domicile déjà planifiés.';
    if (effective === 2) return `Attention — 2/3 matchs à domicile ce jour.`;
    return null;
});

const teamLimitWarning = computed(() => {
    if (!form.value.date) return null;
    const y = form.value.date.getFullYear();
    const m = String(form.value.date.getMonth() + 1).padStart(2, '0');
    const d = String(form.value.date.getDate()).padStart(2, '0');
    const dateKey = `${y}-${m}-${d}`;
    const teamId = isSuperAdmin.value ? form.value.teamId : props.userTeamId;
    if (!teamId) return null;
    const existingGameId = props.teamDateMap?.[`${dateKey}|${teamId}`];
    if (existingGameId === undefined) return null;
    if (isEdit.value && existingGameId === props.game?.id) return null;
    return 'Cette équipe a déjà un match planifié ce jour.';
});

type FormState = {
    opponent: string;
    date: Date | null;
    meetingTime: string;
    venue: GameVenue;
    location: string;
    teamId: number | null;
};

const defaultForm = (): FormState => ({
    opponent: '',
    date: props.initialDate ?? null,
    meetingTime: '',
    venue: GameVenue.HOME,
    location: '',
    teamId: props.userTeamId ?? null,
});

const form = ref<FormState>(defaultForm());

watch(() => props.visible, (val) => {
    if (!val) return;
    errors.value = {};
    if (props.game) {
        form.value = {
            opponent: props.game.opponent,
            date: new Date(props.game.date + 'T00:00:00'),
            meetingTime: props.game.meetingTime ?? '',
            venue: (props.game.venue as GameVenue) ?? GameVenue.HOME,
            location: props.game.location ?? '',
            teamId: props.game.team.id,
        };
    } else {
        form.value = defaultForm();
    }
}, { immediate: true });

const validate = (): boolean => {
    errors.value = {};
    if (!form.value.opponent.trim()) errors.value.opponent = "L'équipe adverse est requise";
    if (!form.value.date) errors.value.date = 'La date est requise';
    if (isSuperAdmin.value && !form.value.teamId) errors.value.teamId = "L'équipe est requise";
    return Object.keys(errors.value).length === 0;
};

const toDateString = (d: Date): string => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const handleSubmit = async () => {
    if (!validate()) return;
    if (teamLimitWarning.value) return;
    if (homeCountWarning.value?.startsWith('Journée')) return;
    loading.value = true;
    try {
        const dto: CreateUpdateGameDto = {
            opponent:    form.value.opponent.trim(),
            date:        toDateString(form.value.date as Date),
            meetingTime: form.value.meetingTime.trim() || null,
            venue:       form.value.venue,
            location:    form.value.location.trim() || null,
            teamId:      isSuperAdmin.value ? form.value.teamId : undefined,
        };

        const saved = props.game
            ? await repository.update(props.game.id, dto)
            : await repository.create(dto);

        toast.add({ severity: 'success', summary: isEdit.value ? 'Match modifié' : 'Match créé', life: 3000 });
        emit('saved', saved);
        emit('update:visible', false);
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e?.data?.message ?? 'Une erreur est survenue', life: 4000 });
    } finally {
        loading.value = false;
    }
};
</script>
