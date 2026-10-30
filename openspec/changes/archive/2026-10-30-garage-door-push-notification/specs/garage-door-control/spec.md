## ADDED Requirements

### Requirement: Prolonged Open State Push Notification
The system SHALL send a mobile push notification when the garage door has been open continuously for longer than the configured warning duration.

#### Scenario: Door open duration exceeds warning threshold
- **WHEN** the garage door is in an open or stopped state, `EnablePushNotification` is true, and open duration reaches `WarnOpenMinutes`
- **THEN** the system dispatches a push notification via `VISU_PostNotification` (or compatible notification API) with title "Garagentor offen!", description including the open duration, and target set to the garage door instance ID

#### Scenario: Repeat reminder interval
- **WHEN** the door remains open beyond the initial alert and `PushRepeatIntervalMinutes` is greater than 0
- **THEN** the system dispatches a repeat push notification every `PushRepeatIntervalMinutes` until the door is closed

#### Scenario: Door closed resets push state
- **WHEN** the garage door closes completely
- **THEN** the system resets the push notification tracking state so future openings will trigger a fresh alert when thresholds are met
