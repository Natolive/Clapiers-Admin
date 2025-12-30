<template>
  <Dialog
    :visible="visible"
    :header="header"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <TeamForm
      :team="team"
      :loading="internalLoading"
      @submit="handleSubmit"
    />
  </Dialog>
</template>

<script setup lang="ts">
import TeamForm from '~/components/forms/team-form.vue';
import type { Team } from '~/types/entity/Team';

interface Props {
  visible?: boolean;
  team?: Team | null;
  loading?: boolean;
  modal?: boolean;
  style?: string | object;
  onSubmit?: (values: { name: string }) => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  team: null,
  loading: false,
  modal: true,
  style: () => ({ width: '30rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'submit': [values: { name: string }];
}>();

const internalLoading = ref(false);

const header = computed(() =>
  props.team ? 'Modifier l\'équipe' : 'Nouvelle équipe'
);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleSubmit = async (values: { name: string }) => {
  if (props.onSubmit) {
    internalLoading.value = true;
    try {
      await props.onSubmit(values);
      emit('update:visible', false);
    } finally {
      internalLoading.value = false;
    }
  } else {
    emit('submit', values);
  }
};
</script>
