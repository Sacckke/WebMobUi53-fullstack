<script setup>
import { ref, computed, watch } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  // null = mode création, objet = mode édition
  poll: { type: Object, default: null },
});

const emit = defineEmits(['saved']);

const { createPoll, savePoll } = usePollStore();

const modeEdition = computed(() => props.poll !== null);

// --- État du formulaire ---
const question = ref('');
const titre = ref('');
const options = ref(['', '']); // min 2
const allowMultiple = ref(false);
const allowVoteChange = ref(false);
const resultsPublic = ref(true);
const durationMinutes = ref(''); // vide = pas de durée
const lancerMaintenant = ref(false); // uniquement à la création

const chargement = ref(false);
const erreurs = ref({});

// Pré-remplir en mode édition
watch(
  () => props.poll,
  (poll) => {
    if (!poll) return;
    question.value = poll.question ?? '';
    titre.value = poll.title ?? '';
    options.value = poll.options?.map((o) => o.label) ?? ['', ''];
    allowMultiple.value = poll.allow_multiple_choices ?? false;
    allowVoteChange.value = poll.allow_vote_change ?? false;
    resultsPublic.value = poll.results_public ?? true;
    // durée stockée en secondes dans la DB, on affiche en minutes
    durationMinutes.value = poll.duration ? Math.round(poll.duration / 60) : '';
  },
  { immediate: true }
);

// --- Gestion des options ---
function ajouterOption() {
  options.value.push('');
}

function supprimerOption(idx) {
  if (options.value.length <= 2) return;
  options.value.splice(idx, 1);
}

// --- Validation basique côté frontend ---
function valider() {
  erreurs.value = {};
  if (!question.value.trim()) {
    erreurs.value.question = 'La question est obligatoire.';
  }
  const optionsFilled = options.value.filter((o) => o.trim());
  if (optionsFilled.length < 2) {
    erreurs.value.options = 'Il faut au moins 2 options remplies.';
  }
  if (options.value.some((o) => !o.trim())) {
    erreurs.value.options = 'Toutes les options doivent être remplies.';
  }
  return Object.keys(erreurs.value).length === 0;
}

async function soumettre() {
  if (!valider()) return;

  const payload = {
    question: question.value.trim(),
    title: titre.value.trim() || null,
    options: options.value.map((o) => o.trim()).filter(Boolean),
    allow_multiple_choices: allowMultiple.value,
    allow_vote_change: allowVoteChange.value,
    results_public: resultsPublic.value,
    duration: durationMinutes.value ? parseInt(durationMinutes.value) * 60 : null,
  };

  chargement.value = true;
  erreurs.value = {};

  try {
    if (modeEdition.value) {
      await savePoll(props.poll.id, payload);
    } else {
      // Le backend accepte 'launch: true' pour lancer direct
      if (lancerMaintenant.value) payload.launch = true;
      await createPoll(payload);
    }
    emit('saved');
  } catch (e) {
    // Erreurs Laravel 422 avec détail par champ
    if (e?.data?.errors) {
      const laravelErrors = {};
      for (const [key, msgs] of Object.entries(e.data.errors)) {
        laravelErrors[key] = Array.isArray(msgs) ? msgs[0] : msgs;
      }
      erreurs.value = laravelErrors;
    } else {
      erreurs.value.global = e?.data?.message ?? 'Une erreur est survenue.';
    }
  } finally {
    chargement.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="soumettre" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
    <h2 class="text-lg font-bold text-slate-800 mb-5">
      {{ modeEdition ? '✏️ Modifier le sondage' : '✨ Nouveau sondage' }}
    </h2>

    <!-- Erreur globale -->
    <div
      v-if="erreurs.global"
      class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-4"
    >
      {{ erreurs.global }}
    </div>

    <!-- Question -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-slate-700 mb-1">
        Question <span class="text-red-500">*</span>
      </label>
      <input
        v-model="question"
        type="text"
        placeholder="Ex : Quelle est votre couleur préférée ?"
        :class="[
          'w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500',
          erreurs.question ? 'border-red-400' : 'border-slate-200',
        ]"
      />
      <p v-if="erreurs.question" class="text-xs text-red-500 mt-1">{{ erreurs.question }}</p>
    </div>

    <!-- Titre optionnel -->
    <div class="mb-5">
      <label class="block text-sm font-medium text-slate-700 mb-1">
        Titre <span class="text-slate-400 font-normal">(optionnel)</span>
      </label>
      <input
        v-model="titre"
        type="text"
        placeholder="Un titre court pour identifier ce sondage"
        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      />
    </div>

    <!-- Options de réponse -->
    <div class="mb-5">
      <label class="block text-sm font-medium text-slate-700 mb-2">
        Options de réponse <span class="text-red-500">*</span>
      </label>
      <p v-if="erreurs.options" class="text-xs text-red-500 mb-2">{{ erreurs.options }}</p>

      <div v-for="(_, idx) in options" :key="idx" class="flex gap-2 mb-2">
        <input
          v-model="options[idx]"
          type="text"
          :placeholder="`Option ${idx + 1}`"
          class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <button
          type="button"
          @click="supprimerOption(idx)"
          :disabled="options.length <= 2"
          class="text-slate-400 hover:text-red-500 disabled:opacity-30 px-2 transition-colors"
          title="Supprimer cette option"
        >
          ✕
        </button>
      </div>

      <button
        type="button"
        @click="ajouterOption"
        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
      >
        + Ajouter une option
      </button>
    </div>

    <!-- Paramètres -->
    <div class="mb-5 border-t border-slate-100 pt-4">
      <p class="text-sm font-medium text-slate-700 mb-3">Paramètres</p>
      <div class="space-y-3">
        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="allowMultiple" type="checkbox" class="rounded text-indigo-600" />
          <span class="text-sm text-slate-700">Autoriser plusieurs choix</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="resultsPublic" type="checkbox" class="rounded text-indigo-600" />
          <span class="text-sm text-slate-700">Résultats publics <span class="text-slate-400">(visibles sans connexion)</span></span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="allowVoteChange" type="checkbox" class="rounded text-indigo-600" />
          <span class="text-sm text-slate-700">Autoriser la modification du vote</span>
        </label>

        <!-- Durée en minutes -->
        <div class="flex items-center gap-3">
          <input
            v-model="durationMinutes"
            type="number"
            min="1"
            placeholder="—"
            class="w-20 border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <span class="text-sm text-slate-600">
            minutes de disponibilité <span class="text-slate-400">(optionnel)</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Lancer maintenant (uniquement à la création) -->
    <div v-if="!modeEdition" class="mb-5 bg-indigo-50 border border-indigo-100 rounded-lg p-3">
      <label class="flex items-center gap-3 cursor-pointer">
        <input v-model="lancerMaintenant" type="checkbox" class="rounded text-indigo-600" />
        <span class="text-sm text-slate-700 font-medium">
          🚀 Lancer immédiatement
          <span class="font-normal text-slate-400"> — sinon sauvegardé en brouillon</span>
        </span>
      </label>
    </div>

    <!-- Bouton submit -->
    <button
      type="submit"
      :disabled="chargement"
      class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
    >
      <span v-if="chargement">En cours...</span>
      <span v-else-if="modeEdition">Sauvegarder les modifications</span>
      <span v-else-if="lancerMaintenant">Créer et lancer</span>
      <span v-else>Créer en brouillon</span>
    </button>
  </form>
</template>
