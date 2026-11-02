## Why

Currently, garage door travel times (`TravelTimeOpenSec` and `TravelTimeCloseSec`) are statically configured fixed numbers (e.g., 16 seconds). Mechanical tolerances, temperature changes, and track resistance cause real travel times to vary slightly, causing position estimation and preset stopping positions (such as ventilation mode) to drift over time. By automatically measuring the exact duration of full, uninterrupted closing cycles from fully open (100%) until the physical magnetic contact (0%) trips, the module can learn the actual travel time and calibrate the percentage estimation automatically.

## What Changes

- Automatically record and measure the exact duration (in seconds, with decimal precision) during uninterrupted full closing cycles (from `STATE_OPEN` to `STATE_CLOSED` triggered by `SensorClosedVariable`).
- Store the last measured travel time in a persistent module attribute (`MeasuredCloseTimeSec`) and maintain a child variable `Gemessene Fahrzeit` (Calibrated Travel Time) for transparency in the WebFront / detail view.
- Provide an `AutoCalibrateTravelTime` toggle (default: true) in the instance configuration: when enabled, the module automatically updates the effective closing travel time (and optionally updates the instance property) so that position estimation and preset movements use the calibrated real-world timing.
- Expose the measured travel time and calibration status in the module configuration form and debug logs.

## Capabilities

### Modified Capabilities
- `garage-door-control`: Add requirements for automatic travel time measurement on full closing cycles and dynamic calibration of position estimation.

## Impact

- `GarageDoorTile/module.php`: Measurement logic in `SyncWithSensors()` / `HandleTravelProgress()`, child variable registration, attribute persistence, and dynamic travel time resolution.
- `GarageDoorTile/form.json`: Configuration elements for `AutoCalibrateTravelTime` and read-only status display of the last measured time.
- `GarageDoorTile/locale.json`: German and English translations for calibration labels.
