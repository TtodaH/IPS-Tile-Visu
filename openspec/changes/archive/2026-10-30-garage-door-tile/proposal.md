## Why

The current garage door setup requires manual off/on toggling of a Shelly 1 switch (`ID 44039`) because the garage door motor requires a momentary contact impulse (Tasterimpuls) rather than a continuous toggle switch. Furthermore, existing automations and workflows (such as `54874` and `49291`) operate unreliably with scripts and timers, while the UI lacks visualization of movement state (opening/closing), estimated percentage of door opening, and door open duration.

Introducing `garage-door-tile` provides a dedicated, native IP-Symcon 7+/9+ HTML-SDK tile module with automated, non-blocking impulse triggering, intelligent state-machine tracking, percentage estimation, and seamless tile visualization.

## What Changes

* **New Module `GarageDoorTile`**: Production-ready IP-Symcon module using the native HTML-SDK (`SetVisualizationType(1)`).
* **Automated Momentary Contact (Impulse) Controller**: Replaces manual toggle switching with an automatic Shelly 1 impulse (`RequestAction(44039, true)`, configurable hold time of e.g. 800ms, and automatic reset to `false` via a non-blocking module timer).
* **Door State Machine & Runtime Estimation**: Tracks door state (`CLOSED`, `OPENING`, `OPEN`, `CLOSING`, `STOPPED`) and estimates current opening percentage (0–100%) based on configured opening and closing travel times, calibrated by the bottom reed switch (`ID 39824`).
* **HTML-SDK Tile Visualization**: Modern responsive tile with animated sectional garage door graphics, one-touch control button, open duration counter, and theme-blended styling.
* **Child Status Variables**: Automatically registers native IP-Symcon status variables (`MaintainVariable`) for fullscreen/detail mode (`⤢`).
* **Workflow Deprecation**: Replaces flaky legacy scripts and workflows (`38346`, `54874`, `49291`).

## Capabilities

### New Capabilities
- `garage-door-control`: Automated impulse contact control, non-blocking Shelly 1 pulse simulation, travel time calibration, and door state-machine tracking.
- `garage-door-tile-ui`: Modern IP-Symcon 7+/9+ HTML-SDK tile visualization with animated door, live status badge, open-since counter, and one-touch interactive button.

### Modified Capabilities

None.

## Impact

* **New Module Directory**: `GarageDoorTile/` containing `module.json`, `module.php`, `module.html`, `form.json`, and `locale.json`.
* **Hardware Integration**: Shelly 1 (`44039`, switch actuator), Shelly Pro 4PM (`39824`, closed sensor input), and Shelly reachability (`30959`).
* **Legacy Automations**: Workflows `54874` and `49291` and script `38346` become redundant and can be retired.
