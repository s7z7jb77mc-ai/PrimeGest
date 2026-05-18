# Landing Page — Menu Hamburger Mobile

> **For agentic workers:** Implement via `superpowers:subagent-driven-development`.

**Goal:** Ajouter un menu hamburger dans le header de `Home.vue` pour remplacer la nav qui disparaît à ≤1100px sur mobile.

**Architecture:** Modification unique de `resources/js/Pages/Home.vue`. Aucun changement backend.

---

## Fichier modifié

`resources/js/Pages/Home.vue`

---

## Changements script setup

Ajouter après les imports existants :

```typescript
const menuOuvert = ref(false)

function fermerMenu(): void {
    menuOuvert.value = false
}
```

---

## Changements template

### 1. Bouton hamburger dans `.pg-header__actions`

Ajouter **avant** les boutons CTA existants :

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

### 2. Panneau mobile sous le header

Ajouter **immédiatement après** la balise `</header>` fermante :

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

---

## Changements CSS

### Bouton hamburger — visible uniquement sur mobile

```css
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
```

### Panneau mobile

```css
.pg-mobile-menu {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: rgba(0, 0, 0, 0.15);
    opacity: 1;
    transition: opacity 0.15s;
}

.pg-mobile-menu__nav {
    position: absolute;
    top: 4.5rem; /* hauteur du header mobile */
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

---

## Ce qui NE change PAS

- La nav desktop `.pg-header__nav` (inchangée, toujours cachée à ≤1100px)
- Les boutons `.pg-header__actions` existants (lang toggle, theme toggle, CTA)
- Toute la logique JS existante (thème, langue, contenu bilingue)
- Les sections hero, features, pricing, footer
