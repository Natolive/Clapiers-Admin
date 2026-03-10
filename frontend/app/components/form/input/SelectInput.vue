<template>
  <FormField v-slot="$field" v-bind="$attrs">
    <Select
      :modelValue="$field.value"
      :options="options"
      :optionLabel="optionLabel"
      :optionValue="optionValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :filter="filter"
      :filterPlaceholder="filterPlaceholder"
      :emptyFilterMessage="emptyFilterMessage"
      :emptyMessage="emptyMessage"
      :showClear="showClear"
      :invalid="$field.invalid"
      fluid
      @change="$field.onChange"
    />
  </FormField>
</template>

<script setup lang="ts">
import { FormField } from '@primevue/forms';

defineOptions({ inheritAttrs: false });

withDefaults(defineProps<{
  options: any[];
  optionLabel?: string;
  optionValue?: string;
  placeholder?: string;
  disabled?: boolean;
  filter?: boolean;
  filterPlaceholder?: string;
  emptyFilterMessage?: string;
  emptyMessage?: string;
  showClear?: boolean;
}>(), {
  optionLabel: undefined,
  optionValue: undefined,
  placeholder: 'Sélectionnez',
  disabled: false,
  filter: false,
  filterPlaceholder: 'Rechercher...',
  emptyFilterMessage: 'Aucun résultat',
  emptyMessage: 'Aucune option',
  showClear: false,
});
</script>
