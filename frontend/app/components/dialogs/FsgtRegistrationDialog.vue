<template>
  <Dialog
    :visible="visible"
    :header="registered ? 'Retirer l\'inscription FSGT' : 'Inscrire à la FSGT'"
    :modal="true"
    :style="{ width: 'min(95vw, 440px)' }"
    @update:visible="onVisible"
  >
    <div class="mb-3">
      <p class="m-0"><strong>{{ member.firstName }} {{ member.lastName }}</strong></p>
      <p class="m-0 text-500">Saison {{ season }}</p>
    </div>

    <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>

    <!-- Décocher : rien à saisir, on rappelle juste ce qui est conservé. -->
    <Message v-if="registered" severity="warn" :closable="false" class="mb-3">
      Le licencié ne sera plus compté comme inscrit pour cette saison.
      Son numéro de licence est conservé.
    </Message>

    <div v-else class="mb-3">
      <label for="fsgt-number" class="block mb-2 font-medium">N° de licence FSGT</label>
      <InputText
        id="fsgt-number"
        v-model="licenseNumber"
        class="w-full"
        maxlength="50"
        placeholder="Ex : 123456789"
        autofocus
        @keyup.enter="submit"
      />
      <small class="text-500">
        Attribué par la fédération. Il est enregistré sur la licence de la saison {{ season }}.
      </small>
    </div>

    <div class="flex justify-content-end gap-2">
      <Button label="Annuler" severity="secondary" text @click="onVisible(false)" />
      <Button
        :label="registered ? 'Retirer l\'inscription' : 'Inscrire'"
        :icon="registered ? 'pi pi-times' : 'pi pi-check'"
        :severity="registered ? 'danger' : 'success'"
        :loading="loading"
        :disabled="!registered && !licenseNumber.trim()"
        @click="submit"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';

const props = withDefaults(defineProps<{
  visible?: boolean;
  member: Member;
  season: string;
  /** État courant : true = déjà inscrit, le dialog propose alors de retirer. */
  registered: boolean;
  onSaved?: () => void;
}>(), { visible: true });

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const repository = new MemberRepository();
// Pré-rempli avec le numéro déjà connu : un renouvellement l'apporte souvent
// depuis le formulaire public, autant ne pas le faire retaper.
const licenseNumber = ref(props.member.licenseNumber ?? '');
const loading = ref(false);
const error = ref('');

const onVisible = (value: boolean) => emit('update:visible', value);

const submit = async () => {
  if (!props.registered && !licenseNumber.value.trim()) return;

  loading.value = true;
  error.value = '';
  try {
    await repository.setFsgtRegistration(
      props.member.id,
      !props.registered,
      props.registered ? null : licenseNumber.value.trim(),
      props.season,
    );
    props.onSaved?.();
    onVisible(false);
  } catch (e: any) {
    error.value = e?.data?.message ?? "L'inscription n'a pas pu être enregistrée.";
  } finally {
    loading.value = false;
  }
};
</script>
