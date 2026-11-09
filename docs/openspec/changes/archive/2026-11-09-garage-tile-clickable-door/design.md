## Context

The `GarageDoorTile` module provides a responsive HTML-SDK visualization in IP-Symcon. The tile displays an interactive SVG representation of a sectional garage door alongside status and control buttons. While users could trigger the door via the dedicated action button (`#triggerBtn`), tapping or clicking the garage door itself is standard intuitive user behavior on touch screens and desktop dashboards.

## Goals / Non-Goals

**Goals:**
- Enable direct interaction: Clicking or tapping anywhere on the garage door visual (`.garage-svg-wrap`) triggers the same door toggle action (`requestAction('triggerDoor', true)`).
- Provide clear visual feedback:
  - `cursor: pointer` on desktop devices to indicate interactivity.
  - Hover / focus subtle brightness/border highlight.
  - Active press transform (`scale(0.985)`) for instant touch feedback.
  - Co-trigger the impulse feedback indicator so the user immediately knows the command was accepted.
- Full accessibility:
  - Semantics: `role="button"`, `tabindex="0"`, `aria-label="Garagentor bedienen"`.
  - Keyboard support: Pressing `Enter` or `Space` triggers `handleDoorClick()`.

**Non-Goals:**
- Changing backend pulse logic, travel time calibration, or state machine transitions (these are handled centrally in `module.php`'s `TriggerDoor()`).
- Replacing or removing the existing right-hand `#triggerBtn` (it remains fully functional as a complementary control).

## Decisions

1. **Attach interaction to `.garage-svg-wrap` container**:
   - *Rationale*: `.garage-svg-wrap` contains the door SVG, position badge, and motion indicators. Making the wrap container interactive ensures the entire graphic area acts as a comfortable touch target (minimum touch target size is easily met).
   - *Alternatives considered*: Only attaching click to `#doorSlatsGroup` inside SVG. Downside: Tapping the garage opening, frame, or when the door is fully open would fail to trigger the door.

2. **Pointer event transparency on internal overlays**:
   - Ensure child elements like `.pos-badge` and `.motion-indicator` pass events through (`pointer-events: none;`) or cleanly bubble clicks so no click dead-zones occur across the graphic.

3. **Subtle active and hover styling**:
   - Add `.garage-svg-wrap.interactive`:
     - `cursor: pointer;`
     - `user-select: none;`
     - `-webkit-tap-highlight-color: transparent;`
     - Transition for `:hover` (subtle border/glow) and `:active` (subtle scale press `scale(0.985)`).

## Risks / Trade-offs

- *Accidental taps while scrolling on mobile*:
  - *Mitigation*: Standard click event behavior in web browsers ignores drag/scroll gestures.
