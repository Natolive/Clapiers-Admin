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
      :teams="teamOptions"
      :loading="internalLoading"
      @submit="handleSubmit"
    />
  </Dialog>
</template>

<script setup lang="ts">
import UserForm from '~/components/forms/user-form.vue';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';
import type { Team } from '~/types/entity/Team';
import { TeamRepository } from '~/repository/team-repository';

type UserPayload = { email: string; role: AppUserRole; password: string | null; teamIds: number[] };

interface Props {
  visible?: boolean;
  user?: AppUser | null;
  loading?: boolean;
  modal?: boolean;
  style?: string | object;
  onSubmit?: (values: UserPayload) => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  user: null,
  loading: false,
  modal: true,
  style: () => ({ width: 'min(95vw, 30rem)' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'submit': [values: UserPayload];
}>();

const internalLoading = ref(false);

// Le dialog charge lui-même les équipes pour le MultiSelect « Équipes gérées »
const teamOptions = ref<Team[]>([]);
onMounted(async () => {
  try {
    teamOptions.value = await new TeamRepository().getAll();
  } catch (error) {
    console.error('Error loading teams:', error);
  }
});

const header = computed(() =>
  props.user ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'
);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleSubmit = async (values: UserPayload) => {
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
