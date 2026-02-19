<template>
  <Dialog
    :visible="visible"
    header="Associer un membre"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <IconField class="mb-3">
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="searchQuery"
        placeholder="Rechercher un membre..."
        class="w-full"
      />
    </IconField>

    <div class="member-list">
      <div v-if="loading" class="flex justify-content-center p-4">
        <ProgressSpinner style="width: 2rem; height: 2rem" />
      </div>
      <div v-else-if="filteredMembers.length === 0" class="text-center p-4 text-color-secondary">
        Aucun membre trouvé
      </div>
      <div
        v-for="member in filteredMembers"
        :key="member.id"
        class="member-item"
        :class="{ disabled: member._linked }"
        @click="!member._linked && selectMember(member)"
      >
        <MemberAvatar :member="member" size="normal" />
        <div class="member-info">
          <span class="member-name">{{ member.firstName }} {{ member.lastName }}</span>
          <span class="member-team">{{ member.team.name }}</span>
        </div>
        <Tag v-if="member._linked" value="Déjà associé" severity="warn" class="ml-auto" />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';
import { UserRepository } from '~/repository/user-repository';

interface Props {
  visible?: boolean;
  modal?: boolean;
  style?: string | object;
  onSelect?: (memberId: number) => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  modal: true,
  style: () => ({ width: '30rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
}>();

type MemberWithLinked = Member & { _linked: boolean };

const memberRepository = new MemberRepository();
const userRepository = new UserRepository();
const searchQuery = ref('');
const allMembers = ref<MemberWithLinked[]>([]);
const loading = ref(false);
const selectLoading = ref(false);

const filteredMembers = computed(() => {
  if (!searchQuery.value) return allMembers.value;
  const q = searchQuery.value.toLowerCase();
  return allMembers.value.filter(m =>
    m.firstName.toLowerCase().includes(q) ||
    m.lastName.toLowerCase().includes(q) ||
    m.email.toLowerCase().includes(q)
  );
});

const fetchMembers = async () => {
  loading.value = true;
  try {
    const [members, users] = await Promise.all([
      memberRepository.getAll(),
      userRepository.getAll()
    ]);

    const linkedMemberIds = new Set(
      users.filter(u => u.member).map(u => u.member!.id)
    );

    allMembers.value = members.map(m => ({
      ...m,
      _linked: linkedMemberIds.has(m.id)
    }));
  } finally {
    loading.value = false;
  }
};

const selectMember = async (member: MemberWithLinked) => {
  if (selectLoading.value) return;
  selectLoading.value = true;
  try {
    if (props.onSelect) {
      await props.onSelect(member.id);
      emit('update:visible', false);
    }
  } finally {
    selectLoading.value = false;
  }
};

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

onMounted(() => {
  fetchMembers();
});
</script>

<style scoped>
.member-list {
  max-height: 20rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.member-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.member-item:hover:not(.disabled) {
  background: var(--p-surface-100);
}

.member-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.member-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.member-name {
  font-weight: 500;
  font-size: 0.9rem;
}

.member-team {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
</style>
