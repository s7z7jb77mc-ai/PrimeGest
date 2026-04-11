import { ref, onBeforeUnmount, onMounted } from 'vue';
import { getStoredLang } from '@/lang';

export function useLang() {
  const lang = ref(getStoredLang());

  function handleLangChange() {
    lang.value = getStoredLang();
  }

  onMounted(() => {
    window.addEventListener('primegest:lang', handleLangChange);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('primegest:lang', handleLangChange);
  });

  return lang;
}
