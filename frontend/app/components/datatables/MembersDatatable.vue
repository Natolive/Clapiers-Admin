<template>
  <DataTable
    :value="members"
    stripedRows
    responsiveLayout="scroll"
    class="p-datatable-sm"
  >
    <Column header="Membre" sortable field="firstName" style="width: 35%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-3">
          <MemberAvatar :member="slotProps.data" />
          <span>{{ slotProps.data.firstName }} {{ slotProps.data.lastName }}</span>
        </div>
      </template>
    </Column>
    <Column field="team.name" header="Équipe" sortable style="width: 35%"></Column>
    <Column field="createdAt" header="Date de création" sortable style="width: 20%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 10%">
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
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

const props = defineProps<{
  members: Member[]
  teams: Team[]
}>();

const emit = defineEmits<{
  memberUpdated: [member: Member]
  memberCreated: [member: Member]
}>();

const { show } = useDialogManager();

// Open dialog for create or edit
const openDialog = (member?: Member) => {
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: member || null,
      teams: props.teams,
      onSubmit: async (values: { firstName: string; lastName: string; teamId: number }) => {
        const { MemberRepository } = await import('~/repository/member-repository');
        const memberRepository = new MemberRepository();
        const savedMember = await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.teamId,
          member?.id || null
        );

        if (member) {
          emit('memberUpdated', savedMember);
        } else {
          emit('memberCreated', savedMember);
        }
      }
    }
  });
};
</script>
