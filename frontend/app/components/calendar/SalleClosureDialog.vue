<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        header="Fermetures de la salle"
        modal
        :style="{ width: '34rem' }"
    >
        <div class="flex flex-column gap-4 pt-2">

            <!-- Add form -->
            <form class="closure-form" @submit.prevent="handleCreate">
                <div class="flex flex-column gap-2 flex-1">
                    <label class="font-medium text-sm">Du <span class="text-red-500">*</span></label>
                    <DatePicker
                        v-model="form.startDate"
                        dateFormat="dd/mm/yy"
                        :invalid="!!error"
                        :fluid="true"
                        showIcon
                    />
                </div>
                <div class="flex flex-column gap-2 flex-1">
                    <label class="font-medium text-sm">Au <span class="text-red-500">*</span></label>
                    <DatePicker
                        v-model="form.endDate"
                        dateFormat="dd/mm/yy"
                        :min-date="form.startDate ?? undefined"
                        :invalid="!!error"
                        :fluid="true"
                        showIcon
                    />
                </div>
                <div class="flex flex-column gap-2 flex-2">
                    <label class="font-medium text-sm">Motif</label>
                    <InputText v-model="form.reason" placeholder="Ex: Vacances de Noël" />
                </div>
                <Button
                    type="submit"
                    icon="pi pi-plus"
                    label="Ajouter"
                    :loading="creating"
                    class="closure-form__submit"
                />
            </form>

            <Message v-if="error" severity="error" :closable="false" size="small">{{ error }}</Message>

            <!-- List -->
            <div class="closure-list">
                <div v-if="closures.length === 0" class="closure-empty">
                    <i class="pi pi-calendar-times"></i>
                    <span>Aucune fermeture planifiée</span>
                </div>

                <div v-for="closure in closures" :key="closure.id" class="closure-row">
                    <i class="pi pi-lock closure-row__icon"></i>
                    <div class="closure-row__info">
                        <span class="closure-row__dates">{{ formatRange(closure) }}</span>
                        <span v-if="closure.reason" class="closure-row__reason">{{ closure.reason }}</span>
                    </div>
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        :loading="deletingId === closure.id"
                        @click="handleDelete(closure)"
                    />
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { SalleClosureRepository } from '~/repository/salle-closure-repository';
import type { SalleClosure, CreateSalleClosureDto } from '~/types/entity/SalleClosure';
import { toDateKey } from '~/utils/calendarRules';

defineProps<{
    visible: boolean;
    closures: SalleClosure[];
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    /** Emitted after a closure is created or deleted, so the parent can reload */
    changed: [];
}>();

const repository = new SalleClosureRepository();
const toast = usePVToastService();

const creating = ref(false);
const deletingId = ref<number | null>(null);
const error = ref('');

const form = ref<{ startDate: Date | null; endDate: Date | null; reason: string }>({
    startDate: null,
    endDate: null,
    reason: '',
});

const resetForm = () => {
    form.value = { startDate: null, endDate: null, reason: '' };
};

const handleCreate = async () => {
    error.value = '';
    if (!form.value.startDate || !form.value.endDate) {
        error.value = 'Les dates de début et de fin sont requises.';
        return;
    }
    if (form.value.endDate < form.value.startDate) {
        error.value = 'La date de fin doit être postérieure ou égale à la date de début.';
        return;
    }

    creating.value = true;
    try {
        const dto: CreateSalleClosureDto = {
            startDate: toDateKey(form.value.startDate),
            endDate: toDateKey(form.value.endDate),
            reason: form.value.reason.trim() || null,
        };
        await repository.create(dto);
        toast.add({ severity: 'success', summary: 'Fermeture ajoutée', life: 3000 });
        resetForm();
        emit('changed');
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e?.data?.message ?? 'Une erreur est survenue', life: 4000 });
    } finally {
        creating.value = false;
    }
};

const handleDelete = async (closure: SalleClosure) => {
    deletingId.value = closure.id;
    try {
        await repository.delete(closure.id);
        toast.add({ severity: 'success', summary: 'Fermeture supprimée', life: 3000 });
        emit('changed');
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e?.data?.message ?? 'Impossible de supprimer', life: 4000 });
    } finally {
        deletingId.value = null;
    }
};

const DATE_FMT = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
const toDate = (key: string) => new Date(key + 'T00:00:00');

const formatRange = (closure: SalleClosure): string => {
    if (closure.startDate === closure.endDate) {
        return DATE_FMT.format(toDate(closure.startDate));
    }
    return `${DATE_FMT.format(toDate(closure.startDate))} → ${DATE_FMT.format(toDate(closure.endDate))}`;
};
</script>

<style scoped>
.closure-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
}

.flex-2 { flex: 2; min-width: 10rem; }

.closure-form__submit {
    flex-shrink: 0;
}

.closure-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 18rem;
    overflow-y: auto;
}

.closure-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    color: var(--p-text-muted-color);
}

.closure-empty i {
    font-size: 2rem;
    opacity: 0.5;
}

.closure-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--p-surface-border);
    border-radius: 8px;
}

.closure-row__icon {
    color: var(--p-text-muted-color);
    font-size: 0.875rem;
    flex-shrink: 0;
}

.closure-row__info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.closure-row__dates {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--p-text-color);
}

.closure-row__reason {
    font-size: 0.75rem;
    color: var(--p-text-muted-color);
}

@media (max-width: 640px) {
    .closure-form__submit {
        width: 100%;
    }
}
</style>
