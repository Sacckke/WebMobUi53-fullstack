<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';
import PollResultsChart from './components/PollResultsChart.vue';

const props = defineProps({
  token: { type: String, required: true },
  authenticated: { type: Boolean, default: false },
  loginUrl: { type: String, default: '/auth/login' },
});

const { fetchApi } = useFetchApi();

const poll = ref(null);
const results = ref(null);
const chargement = ref(true);
const erreur = ref(null);

const optionsSelectionnees = ref([]);
const voteEnCours = ref(false);
const messageVote = ref(null);
const dejaVote = ref(false);

let timerResultats = null;

const estTermine = computed(() => {
  if (!poll.value?.ends_at) return false;
  return new Date(poll.value.ends_at) < new Date();
});

const peutVoter = computed(() => {
  if (!props.authenticated) return false;
  if (!poll.value || poll.value.is_draft || estTermine.value) return false;
  if (dejaVote.value && !poll.value.allow_vote_change) return false;
  return true;
});

const resultatsVisibles = computed(() => {
  if (!results.value) return false;
  if (poll.value?.results_public) return true;
  if (poll.value?.is_owner) return true;
  if (props.authenticated && dejaVote.value) return true;
  return false;
});

async function chargerSondage() {
  try {
    const data = await fetchApi({ url: `/polls/${props.token}` });
    poll.value = data;
    dejaVote.value = (data.user_voted_option_ids?.length ?? 0) > 0;
    if (dejaVote.value) optionsSelectionnees.value = data.user_voted_option_ids;
  } catch (e) {
    erreur.value = e.status === 404 ? 'Sondage introuvable.' : 'Erreur de chargement.';
  } finally {
    chargement.value = false;
  }
}

async function chargerResultats() {
  try {
    const data = await fetchApi({ url: `/polls/${props.token}/results` });
    results.value = data;
  } catch {
    // silencieux si pas le droit
  }
}

function toggleOption(id) {
  if (poll.value?.allow_multiple_choices) {
    const idx = optionsSelectionnees.value.indexOf(id);
    if (idx === -1) optionsSelectionnees.value.push(id);
    else optionsSelectionnees.value.splice(idx, 1);
  } else {
    optionsSelectionnees.value = [id];
  }
}

async function voter() {
  if (optionsSelectionnees.value.length === 0) {
    messageVote.value = { type: 'error', text: 'Sélectionnez au moins une option.' };
    return;
  }
  voteEnCours.value = true;
  messageVote.value = null;
  try {
    await fetchApi({
      url: `/polls/${props.token}/vote`,
      method: 'POST',
      data: { option_ids: optionsSelectionnees.value },
    });
    dejaVote.value = true;
    messageVote.value = { type: 'success', text: 'Vote enregistré ! Merci 🎉' };
    await chargerResultats();
  } catch (e) {
    messageVote.value = { type: 'error', text: e?.data?.message ?? 'Erreur lors du vote.' };
  } finally {
    voteEnCours.value = false;
  }
}

function formatDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

onMounted(async () => {
  await chargerSondage();
  if (poll.value && !poll.value.is_draft) {
    await chargerResultats();
    timerResultats = setInterval(chargerResultats, 5000);
  }
});

onUnmounted(() => {
  if (timerResultats) clearInterval(timerResultats);
});
</script>

<template>
  <div class="space-y-4">

    <!-- Chargement -->
    <p v-if="chargement" class="text-center py-12 text-slate-400 animate-pulse">
      Chargement du sondage...
    </p>

    <!-- Erreur -->
    <div v-else-if="erreur" class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
      <p class="text-4xl mb-3">😕</p>
      <p class="text-red-700 font-semibold">{{ erreur }}</p>
    </div>

    <template v-else-if="poll">

      <!-- Brouillon -->
      <div v-if="poll.is_draft" class="bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
        <p class="text-4xl mb-3">🔧</p>
        <p class="text-yellow-700 font-semibold">Ce sondage n'est pas encore disponible.</p>
      </div>

      <template v-else>
        <!-- Infos du sondage -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <h1 class="text-xl font-bold text-slate-800 mb-1">{{ poll.question }}</h1>
          <p v-if="poll.title" class="text-slate-500 text-sm">{{ poll.title }}</p>
          <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-xs text-slate-400">
            <span v-if="poll.allow_multiple_choices">• Plusieurs choix possibles</span>
            <span v-if="poll.ends_at">• Fin : {{ formatDate(poll.ends_at) }}</span>
          </div>
          <div v-if="estTermine" class="mt-3 flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-sm text-red-700 font-medium">
            🔒 Ce sondage est terminé — le vote n'est plus possible.
          </div>
        </div>

        <!-- Formulaire de vote -->
        <div v-if="peutVoter" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <h2 class="font-semibold text-slate-700 mb-4">
            {{ dejaVote && poll.allow_vote_change ? '🔄 Modifier votre vote' : '🗳️ Voter' }}
          </h2>
          <div class="space-y-2 mb-4">
            <div
              v-for="option in poll.options"
              :key="option.id"
              @click="toggleOption(option.id)"
              :class="[
                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all',
                optionsSelectionnees.includes(option.id)
                  ? 'border-teal-400 bg-teal-50'
                  : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
              ]"
            >
              <span :class="[
                'w-4 h-4 border-2 flex-shrink-0 flex items-center justify-center transition-colors',
                poll.allow_multiple_choices ? 'rounded' : 'rounded-full',
                optionsSelectionnees.includes(option.id) ? 'bg-teal-500 border-teal-500' : 'border-slate-300',
              ]">
                <svg v-if="optionsSelectionnees.includes(option.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span class="text-sm text-slate-700 select-none">{{ option.label }}</span>
            </div>
          </div>
          <p v-if="messageVote" :class="['text-sm mb-3 font-medium', messageVote.type === 'error' ? 'text-red-600' : 'text-green-600']">
            {{ messageVote.text }}
          </p>
          <button
            @click="voter"
            :disabled="voteEnCours || optionsSelectionnees.length === 0"
            class="w-full bg-teal-600 text-white py-2.5 rounded-lg font-semibold hover:bg-teal-700 disabled:opacity-50 transition-colors"
          >
            {{ voteEnCours ? 'Envoi en cours...' : 'Valider mon vote' }}
          </button>
        </div>

        <!-- Pas connecté -->
        <div v-else-if="!authenticated && !estTermine" class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
          <p class="text-blue-800 font-medium mb-3">Connectez-vous pour voter à ce sondage.</p>
          <a :href="loginUrl" class="inline-block bg-teal-600 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-teal-700 transition-colors">
            Se connecter
          </a>
        </div>

        <!-- Déjà voté sans modif possible -->
        <div v-else-if="dejaVote && !poll.allow_vote_change && !estTermine" class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-700 font-medium">
          ✅ Vous avez déjà voté à ce sondage.
        </div>

        <!-- Résultats -->
        <PollResultsChart v-if="resultatsVisibles" :results="results" />

        <!-- Résultats privés -->
        <div v-else-if="authenticated && !poll.results_public && !poll.is_owner && !resultatsVisibles" class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center text-slate-500 text-sm">
          🔒 Les résultats sont privés.
        </div>

      </template>
    </template>

  </div>
</template>
