<template>
  <div class="flex flex-column gap-3">
    <div v-for="i in count" :key="i" class="flex align-items-center gap-3">
      <Skeleton shape="circle" size="3rem" />
      <div class="flex-1">
        <Skeleton width="60%" height="1.5rem" class="mb-2" />
        <Skeleton width="80%" height="1rem" />
      </div>
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
