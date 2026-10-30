## Context

IP-Symcon 7+ provides the `VISU_PostNotification()` function for the modern Tile Visualization (`{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}`), as well as legacy `WFC_PushNotification()` for WebFront and `NC_PushNotification()` for Notification Control. Users currently configure external scripts (such as `VISU_PostNotification(14065, ...)`) with independent timers to send alerts when the garage door is left open.

## Goals / Non-Goals

**Goals:**
- Provide a built-in, reliable push notification mechanism directly inside `GarageDoorTile`.
- Allow the user to select the target visualization instance or auto-detect it without requiring configuration.
- Support initial warning dispatch at `WarnOpenMinutes` with optional repeating reminders (`PushRepeatIntervalMinutes`).
- Link the notification directly to the garage door instance ID so tapping it opens the door tile.

**Non-Goals:**
- Sending third-party messages (Pushover, Telegram, WhatsApp) outside IP-Symcon's official notification system.

## Decisions

### 1. Unified Dispatch Helper with Auto-Detection
- **Decision:** Implement a private helper `SendPushNotification(string $title, string $text)` that resolves the target instance automatically if `PushInstanceID` is 0 by querying `IPS_GetInstanceListByModuleID('{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}')` (Tile Visualization) and selecting the first available instance.
- **Rationale:** Works zero-config out of the box on IP-Symcon 7+ systems, while still allowing manual override in settings.

### 2. Multi-API Support
- **Decision:** Determine the module type of the resolved instance and invoke the matching native function:
  - `VISU_PostNotification($instID, $title, $text, 'Garage', $targetID)` for Tile Visualization
  - `WFC_PushNotification($instID, $title, $text, 'alarm', $targetID)` for WebFront
  - `NC_PushNotification($instID, $title, $text, 'Alarm', $targetID)` for Notification Control
- **Rationale:** Ensures compatibility across different Symcon setups and versions.

### 3. Spam Prevention State Tracking
- **Decision:** Track `LastPushSentTimestamp` (Attribute, Integer).
  - First alert is dispatched when `openMinutes >= WarnOpenMinutes` and `LastPushSentTimestamp === 0`.
  - Repeat alerts are dispatched if `PushRepeatIntervalMinutes > 0` and `(now - lastSent) >= PushRepeatIntervalMinutes * 60`.
  - When the door closes (`STATE_CLOSED`), `LastPushSentTimestamp` is reset to 0.
- **Rationale:** Prevents annoying alert storms while still providing periodic reminders if desired.

## Risks / Trade-offs

- **[Risk]** Target visualization instance is deleted or offline.  
  → **Mitigation:** Safe execution with `@IPS_InstanceExists()` and `@VISU_PostNotification()` wrapped in try/catch to avoid unhandled script engine exceptions.
