<template>
  <Select
    v-model="selectedValue"
    :options="roles"
    :disabled="disabled"
    :invalid="invalid"
    fluid
    placeholder="Sélectionnez un rôle"
    @update:modelValue="handleChange"
  >
    <template #value="slotProps">
      <div v-if="slotProps.value" class="flex align-items-center">
        <RoleBadge
          :role="slotProps.value"
          size="sm"
        />
      </div>
      <span v-else>{{ slotProps.placeholder }}</span>
    </template>
    <template #option="slotProps">
      <div class="flex align-items-center">
        <RoleBadge
          :role="slotProps.option"
          size="sm"
        />
      </div>
    </template>
  </Select>
</template>

<script setup lang="ts">
import RoleBadge from '~/components/badge/RoleBadge.vue';
import { AppUserRole } from '~/types/entity/AppUser';

interface Props {
  modelValue?: string;
  disabled?: boolean;
  invalid?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  disabled: false,
  invalid: false
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const roles = [
  AppUserRole.SUPER_ADMIN,
  AppUserRole.ADMIN,
  AppUserRole.USER
];

const selectedValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const handleChange = (value: string) => {
  emit('update:modelValue', value);
};
</script>
