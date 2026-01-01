<template>
  <Badge
    :value="getRoleLabel(role)"
    :severity="getRoleSeverity(role)"
    :class="badgeClass"
  />
</template>

<script setup lang="ts">
interface Props {
  role: string;
  size?: 'xs' | 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md'
});

const { getRoleLabel, getRoleSeverity } = useUserRole();

const badgeClass = computed(() => {
  const sizeClasses: Record<string, string> = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: '',
    lg: 'text-lg'
  };
  return sizeClasses[props.size];
});
</script>
