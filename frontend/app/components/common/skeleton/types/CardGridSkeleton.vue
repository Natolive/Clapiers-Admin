<template>
  <div class="grid">
    <div v-for="i in count" :key="i" class="col-12 md:col-6 lg:col-4">
      <Card>
        <template #title>
          <Skeleton width="70%" height="2rem" />
        </template>
        <template #content>
          <div class="flex flex-column gap-3">
            <div class="flex align-items-center gap-2">
              <Skeleton shape="circle" size="1.5rem" />
              <Skeleton width="30%" height="1rem" />
              <Skeleton width="20%" height="1rem" />
            </div>
            <div class="flex align-items-center gap-2">
              <Skeleton shape="circle" size="1.5rem" />
              <Skeleton width="30%" height="1rem" />
              <Skeleton width="20%" height="1rem" />
            </div>
          </div>
        </template>
      </Card>
    </div>
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
