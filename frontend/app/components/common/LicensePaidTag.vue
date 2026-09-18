<template>
  <Tag
    :value="paid ? 'Payée' : 'Impayée'"
    :severity="paid ? 'success' : 'danger'"
    :class="{ 'text-xs': small }"
  />
</template>

<script setup lang="ts">
// Statut de paiement d'une licence : un seul endroit décide du libellé et de la
// couleur. Les six vues qui l'affichaient divergeaient (« Non payée » /
// « Licence non payée », danger ici / warn là).
withDefaults(defineProps<{
  paid: boolean
  /** Compact (listes et cartes) ; false pour les badges pleine taille. */
  small?: boolean
}>(), { small: true });
</script>
