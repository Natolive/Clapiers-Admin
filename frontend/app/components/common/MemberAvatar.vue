<template>
  <div class="member-avatar-wrapper">
    <Avatar
      v-if="member.profilePictureUrl"
      :image="member.profilePictureUrl"
      shape="circle"
      :size="size"
    />
    <Avatar
      v-else
      :label="initials"
      :style="{ backgroundColor: member.color, color: 'white' }"
      shape="circle"
      :size="size"
    />
  </div>
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';

// `profilePictureUrl` est une URL CDN signée servie par l'API dans le payload
// du licencié : le navigateur la charge et la met en cache comme n'importe
// quelle image. Absente (pas de photo, ou stockage non configuré) → initiales.
const props = withDefaults(defineProps<{
  member: Member
  size?: 'normal' | 'large' | 'xlarge'
}>(), {
  size: 'large'
});

const initials = computed(() => {
  const firstInitial = props.member.firstName.charAt(0).toUpperCase();
  const lastInitial = props.member.lastName.charAt(0).toUpperCase();
  return `${firstInitial}${lastInitial}`;
});
</script>

<style scoped>
.member-avatar-wrapper {
  position: relative;
  display: inline-block;
}

/* Photo non carrée : on affiche le carré central au lieu d'étirer l'image. */
.member-avatar-wrapper :deep(.p-avatar img) {
  object-fit: cover;
  object-position: center;
}
</style>
