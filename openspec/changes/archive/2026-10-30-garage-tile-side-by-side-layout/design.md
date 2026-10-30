## Context

The IP-Symcon 7+ tile visualization renders custom modules in fixed or responsive tile grids. On smartphones (mobile WebFront or Symcon app), tiles typically have an aspect ratio of approximately 16:9 to 2:1 (width ~320px–380px, height ~140px–170px). The top 20px contains the native tile title and the expand/fullscreen icon.
In the previous vertically stacked design, the SVG door (~118px) plus header spacer (20px) plus status row (20px) plus action button (38px) plus presets (28px) plus meta row (15px) exceeded 230px, overflowing mobile tiles.
Placing the door on the left and a dedicated control column on the right allows the tile to comfortably fit within 140px–150px height without any scrolling.

## Goals / Non-Goals

**Goals:**
- Create a 2-column horizontal flex layout (`.tile-content-row`) below `.symcon-header-spacer`.
- Position the sectional garage door SVG on the left column (`.garage-col`), auto-scaling cleanly while maintaining aspect ratio (180:120).
- Position the controls in the right column (`.controls-col`):
  1. Status pill (`● Geschlossen`, `● Offen`, etc.) & offline badge.
  2. Main action button (icon + compact text: "Öffnen", "Schließen", "Stopp").
  3. Presets row (compact icon + percent buttons for ventilation and custom preset).
  4. Meta information row (open duration timer and impulse activity dot).
- Eliminate any clash with Symcon's native top-left title and top-right fullscreen expand icon.
- Maintain Frame-0 pre-rendering with zero visual delay.

**Non-Goals:**
- Modifying backend state machine logic or relay impulse timings.
- Altering the SVG artwork structure itself (only container layout and sizing).

## Decisions

1. **Flexbox 2-Column Split**:
   - `.garage-col`: `flex: 1 1 auto; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; min-width: 0;`
   - `.controls-col`: `flex: 0 0 46%; max-width: 165px; height: 100%; display: flex; flex-direction: column; justify-content: center; gap: 5px;`
   - *Rationale*: Guarantees the SVG scales dynamically to available height, while controls remain crisp, fixed-width, and touch-friendly (buttons >= 32px height).

2. **Compact Button Text & SVG Icons**:
   - Short action texts: "Öffnen", "Schließen", "Stopp" (instead of longer "Tor öffnen", "Tor schließen", "Tor stoppen") so they fit comfortably on 140px–165px wide control columns without truncation.
   - Preset buttons display SVG icon + `%` (e.g. airflow icon + `15%`, sliders icon + `50%`), with full descriptive text provided via HTML `title` attributes for tooltips.

3. **Status Pill Placement**:
   - Move status pill into `.controls-col` above the main action button instead of absolute floating in the tile header.
   - *Rationale*: Prevents any overlap with Symcon's expand arrow icon in the top right corner.

## Risks / Trade-offs

- *[Risk] Extreme narrow viewports (< 260px)* → *Mitigation*: Flex wrap or min-width boundaries, percentage badge shrinks cleanly, buttons utilize `white-space: nowrap` and `overflow: hidden`.
- *[Risk] Frame-0 string replacement mismatch* → *Mitigation*: Ensure `GetVisualizationTile()` replacements match the exact markup in `module.html`.
