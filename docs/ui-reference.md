# UI reference

Visual style to follow, taken from the developer's earlier project `DSC-Home-Assignment` (Laravel 13 + Tailwind v4).

## 0. Precedence (read first)

- **`CLAUDE.md` always wins.** This file only describes *appearance*: colours, font, spacing, radius, shadows, page layout, and Tailwind classes. It never decides architecture, features, file structure, component names, or behaviour.
- Take **only** the aesthetic from the reference project. Do not copy its code, features, JavaScript, routes, search, modals logic or libraries. (Exception agreed with the developer: its login/logout flow and role middleware may be used as a pattern, rewritten to CLAUDE.md rules.) Build every view fresh, the CLAUDE.md way, and dress it in these classes.
- Component names and responsibilities are the ones in CLAUDE.md section 6 (`x-layout`, `x-button`, `x-link-button`, `x-input`, `x-select`, `x-alert`, `x-delete-form`, `x-table`). The class strings below are just what goes inside them.
- Features shown below as UI patterns (search bar, navbar user area, status dots, choice cards) are used **only if the task brief needs them**. Never add a feature because the reference had it.
- Do not open the reference project again to copy from it; this file is the whole hand-off. If something here conflicts with CLAUDE.md, follow CLAUDE.md and tell the developer.

Mood: warm, calm, coffee/brown palette on an off-white background; rounded, soft shadows, bold headings; airy lists instead of boxed tables.

## 1. Design tokens (`resources/css/app.css`)

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;

    --color-primary-50: #fbf8f5;
    --color-primary-100: #f5eee5;
    --color-primary-200: #eadecc;
    --color-primary-300: #dcc4ab;
    --color-primary-400: #c9a484;
    --color-primary-500: #b98762;
    --color-primary-600: #aa704f;
    --color-primary-700: #8d5841;
    --color-primary-800: #734837;
    --color-primary-900: #3d2100;

    --color-primary: #3d2100;   /* main buttons, headings */
    --color-secondary: #8B5A2B; /* hover, focus ring, accents, links */
    --color-tertiary: #D2B48C;
}

@view-transition { navigation: auto; }
header { view-transition-name: main-navbar; }

body { @apply text-primary-900 bg-primary-50; }
```

Font: Instrument Sans 400-900 from `https://fonts.bunny.net` (preconnect + `display=swap`).

| Role | Classes |
|---|---|
| Page background / text | `bg-primary-50 text-primary-900` |
| Muted text | `text-primary-600` (meta), `text-primary-700` (body copy), `text-primary-400` (placeholder/icons) |
| Lines | `border-primary-200/60` (dividers), `border-primary-300` (inputs, secondary buttons) |
| Surfaces | Mostly no cards; page bg directly. Cards/modals: `bg-white rounded-3xl border border-primary-200 shadow-2xl` |
| Success | `bg-emerald-50 border-emerald-200 text-emerald-800`, icon `text-emerald-600`, dot `bg-emerald-500` |
| Danger | `bg-rose-50 border-rose-200 text-rose-800`, solid `bg-rose-600 hover:bg-rose-700`, dot `bg-rose-500`, required `*` `text-rose-500` |
| Radius | buttons/inputs `rounded-xl`, small chips `rounded-lg`, alerts `rounded-2xl`, modals/cards `rounded-3xl` |
| Shadows | `shadow-2xs` (chips, inputs), `shadow-md hover:shadow-lg` (primary buttons), `shadow-2xl` (modals) |
| Motion | `transition-all duration-200`, primary buttons lift `transform hover:-translate-y-0.5` |
| Selection | `selection:bg-primary-300 selection:text-primary-900` |

## 2. Layout shell (top navbar)

```
┌──────────────────────────────────────────────────────────────┐
│ header (sticky, border-b border-primary-900)                 │
│  Brand   Menu1  Menu2  Menu3            Name · Role   [⎋]    │
├──────────────────────────────────────────────────────────────┤
│ main  max-w-6xl mx-auto px-6 sm:px-10 py-8 md:py-12          │
│   flash alerts (mb-6)                                        │
│   H1 (mb-6)                                                  │
│   action row: create button left (search right if needed)   │
│   content (forms inside max-w-4xl)                           │
└──────────────────────────────────────────────────────────────┘
mobile: row 1 = brand + user/logout, row 2 = menu links (scroll sideways)
```

- `<x-layout>` body: `font-sans antialiased bg-primary-50 text-primary-900`, wrapper `min-h-screen flex flex-col relative overflow-x-clip`, plus decorative glow
  `<div class="absolute inset-0 z-0 pointer-events-none opacity-50 bg-[radial-gradient(ellipse_at_top,_var(--color-primary-200),_transparent_70%)]"></div>`, content `z-10`.
- The navbar and `<main>` live inside the layout itself (not repeated in every page). The layout takes a `title` prop that is actually used in `<title>`.
- Header: `<header class="sticky top-0 z-20 border-b border-primary-900 bg-primary-50/85 backdrop-blur-md">`.
- Header inner (same width as `<main>` so edges line up): `max-w-6xl mx-auto px-6 sm:px-10 py-4 md:py-0 md:h-16 flex flex-wrap md:flex-nowrap items-center justify-between gap-x-8 gap-y-3`.
- Brand (left): app name, `text-lg font-black tracking-tight text-primary-900`, links to the role's home page.
- Menu `<nav aria-label="Menu utama">`: `order-last md:order-none w-full md:w-auto flex items-center gap-6 md:gap-8 overflow-x-auto whitespace-nowrap md:flex-1`. Links are plain text, `text-sm sm:text-base tracking-wide py-1`:
  - active: `text-primary-900 font-bold underline decoration-2 underline-offset-8` + `aria-current="page"`
  - idle: `text-primary-800/80 font-medium hover:text-primary-900 hover:underline hover:decoration-primary-400 hover:underline-offset-8`
  - Decide active with `request()->routeIs('admin.garages.*')` per link; no big `@php` blocks.
- Menu items are the resources from the task brief, nothing else, and depend on the logged-in role.
- User area (right, **only when logged in**): avatar circle `w-9 h-9 rounded-full bg-primary-200/70 border border-primary-300/60` with a person icon, name `text-sm font-semibold truncate max-w-[10rem]`, role `text-xs text-primary-600`, then a logout icon button (`p-1.5 text-primary-500 hover:text-primary-900 hover:bg-primary-100 rounded-lg`, `aria-label`) inside a POST form with `@csrf`. Name and role hide below `sm` (`hidden sm:block`) to save space.
- Guest pages (login, register) have no navbar: centered card `w-full sm:max-w-md px-6 py-8 sm:px-8 sm:py-10 bg-white/80 backdrop-blur-md shadow-2xl sm:rounded-3xl border border-white/40`, heading `text-2xl sm:text-3xl font-black`.

## 3. Typography

| Element | Classes |
|---|---|
| Page H1 | `text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6` |
| Modal/section title | `text-lg font-black text-primary-900 tracking-tight` |
| Row title | `text-base font-bold text-primary-900 truncate` |
| Label | `block font-semibold text-sm text-primary-800 mb-2` |
| Meta / secondary | `text-xs sm:text-sm text-primary-600` |
| Back link | `inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-secondary hover:text-primary-900` + left-arrow icon |

## 4. Components (map to CLAUDE.md component names)

**`x-button` / `x-link-button`** with `variant` prop:

| variant | Classes (all: `inline-flex items-center justify-center gap-2 rounded-xl text-sm transition-all duration-200 cursor-pointer`) |
|---|---|
| `primary` | `px-6 py-2.5 bg-primary text-white font-bold shadow-md hover:bg-secondary hover:shadow-lg transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2 focus:ring-offset-primary-50 disabled:opacity-50 disabled:cursor-not-allowed` |
| `secondary` (Cancel) | `px-5 py-2.5 font-semibold text-primary-700 bg-white hover:bg-primary-100/80 border border-primary-300 shadow-2xs hover:shadow-xs` |
| `danger` | `px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md hover:shadow-lg focus:ring-rose-500 transform hover:-translate-y-0.5` |
| `row-edit` (small) | `px-3.5 py-1.5 text-xs font-bold text-primary-900 bg-white hover:bg-primary-100 border border-primary-200 rounded-lg shadow-2xs` |
| `row-delete` (small) | `px-3.5 py-1.5 text-xs font-bold text-rose-700 bg-white hover:bg-rose-50 border border-rose-200 rounded-lg shadow-2xs` |

**`x-input`**: `w-full border border-primary-300 rounded-xl shadow-sm bg-white/80 backdrop-blur-sm px-4 py-3 text-primary-900 placeholder-primary-400 hover:bg-white focus:bg-white focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/50 transition-all duration-300`.
Use the same look for `x-select` and `x-textarea`. The component should render label + input + `@error` message (`text-sm text-red-600 mt-1.5`) together, with a `required` prop that adds `<span class="text-rose-500 ml-0.5">*</span>`.

**Choice cards** (radio/boolean instead of a plain select):
`label` = `flex items-center justify-between p-3 min-h-[48px] rounded-xl border border-primary-300/80 bg-white/70 cursor-pointer hover:bg-white has-checked:border-secondary has-checked:bg-primary-100/50 has-checked:ring-2 has-checked:ring-secondary/40`, coloured status dot on the left, radio on the right.

**`x-alert`** (`type="success|error"`, reads `session('success')` / `session('error')`):
`p-4 rounded-2xl border text-sm shadow-2xs flex items-start gap-3` + success/danger colours from section 1 + icon (`check-circle` / `exclamation-circle`, `w-5 h-5 shrink-0`), message `font-semibold` (success) or `font-bold` (error). Validation summary: bold title + `list-disc list-inside text-xs`. Add `role="status"` / `aria-live="polite"`.

**List rows (index pages)**, instead of a boxed table:
- Container: `divide-y divide-primary-200/60 border-t border-b border-primary-200/60`
- Row: `py-4 px-3 sm:px-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl hover:bg-primary-100/50 transition-colors`
- Left: status dot `w-2.5 h-2.5 rounded-full` + bold title. Middle: meta with small icons (`text-primary-400`). Chip: `text-xs sm:text-sm font-semibold text-primary-800 bg-primary-100/80 border border-primary-200/60 px-2.5 py-1 rounded-lg` with icon in `text-secondary`. Right: Edit + Delete small buttons.
- If the data is truly tabular (many columns), use `x-table` with the same divider/hover styling and `<th scope="col" class="text-xs font-semibold text-primary-600 uppercase tracking-wide">`.

**Empty state**: `py-16 text-center`, icon `w-8 h-8 text-secondary`, text `text-lg text-primary-600 max-w-md mx-auto mb-6`, then a primary create button.

**Action row**: `flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6`. Create button (primary, `+` icon) on the left. A search box goes on the right **only if the brief asks for search** (`relative w-full sm:w-72 md:w-80`, input `pl-10 pr-4 py-2.5 rounded-xl border border-primary-300/80 bg-white/70 text-sm shadow-2xs`, magnifier icon absolutely positioned); it is then a GET form handled in the controller.

**Delete confirmation (`x-delete-form`)**: default is CLAUDE.md's `x-delete-form` with `@csrf`, `@method('DELETE')`, `action="{{ route('items.destroy', $item) }}"` and `onsubmit="return confirm('...')"`, styled as the `row-delete` button. A styled modal is optional and only if the developer asks; if so, use this look:
- Overlay `fixed inset-0 z-50 bg-black/45 backdrop-blur-xs flex items-center justify-center p-4`, `role="dialog" aria-modal="true"`.
- Panel `bg-white rounded-3xl shadow-2xl border border-primary-200 max-w-md w-full overflow-hidden`.
- Header `p-6 pb-4 border-b border-primary-200/60` with icon tile `w-8 h-8 rounded-xl bg-rose-100/70 text-rose-600 border border-rose-200/60`, title, close X.
- Body `p-6 text-sm text-primary-700 leading-relaxed`. Footer `p-4 sm:p-5 border-t border-primary-200/60 bg-primary-50/30 flex justify-end gap-3` with secondary Cancel + danger Confirm.
- The form `action` comes from `route('items.destroy', $item)`, never a hard-coded `'/items/' + id`.

**Forms (create/edit)**: page `max-w-4xl`, back link `mb-4`, form `space-y-6 p-6 sm:p-8`, fields in `grid grid-cols-1 md:grid-cols-3 gap-6` where short, footer `flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60` with Cancel (secondary) + Save/Create (primary). Share fields between create and edit through one `_form` partial/component.

## 5. Icons

Installed: `blade-ui-kit/blade-heroicons` 2.7 (listed in the README libraries table). Use outline icons `<x-heroicon-o-plus class="w-4 h-4" />`; `-s-` solid only where a filled look is needed.
Commonly used: `plus`, `pencil-square`, `trash`, `arrow-left`, `check-circle`, `exclamation-circle`, `exclamation-triangle`, `x-mark`.
Decorative icons next to text are fine as-is (the package adds `aria-hidden="true"`); an icon-only button needs `aria-label`.

## 6. JavaScript

Nothing is copied from the reference. Agreed with the developer:

- **Rules live in PHP only.** Totals, end dates, max days, charging and garage availability are calculated by models/controllers and shown through a two-step server form (step 1 details, step 2 summary + garage picker + upload). JavaScript never recalculates them.
- **Small UI-only scripts are allowed** in `resources/js/app.js`: show/hide fields (plate for non-bicycles, battery fields for electric cars, days for daily rentals) and limiting garage-type options to the chosen vehicle type. Drive them with `data-*` attributes, not inline `onclick`. The server still validates every field.

## 7. Do not copy from the reference

Known clashes found in the reference project. Treat this list as examples, not the full set: anything not in sections 1-5 is not carried over.

| Reference did | Do instead (CLAUDE.md) |
|---|---|
| Client-side JS search filtering, double-submit spinner script, modal open/close scripts | Only the show/hide scripts agreed in section 6 |
| Role-based menus (admin/staff), login page, logout modal | Only what the task brief defines |
| Hand-written `<button>`/`<a>` class strings repeated in pages | Use `x-button` / `x-link-button` variants |
| Separate `flash-success`, `flash-error`, `alert-success`, `alert-error` wrappers | One `x-alert` component |
| Form component with its own `@php` deciding route/method/labels | Page passes `action`, `method`, model as props; `_form` only renders fields |
| `addslashes()` in inline `onclick` strings | No inline JS with user data |
| `\App\Models\X::latest()->get()` inside a Blade view | Controller queries and passes `$items` |
| Big `@php` role/menu logic in navbar | Simple `active` prop / `request()->routeIs()` |
| Hard-coded URLs in JS (`'/admin/meeting-rooms/' + id`) | `route()` in Blade |
| Inline `<style>` and CDN libraries (Quill) inside components | Tailwind classes only; no extra libraries unless asked |
| Long class strings repeated in each page | Put them once in a component variant |
| Layout ignores its `title` | `<title>{{ $title ?? config('app.name') }}</title>` |
| Pages `@include` other pages | Each controller action returns its own view |
