## 1. Theme detection

- [x] 1.1 Add theme bootstrap script to `TileTemplate/module.html` (parses `cardcolor`/`textcolor`, sets `data-theme`, exposes `--symcon-*`)
- [x] 1.2 Add the same bootstrap to `GarageDoorTile/module.html`
- [x] 1.3 Add the same bootstrap to `WeatherStationTile/module.html`

## 2. Light theme CSS

- [x] 2.1 Replace `prefers-color-scheme` block in `TileTemplate` with `:root[data-theme="light"]` overrides
- [x] 2.2 Add light overrides for text, buttons, presets and status pill in `GarageDoorTile`
- [x] 2.3 Add light overrides for text, badges, divider and speed color in `WeatherStationTile`
- [x] 2.4 Use `var(--symcon-card, …)` as tile background fallback

## 3. SVG artwork theming

- [x] 3.1 Move garage wall/frame/interior/slat/seal/hinge/threshold colors into CSS variables with light overrides
- [x] 3.2 Move wind rose dial, ticks, letters, ghost needle, hub, needle tail and shadow into CSS variables with light overrides

## 4. Runtime colors

- [x] 4.1 Add `themeColor()` helper in `GarageDoorTile` and darken status/button border colors in the light theme
- [x] 4.2 Add `themeColor()` helper in `WeatherStationTile` and darken gust color in the light theme

## 5. Verification

- [x] 5.1 Render all three tiles with dark parameters (`cardcolor=333438`) — unchanged appearance
- [x] 5.2 Render all three tiles with light parameters (`cardcolor=FFFFFF`) — readable text, badges, buttons and artwork
- [ ] 5.3 Visual check in the live IP-Symcon WebFront (light and dark theme)
- [x] 5.4 Update repo `README.md` with a short note about automatic light/dark theme support
