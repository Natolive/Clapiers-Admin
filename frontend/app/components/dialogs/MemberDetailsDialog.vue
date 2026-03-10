<template>
  <Dialog
    :visible="visible"
    :modal="true"
    :closable="true"
    :style="{ width: 'min(95vw, 980px)', maxHeight: '95dvh', overflow: 'hidden' }"
    :pt="{ header: { style: 'display:none' }, content: { style: 'padding:0; border-radius: inherit; overflow: hidden; display: flex; flex-direction: column;' } }"
    @update:visible="emit('update:visible', $event)"
  >
    <div class="member-modal">

      <!-- Header -->
      <div class="member-header">
        <div class="member-header__identity">
          <MemberAvatar
            :member="currentMember"
            size="xlarge"
            :editable="isAdmin"
            @upload="onUpload"
            @delete="onDelete"
          />
          <div>
            <h2 class="member-name">{{ currentMember.firstName }} {{ currentMember.lastName }}</h2>
            <span class="member-team">{{ currentMember.team.name }}</span>
            <div class="member-badges">
              <Tag :value="currentMember.licensePaid ? 'Licence payée' : 'Licence non payée'" :severity="currentMember.licensePaid ? 'success' : 'danger'" />
              <Tag v-if="currentMember.licenseNumber" :value="`N° ${currentMember.licenseNumber}`" severity="secondary" />
            </div>
          </div>
        </div>
        <Button icon="pi pi-times" text rounded size="small" class="member-close" @click="emit('update:visible', false)" />
      </div>

      <!-- Body -->
      <div class="member-body">

        <!-- Left: Admin edit panel -->
        <div class="member-panel member-panel--form">
          <div class="panel-title">
            <i class="pi pi-pencil" />
            <span>{{ isAdmin ? 'Modifier le membre' : 'Informations' }}</span>
          </div>

          <MemberForm
            v-if="isAdmin"
            :member="currentMember"
            :teams="teams"
            :loading="saving"
            @formSubmit="onSave"
          />

          <!-- Read-only for non-admins -->
          <template v-else>
            <div class="info-grid">
              <InfoRow icon="pi-envelope" label="Email" :value="currentMember.email" />
              <InfoRow icon="pi-phone" label="Téléphone" :value="currentMember.phoneNumber" />
              <InfoRow icon="pi-user" label="Sexe" :value="MemberGenderLabels[currentMember.gender]" />
              <InfoRow icon="pi-calendar" label="Naissance" :value="formatDate(currentMember.birthDate)" />
              <InfoRow icon="pi-globe" label="Nationalité" :value="currentMember.nationality" />
              <InfoRow icon="pi-map-marker" label="Rue" :value="currentMember.address.street" />
              <InfoRow icon="pi-map-marker" label="Ville" :value="`${currentMember.address.zip} ${currentMember.address.city}`" />
            </div>
          </template>
        </div>

        <!-- Right: Quick info -->
        <div class="member-panel member-panel--info">
          <div class="panel-title">
            <i class="pi pi-info-circle" />
            <span>Récapitulatif</span>
          </div>

          <div class="info-grid">
            <InfoRow icon="pi-envelope" label="Email" :value="currentMember.email" />
            <InfoRow icon="pi-phone" label="Téléphone" :value="currentMember.phoneNumber" />
            <InfoRow icon="pi-user" label="Sexe" :value="MemberGenderLabels[currentMember.gender]" />
            <InfoRow icon="pi-calendar" label="Naissance" :value="formatDate(currentMember.birthDate)" />
            <InfoRow icon="pi-globe" label="Nationalité" :value="currentMember.nationality" />
            <InfoRow v-if="currentMember.licenseNumber" icon="pi-id-card" label="N° licence" :value="currentMember.licenseNumber" />
            <InfoRow icon="pi-map-marker" label="Rue" :value="currentMember.address.street" />
            <InfoRow icon="pi-map-marker" label="Ville" :value="`${currentMember.address.zip} ${currentMember.address.city}`" />
          </div>

          <Divider />

          <!-- License actions (admin) -->
          <template v-if="isAdmin">
            <div class="panel-title">
              <i class="pi pi-file" />
              <span>Licence</span>
            </div>

            <!-- Toggle licence payée -->
            <div class="license-toggle">
              <ToggleSwitch
                :modelValue="currentMember.licensePaid"
                :disabled="togglingLicense"
                @update:modelValue="toggleLicensePaid"
              />
              <span class="text-sm">{{ currentMember.licensePaid ? 'Licence payée' : 'Licence non payée' }}</span>
            </div>

            <div class="license-actions mt-2">
              <Button
                :icon="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-upload'"
                label="Importer"
                severity="info"
                size="small"
                outlined
                :disabled="uploading"
                @click="triggerLicenseUpload"
              />
              <Button
                v-if="currentMember.licenseFileName"
                icon="pi pi-download"
                label="Télécharger"
                severity="success"
                size="small"
                outlined
                @click="downloadLicense"
              />
              <Button
                v-if="currentMember.licenseFileName"
                icon="pi pi-trash"
                severity="danger"
                size="small"
                text
                @click="deleteLicense"
              />
            </div>
            <Tag v-if="currentMember.licenseFileName" value="Fichier présent" severity="success" class="mt-2" />
            <span v-else class="text-sm text-color-secondary">Aucun fichier importé</span>
          </template>

          <Divider />

          <div class="info-grid">
            <InfoRow icon="pi-clock" label="Créé le" :value="new Date(currentMember.createdAt).toLocaleDateString('fr-FR')" />
            <InfoRow icon="pi-refresh" label="Modifié le" :value="new Date(currentMember.updatedAt).toLocaleDateString('fr-FR')" />
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden file input -->
    <input ref="licenseFileInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleLicenseFile" />
  </Dialog>
</template>

<script setup lang="ts">
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import { MemberGenderLabels } from '~/types/enum/MemberGender';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import MemberForm from '~/components/forms/member-form.vue';
import { MemberRepository } from '~/repository/member-repository';

const props = defineProps<{
  visible?: boolean;
  member: Member;
  teams?: Team[];
}>();

const emit = defineEmits<{
  'update:visible': [value: boolean];
  'update:member': [member: Member];
  'saved': [member: Member];
}>();

const { isAdmin } = useUserRole();
const memberRepository = new MemberRepository();
const toast = usePVToastService();

const currentMember = ref<Member>({ ...props.member });
watch(() => props.member, m => { currentMember.value = { ...m }; }, { deep: true });

const saving = ref(false);
const uploading = ref(false);
const togglingLicense = ref(false);
const licenseFileInput = ref<HTMLInputElement | null>(null);

const formatDate = (dateStr: string) =>
    new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });

// ── Profile picture ───────────────────────────────────

const onUpload = async (file: File) => {
  const updated = await memberRepository.uploadProfilePicture(currentMember.value.id, file);
  currentMember.value = updated;
  emit('update:member', updated);
};

const onDelete = async () => {
  const updated = await memberRepository.deleteProfilePicture(currentMember.value.id);
  currentMember.value = updated;
  emit('update:member', updated);
};

// ── Save form ─────────────────────────────────────────

const onSave = async (values: Record<string, any>) => {
  saving.value = true;
  try {
    const updated = await memberRepository.createUpdate({ ...values, id: currentMember.value.id });
    currentMember.value = updated;
    emit('update:member', updated);
    emit('saved', updated);
    toast.add({ severity: 'success', summary: 'Membre mis à jour', life: 2500 });
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour le membre', life: 4000 });
  } finally {
    saving.value = false;
  }
};

// ── Toggle licence payée ──────────────────────────────

const toggleLicensePaid = async () => {
  togglingLicense.value = true;
  try {
    const updated = await memberRepository.toggleLicense(currentMember.value.id);
    currentMember.value = updated;
    emit('update:member', updated);
    emit('saved', updated);
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier le statut', life: 3000 });
  } finally {
    togglingLicense.value = false;
  }
};

// ── License file ──────────────────────────────────────

const triggerLicenseUpload = () => licenseFileInput.value?.click();

const handleLicenseFile = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploading.value = true;
  try {
    const updated = await memberRepository.uploadLicense(currentMember.value.id, file);
    currentMember.value = updated;
    emit('update:member', updated);
    emit('saved', updated);
  } finally {
    uploading.value = false;
    (e.target as HTMLInputElement).value = '';
  }
};

const downloadLicense = async () => {
  const config = useRuntimeConfig();
  const url = `${config.public.apiBase}/member/${currentMember.value.id}/download-license`;
  const token = useCookie('auth_token').value;
  const res = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
  const blob = await res.blob();
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = currentMember.value.licenseFileName || 'licence';
  a.click();
  URL.revokeObjectURL(a.href);
};

const deleteLicense = async () => {
  const updated = await memberRepository.deleteLicense(currentMember.value.id);
  currentMember.value = updated;
  emit('update:member', updated);
  emit('saved', updated);
};
</script>

<!-- InfoRow sub-component inline -->
<script lang="ts">
import { defineComponent, h } from 'vue';
const InfoRow = defineComponent({
  props: { icon: String, label: String, value: String },
  setup(p) {
    return () => h('div', { class: 'info-row' }, [
      h('span', { class: 'info-label' }, [
        h('i', { class: `pi ${p.icon} info-icon` }),
        p.label,
      ]),
      h('span', { class: 'info-value' }, p.value || '—'),
    ]);
  },
});
export { InfoRow };
</script>

<style scoped>
.member-modal {
  display: flex;
  flex-direction: column;
  border-radius: inherit;
  overflow: hidden;
}

/* Header */
.member-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 1.5rem 1.75rem;
  background: linear-gradient(135deg, var(--p-primary-color) 0%, color-mix(in srgb, var(--p-primary-color) 70%, #000) 100%);
  color: white;
  gap: 1rem;
  flex-shrink: 0;
}

.member-header__identity {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  min-width: 0;
}

.member-name {
  font-size: 1.375rem;
  font-weight: 700;
  margin: 0 0 0.2rem;
  color: white;
  word-break: break-word;
}

.member-team {
  font-size: 0.875rem;
  opacity: 0.8;
  display: block;
  margin-bottom: 0.5rem;
}

.member-badges {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.member-close {
  color: rgba(255,255,255,0.8) !important;
  flex-shrink: 0;
}

/* Body */
.member-body {
  display: grid;
  grid-template-columns: 1fr 320px;
  min-height: 0;
  max-height: 75vh;
  overflow: hidden;
}

.member-panel {
  padding: 1.5rem;
  overflow-y: auto;
}

.member-panel--form {
  border-right: 1px solid var(--p-surface-border);
}

.member-panel--info {
  background: var(--p-surface-ground);
}

/* Panel title */
.panel-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--p-text-muted-color);
  margin-bottom: 1rem;
}

/* Info grid */
.info-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* License */
.license-toggle {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.license-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Tablet */
@media (max-width: 860px) {
  .member-body {
    grid-template-columns: 1fr 260px;
  }
}

/* Mobile */
@media (max-width: 640px) {
  .member-modal {
    overflow-y: auto;
    max-height: 95dvh;
  }

  .member-body {
    display: flex;
    flex-direction: column;
    max-height: none;
    overflow: visible;
  }

  .member-panel {
    overflow-y: visible;
  }

  .member-panel--form {
    border-right: none;
    border-bottom: 1px solid var(--p-surface-border);
  }

  .member-panel--info {
    background: var(--p-surface-card);
  }

  .member-header {
    padding: 1.25rem;
  }

  .member-header__identity {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }

  .member-name {
    font-size: 1.125rem;
  }
}
</style>

<style>
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.4rem 0;
  border-bottom: 1px solid var(--p-surface-border);
  font-size: 0.875rem;
}

.info-row:last-child { border-bottom: none; }

.info-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--p-text-muted-color);
  font-weight: 500;
  flex-shrink: 0;
}

.info-icon { font-size: 0.75rem; }

.info-value {
  color: var(--p-text-color);
  text-align: right;
  word-break: break-word;
}
</style>
