<script setup>
import { computed } from 'vue';

const props = defineProps({
  results: { type: Object, required: true },
});

// Trié du plus voté au moins voté
const optionsTries = computed(() =>
  [...(props.results.options ?? [])].sort((a, b) => b.votes_count - a.votes_count)
);

// Couleurs cycliques pour les barres
const couleurs = [
  { bar: 'bg-indigo-500', light: 'bg-indigo-100', text: 'text-indigo-700' },
  { bar: 'bg-violet-500', light: 'bg-violet-100', text: 'text-violet-700' },
  { bar: 'bg-sky-500', light: 'bg-sky-100', text: 'text-sky-700' },
  { bar: 'bg-emerald-500', light: 'bg-emerald-100', text: 'text-emerald-700' },
  { bar: 'bg-amber-500', light: 'bg-amber-100', text: 'text-amber-700' },
  { bar: 'bg-rose-500', light: 'bg-rose-100', text: 'text-rose-700' },
];
</script>

<template>
  <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
    <!-- En-tête -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-bold text-slate-800">📈 Résultats en direct</h2>
      <span class="text-sm text-slate-400 font-medium">
        {{ results.total_votes }} vote{{ results.total_votes !== 1 ? 's' : '' }}
      </span>
    </div>

    <!-- Graphique barres horizontales -->
    <div v-if="optionsTries.length > 0" class="space-y-3">
      <div v-for="(option, idx) in optionsTries" :key="option.id">
        <!-- Label + pourcentage -->
        <div class="flex items-center justify-between text-sm mb-1">
          <span class="text-slate-700 truncate flex-1 mr-2 font-medium">{{ option.label }}</span>
          <span class="text-slate-500 text-xs whitespace-nowrap">
            {{ option.votes_count }} ({{ option.percentage }}%)
          </span>
        </div>

        <!-- Barre de progression -->
        <div :class="['h-3 w-full rounded-full overflow-hidden', couleurs[idx % couleurs.length].light]">
          <div
            :class="['h-full rounded-full transition-all duration-700 ease-out', couleurs[idx % couleurs.length].bar]"
            :style="{ width: option.percentage + '%' }"
          />
        </div>
      </div>
    </div>

    <!-- Aucun vote -->
    <p v-else class="text-slate-400 text-sm text-center py-6">
      Aucun vote pour l'instant. Soyez le premier ! 🗳️
    </p>

    <!-- Timestamp de rafraîchissement -->
    <p class="text-xs text-slate-300 text-right mt-4">
      Mis à jour toutes les 5 secondes
    </p>
  </div>
</template>
