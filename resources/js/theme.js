const THEME_KEY = 'primegest_theme';

export function getStoredTheme() {
  try {
    const value = localStorage.getItem(THEME_KEY);
    return value === 'dark' ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

export function applyTheme(theme) {
  const root = document.documentElement;
  if (theme === 'dark') {
    root.classList.add('dark');
  } else {
    root.classList.remove('dark');
  }
}

export function setTheme(theme) {
  const value = theme === 'dark' ? 'dark' : 'light';
  try {
    localStorage.setItem(THEME_KEY, value);
    // Keep Laravel starter appearance in sync so it doesn't override our choice
    localStorage.setItem('appearance', value);
  } catch {
    // localStorage inaccessible (iOS Safari private mode, etc.)
  }
  applyTheme(value);
}

export function applyStoredTheme() {
  applyTheme(getStoredTheme());
}
