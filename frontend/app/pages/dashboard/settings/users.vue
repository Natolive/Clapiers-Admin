<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouvel utilisateur" icon="pi pi-plus" @click="openCreateDialog()" />
      </template>
    </Toolbar>

    <Card>
      <template #content>
        <UsersDatatable ref="datatableRef" />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import UsersDatatable from '~/components/datatables/UsersDatatable.vue';
import CreateUpdateUserDialog from '~/components/dialogs/CreateUpdateUserDialog.vue';
import { UserRepository } from '~/repository/user-repository';
import type { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

useHead({ title: 'Utilisateurs' });

const { isSuperAdmin } = useUserRole();
const { show } = useDialogManager();
const userRepository = new UserRepository();
const datatableRef = ref<InstanceType<typeof UsersDatatable> | null>(null);

const openCreateDialog = () => {
  show({
    component: CreateUpdateUserDialog,
    props: {
      user: null,
      onSubmit: async (values: { email: string; role: AppUserRole; password: string | null }) => {
        await userRepository.createUpdate(
          values.email,
          values.role,
          values.password,
          null
        );
        datatableRef.value?.refresh();
      }
    }
  });
};

onMounted(async () => {
  if (!isSuperAdmin.value) {
    await navigateTo('/dashboard');
  }
});
</script>
