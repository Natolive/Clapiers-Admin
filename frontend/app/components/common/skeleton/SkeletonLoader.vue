<template>
  <component :is="skeletonComponent" :count="count" />
</template>

<script setup lang="ts">
import CardGridSkeleton from './types/CardGridSkeleton.vue';
import TableSkeleton from './types/TableSkeleton.vue';
import ListSkeleton from './types/ListSkeleton.vue';
import DefaultSkeleton from './types/DefaultSkeleton.vue';

interface Props {
  type?: 'card-grid' | 'table' | 'list' | 'default';
  count?: number;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'default',
  count: 3
});

const skeletonComponents = {
  'card-grid': CardGridSkeleton,
  'table': TableSkeleton,
  'list': ListSkeleton,
  'default': DefaultSkeleton
};

const skeletonComponent = computed(() => skeletonComponents[props.type]);
</script>
