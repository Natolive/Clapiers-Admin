<template>
  <div class="flex flex-column gap-3">
    <Skeleton width="100%" height="3rem" />
    <div v-for="i in count" :key="i" class="flex gap-3">
      <Skeleton width="100%" height="2.5rem" />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  count?: number;
}

withDefaults(defineProps<Props>(), {
  count: 5
});
</script>
