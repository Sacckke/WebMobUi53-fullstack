<script setup>
import { ref, computed } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  poll: { type: Object, required: true },
});

const emit = defineEmits(['edit']);

const { savePoll, deletePoll } = usePollStore();

const chargement = ref(false);
const erreur = ref(null);
const copie = ref(false);

const lienPartage = computed(
  () => `${window.location.origin}/polls/vote/${props.poll.secret_token}`
);

// Statut du sondage : brouillon, en cours, terminé
const statut = computed(() => {
  if (props.poll.is_draft) return { label: 'Brouillon', cls: 'bg-slate-100 text-slate-600' };
  if (props.poll.ends_at && new Date(props.poll.ends_at) < new Date())
    return { label: 'Terminé', cls: 'bg-red-100 text-red-600' };
  return { label: 'En cours', cls: 'bg-green-100 text-green-700' };
});

function formatDate(d) {
  if (!d) return null;
  return new Date(d).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function copierLien() {
  navigator.clipboard.writeText(lienPartage.value);
  copie.value = true;
  setTimeout(() => (copie.value = false), 2000);
}

async function lancer() {
  if (!confirm('Lancer ce sondage ? Il ne sera plus modifiable.')) return;
  chargement.value = true;
  erreur.value = null;
  try {
    await savePoll(props.poll.id, { is_draft: false });
  } catch {
    erreur.value = 'Impossible de lancer le sondage.';
  } finally {
    chargement.value = false;
  }
}

async function supprimer() {
  if (!confirm('Supprimer ce sondage définitivement ?')) return;
  chargement.value = true;
  erreur.value = null;
  try {
    await deletePoll(props.poll.id);
  } catch {
    erreur.value = 'Impossible de supprimer.';
    chargement.value = false;
  }
}
</script>

<template>
  <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
    <!-- En-tête -->
    <div class="mb-3">
      <div class="flex flex-wrap items-center gap-1.5 mb-1">
        <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statut.cls]">
          {{ statut.label }}
        </span>
        <span v-if="poll.allow_multiple_choices" class="text-xs text-slate-400">• Choix multiple</span>
        <span v-if="poll.results_public" class="text-xs text-slate-400">• Résultats publics</span>
        <span v-if="poll.allow_vote_change" class="text-xs text-slate-400">• Vote modifiable</span>
      </div>
      <p class="font-semibold text-slate-800 leading-snug">{{ poll.question }}</p>
      <p v-if="poll.title" class="text-sm text-slate-500 mt-0.5 truncate">{{ poll.title }}</p>
    </div>

    <!-- Infos secondaires -->
    <div class="text-xs text-slate-400 flex flex-wrap gap-x-3 gap-y-0.5 mb-3">
      <span>{{ poll.options?.length ?? 0 }} option(s)</span>
      <span v-if="poll.started_at">Lancé le {{ formatDate(poll.started_at) }}</span>
      <span v-if="poll.ends_at">Fin : {{ formatDate(poll.ends_at) }}</span>
    </div>

    <!-- Message erreur -->
    <p v-if="erreur" class="text-xs text-red-600 mb-2">{{ erreur }}</p>

    <!-- Actions -->
    <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
      <!-- Brouillon : éditer + lancer -->
      <template v-if="poll.is_draft">
        <button
          @click="$emit('edit', poll)"
          class="text-sm bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors"
        >
          ✏️ Éditer
        </button>
        <button
          @click="lancer"
          :disabled="chargement"
          class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          🚀 Lancer
        </button>
      </template>

      <!-- Lancé : copier le lien + voir -->
      <template v-else>
        <button
          @click="copierLien"
          class="text-sm bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors"
        >
          {{ copie ? '✅ Copié !' : '🔗 Copier le lien' }}
        </button>
        <a
          :href="`/polls/vote/${poll.secret_token}`"
          target="_blank"
          class="text-sm bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors"
        >
          👁️ Voir
        </a>
      </template>

      <!-- Supprimer (toujours présent) -->
      <button
        @click="supprimer"
        :disabled="chargement"
        class="text-sm bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors ml-auto"
      >
        🗑️ Supprimer
      </button>
    </div>
  </div>
</template>
