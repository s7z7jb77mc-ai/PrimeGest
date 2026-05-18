# Landing Page — Menu Hamburger Mobile — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un menu hamburger dans le header de `Home.vue` pour la navigation mobile (≤1100px), remplaçant la nav qui disparaît sans alternative.

**Architecture:** Modification unique de `resources/js/Pages/Home.vue` — ajout d'un `ref<boolean>`, d'un bouton hamburger, d'un panneau de navigation mobile, et des styles CSS associés.

**Tech Stack:** Vue 3 Composition API (`ref`), Tailwind CSS, CSS custom (scoped)

---

## Fichiers modifiés

- Modify: `resources/js/Pages/Home.vue`

---

### Task 1 : Ajouter le state et les méthodes dans le script setup

**Files:**
- Modify: `resources/js/Pages/Home.vue`

- [ ] **Step 1 : Ajouter `menuOuvert` dans le script setup**

Dans `<script setup lang="ts">`, après la ligne `const year = new Date().getFullYear()`, ajouter :

```typescript
const menuOuvert = ref(false)

function fermerMenu(): void {
    menuOuvert.value = false
}
```

`ref` est déjà importé depuis `'vue'` au début du fichier — aucun import à ajouter.

- [ ] **Step 2 : Commit intermédiaire**

```bash
git add resources/js/Pages/Home.vue
git commit -m "feat(landing): ajouter state menuOuvert pour le hamburger mobile"
```

---

### Task 2 : Ajouter le bouton hamburger dans le header

**Files:**
- Modify: `resources/js/Pages/Home.vue`

- [ ] **Step 1 : Localiser `.pg-header__actions` dans le template**

Chercher le bloc dans le template :
```html
<div class="pg-header__actions">
```
Ce bloc contient les boutons de langue, thème et CTA.

- [ ] **Step 2 : Ajouter le bouton hamburger en premier enfant**

Insérer **en premier** dans `.pg-header__actions` :

```html
<button
    class="pg-hamburger"
    :aria-expanded="menuOuvert"
    aria-label="Menu"
    @click="menuOuvert = !menuOuvert"
>
    <span v-if="!menuOuvert">☰</span>
    <span v-else>✕</span>
</button>
```

- [ ] **Step 3 : Commit**

```bash
git add resources/js/Pages/Home.vue
git commit -m "feat(landing): ajouter bouton hamburger dans le header"
```

---

### Task 3 : Ajouter le panneau de navigation mobile

**Files:**
- Modify: `resources/js/Pages/Home.vue`

- [ ] **Step 1 : Localiser la balise `</header>` fermante dans le template**

La structure du template est :
```html
<div class="pg-home">
    <header class="pg-header">
        ...
    </header>       ← insérer juste après cette ligne
    <main ...>
```

- [ ] **Step 2 : Insérer le panneau mobile après `</header>`**

```html
<div v-if="menuOuvert" class="pg-mobile-menu" @click.self="fermerMenu">
    <nav class="pg-mobile-menu__nav">
        <a href="#features" @click="fermerMenu">{{ copy.nav.features }}</a>
        <a href="#workflow" @click="fermerMenu">{{ copy.nav.product }}</a>
        <a href="#pricing" @click="fermerMenu">{{ copy.nav.pricing }}</a>
        <hr class="pg-mobile-menu__divider" />
        <a href="/login" @click="fermerMenu">{{ copy.nav.login }}</a>
        <a :href="registerUrl" @click="fermerMenu" class="pg-mobile-menu__cta">
            {{ copy.nav.register }}
        </a>
    </nav>
</div>
```

- [ ] **Step 3 : Commit**

```bash
git add resources/js/Pages/Home.vue
git commit -m "feat(landing): ajouter panneau de navigation mobile"
```

---

### Task 4 : Ajouter les styles CSS

**Files:**
- Modify: `resources/js/Pages/Home.vue`

- [ ] **Step 1 : Ajouter les styles dans `<style scoped>`**

À la fin du bloc `<style scoped>`, avant la balise fermante `</style>`, ajouter :

```css
/* ── Menu hamburger ── */
.pg-hamburger {
    display: none;
    background: none;
    border: none;
    font-size: 1.4rem;
    cursor: pointer;
    color: inherit;
    padding: 0.25rem 0.5rem;
    line-height: 1;
}

@media (max-width: 1100px) {
    .pg-hamburger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}

/* ── Panneau mobile ── */
.pg-mobile-menu {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: rgba(0, 0, 0, 0.15);
    transition: opacity 0.15s;
}

.pg-mobile-menu__nav {
    position: absolute;
    top: 4.5rem;
    left: 0.5rem;
    right: 0.5rem;
    background: #fff;
    border-radius: 1rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.pg-mobile-menu__nav a {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.95rem;
    color: inherit;
    text-decoration: none;
    transition: background 0.1s;
}

.pg-mobile-menu__nav a:hover {
    background: #f5f0e8;
}

.pg-mobile-menu__divider {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 0.25rem 0;
}

.pg-mobile-menu__cta {
    background: #1A56A0;
    color: #fff !important;
    text-align: center;
    font-weight: 600;
}

.pg-mobile-menu__cta:hover {
    background: #0B2D5E !important;
}
```

- [ ] **Step 2 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir http://localhost dans Chrome DevTools en mode mobile (iPhone SE ou similaire, 375px).
Vérifier :
- Bouton hamburger `☰` visible dans le header ✓
- Clic → panneau s'ouvre avec les liens : Modules, Produit, Tarifs, Connexion, Essai gratuit ✓
- Icône change en `✕` quand ouvert ✓
- Clic sur un lien → panneau se ferme et navigue vers la section ✓
- Clic sur le fond semi-transparent → panneau se ferme ✓
- En desktop (>1100px) → bouton hamburger invisible ✓

- [ ] **Step 3 : Commit final**

```bash
git add resources/js/Pages/Home.vue
git commit -m "feat(landing): menu hamburger mobile — styles CSS"
```
