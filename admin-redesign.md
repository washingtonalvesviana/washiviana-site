# Admin Redesign Plan: "Project Obsidian"

## 🎨 Design Commitment
- **Style:** Modern Swiss Technical (Clean, High Contrast, Editorial).
- **Palette:** 
  - **Primary (Sidebar/Nav):** Obsidian (`#18181b`) - Replaces generic white sidebar.
  - **Accent (Actions):** Electric Lime (`#84cc16`) - Replaces generic Bootstrap Blue.
  - **Background:** Ghost White (`#f8fafc`).
- **Geometry:** Stubbornly Sharp (`4px` radius). rejection of the "Soft SaaS" `12px+` trend.
- **Typography:** Montserrat. High contrast headers. Small, uppercase, tracked-out labels.
- **Motion:** Staggered entrance for all cards. Hover lifts on interactive elements.

## 🛠️ Implementation Steps

### 1. CSS Framework Overhaul (`assets/css/admin.css`)
- [ ] Define new CSS Variables (Zinc/Lime palette).
- [ ] Implement `admin-layout` with Dark Sidebar.
- [ ] Redesign `.card`: Remove standard shadow, add border definition.
- [ ] Redesign `.btn`: Remove default rounding, add hover lift.
- [ ] Add `toast` notification styles (replacing alerts).
- [ ] Add `@keyframes` for entrance animations.

### 2. Sidebar Refactor (`admin/includes/sidebar.php`)
- [ ] Update classes to match new dark mode semantic.
- [ ] Ensure active state uses the Lime accent.

### 3. Dashboard Refactor (`admin/dashboard.php`)
- [ ] Apply `animate-enter` classes to grid items.
- [ ] Update "Stats Cards" to use the new visual language (Icon + Value typography).

### 4. Redes Sociais Page (`admin/redes-sociais.php`)
- [ ] Remove inline styles.
- [ ] Implement "Toasts" for feedback instead of `alert()`.
- [ ] Style the "Network Cards" to be distinct (Brand colors for LinkedIn/Insta).

## 🚀 Execution Order
1. `assets/css/admin.css` (The Foundation)
2. `admin/includes/sidebar.php` (The Navigation)
3. `admin/dashboard.php` (The Home)
4. `admin/redes-sociais.php` (The Detail)
