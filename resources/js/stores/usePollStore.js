import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

// Ref partagée entre tous les composants qui appellent usePollStore()
// Ça évite de passer les données en props partout
const polls = ref([]);

export function usePollStore() {
  const { fetchApi } = useFetchApi();

  function setPolls(data) {
    polls.value = data;
  }

  // Recharge la liste depuis l'API (utile après une action)
  async function fetchPolls() {
    const data = await fetchApi({ url: '/polls' });
    polls.value = data;
  }

  async function createPoll(payload) {
    const poll = await fetchApi({ url: '/polls', method: 'POST', data: payload });
    // On ajoute en début de liste pour avoir le nouveau en premier
    polls.value.unshift(poll);
    return poll;
  }

  async function savePoll(id, payload) {
    const poll = await fetchApi({ url: `/polls/${id}`, method: 'PUT', data: payload });
    const idx = polls.value.findIndex(p => p.id === id);
    if (idx !== -1) polls.value[idx] = poll;
    return poll;
  }

  async function deletePoll(id) {
    await fetchApi({ url: `/polls/${id}`, method: 'DELETE' });
    polls.value = polls.value.filter(p => p.id !== id);
  }

  return { polls, setPolls, fetchPolls, createPoll, savePoll, deletePoll };
}
