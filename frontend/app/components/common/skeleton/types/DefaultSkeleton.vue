<template>
  <div>
    <Skeleton v-for="i in count" :key="i" width="100%" height="4rem" class="mb-3" />
  </div>
</template>

<script setup lang="ts">
interface Props {
  count?: number;
}

withDefaults(defineProps<Props>(), {
  count: 3
});
</script>
