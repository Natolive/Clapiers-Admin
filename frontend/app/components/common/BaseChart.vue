<template>
  <div class="base-chart">
    <canvas ref="canvas" />
  </div>
</template>

<script setup lang="ts">
// Remplace le <Chart> de PrimeVue : il importe chart.js en asynchrone après le
// montage, et si la page est quittée entre-temps il crée le graphique sur un
// canvas disparu (erreurs console + graphique jamais détruit). Import statique :
// création, mise à jour et destruction suivent le cycle de vie du composant.
import ChartJs, { type ChartData, type ChartOptions, type ChartType } from 'chart.js/auto';

const props = defineProps<{
  type: ChartType;
  data: ChartData;
  options?: ChartOptions;
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: ChartJs | null = null;

onMounted(() => {
  chart = new ChartJs(canvas.value!, { type: props.type, data: props.data, options: props.options });
});

watch(() => [props.data, props.options], () => {
  if (!chart) return;
  chart.data = props.data;
  chart.options = props.options ?? {};
  chart.update();
});

onBeforeUnmount(() => {
  chart?.destroy();
  chart = null;
});
</script>

<style scoped>
.base-chart {
  position: relative;
  width: 100%;
  height: 100%;
}
</style>
