## 1. Backend Data & Control Logic

- [x] 1.1 Add configuration properties to `GarageDoorTile/form.json` (`DefaultVentilationPosition`, `CustomPresetEnabled`, `CustomPresetLabel`, `CustomPresetPosition`)
- [x] 1.2 Register child variable `VentilationPosition` with action enabled in `GarageDoorTile/module.php`
- [x] 1.3 Implement non-blocking `TargetStop` timer and target-seeking movement algorithm (`MoveToPosition`) in `GarageDoorTile/module.php`
- [x] 1.4 Add `RequestAction` handlers for `triggerPresetVentilation`, `triggerCustomPreset`, and `VentilationPosition` variable updates

## 2. Tile Frontend UI

- [x] 2.1 Add preset button row markup for "Lüften" and custom preset in `GarageDoorTile/module.html`
- [x] 2.2 Add CSS styles for sleek preset pill buttons adhering to dark theme
- [x] 2.3 Connect JavaScript event handlers to trigger preset actions via `requestAction`
- [x] 2.4 Update live message handling to refresh preset labels and percentages dynamically

## 3. Deployment & Visual Verification

- [ ] 3.1 Commit changes and deploy to IP-Symcon server via git
- [ ] 3.2 Verify `VentilationPosition` variable in Symcon console and detail view
- [ ] 3.3 Test target movement stopping logic and take live screenshots of tile UI
