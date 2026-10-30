## ADDED Requirements

### Requirement: Automated Shelly 1 Impulse Generation
The system SHALL simulate a momentary push-button impulse on the Shelly 1 relay switch variable without requiring manual toggling.

#### Scenario: User triggers garage door action
- **WHEN** user commands a door impulse from the visualization or PHP API
- **THEN** the system sets the Shelly 1 relay variable to true via RequestAction, activates a non-blocking module timer with the configured duration (default 800ms), and automatically sets the variable back to false when the timer elapses

#### Scenario: Shelly 1 is offline
- **WHEN** the user triggers a door impulse while the reachability variable is false
- **THEN** the system aborts the impulse and logs a warning in IP-Symcon without hanging or deadlocking

### Requirement: Garage Door State Tracking
The system SHALL maintain a state machine reflecting the physical garage door state (CLOSED, OPENING, OPEN, CLOSING, STOPPED).

#### Scenario: Door begins opening from closed state
- **WHEN** an impulse is sent while the door is CLOSED and the bottom magnetic contact reports false
- **THEN** the system transitions to the OPENING state, marks the start timestamp, and begins runtime position estimation

#### Scenario: Bottom reed switch triggers closed state
- **WHEN** the bottom magnetic contact variable switches to false (closed)
- **THEN** the system immediately transitions to CLOSED, hard-resets the estimated position to 0%, and halts active travel timers

### Requirement: Runtime-Based Position Estimation
The system SHALL estimate door opening percentage (0% to 100%) during motion based on configured opening and closing travel times.

#### Scenario: Door opens during travel
- **WHEN** the door is in the OPENING state and time t has elapsed since impulse
- **THEN** estimated position is calculated as min(100, (elapsedTime / TravelTimeOpenSec) * 100)

#### Scenario: Door reaches full open duration
- **WHEN** elapsed opening time reaches TravelTimeOpenSec
- **THEN** the system sets the position to 100% and transitions to OPEN state

### Requirement: Door Open Duration Tracking
The system SHALL track how long the garage door has been continuously open.

#### Scenario: Door remains open past warning threshold
- **WHEN** the door is in an open or stopped state longer than WarnOpenMinutes
- **THEN** the system flags a duration warning flag in the module payload and detail variables

### Requirement: Preset Target Movement Control
The system SHALL support moving the garage door to an arbitrary intermediate target percentage and automatically stopping it via a timed relay impulse.

#### Scenario: Initiating movement to ventilation position from closed state
- **WHEN** user activates the ventilation preset while the door is CLOSED (0%) and ventilation target is 15%
- **THEN** the system triggers the initial relay impulse to start OPENING, schedules a TargetStop timer for `(15 / 100) * TravelTimeOpenSec`, and triggers a second relay impulse when the timer fires to stop the door at 15%

#### Scenario: Cancelling target stop upon manual user override
- **WHEN** user manually triggers a door impulse while an automatic target stop timer is active
- **THEN** the system immediately cancels the TargetStop timer and executes the manual impulse without scheduling conflicting stops

### Requirement: Configurable Ventilation Position Variable
The system SHALL maintain a child variable for the ventilation position percentage that is editable from the WebFront detail view.

#### Scenario: User adjusts ventilation percentage in detail view
- **WHEN** user changes the VentilationPosition variable in the visualization detail view or via RequestAction
- **THEN** the system updates the variable, validates the range (0% to 100%), and pushes the updated preset configuration to connected visualization tiles

### Requirement: Freely Configurable Custom Preset
The system SHALL support an optional secondary preset with customizable label and target position percentage configured in module settings.

#### Scenario: Triggering custom preset
- **WHEN** user triggers the custom preset action while custom preset is enabled and configured to 50%
- **THEN** the system calculates the required runtime to reach 50% and schedules the stopping impulse accordingly

### Requirement: Full-Cycle Travel Time Measurement
The system SHALL measure and record the exact physical travel time when the garage door moves uninterrupted from fully open (100%) to fully closed (0%) until the bottom closed sensor trips.

#### Scenario: Uninterrupted close cycle completes at closed sensor
- **WHEN** the door starts closing from `STATE_OPEN` (100%) and reaches the closed contact without intermediate stop or direction reversal
- **THEN** the system calculates the elapsed closing time in seconds (`microtime(true) - MotionStartTime`), verifies it is within a valid sanity range (5.0s to 90.0s), and persists the value in the `MeasuredCloseTimeSec` attribute and updates the `CalibratedTravelTime` child variable

#### Scenario: Interrupted closing cycle does not calibrate
- **WHEN** the door stops midway or changes direction before reaching the closed contact
- **THEN** the system does not record a new calibrated closing time and retains the previous measured value

### Requirement: Automatic Travel Time Calibration
The system SHALL support automatic calibration of the active closing travel time based on measured full-cycle durations.

#### Scenario: Auto-calibration updates runtime travel time
- **WHEN** a valid full-cycle closing duration is measured and `AutoCalibrateTravelTime` is enabled
- **THEN** the system updates the effective closing travel time and sets the instance property `TravelTimeCloseSec` to the rounded measured duration, ensuring subsequent position calculations and preset movements use the calibrated time

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
