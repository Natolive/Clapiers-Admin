<template>
  <FormField v-slot="$field" v-bind="$attrs">
    <DatePicker
      :modelValue="$field.value ?? null"
      @update:modelValue="handleChange($field, $event)"
      :placeholder="placeholder"
      :disabled="disabled"
      :dateFormat="dateFormat"
      :showIcon="showIcon"
      :inputId="inputId"
      :maxDate="maxDate"
      :invalid="$field.invalid"
      fluid
    />
  </FormField>
</template>

<script setup lang="ts">
import { FormField } from '@primevue/forms';

defineOptions({ inheritAttrs: false });

withDefaults(defineProps<{
  placeholder?: string;
  disabled?: boolean;
  dateFormat?: string;
  showIcon?: boolean;
  inputId?: string;
  maxDate?: Date;
}>(), {
  placeholder: 'JJ/MM/AAAA',
  disabled: false,
  dateFormat: 'dd/mm/yy',
  showIcon: false,
  inputId: undefined,
  maxDate: undefined,
});

const handleChange = (field: any, val: Date | Date[] | (Date | null)[] | null | undefined) => {
  // PrimeVue Forms attend un objet { value } — jamais une valeur brute ou undefined
  if (val instanceof Date && !isNaN(val.getTime())) {
    field.onChange({ value: val });
  } else {
    // Champ effacé ou Invalid Date (frappe en cours) → reset sans crasher
    field.onChange({ value: undefined });
  }
};
</script>
