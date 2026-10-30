## 1. Properties and Attribute Registration

- [x] 1.1 Register properties `EnablePushNotification` (Boolean, default true), `PushInstanceID` (Integer, default 0), and `PushRepeatIntervalMinutes` (Integer, default 0) in `GarageDoorTile/module.php`
- [x] 1.2 Register attribute `LastPushSentTimestamp` (Integer, default 0) in `GarageDoorTile/module.php`
- [x] 1.3 Add push notification settings panel to `GarageDoorTile/form.json` and localizations to `GarageDoorTile/locale.json`

## 2. Notification Dispatch Logic

- [x] 2.1 Implement `SendPushNotification(string $title, string $message)` helper in `GarageDoorTile/module.php` with auto-detection of Tile Visualization/WebFront instances
- [x] 2.2 In `HandleOpenDurationTick()`, evaluate `WarnOpenMinutes`, `LastPushSentTimestamp`, and repeat intervals, triggering push notification when due
- [x] 2.3 Reset `LastPushSentTimestamp = 0` in `SyncWithSensors()` when the door is closed

## 3. Deployment & Verification

- [ ] 3.1 Push changes to GitHub and Gitea, pull to `unserver`, reload module and apply changes on instance 23387
- [ ] 3.2 Verify push properties and configuration form in IP-Symcon
