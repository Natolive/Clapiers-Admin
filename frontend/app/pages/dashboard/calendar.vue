<template>
  <div class="calendar-container">
    <div class="flex justify-content-between align-items-center mb-3 gap-3">
      <div class="flex gap-2">
        <Button icon="pi pi-chevron-left" @click="handlePrev" text rounded />
        <Button icon="pi pi-chevron-right" @click="handleNext" text rounded />
      </div>
      <div class="flex-1 text-center">
        <h2 class="text-2xl md:text-3xl font-semibold m-0 capitalize">{{ currentTitle }}</h2>
      </div>
      <div class="toolbar-right flex gap-2">
        <Button label="Aujourd'hui" @click="handleToday" />
      </div>
    </div>
    <FullCalendar ref="calendarRef" :options="calendarOptions" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import type { CalendarOptions } from '@fullcalendar/core'
import frLocale from '@fullcalendar/core/locales/fr'

definePageMeta({
   middleware: 'auth-middleware',
  layout: 'dashboard'
});

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null)
const currentTitle = ref('')

const calendarOptions: CalendarOptions = {
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: frLocale,
  headerToolbar: false,
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  events: [],
  height: '100%',
  datesSet: (dateInfo) => {
    currentTitle.value = dateInfo.view.title
  }
}

const getCalendarApi = () => {
  return calendarRef.value?.getApi()
}

const handlePrev = () => {
  getCalendarApi()?.prev()
}

const handleNext = () => {
  getCalendarApi()?.next()
}

const handleToday = () => {
  getCalendarApi()?.today()
}

onMounted(() => {
  const api = getCalendarApi()
  if (api) {
    currentTitle.value = api.view.title
  }
})
</script>

<style scoped>
.calendar-container {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 1rem;
}

@media (max-width: 768px) {
  .calendar-container {
    padding: 0;
  }

  .calendar-container > div:first-child {
    padding: 1rem;
    margin-bottom: 0 !important;
  }
}
</style>
