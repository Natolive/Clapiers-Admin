<template>
  <div class="member-avatar-wrapper">
    <Avatar
      v-if="blobUrl"
      :image="blobUrl"
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

const props = withDefaults(defineProps<{
  member: Member
  size?: 'normal' | 'large' | 'xlarge'
  /** URL de l'endpoint photo (défaut : route super-admin). Les pages coach
   *  passent leur route protégée par équipe. */
  src?: string
}>(), {
  size: 'large'
});

const config = useRuntimeConfig();
const blobUrl = ref<string | null>(null);

const initials = computed(() => {
  const firstInitial = props.member.firstName.charAt(0).toUpperCase();
  const lastInitial = props.member.lastName.charAt(0).toUpperCase();
  return `${firstInitial}${lastInitial}`;
});

const fetchProfilePicture = async () => {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value);
    blobUrl.value = null;
  }

  // On tente toujours : l'endpoint sert la photo de la médiathèque (slot « Photo
  // de profil »), et renvoie 404 s'il n'y en a pas → repli sur les initiales.
  const token = useCookie('auth_token').value;
  const url = props.src ?? `${config.public.apiBase}/member/${props.member.id}/profile-picture`;

  try {
    const res = await fetch(url, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    });
    if (res.ok) {
      const blob = await res.blob();
      blobUrl.value = URL.createObjectURL(blob);
    }
  } catch {
    // Silently fail — fallback to initials
  }
};

watch(() => [props.member.id, props.src], () => {
  fetchProfilePicture();
}, { immediate: true });

onBeforeUnmount(() => {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value);
  }
});
</script>

<style scoped>
.member-avatar-wrapper {
  position: relative;
  display: inline-block;
}
</style>
