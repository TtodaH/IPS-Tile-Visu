## 1. Property, Attribute and Variable Registration

- [x] 1.1 Register property `AutoCalibrateTravelTime` (Boolean, default true) and attributes `MeasuredCloseTimeSec` (Float) and `IsFullCloseRun` (Boolean) in `GarageDoorTile/module.php`
- [x] 1.2 Maintain child variable `CalibratedTravelTime` (Float, formatted in seconds with 1 decimal place) in `ApplyChanges()`
- [x] 1.3 Add `AutoCalibrateTravelTime` checkbox and calibration info to `GarageDoorTile/form.json` and update `GarageDoorTile/locale.json`

## 2. Measurement & Calibration Logic

- [x] 2.1 Set `IsFullCloseRun = true` when closing starts from `STATE_OPEN` (100%), and clear `IsFullCloseRun = false` on manual stop or direction reversal
- [x] 2.2 In `SyncWithSensors()` / `HandleTravelProgress()`, measure elapsed closing time upon closed sensor trip when `IsFullCloseRun` is true
- [x] 2.3 Validate duration within sanity range (5.0s to 90.0s), update `MeasuredCloseTimeSec` and child variable `CalibratedTravelTime`, and update `TravelTimeCloseSec` property if `AutoCalibrateTravelTime` is active
- [x] 2.4 Use calibrated travel time dynamically in runtime position estimation and preset movement calculations

## 3. Deployment & Verification

- [x] 3.1 Commit and push changes to GitHub and Gitea, pull on `unserver`, reload module and apply changes on instance 23387
- [x] 3.2 Verify variable creation, property configuration, and simulation/readiness in IP-Symcon
