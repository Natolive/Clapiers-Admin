<template>
  <Dialog
    :visible="visible"
    :header="header"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <MemberForm
      :member="member"
      :teams="teams"
      :loading="loading"
      show-cancel
      @formSubmit="handleSubmit"
      @cancel="handleVisibilityChange(false)"
    />
  </Dialog>
</template>

<script setup lang="ts">
import MemberForm from '~/components/forms/member-form.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import type { MemberGender } from '~/types/enum/MemberGender';

type MemberPayload = {
  firstName: string; lastName: string; phoneNumber: string; email: string; teamIds: number[];
  licenseNumber: string | null;
  addressStreet: string; addressZip: string; addressCity: string;
  gender: MemberGender; birthDate: string; nationality: string;
  /** Licence de la saison à créer avec la fiche ; absent = fiche seule. */
  license?: { season: string; helloAssoTierId: number | null; amount: number | null; sendPaymentEmail: boolean };
};

interface Props {
  visible?: boolean;
  member?: Member | null;
  teams?: Team[];
  modal?: boolean;
  style?: string | object;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  member: null,
  teams: () => [],
  modal: true,
  style: () => ({ width: 'min(95vw, 760px)' }),
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'saved': [member: Member];
}>();

const memberRepository = new MemberRepository();
const toast = usePVToastService();
const loading = ref(false);

const header = computed(() =>
  props.member ? 'Modifier le licencié' : 'Nouveau licencié'
);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleSubmit = async (values: MemberPayload) => {
  loading.value = true;
  try {
    const payload = props.member ? { ...values, id: props.member.id } : values;
    const saved = await memberRepository.createUpdate(payload);
    emit('saved', saved);
    emit('update:visible', false);
    toast.add({
      severity: 'success',
      summary: 'Licencié enregistré',
      detail: values.license?.sendPaymentEmail ? 'Le lien de paiement a été envoyé par e-mail.' : undefined,
      life: 2500,
    });
  } catch (e: any) {
    // Le back renvoie des refus parlants (tarif manquant, licence déjà là) :
    // les masquer derrière « Erreur » laisse l'admin sans rien à corriger.
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.data?.message || 'Impossible d\'enregistrer le licencié',
      life: 4000,
    });
  } finally {
    loading.value = false;
  }
};
</script>
