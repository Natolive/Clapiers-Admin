<template>
  <Select
    v-model="selectedValue"
    :options="teams"
    optionLabel="name"
    optionValue="id"
    :disabled="disabled"
    :invalid="invalid"
    fluid
    showClear
    placeholder="Sélectionnez une équipe"
    @update:modelValue="handleChange"
  />
</template>

<script setup lang="ts">
import type { Team } from '~/types/entity/Team';
import { TeamRepository } from '~/repository/team-repository';

interface Props {
  modelValue?: number | null;
  disabled?: boolean;
  invalid?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  disabled: false,
  invalid: false
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const teams = ref<Team[]>([]);

onMounted(async () => {
  const teamRepository = new TeamRepository();
  teams.value = await teamRepository.getAll();
});

const selectedValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value ?? null)
});

const handleChange = (value: number | null) => {
  emit('update:modelValue', value ?? null);
};
</script>
