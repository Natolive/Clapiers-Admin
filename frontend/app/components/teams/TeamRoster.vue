<template>
  <div class="roster">
    <div class="roster__head">
      <h5 class="roster__title">
        Effectif {{ team.season ?? '' }}
      </h5>
      <span class="roster__counts">
        {{ members.length }} licencié{{ members.length > 1 ? 's' : '' }} ·
        {{ paidCount }} licence{{ paidCount > 1 ? 's' : '' }} payée{{ paidCount > 1 ? 's' : '' }}
      </span>
    </div>

    <div class="roster__add">
      <AutoComplete
        v-model="toAdd"
        multiple
        :suggestions="suggestions"
        option-label="label"
        :delay="300"
        complete-on-focus
        placeholder="Rechercher un licencié à ajouter..."
        class="roster__select"
        @complete="searchMembers"
      />
      <Button
        label="Ajouter"
        icon="pi pi-user-plus"
        size="small"
        :disabled="!toAdd.length"
        :loading="adding"
        @click="submitAdd"
      />
    </div>

    <div v-if="loading" class="roster__loading">
      <i class="pi pi-spinner pi-spin" />
    </div>

    <template v-else-if="members.length">
      <div v-for="member in members" :key="member.id" class="roster-row">
        <MemberAvatar :member="member" size="normal" />
        <button type="button" class="roster-row__name" @click="emit('open', member)">
          {{ member.firstName }} {{ member.lastName }}
        </button>
        <Tag
          :value="member.licensePaid ? 'Payée' : 'Non payée'"
          :severity="member.licensePaid ? 'success' : 'danger'"
          class="text-xs"
        />
        <Button
          icon="pi pi-user-minus"
          severity="danger"
          text
          rounded
          v-tooltip.left="'Retirer de l\'équipe'"
          @click="emit('remove', member)"
        />
      </div>
    </template>

    <span v-else class="roster__empty">Aucun licencié dans cette équipe pour cette saison</span>
  </div>
</template>

<script setup lang="ts">
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

const props = defineProps<{
  team: Team
  members: Member[]
  loading: boolean
  adding: boolean
}>();

const emit = defineEmits<{
  add: [memberIds: number[]]
  remove: [member: Member]
  open: [member: Member]
}>();

type MemberOption = { label: string; value: number };

const memberRepository = new MemberRepository();
const toAdd = ref<MemberOption[]>([]);
const suggestions = ref<MemberOption[]>([]);

// Même moteur de recherche que la liste des licenciés (accents, mots dans
// n'importe quel ordre, téléphone) : on interroge l'API plutôt que de filtrer
// une liste préchargée. Sans saison : on peut rattacher un licencié d'une autre
// saison, il apparaîtra dans l'effectif quand sa licence sera validée.
const searchMembers = async (event: { query: string }) => {
  const result = await memberRepository.getPaginated({
    page: 1,
    limit: 20,
    sortField: 'lastName',
    sortOrder: 'asc',
    search: event.query.trim() || undefined,
  });

  const excluded = new Set([
    ...props.members.map(m => m.id),
    ...toAdd.value.map(o => o.value),
  ]);

  suggestions.value = result.data
    .filter(m => !excluded.has(m.id))
    .map(m => ({ label: `${m.lastName} ${m.firstName}`, value: m.id }));
};

const paidCount = computed(() => props.members.filter(m => m.licensePaid).length);

const submitAdd = () => {
  emit('add', toAdd.value.map(o => o.value));
  toAdd.value = [];
};
</script>

<style scoped>
.roster {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.roster__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.roster__title {
  margin: 0;
  font-size: 0.95rem;
}

.roster__counts {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.roster__add {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.roster__select {
  flex: 1 1 18rem;
  min-width: 0;
}

.roster__loading,
.roster__empty {
  padding: 0.75rem;
  text-align: center;
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
}

.roster-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 8px;
}

.roster-row:hover {
  background: var(--p-surface-hover);
}

.roster-row__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
  background: none;
  border: none;
  padding: 0;
  font: inherit;
  color: var(--p-text-color);
  cursor: pointer;
}

.roster-row__name:hover {
  text-decoration: underline;
}
</style>
