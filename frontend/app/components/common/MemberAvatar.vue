<template>
  <Avatar
    :label="initials"
    :style="{ backgroundColor: member.color, color: 'white' }"
    shape="circle"
    :size="size"
  />
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';

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
