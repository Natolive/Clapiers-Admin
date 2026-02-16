<template>
  <div>
    <SkeletonLoader v-if="loading" type="card-grid" :count="6" />

    <!-- No team assigned -->
    <Card v-else-if="!userTeam" class="text-center">
      <template #content>
        <div class="flex flex-column align-items-center gap-3 py-5">
          <i class="pi pi-users text-5xl text-color-secondary"></i>
          <p class="text-xl font-semibold m-0">Aucune équipe assignée</p>
          <p class="text-color-secondary m-0">
            Contactez un Super Administrateur pour être associé à une équipe.
          </p>
        </div>
      </template>
    </Card>

    <!-- Team assigned but no members -->
    <template v-else-if="members.length === 0">
      <div class="flex align-items-center gap-2 mb-4">
        <Tag :value="userTeam.name" severity="info" class="text-lg px-3 py-1" />
      </div>
      <Card class="text-center">
        <template #content>
          <div class="flex flex-column align-items-center gap-3 py-5">
            <i class="pi pi-user-minus text-5xl text-color-secondary"></i>
            <p class="text-xl font-semibold m-0">Aucun membre dans cette équipe</p>
            <p class="text-color-secondary m-0">
              Il n'y a pas encore de licenciés associés à votre équipe.
            </p>
          </div>
        </template>
      </Card>
    </template>

    <!-- Members grid -->
    <template v-else>
      <div class="flex align-items-center gap-2 mb-4">
        <Tag :value="userTeam.name" severity="info" class="text-lg px-3 py-1" />
        <span class="text-color-secondary">{{ members.length }} membre{{ members.length > 1 ? 's' : '' }}</span>
      </div>
      <div class="grid">
        <div v-for="member in members" :key="member.id" class="col-12 md:col-6 lg:col-4">
          <Card>
            <template #content>
              <div class="flex flex-column align-items-center gap-3">
                <MemberAvatar :member="member" size="xlarge" />
                <div class="text-center">
                  <p class="text-xl font-semibold m-0">{{ member.firstName }} {{ member.lastName }}</p>
                </div>
                <div class="flex flex-column gap-2 w-full">
                  <div class="flex align-items-center gap-2">
                    <i class="pi pi-phone text-color-secondary"></i>
                    <a :href="`tel:${member.phoneNumber}`" class="text-color no-underline hover:underline">
                      {{ member.phoneNumber }}
                    </a>
                  </div>
                  <div class="flex align-items-center gap-2">
                    <i class="pi pi-envelope text-color-secondary"></i>
                    <a :href="`mailto:${member.email}`" class="text-color no-underline hover:underline text-overflow-ellipsis overflow-hidden white-space-nowrap">
                      {{ member.email }}
                    </a>
                  </div>
                  <div class="flex align-items-center justify-content-between mt-2 pt-2 border-top-1 surface-border">
                    <div class="flex align-items-center gap-2">
                      <i class="pi pi-id-card text-color-secondary"></i>
                      <span class="text-sm">Licence</span>
                    </div>
                    <div class="flex align-items-center gap-2">
                      <Tag
                        :value="member.licensePaid ? 'Payée' : 'Non payée'"
                        :severity="member.licensePaid ? 'success' : 'warn'"
                        class="text-xs"
                      />
                      <Button
                        v-if="member.licenseFileName"
                        icon="pi pi-download"
                        label="Fichier"
                        severity="info"
                        text
                        size="small"
                        class="text-xs p-0 pl-1"
                        @click="downloadLicense(member)"
                      />
                      <Tag
                        v-else
                        value="Aucune licence"
                        severity="danger"
                        class="text-xs"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { TeamRepository } from '~/repository/team-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

useHead({ title: 'Mon équipe' });

const { isAdmin } = useUserRole();
const authStore = useAuthStore();

if (!isAdmin.value) {
  await navigateTo('/dashboard');
}

const members = ref<Member[]>([]);
const loading = ref(true);

const userTeam = computed<Team | null>(() => authStore.user?.team ?? null);

const downloadLicense = async (member: Member) => {
  const config = useRuntimeConfig();
  const url = `${config.public.apiBase}/team/my-team/license/${member.id}`;
  const token = useCookie('auth_token').value;

  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const blob = await res.blob();
  const blobUrl = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = blobUrl;
  a.download = member.licenseFileName || 'licence';
  a.click();
  URL.revokeObjectURL(blobUrl);
};

onMounted(async () => {
  try {
    if (userTeam.value) {
      const teamRepository = new TeamRepository();
      members.value = await teamRepository.getMyTeam();
    }
  } finally {
    loading.value = false;
  }
});
</script>
