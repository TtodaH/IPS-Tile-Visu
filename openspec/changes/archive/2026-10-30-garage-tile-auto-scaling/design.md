## Context

IP-Symcon tiles can be rendered at varying grid sizes (1x1, 2x1, 2x2, full-width) and opened in full-screen modal overlays (`⤢`). While the compact smartphone tile layout (width ~320-360px, height ~140-150px) constrained the door to ~114px, on larger desktop screens and modal views the graphic must scale smoothly to fill available area.

## Goals / Non-Goals

**Goals:**
- Replace flexbox layout with CSS Grid on `.tile-content-row` (`grid-template-columns: minmax(0, 1fr) 148px; gap: 10px;`) to enforce strict bounds on the door column and prevent control column squeezing.
- Use CSS Container Queries on `.garage-col` (`container-type: size;`) and container query units on `.garage-svg-wrap`:
  - `width: min(100cqw, calc(100cqh * 1.5));`
  - `height: min(100cqh, calc(100cqw / 1.5));`
- Scale overlay badges (`.pos-badge` and `.motion-indicator`) relative to the scaled door.
- Fix HTML markup to ensure proper closing `</div>` tags.

**Non-Goals:**
- Changing control buttons logic or PHP backend functions.

## Decisions

1. **CSS Grid Track Sizing**:
   - `grid-template-columns: minmax(0, 1fr) 148px;` guarantees the right column maintains its touch-friendly 148px width, while the left column claims all remaining width without horizontal overflow.
2. **Container Query Proportional Sizing**:
   - Using `min(100cqw, calc(100cqh * 1.5))` for width and `min(100cqh, calc(100cqw / 1.5))` for height ensures the door never exceeds the container's width or height, scaling up smoothly to the maximum bounding box while preserving the 180:120 (3:2) ratio.
