<template>
  <div v-if="user.member" class="member-card linked" @click="openDetails">
    <MemberAvatar :member="user.member" size="normal" />
    <span class="member-name">{{ user.member.firstName }} {{ user.member.lastName }}</span>
    <Button
      icon="pi pi-times"
      severity="danger"
      text
      rounded
      size="small"
      class="unlink-btn"
      @click.stop="confirmUnlink"
      v-tooltip.top="'Dissocier'"
    />
  </div>
  <div v-else class="member-card empty" @click="openLinkDialog">
    <i class="pi pi-plus" />
    <span>Associer un membre</span>
  </div>
</template>

<script setup lang="ts">
import type { AppUser } from '~/types/entity/AppUser';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import LinkMemberDialog from '~/components/dialogs/LinkMemberDialog.vue';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';

const props = defineProps<{
  user: AppUser;
  onLink: (memberId: number) => Promise<void>;
  onUnlink: () => Promise<void>;
}>();

const { show } = useDialogManager();

const openDetails = () => {
  if (!props.user.member) return;
  show({
    component: MemberDetailsDialog,
    props: {
      member: props.user.member,
    }
  });
};

const openLinkDialog = () => {
  show({
    component: LinkMemberDialog,
    props: {
      onSelect: async (memberId: number) => {
        await props.onLink(memberId);
      }
    }
  });
};

const confirmUnlink = () => {
  show({
    component: ConfirmDeleteDialog,
    props: {
      header: 'Dissocier le membre',
      message: `Voulez-vous dissocier ${props.user.member?.firstName} ${props.user.member?.lastName} de cet utilisateur ?`,
      confirmLabel: 'Dissocier',
      onConfirm: async () => {
        await props.onUnlink();
      }
    }
  });
};
</script>

<style scoped>
.member-card {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.65rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  max-width: 100%;
}

.member-card.linked {
  background: var(--p-surface-100);
  border: 1px solid var(--p-surface-200);
}

.member-card.linked:hover {
  background: var(--p-surface-200);
}

.member-card.empty {
  border: 1.5px dashed var(--p-surface-300);
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
}

.member-card.empty:hover {
  border-color: var(--p-primary-color);
  color: var(--p-primary-color);
  background: var(--p-primary-50);
}

.member-name {
  font-size: 0.85rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.unlink-btn {
  margin-left: auto;
  flex-shrink: 0;
}
</style>
