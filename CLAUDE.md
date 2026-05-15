# CLAUDE.md

Guidance for Claude sessions working in this repo. Read once, then refer back as needed.

## Project overview

Marketing website for **4viso**. Server-rendered Craft CMS 5 + Twig, styled with Tailwind, light interactivity via Alpine.js. Content is composed by editors from typed Matrix blocks (`commonHero`, `commonBody`) — most page work happens by adding a new block entry type and its matching Twig template.

## Tech stack

| Layer | Tool |
|---|---|
| CMS | Craft CMS 5.8.20 (PHP 8.2) |
| Plugins | CKEditor, ether/seo, spicyweb/craft-neo, presseddigital/linkit, verbb/field-manager |
| Templating | Twig |
| CSS | Tailwind CSS 3.4.18 (custom theme) |
| JS | Alpine.js 3.15 (CDN) |
| Bundler | Laravel Mix 6 (Webpack) |
| Local dev | DDEV |

## Build / dev commands

- `npm run dev` — one-off dev build
- `npm run watch` — watch mode; **use this during template work** so new Tailwind classes get compiled
- `npm run production` — versioned production build

Asset entry points: `resources/js/app.js` → `web/js`, `resources/scss/app.scss` → `web/css`.

**Critical:** Tailwind JIT scans `templates/**/*.{twig,html}` (per `tailwind.config.js`). Any new class added to a template requires a recompile or the styles silently won't apply.

## Directory layout

```
templates/        Twig templates (frontend)
config/project/   Craft project config YAML (sections, entry types, fields)
resources/        Frontend source (JS + SCSS that Mix compiles)
web/              Webroot; compiled assets land in web/css and web/js
modules/          Custom Craft module PHP code
.ddev/            Local Docker environment
```

## Template architecture

**Layout:** `_layouts/base.twig` provides the shell (head, header include, footer include, `{% block content %}`, `{% block head %}`).

**Block-based composition:** pages assemble content from two Matrix fields, each with a loader template that dynamically resolves block templates by handle:

- `entry.commonHero` → loaded by `templates/hero/_index.twig` → resolves to `templates/hero/{type.handle}.twig`
- `entry.commonBody` → loaded by `templates/body/_index.twig` → resolves to `templates/body/{type.handle}.twig`

**To add a new block type:**
1. Create the entry type YAML in `config/project/entryTypes/`
2. Add the matching template at `templates/body/{handle}.twig` (or `templates/hero/{handle}.twig`)
3. The loader picks it up automatically — no other wiring needed

**Page templates:**
- Channel section detail pages live at `templates/{section}/_entry.twig` (e.g. `templates/news/_entry.twig`)
- The typical pattern is: extend `_layouts/base.twig`, then include the hero and body loaders inside `{% block content %}`
- `templates/index.twig` is the **default Craft welcome page** — not the live home. Don't mistake it for the real homepage.

**Reusable components** (in `templates/components/`):

- `image.twig` — performant `<picture>` with WebP srcset.
  ```twig
  {% include 'components/image' with {
      asset: someAsset,
      alt: '...',
      sizes: '(max-width: 768px) 100vw, 48rem',
      class: 'w-full',
      lazy: false,                {# pass false for above-the-fold #}
      widths: [600, 900, 1200],   {# default [400, 800, 1200] #}
  } %}
  ```

- `button.twig` — variants `primary` (default) / `secondary` / `white`.
  ```twig
  {% include 'components/button' with {
      href: link.url ?? '#',
      text: link.text ?? 'Click',
      variant: 'primary',
      icon: 'arrow-right',        {# phosphor icon name, optional #}
      positionIcon: 'right',
  } %}
  ```

- `link.twig` — anchor wrapper.

## Naming conventions (strict)

- **Cross-block fields use the `common*` prefix.** Examples: `commonTitle`, `commonDescription`, `commonAsset`, `commonLink`, `commonCta`, `commonHero`, `commonBody`, `commonWysiwyg`, `commonSpacing`, `commonColor`, `commonId`, `commonTeammembers`, `commonFeatures`.
- **Re-use existing `common*` fields** rather than creating new ones for the same data.
- **Block entry types:**
  - `body{Name}` — full-page section blocks (e.g. `bodyNews`, `bodyAbout`, `bodyMainFeatures`)
  - `common{Name}Block` / `common{Name}` — reusable inner blocks (e.g. `commonWysiwygBlock`, `commonImageBlock`, `commonWysiwygCta`)

## Image rendering — two patterns

- **Inline content image** (article body, intrinsically-sized cards): use `components/image.twig`.
- **Background-filled container** (hero overlays, fixed-aspect cards): use a `background-image` style with a transformed URL. The `<picture>` element doesn't fill absolutely-positioned containers cleanly, so this pattern wins there.
  ```twig
  <div class="absolute inset-0 bg-cover bg-center"
       style="background-image: url('{{ image.getUrl({ width: 1200, format: 'webp', quality: 80 }) }}');"></div>
  ```
  Reference: `templates/body/bodyNews.twig:121-122`.

## CKEditor & WYSIWYG rendering

- CKEditor fields (`commonTitle`, `commonDescription`, etc.) output HTML.
  - Titles: `{{ field|striptags|raw }}` — keeps bold, strips block tags
  - Body text where strong/br are allowed: `{{ field|striptags('<strong><br><br/>')|raw }}`
  - Full content: `{{ field|raw }}`
- **Matrix wysiwyg output** (the `commonWysiwyg` field's `commonWysiwygBlock` content): wrap the container with the `.s-wysiwyg` class — gives lists `disc` bullets and adds table styling.
- Other custom `.s-*` editorial classes exist in the compiled CSS: `.s-mainfeatures`, `.s-stakeholder` (custom 4viso-logo bullets), `.s-hero-pill` (glassmorphism). Use the matching class when its section context applies.

## Animation conventions (Alpine.js)

**Scroll-triggered fade-up** — the standard pattern for body sections:

```twig
<section x-data="{ visible: false }" x-intersect.once.margin.0px.0px.-100px.0px="visible = true">
    <div
        style="opacity: 0; transform: translateY(40px);"
        :style="`opacity: ${visible ? 1 : 0}; transform: translateY(${visible ? 0 : 40}px); transition: opacity 0.5s ease-out, transform 0.5s ease-out;`">
        ...
    </div>
</section>
```

Stagger children by adjusting the transition delay (`0.5s 100ms ease-out`, `0.5s 200ms ease-out`, etc.).

**Page-load animations:** Tailwind utilities `animate-fade-up` / `animate-fade-down`, optionally with `[animation-delay:100ms]` etc.

## Tailwind theme tokens

| Token | Hex | Use |
|---|---|---|
| `bg-primary` | `#288760` | brand green |
| `bg-primary-pine` | `#1A5140` | dark green |
| `bg-primary-mint` | `#5CA87C` | mid green |
| `bg-primary-lightest-green` | `#B7E5BA` | light pill bg |
| `text-peach` | `#FAC090` | accent |
| `bg-peach-light` | `#FDEADA` | light accent bg |
| `text-dark` | `#162C26` | body text |
| `text-grey` | `#666666` | secondary text |
| `bg-card-bg` | `#FBFEFC` | card surface |
| `border-card-border` | `#DEEEE5` | card border |

- **Fonts:** `font-sans` = "Stack Sans Text"; `h1`–`h5` automatically use "Stack Sans Headline" via global styles.
- **Default border radius:** `rounded` = `10px` (overridden in `tailwind.config.js`).

## Gotchas

- **Header overlays the page top.** It's `position: absolute; z-50` — hero sections must own their top padding.
- **Anchor underlines are global.** Every anchor gets an underline by default. Add `no-underline` on buttons/CTAs.
- **`templates/index.twig` is the default Craft welcome page** — not the real home. Don't edit it expecting changes to show on `/`.
- **Project config syncs via YAML**, not migrations. New `common*` fields and entry types go in `config/project/` and Craft picks them up via Project Config sync.

## Examples to crib from

- **Hero patterns:** `templates/hero/heroHome.twig`, `templates/hero/heroDefault.twig`
- **Body block with carousel:** `templates/body/bodyNews.twig`
- **Body block with grid:** `templates/body/bodyAbout.twig` (team members)
- **Channel detail page:** `templates/news/_entry.twig`
- **Entries-relation CTA:** `templates/body/bodyBenefits.twig` shows how to dereference a `commonCta` entry → `commonLink` → button component
