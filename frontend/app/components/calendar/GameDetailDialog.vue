<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        :header="game ? `vs ${game.opponent}` : ''"
        modal
        :style="{ width: '26rem' }"
    >
        <div v-if="game" class="flex flex-column gap-3">

            <!-- Team badge -->
            <span
                class="text-xs font-semibold px-2 py-1 border-round-lg w-fit"
                :style="{ background: teamColor + '22', color: teamColor }"
            >
                {{ game.team.name }}
            </span>

            <Divider class="my-0" />

            <div class="flex flex-column gap-3">
                <!-- Date -->
                <div class="flex align-items-center gap-3">
                    <i class="pi pi-calendar text-primary" style="width:1rem;text-align:center"></i>
                    <span class="text-sm font-medium">{{ formatDate(game.date) }}</span>
                </div>

                <!-- Meeting time -->
                <div v-if="game.meetingTime" class="flex align-items-center gap-3">
                    <i class="pi pi-clock text-primary" style="width:1rem;text-align:center"></i>
                    <span class="text-sm">
                        Rendez-vous à <strong>{{ game.meetingTime }}</strong>
                    </span>
                </div>

                <!-- Venue -->
                <div class="flex align-items-center gap-3">
                    <i class="pi pi-home text-primary" style="width:1rem;text-align:center"></i>
                    <Tag
                        :value="GameVenueLabels[game.venue as GameVenue]"
                        :severity="game.venue === GameVenue.HOME ? 'success' : 'warn'"
                    />
                </div>

                <!-- Location -->
                <div v-if="game.location" class="flex align-items-center gap-3">
                    <i class="pi pi-map-marker text-primary" style="width:1rem;text-align:center"></i>
                    <span class="text-sm">{{ game.location }}</span>
                </div>
            </div>

            <!-- Actions -->
            <div v-if="canEdit" class="flex justify-content-between align-items-center pt-3 border-top-1 surface-border">
                <Button icon="pi pi-trash" label="Supprimer" severity="danger" text size="small" :loading="deleting" @click="handleDelete" />
                <Button icon="pi pi-pencil" label="Modifier" size="small" @click="$emit('edit', game)" />
            </div>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { GameRepository } from '~/repository/game-repository';
import type { Game } from '~/types/entity/Game';
import { getTeamColor } from '~/utils/teamColors';
import { GameVenueLabels, GameVenue } from '~/types/enum/GameVenue';

const props = defineProps<{ visible: boolean; game: Game | null }>();
const emit = defineEmits<{
    'update:visible': [value: boolean];
    edit: [game: Game];
    deleted: [id: number];
}>();

const { isSuperAdmin } = useUserRole();
const authStore = useAuthStore();
const toast = usePVToastService();
const repository = new GameRepository();
const deleting = ref(false);

const teamColor = computed(() => props.game ? getTeamColor(props.game.team.id) : '#3b82f6');

const canEdit = computed(() => {
    if (isSuperAdmin.value) return true;
    return props.game?.team.id === authStore.user?.member?.team?.id;
});

const formatDate = (dateStr: string) =>
    new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });

const handleDelete = async () => {
    if (!props.game) return;
    deleting.value = true;
    try {
        await repository.delete(props.game.id);
        toast.add({ severity: 'success', summary: 'Match supprimé', life: 3000 });
        emit('deleted', props.game.id);
        emit('update:visible', false);
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e?.data?.message ?? 'Impossible de supprimer', life: 4000 });
    } finally {
        deleting.value = false;
    }
};
</script>
