<template>
  <Dialog
    :visible="visible"
    :header="header"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <UserForm
      :user="user"
      :loading="internalLoading"
      @submit="handleSubmit"
    />
  </Dialog>
</template>

<script setup lang="ts">
import UserForm from '~/components/forms/user-form.vue';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';

interface Props {
  visible?: boolean;
  user?: AppUser | null;
  loading?: boolean;
  modal?: boolean;
  style?: string | object;
  onSubmit?: (values: { email: string; role: AppUserRole; password: string | null }) => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  user: null,
  loading: false,
  modal: true,
  style: () => ({ width: '30rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'submit': [values: { email: string; role: AppUserRole; password: string | null }];
}>();

const internalLoading = ref(false);

const header = computed(() =>
  props.user ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'
);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleSubmit = async (values: { email: string; role: AppUserRole; password: string | null }) => {
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
