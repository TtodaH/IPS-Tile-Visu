## Why

While the side-by-side garage door tile layout works well on compact smartphone viewports, on larger screens (desktop dashboards, tablet views, 2x2 grid tiles, and expanded full-screen modals) the garage door graphic was hard-capped to `max-height: 114px`. This left excessive unused whitespace and prevented the door SVG from automatically scaling up to fill larger tile dimensions proportionally. Furthermore, `.garage-col` lacked a closed HTML container tag and container queries, causing layout distortion when resized.

## What Changes

- Implement robust 2-column CSS Grid (`grid-template-columns: minmax(0, 1fr) 148px; gap: 10px;`) for `.tile-content-row`.
- Fix missing closing `</div>` on `.garage-col`.
- Implement dynamic aspect-ratio-preserving container query sizing on `.garage-svg-wrap`:
  - `width: min(100cqw, calc(100cqh * 1.5));`
  - `height: min(100cqh, calc(100cqw / 1.5));`
  - Auto-scales seamlessly to any viewport dimensions (smartphone, tablet, desktop, fullscreen modal) while strictly maintaining the 3:2 (180:120) aspect ratio.
- Dynamically scale `.pos-badge` and `.motion-indicator` relative to the scaled SVG container so badges and arrows remain perfectly placed across all scales.

## Capabilities

### Modified Capabilities
- `garage-door-tile-ui`: Update HTML-SDK tile visualization requirement to specify automatic proportional scaling of the garage door graphic across all viewport dimensions.

## Impact

- `GarageDoorTile/module.html`: CSS Grid container, container query auto-scaling rules, HTML structure fix.
