<template>
  <FormField v-slot="$field" v-bind="$attrs">
    <PhoneInputInner
      :field="$field"
      :disabled="disabled"
      :placeholder="placeholder"
      :defaultCountry="defaultCountry"
    />
  </FormField>
</template>

<script setup lang="ts">
import { defineComponent, h, computed, ref, watch } from 'vue';
import { FormField } from '@primevue/forms';
import { VueTelInput } from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';

defineOptions({
  inheritAttrs: false
});

interface Props {
  disabled?: boolean;
  placeholder?: string;
  defaultCountry?: string;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  placeholder: 'Entrez le numéro de téléphone',
  defaultCountry: 'fr'
});

const PhoneInputInner = defineComponent({
  props: ['field', 'disabled', 'placeholder', 'defaultCountry'],
  setup(props: any) {
    const internalValue = ref(props.field?.value || '');

    watch(() => props.field?.value, (newVal) => {
      if (newVal !== internalValue.value) {
        internalValue.value = newVal || '';
      }
    });

    const handleInput = (value: string) => {
      internalValue.value = value;
      if (props.field?.onInput) {
        props.field.onInput({ target: { value } });
      } else if (props.field?.onChange) {
        props.field.onChange({ target: { value } });
      }
    };

    const inputOptions = computed(() => ({
      placeholder: props.placeholder || 'Entrez le numéro de téléphone',
      maxlength: 25,
      autocomplete: 'tel'
    }));

    const dropdownOptions = {
      showDialCodeInList: true,
      showFlags: true,
      showSearchBox: true,
      showDialCodeInSelection: true
    };

    return () => h(VueTelInput, {
      modelValue: internalValue.value,
      'onUpdate:modelValue': handleInput,
      disabled: props.disabled,
      inputOptions: inputOptions.value,
      dropdownOptions: dropdownOptions,
      validCharactersOnly: true,
      defaultCountry: props.defaultCountry,
      mode: 'international',
      class: ['vue-tel-input-custom', props.field?.invalid ? 'p-invalid' : '']
    });
  }
});
</script>

<style scoped>
.phone-input-wrapper {
  width: 100%;
}

:deep(.vue-tel-input) {
  border: 1px solid var(--p-inputtext-border-color);
  border-radius: var(--p-inputtext-border-radius);
  background: var(--p-inputtext-background);
  transition: background-color 0.2s, color 0.2s, border-color 0.2s, box-shadow 0.2s;
}

:deep(.vue-tel-input:hover) {
  border-color: var(--p-inputtext-hover-border-color);
}

:deep(.vue-tel-input:focus-within) {
  outline: 0 none;
  outline-offset: 0;
  border-color: var(--p-inputtext-focus-border-color);
  box-shadow: var(--p-inputtext-focus-ring-shadow);
}

:deep(.vue-tel-input.disabled) {
  opacity: var(--p-inputtext-disabled-opacity);
  background: var(--p-inputtext-disabled-background);
}

:deep(.vti__input) {
  border: none !important;
  background: transparent !important;
  padding: 0.75rem 0.75rem;
  font-family: var(--p-inputtext-font-family);
  font-size: var(--p-inputtext-font-size);
  color: var(--p-inputtext-color);
  outline: none;
  width: 100%;
}

:deep(.vti__dropdown) {
  padding: 0.5rem;
  background: transparent;
  border-right: 1px solid var(--p-inputtext-border-color);
}

:deep(.vti__dropdown:hover) {
  background: var(--p-inputtext-hover-background);
}

:deep(.vti__dropdown-list) {
  background: var(--p-menu-background);
  border: 1px solid var(--p-menu-border-color);
  border-radius: var(--p-menu-border-radius);
  box-shadow: var(--p-menu-shadow);
  max-height: 300px;
  overflow-y: auto;
  z-index: 1000;
}

:deep(.vti__dropdown-item) {
  padding: 0.75rem 1rem;
  color: var(--p-menu-item-color);
  cursor: pointer;
}

:deep(.vti__dropdown-item:hover) {
  background: var(--p-menu-item-focus-background);
  color: var(--p-menu-item-focus-color);
}

:deep(.vti__dropdown-item.highlighted) {
  background: var(--p-menu-item-focus-background);
  color: var(--p-menu-item-focus-color);
}

:deep(.vti__search_box) {
  padding: 0.75rem;
  border-bottom: 1px solid var(--p-inputtext-border-color);
}

:deep(.vti__search_box input) {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--p-inputtext-border-color);
  border-radius: var(--p-inputtext-border-radius);
  background: var(--p-inputtext-background);
  color: var(--p-inputtext-color);
  font-family: var(--p-inputtext-font-family);
}
</style>
