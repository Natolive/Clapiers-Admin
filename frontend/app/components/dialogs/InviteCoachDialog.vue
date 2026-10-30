<template>
  <Dialog
    :visible="visible"
    :header="status ? 'Accès chef d\'équipe' : 'Inviter comme chef d\'équipe'"
    :modal="true"
    :style="{ width: 'min(95vw, 460px)' }"
    @update:visible="onVisible"
  >
    <div class="mb-3">
      <p class="m-0"><strong>{{ member.firstName }} {{ member.lastName }}</strong></p>
      <p class="m-0 text-500">{{ member.email || 'Aucune adresse e-mail' }}</p>
    </div>

    <Message v-if="status === 'invited'" severity="info" :closable="false" class="mb-3">
      Invitation envoyée, en attente : le mot de passe n'a pas encore été créé.
      Vous pouvez la relancer si le mail s'est perdu.
    </Message>
    <Message v-else-if="status === 'expired'" severity="warn" :closable="false" class="mb-3">
      L'invitation a expiré sans être utilisée. Relancez-la pour envoyer un nouveau lien.
    </Message>
    <Message v-else-if="status === 'active'" severity="success" :closable="false" class="mb-3">
      Compte actif. Renvoyer un lien met à jour ses équipes et lui permet de
      redéfinir son mot de passe (l'actuel reste valable d'ici là).
    </Message>

    <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>

    <div class="mb-3">
      <label for="invite-teams" class="block mb-2 font-medium">Équipe(s) dont il/elle est responsable</label>
      <MultiSelect
        v-model="teamIds"
        :options="teams"
        optionLabel="name"
        optionValue="id"
        :maxSelectedLabels="2"
        selectedItemsLabel="{0} équipes sélectionnées"
        filter
        filterPlaceholder="Rechercher une équipe"
        inputId="invite-teams"
        placeholder="Sélectionnez les équipes"
        fluid
      />
      <small class="text-500">
        Un e-mail lui permettra de créer son mot de passe (lien valable 7 jours).
        Tout nouvel envoi remplace ses équipes et invalide le lien précédent.
      </small>
    </div>

    <div class="flex justify-content-end gap-2">
      <Button label="Annuler" severity="secondary" text @click="onVisible(false)" />
      <Button
        :label="{ invited: 'Relancer l\'invitation', expired: 'Relancer l\'invitation', active: 'Mettre à jour et renvoyer un lien' }[status ?? ''] ?? 'Envoyer l\'invitation'"
        icon="pi pi-send"
        :loading="loading"
        :disabled="!teamIds.length || !member.email"
        @click="submit"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { UserRepository } from '~/repository/user-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

const props = withDefaults(defineProps<{
  visible?: boolean;
  member: Member;
  teams: Team[];
  onSaved?: () => void;
}>(), { visible: true });

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const repository = new UserRepository();
const toast = usePVToastService();
const status = props.member.account?.invitationStatus ?? null;
// Relance : on repart des équipes déjà confiées.
const teamIds = ref<number[]>([...(props.member.account?.teamIds ?? [])]);
const loading = ref(false);
const error = ref('');

const onVisible = (value: boolean) => emit('update:visible', value);

const submit = async () => {
  loading.value = true;
  error.value = '';
  try {
    await repository.inviteCoach(props.member.id, teamIds.value);
    toast.add({
      severity: 'success',
      summary: status ? 'Invitation relancée' : 'Invitation envoyée',
      detail: props.member.email,
      life: 4000,
    });
    props.onSaved?.();
    onVisible(false);
  } catch (e: any) {
    error.value = apiErrorMessage(e);
  } finally {
    loading.value = false;
  }
};
</script>
