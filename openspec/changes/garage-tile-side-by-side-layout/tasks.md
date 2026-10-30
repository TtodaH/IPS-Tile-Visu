## 1. Layout Restructuring in HTML & CSS

- [x] 1.1 Update `GarageDoorTile/module.html` container layout to 2-column flexbox (`.tile-content-row`, `.garage-col`, `.controls-col`)
- [x] 1.2 Restyle `#triggerBtn` and preset buttons for compact side-by-side presentation with clean iconography
- [x] 1.3 Move status pill and meta rows into `.controls-col` to avoid header/expand button clash

## 2. Backend Pre-rendering & Dynamic Labels

- [x] 2.1 Update `GetTilePayload()` and button strings in `GarageDoorTile/module.php` for compact action labels ("Öffnen", "Schließen", "Stopp")
- [x] 2.2 Update `GetVisualizationTile()` string replacements to match the updated HTML structure for zero visual delay

## 3. Visual Verification & Deployment

- [x] 3.1 Test rendering in mobile viewports (e.g. 360x150, 320x140) using headless Chrome screenshots
- [ ] 3.2 Commit changes, push to Gitea and GitHub, pull to `unserver`, and reload module in IP-Symcon
