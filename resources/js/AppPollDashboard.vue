<script setup>
import { ref } from 'vue';
import PollCard from './components/PollCard.vue';
import PollForm from './components/PollForm.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  polls: { type: Array, default: () => [] },
  loginUrl: { type: String, default: null },
  username: { type: String, default: null },
});

const { polls, setPolls } = usePollStore();
setPolls(props.polls);

const vue = ref('list');
const pollAEditer = ref(null);

function ouvrirCreation() {
  pollAEditer.value = null;
  vue.value = 'form';
}

function ouvrirEdition(poll) {
  pollAEditer.value = poll;
  vue.value = 'form';
}

function fermerFormulaire() {
  vue.value = 'list';
  pollAEditer.value = null;
}
</script>

<template>
  <div>
    <!-- Titre + bouton action -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">📊 Mes sondages</h1>
      <button
        v-if="vue === 'list'"
        @click="ouvrirCreation"
        class="bg-teal-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors"
      >
        + Nouveau
      </button>
      <button
        v-else
        @click="fermerFormulaire"
        class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-200 transition-colors"
      >
        ← Retour
      </button>
    </div>

    <!-- Formulaire création / édition -->
    <PollForm
      v-if="vue === 'form'"
      :poll="pollAEditer"
      @saved="fermerFormulaire"
    />

    <!-- Liste des sondages -->
    <template v-else>
      <div v-if="polls.length === 0" class="text-center py-12 text-slate-400">
        <p class="text-4xl mb-3">📭</p>
        <p>Aucun sondage pour l'instant.</p>
        <p class="text-sm mt-1">Cliquez sur « + Nouveau » pour en créer un !</p>
      </div>
      <div v-else class="flex flex-col gap-3">
        <PollCard
          v-for="poll in polls"
          :key="poll.id"
          :poll="poll"
          @edit="ouvrirEdition"
        />
      </div>
    </template>
  </div>
</template>
