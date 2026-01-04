<template>
  <Dialog
    :visible="visible"
    :header="header"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <MemberForm
      :member="member"
      :teams="teams"
      :loading="internalLoading"
      @submit="handleSubmit"
    />
  </Dialog>
</template>

<script setup lang="ts">
import MemberForm from '~/components/forms/member-form.vue';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

interface Props {
  visible?: boolean;
  member?: Member | null;
  teams?: Team[];
  loading?: boolean;
  modal?: boolean;
  style?: string | object;
  onSubmit?: (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  member: null,
  teams: () => [],
  loading: false,
  modal: true,
  style: () => ({ width: '30rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'submit': [values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }];
}>();

const internalLoading = ref(false);

const header = computed(() =>
  props.member ? 'Modifier le membre' : 'Nouveau membre'
);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleSubmit = async (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => {
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
