## Context

IP-Symcon HTML-SDK tiles are rendered in an iframe. The WebFront appends a fixed set of query parameters to the tile URL, including `accentcolor`, `textcolor`, `cardcolor`, `fontsize`, `margintop`, `marginside`, `marginbottom` and `token`. The SDK script (`/tile/html-sdk.js`) already consumes those parameters to style `body`.

Therefore the reliable source for the active visualization theme is the **card color of the current theme**, not `prefers-color-scheme` (which describes the operating system).

## Goals / Non-Goals

**Goals:**
- Detect light vs. dark theme reliably from the tile frame URL, before first paint (no flash of wrong colors).
- Keep the dark theme visually unchanged.
- Keep every text/element readable (contrast) in the light theme, including SVG artwork.
- No external dependencies, no PHP changes, works in IP-Symcon 7.1+.

**Non-Goals:**
- Support for arbitrary user-customized skins beyond luminance detection.
- Automatic color-role extraction (e.g. using the user's accent color everywhere); accents stay defined by module properties.

## Decisions

### 1. Theme detection via `cardcolor` luminance
- **Decision**: Parse `cardcolor` (fallback `textcolor`, then `prefers-color-scheme`) and compute WCAG relative luminance. Cards with luminance > 0.45 are treated as light.
- **Rationale**: `cardcolor` is the actual surface the tile sits on; a threshold in the middle of the luminance range cleanly separates the shipped dark (`#333438`, L ≈ 0.03) and light (`#FFFFFF`, L = 1.0) themes.
- **Alternatives**: `prefers-color-scheme` alone — rejected, it tracks the OS. `textcolor` alone — usable but less direct than the surface color.

### 2. Bootstrap inline in `<head>`
- **Decision**: A small synchronous `<script>` immediately after `<title>` sets `data-theme` on `<html>` and exposes `--symcon-card`, `--symcon-text`, `--symcon-accent`.
- **Rationale**: Runs before the first paint, so there is no light/dark flash. Attribute selector `:root[data-theme="light"]` lets CSS stay declarative.

### 3. CSS variables for SVG artwork
- **Decision**: Every previously hard-coded artwork color becomes a CSS custom property with a dark default and a light override.
- **Rationale**: Keeps SVG markup untouched structurally; theme switching is a pure variable swap and animations/transforms keep working.

### 4. `themeColor()` darkening for runtime colors pushed from PHP
- **Decision**: Status colors that PHP sends via JSON (`stateColor`, needle/gust colors) are multiplied by a factor in the light theme.
- **Rationale**: PHP does not know the client theme; darkening at render time preserves the semantic color while reaching readable contrast.

## Risks / Trade-offs

- Luminance threshold 0.45 could misclassify an unusual mid-grey custom skin. Mitigation: `textcolor` fallback and an easily adjusted constant.
- `color-mix()` is used in two cosmetic rules; browsers without support simply keep the previous color (graceful degradation).
