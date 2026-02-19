<template>
  <div class="member-avatar-wrapper" :class="{ editable }" @click="editable && triggerUpload()">
    <Avatar
      v-if="blobUrl"
      :image="blobUrl"
      shape="circle"
      :size="size"
    />
    <Avatar
      v-else
      :label="initials"
      :style="{ backgroundColor: member.color, color: 'white' }"
      shape="circle"
      :size="size"
    />

    <div v-if="editable" class="avatar-overlay">
      <i class="pi pi-camera" />
    </div>

    <button
      v-if="editable && member.profilePicture"
      class="avatar-delete-btn"
      @click.stop="emit('delete')"
    >
      <i class="pi pi-times" />
    </button>

    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      class="hidden"
      @change="onFileSelected"
    />
  </div>
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';

const props = withDefaults(defineProps<{
  member: Member
  size?: 'normal' | 'large' | 'xlarge'
  editable?: boolean
}>(), {
  size: 'large',
  editable: false
});

const emit = defineEmits<{
  upload: [file: File]
  delete: []
}>();

const config = useRuntimeConfig();
const fileInput = ref<HTMLInputElement | null>(null);
const blobUrl = ref<string | null>(null);

const initials = computed(() => {
  const firstInitial = props.member.firstName.charAt(0).toUpperCase();
  const lastInitial = props.member.lastName.charAt(0).toUpperCase();
  return `${firstInitial}${lastInitial}`;
});

const fetchProfilePicture = async () => {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value);
    blobUrl.value = null;
  }

  if (!props.member.profilePicture) return;

  const token = useCookie('auth_token').value;
  const url = `${config.public.apiBase}/member/${props.member.id}/profile-picture`;

  try {
    const res = await fetch(url, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    });
    if (res.ok) {
      const blob = await res.blob();
      blobUrl.value = URL.createObjectURL(blob);
    }
  } catch {
    // Silently fail — fallback to initials
  }
};

watch(() => props.member.profilePicture, () => {
  fetchProfilePicture();
}, { immediate: true });

onBeforeUnmount(() => {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value);
  }
});

function triggerUpload() {
  fileInput.value?.click();
}

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (file) {
    emit('upload', file);
    input.value = '';
  }
}
</script>

<style scoped>
.member-avatar-wrapper {
  position: relative;
  display: inline-block;
}

.member-avatar-wrapper.editable {
  cursor: pointer;
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  opacity: 0;
  transition: opacity 0.2s;
  color: white;
  font-size: 1.2rem;
}

.member-avatar-wrapper.editable:hover .avatar-overlay {
  opacity: 1;
}

.avatar-delete-btn {
  position: absolute;
  top: -4px;
  right: -4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: none;
  background: var(--p-red-500);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.6rem;
  opacity: 0;
  transition: opacity 0.2s;
}

.member-avatar-wrapper.editable:hover .avatar-delete-btn {
  opacity: 1;
}

@media (hover: none) {
  .member-avatar-wrapper.editable .avatar-overlay {
    opacity: 1;
    background: rgba(0, 0, 0, 0.3);
  }

  .member-avatar-wrapper.editable .avatar-delete-btn {
    opacity: 1;
  }
}

.hidden {
  display: none;
}
</style>
