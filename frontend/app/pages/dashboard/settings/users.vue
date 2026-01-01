<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouvel utilisateur" icon="pi pi-plus" @click="openCreateDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <UsersDatatable
          :users="users"
          @user-updated="handleUserUpdated"
          @user-created="handleUserCreated"
        />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import UsersDatatable from '~/components/datatables/UsersDatatable.vue';
import CreateUpdateUserDialog from '~/components/dialogs/CreateUpdateUserDialog.vue';
import { UserRepository } from '~/repository/user-repository';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();
const { show } = useDialogManager();
const userRepository = new UserRepository();
const users = ref<AppUser[]>([]);
const loading = ref(true);

// Handle user updates
const handleUserUpdated = (updatedUser: AppUser) => {
  const index = users.value.findIndex(u => u.id === updatedUser.id);
  if (index !== -1) {
    users.value[index] = updatedUser;
  }
};

// Handle user creation
const handleUserCreated = (newUser: AppUser) => {
  users.value.push(newUser);
};

// Open dialog for creating a new user
const openCreateDialog = () => {
  show({
    component: CreateUpdateUserDialog,
    props: {
      user: null,
      onSubmit: async (values: { email: string; role: AppUserRole; password: string | null }) => {
        const savedUser = await userRepository.createUpdate(
          values.email,
          values.role,
          values.password,
          null
        );
        handleUserCreated(savedUser);
      }
    }
  });
};

onMounted(async () => {
  if (!isSuperAdmin.value) {
    await navigateTo('/dashboard');
    return;
  }

  try {
    users.value = await userRepository.getAll();
  } finally {
    loading.value = false;
  }
});
</script>
