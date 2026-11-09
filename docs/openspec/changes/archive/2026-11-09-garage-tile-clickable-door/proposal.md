## Why

Currently, operating the garage door requires specifically clicking the control button ("Öffnen" / "Schließen" / "Stopp") on the right side of the tile. On mobile screens, tablets, and desktop dashboards, users intuitively tap or click directly on the large visual representation of the garage door itself. Making the garage door graphic clickable allows immediate one-touch door toggling with visual and haptic feedback.

## What Changes

- Make the garage door SVG visualization (`.garage-svg-wrap`) directly clickable and keyboard-accessible (`role="button"`, `tabindex="0"`, `aria-label`).
- Tapping or clicking the garage door triggers `handleDoorClick()` (invoking `requestAction('triggerDoor', true)`).
- Provide visual interaction feedback when the door is hovered, focused, or pressed (subtle hover glow/brightness, active scale/press effect, and triggering the impulse ripple animation).
- Ensure `.pos-badge` and `.motion-indicator` pass through pointer events or properly bubble clicks to the door container.

## Capabilities

### Modified Capabilities
- `garage-door-tile-ui`: Allow user interaction and door triggering directly via clicking/tapping the garage door visual element.

## Impact

- `GarageDoorTile/module.html`: Add interactive attributes, keyboard handlers, CSS styling/hover/active states, and click triggers to `.garage-svg-wrap`.
- GitHub Issue #1: Linked and resolved by this change.
