<template>
  <component
    v-for="dialog in dialogs"
    :key="dialog.id.toString()"
    :is="dialog.component"
    v-bind="dialog.props"
  />
</template>

<script setup lang="ts">
const { dialogs } = useDialogManager();
</script>
