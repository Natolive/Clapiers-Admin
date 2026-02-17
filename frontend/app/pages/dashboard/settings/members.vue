<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouveau membre" icon="pi pi-plus" @click="openCreateDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <MembersDatatable
          ref="datatableRef"
          :teams="teams"
        />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import MembersDatatable from '~/components/datatables/MembersDatatable.vue';
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import { MemberRepository } from '~/repository/member-repository';
import { TeamRepository } from '~/repository/team-repository';
import type { Team } from '~/types/entity/Team';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

useHead({ title: 'Membres' });

const { isSuperAdmin } = useUserRole();
const { show } = useDialogManager();
const memberRepository = new MemberRepository();
const teamRepository = new TeamRepository();
const datatableRef = ref<InstanceType<typeof MembersDatatable> | null>(null);
const teams = ref<Team[]>([]);
const loading = ref(true);

const openCreateDialog = () => {
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: null,
      teams: teams.value,
      onSubmit: async (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => {
        await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.phoneNumber,
          values.email,
          values.teamId,
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
    return;
  }

  try {
    teams.value = await teamRepository.getAll();
  } finally {
    loading.value = false;
  }
});
</script>
