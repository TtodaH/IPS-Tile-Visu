## Why

Leaving a garage door open inadvertently creates security, pest, and weather hazards. While `GarageDoorTile` already tracks open duration and displays a visual warning badge on the tile, users away from the WebFront or phone screen need an active push notification. Integrating native IP-Symcon push notification dispatching (`VISU_PostNotification` / `WFC_PushNotification`) directly into the module eliminates the need for separate, unreliable custom timer scripts and workflows.

## What Changes

- Add module properties for push notifications:
  - `EnablePushNotification` (Boolean, default: true): Enable or disable active push notifications.
  - `PushInstanceID` (Integer/SelectInstance, optional): Target Visualization or WebFront instance. Auto-detects active Tile Visualization if unset (0).
  - `PushRepeatIntervalMinutes` (Integer, default: 0): Configurable reminder interval in minutes (0 = send once, >0 = repeat alert).
- Dispatch native IP-Symcon push notifications via `VISU_PostNotification` (or `WFC_PushNotification` / `NC_PushNotification` depending on target instance type) when the open duration reaches `WarnOpenMinutes`.
- Link the notification target directly to the garage door instance ID so tapping the push notification on the mobile app navigates straight to the garage door tile.
- Persist `LastPushSentTimestamp` to cleanly track alert dispatch and prevent repetitive spam, resetting immediately when the door returns to the closed state.
- Expose push settings in `form.json` with German and English localizations in `locale.json`.

## Capabilities

### Modified Capabilities
- `garage-door-control`: Add requirement for configurable push notification alerts on prolonged open state.

## Impact

- `GarageDoorTile/module.php`: Notification dispatch helper, `OpenDurationTick` handler updates, state reset on close.
- `GarageDoorTile/form.json`: New "Push-Benachrichtigung" configuration section.
- `GarageDoorTile/locale.json`: German and English translations for push options.
