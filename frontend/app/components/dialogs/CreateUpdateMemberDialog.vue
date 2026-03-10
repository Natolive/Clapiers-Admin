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
      @formSubmit="handleSubmit"
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
  firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number;
  licenseNumber: string | null;
  addressStreet: string; addressZip: string; addressCity: string;
  gender: MemberGender; birthDate: string; nationality: string;
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
  props.member ? 'Modifier le membre' : 'Nouveau membre'
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
    toast.add({ severity: 'success', summary: 'Membre enregistré', life: 2500 });
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'enregistrer le membre', life: 4000 });
  } finally {
    loading.value = false;
  }
};
</script>
