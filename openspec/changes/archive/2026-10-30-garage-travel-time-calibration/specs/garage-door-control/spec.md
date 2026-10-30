## ADDED Requirements

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
