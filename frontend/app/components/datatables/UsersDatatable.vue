<template>
  <DataTable
    :value="users"
    stripedRows
    responsiveLayout="scroll"
    class="p-datatable-sm"
  >
    <Column field="email" header="Email" sortable style="width: 35%"></Column>
    <Column field="roles" header="Rôle" sortable style="width: 25%">
      <template #body="slotProps">
        <RoleBadge
          :role="slotProps.data.roles[0]"
          size="xs"
        />
      </template>
    </Column>
    <Column field="team" header="Équipe" sortable style="width: 15%">
      <template #body="slotProps">
        {{ slotProps.data.team?.name || '-' }}
      </template>
    </Column>
    <Column field="createdAt" header="Date de création" sortable style="width: 15%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column field="updatedAt" header="Dernière modification" sortable style="width: 15%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.updatedAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 5%">
      <template #body="slotProps">
        <Button
          icon="pi pi-pencil"
          severity="secondary"
          text
          rounded
          @click="openDialog(slotProps.data)"
        />
      </template>
    </Column>
  </DataTable>
</template>

<script setup lang="ts">
import CreateUpdateUserDialog from '~/components/dialogs/CreateUpdateUserDialog.vue';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';
import RoleBadge from '../badge/RoleBadge.vue';

const props = defineProps<{
  users: AppUser[]
}>();

const emit = defineEmits<{
  userUpdated: [user: AppUser]
  userCreated: [user: AppUser]
}>();

const { show } = useDialogManager();

// Open dialog for create or edit
const openDialog = (user?: AppUser) => {
  show({
    component: CreateUpdateUserDialog,
    props: {
      user: user || null,
      onSubmit: async (values: { email: string; role: AppUserRole; password: string | null; teamId: number | null }) => {
        const { UserRepository } = await import('~/repository/user-repository');
        const userRepository = new UserRepository();
        const savedUser = await userRepository.createUpdate(
          values.email,
          values.role,
          values.password,
          user?.id || null,
          values.teamId
        );

        if (user) {
          emit('userUpdated', savedUser);
        } else {
          emit('userCreated', savedUser);
        }
      }
    }
  });
};
</script>
