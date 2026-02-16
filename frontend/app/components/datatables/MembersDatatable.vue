<template>
  <DataTable
    :value="members"
    stripedRows
    responsiveLayout="scroll"
    class="p-datatable-sm"
  >
    <Column header="Membre" sortable field="firstName" style="width: 20%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-3">
          <MemberAvatar :member="slotProps.data" size="normal" />
          <span>{{ slotProps.data.firstName }} {{ slotProps.data.lastName }}</span>
        </div>
      </template>
    </Column>
    <Column field="phoneNumber" header="Téléphone" sortable style="width: 15%">
      <template #body="slotProps">
        <span>{{ slotProps.data.phoneNumber }}</span>
      </template>
    </Column>
    <Column field="email" header="Email" sortable style="width: 15%">
      <template #body="slotProps">
        <span>{{ slotProps.data.email }}</span>
      </template>
    </Column>
    <Column field="team.name" header="Équipe" sortable style="width: 10%"></Column>
    <Column field="licensePaid" header="Payée" sortable style="width: 8%">
      <template #body="slotProps">
        <ToggleSwitch
          :modelValue="slotProps.data.licensePaid"
          @update:modelValue="toggleLicense(slotProps.data)"
          :disabled="togglingIds.has(slotProps.data.id)"
        />
      </template>
    </Column>
    <Column header="Fichier licence" style="width: 17%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-2">
          <Button
            :icon="uploadingIds.has(slotProps.data.id) ? 'pi pi-spin pi-spinner' : 'pi pi-upload'"
            severity="info"
            text
            rounded
            size="small"
            :disabled="uploadingIds.has(slotProps.data.id)"
            @click="triggerUpload(slotProps.data)"
            v-tooltip.top="'Importer licence'"
          />
          <Button
            v-if="slotProps.data.licenseFileName"
            icon="pi pi-download"
            severity="success"
            text
            rounded
            size="small"
            @click="downloadLicense(slotProps.data)"
            v-tooltip.top="'Télécharger licence'"
          />
          <Button
            v-if="slotProps.data.licenseFileName"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            @click="deleteLicense(slotProps.data)"
            v-tooltip.top="'Supprimer licence'"
          />
          <Tag
            v-if="slotProps.data.licenseFileName"
            value="Fichier"
            severity="success"
            class="text-xs"
          />
          <span v-else class="text-color-secondary text-sm">-</span>
        </div>
      </template>
    </Column>
    <Column field="createdAt" header="Créé le" sortable style="width: 8%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 7%">
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

  <input
    ref="fileInput"
    type="file"
    class="hidden"
    accept=".pdf,.jpg,.jpeg,.png"
    @change="handleFileSelected"
  />
</template>

<script setup lang="ts">
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';
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
const togglingIds = ref(new Set<number>());
const uploadingIds = ref(new Set<number>());
const fileInput = ref<HTMLInputElement | null>(null);
const uploadTargetMember = ref<Member | null>(null);

const toggleLicense = async (member: Member) => {
  togglingIds.value.add(member.id);
  try {
    const { MemberRepository } = await import('~/repository/member-repository');
    const memberRepository = new MemberRepository();
    const updatedMember = await memberRepository.toggleLicense(member.id);
    emit('memberUpdated', updatedMember);
  } finally {
    togglingIds.value.delete(member.id);
  }
};

const triggerUpload = (member: Member) => {
  if (member.licenseFileName) {
    show({
      component: ConfirmDeleteDialog,
      props: {
        header: 'Remplacer la licence',
        message: `Une licence existe déjà pour ${member.firstName} ${member.lastName}. Voulez-vous la remplacer ?`,
        confirmLabel: 'Remplacer',
        onConfirm: async () => {
          uploadTargetMember.value = member;
          fileInput.value?.click();
        }
      }
    });
  } else {
    uploadTargetMember.value = member;
    fileInput.value?.click();
  }
};

const handleFileSelected = async (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  const member = uploadTargetMember.value;

  if (!file || !member) return;

  uploadingIds.value.add(member.id);
  try {
    const { MemberRepository } = await import('~/repository/member-repository');
    const memberRepository = new MemberRepository();
    const updatedMember = await memberRepository.uploadLicense(member.id, file);
    emit('memberUpdated', updatedMember);
  } finally {
    uploadingIds.value.delete(member.id);
    uploadTargetMember.value = null;
    // Reset input so the same file can be re-selected
    input.value = '';
  }
};

const deleteLicense = (member: Member) => {
  show({
    component: ConfirmDeleteDialog,
    props: {
      message: `Êtes-vous sûr de vouloir supprimer la licence de ${member.firstName} ${member.lastName} ?`,
      onConfirm: async () => {
        const { MemberRepository } = await import('~/repository/member-repository');
        const memberRepository = new MemberRepository();
        const updatedMember = await memberRepository.deleteLicense(member.id);
        emit('memberUpdated', updatedMember);
      }
    }
  });
};

const downloadLicense = async (member: Member) => {
  const config = useRuntimeConfig();
  const url = `${config.public.apiBase}/member/${member.id}/download-license`;
  const token = useCookie('auth_token').value;

  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const blob = await res.blob();
  const blobUrl = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = blobUrl;
  a.download = member.licenseFileName || 'licence';
  a.click();
  URL.revokeObjectURL(blobUrl);
};

// Open dialog for create or edit
const openDialog = (member?: Member) => {
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: member || null,
      teams: props.teams,
      onSubmit: async (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => {
        const { MemberRepository } = await import('~/repository/member-repository');
        const memberRepository = new MemberRepository();
        const savedMember = await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.phoneNumber,
          values.email,
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
