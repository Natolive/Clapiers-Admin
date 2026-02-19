<template>
  <Dialog
    :visible="visible"
    header="Détails du membre"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <div class="flex flex-column align-items-center gap-3 mb-4">
      <MemberAvatar
        :member="member"
        size="xlarge"
        :editable="isAdmin"
        @upload="onUpload"
        @delete="onDelete"
      />
      <div class="text-center">
        <div class="text-xl font-semibold">{{ member.firstName }} {{ member.lastName }}</div>
        <div class="text-color-secondary">{{ member.team.name }}</div>
      </div>
    </div>

    <div class="flex flex-column gap-3">
      <div class="detail-row">
        <span class="detail-label"><i class="pi pi-envelope mr-2" />Email</span>
        <span>{{ member.email }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><i class="pi pi-phone mr-2" />Téléphone</span>
        <span>{{ member.phoneNumber }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><i class="pi pi-users mr-2" />Équipe</span>
        <span>{{ member.team.name }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><i class="pi pi-id-card mr-2" />Licence</span>
        <Tag
          :value="member.licensePaid ? 'Payée' : 'Non payée'"
          :severity="member.licensePaid ? 'success' : 'danger'"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';

interface Props {
  visible?: boolean;
  member: Member;
  modal?: boolean;
  style?: string | object;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  modal: true,
  style: () => ({ width: '25rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'update:member': [member: Member];
}>();

const { isAdmin } = useUserRole();
const memberRepository = new MemberRepository();

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const onUpload = async (file: File) => {
  const updated = await memberRepository.uploadProfilePicture(props.member.id, file);
  emit('update:member', updated);
};

const onDelete = async () => {
  const updated = await memberRepository.deleteProfilePicture(props.member.id);
  emit('update:member', updated);
};
</script>

<style scoped>
.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--p-surface-200);
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 500;
  color: var(--p-text-muted-color);
  display: flex;
  align-items: center;
}
</style>
