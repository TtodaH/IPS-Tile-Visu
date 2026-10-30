## 1. CSS & HTML Implementation

- [x] 1.1 Update `GarageDoorTile/module.html` container layout to CSS Grid (`grid-template-columns: minmax(0, 1fr) 148px; gap: 10px;`)
- [x] 1.2 Add container queries (`container-type: size;`) and dynamic proportional scaling (`min(100cqw, calc(100cqh * 1.5))`) to `.garage-svg-wrap`
- [x] 1.3 Fix missing closing `</div>` on `.garage-col` and adjust overlay position coordinates for `.pos-badge` and `.motion-indicator`

## 2. Verification & Deployment

- [x] 2.1 Verify multi-viewport responsiveness (smartphone, tablet, desktop, fullscreen modal) via automated screenshot tests
- [x] 2.2 Commit changes, push to Gitea and GitHub, pull to `unserver`, and reload module in IP-Symcon
