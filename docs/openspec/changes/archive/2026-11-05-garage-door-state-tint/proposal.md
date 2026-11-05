## Why

The garage door tile shows the door state only in a small pill ("Geschlossen" / "Offen"). The door graphic itself stays neutral grey in every state, so the most important information on the tile — is the door open or closed — needs a second look at a small label instead of being readable at a glance.

## What Changes

- The door frame and the door slats take a tint derived from the current state colour.
- Closed reads green, open reads red, moving reads blue/amber, stopped and offline stay neutral.
- The tint is muted, not a full repaint: the door must still look like a garage door, not like a coloured block.
- The tint works in both the dark and the light visualization design.

## Capabilities

### Modified Capabilities
- `garage-door-tile-ui`: the door graphic gains a state-dependent tint.

## Impact

- `GarageDoorTile/module.html` only. No backend change, no new variables, no writes to the bus.
