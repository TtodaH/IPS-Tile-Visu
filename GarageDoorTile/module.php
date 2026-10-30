<?php

declare(strict_types=1);

if (!defined('VM_UPDATE')) {
    define('VM_UPDATE', 10603);
}

/**
 * GarageDoorTile
 *
 * Smarte, flackerfreie Kachel-Visualisierung (HTML-SDK) für Garagentore.
 * Simuliert einen sauberen Taster-Impuls (Shelly 1), führt eine State-Machine
 * mit Schätzung des Öffnungsgrads (0-100%) und kalibriert sich am Endlagensensor.
 *
 * @author Thomas Hellstern
 */
class GarageDoorTile extends IPSModule
{
    // Zustands-Konstanten
    public const STATE_CLOSED  = 0;
    public const STATE_OPENING = 1;
    public const STATE_OPEN    = 2;
    public const STATE_CLOSING = 3;
    public const STATE_STOPPED = 4;

    public function Create(): void
    {
        parent::Create();

        // Aktiviert das IP-Symcon HTML-SDK für Kachelvisualisierung (Typ 1)
        $this->SetVisualizationType(1);

        // Hardware- & Sensorzuordnung
        $this->RegisterPropertyInteger('SwitchVariable', 0);       // z.B. 44039 (Shelly 1 Relais)
        $this->RegisterPropertyInteger('SensorClosedVariable', 0); // z.B. 39824 (Shelly Pro 4PM Input)
        $this->RegisterPropertyBoolean('SensorInverted', false);    // false = 0 ist geschlossen
        $this->RegisterPropertyInteger('ReachableVariable', 0);    // z.B. 30959 (Online-Status)

        // Zeiten & Parameter
        $this->RegisterPropertyInteger('ImpulseDurationMs', 400);  // 400 ms Taster-Haltezeit (Shelly 1)
        $this->RegisterPropertyInteger('TravelTimeOpenSec', 16);   // 16s Fahrzeit Öffnen
        $this->RegisterPropertyInteger('TravelTimeCloseSec', 16);  // 16s Fahrzeit Schließen
        $this->RegisterPropertyBoolean('AutoCalibrateTravelTime', true); // Automatische Kalibrierung am Endlagensensor
        $this->RegisterPropertyInteger('WarnOpenMinutes', 30);     // Warnung ab 30 Min. offen

        // Push-Benachrichtigung
        $this->RegisterPropertyBoolean('EnablePushNotification', true);
        $this->RegisterPropertyInteger('PushInstanceID', 0);              // 0 = Automatisch ermitteln
        $this->RegisterPropertyInteger('PushRepeatIntervalMinutes', 15);  // 0 = nur einmalig, sonst alle X Minuten

        // Presets & Positionen
        $this->RegisterPropertyInteger('DefaultVentilationPosition', 15); // Standard-Lüftung 15%
        $this->RegisterPropertyBoolean('CustomPresetEnabled', true);
        $this->RegisterPropertyString('CustomPresetLabel', 'Halb offen');
        $this->RegisterPropertyInteger('CustomPresetPosition', 50);

        // Darstellung
        $this->RegisterPropertyString('TileTitle', 'Garagentor');
        $this->RegisterPropertyBoolean('ShowPercentage', true);
        $this->RegisterPropertyBoolean('ShowOpenDuration', true);
        $this->RegisterPropertyBoolean('ShowReachableBadge', true);

        // Modul-Timer
        $this->RegisterTimer('ImpulseOff', 0, 'GARDOOR_HandleImpulseOff($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TravelProgress', 0, 'GARDOOR_HandleTravelProgress($_IPS[\'TARGET\']);');
        $this->RegisterTimer('OpenDurationTick', 0, 'GARDOOR_HandleOpenDurationTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TargetStop', 0, 'GARDOOR_HandleTargetStop($_IPS[\'TARGET\']);');

        // Modul-Attribute für persistente Zustandsspeicherung
        $this->RegisterAttributeInteger('DoorState', self::STATE_CLOSED);
        $this->RegisterAttributeFloat('CurrentPosition', 0.0);
        $this->RegisterAttributeFloat('MotionStartTime', 0.0);
        $this->RegisterAttributeFloat('MotionStartPosition', 0.0);
        $this->RegisterAttributeInteger('OpenedAtTimestamp', 0);
        $this->RegisterAttributeInteger('LastDirection', 1); // 1 = nach oben, -1 = nach unten
        $this->RegisterAttributeFloat('TargetPosition', -1.0); // -1 = kein aktives Zielfahren
        $this->RegisterAttributeFloat('LastImpulseOffTime', 0.0);
        $this->RegisterAttributeFloat('MeasuredCloseTimeSec', 0.0);
        $this->RegisterAttributeBoolean('IsFullCloseRun', false);
        $this->RegisterAttributeInteger('LastPushSentTimestamp', 0);
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        // 1. Nachrichten & Referenzen bereinigen
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        // 2. Variablen registrieren
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
        $reachID  = $this->ReadPropertyInteger('ReachableVariable');

        // 3. Eigene Profile anlegen
        if (!IPS_VariableProfileExists('GARDOOR.State')) {
            IPS_CreateVariableProfile('GARDOOR.State', 1); // Integer
            IPS_SetVariableProfileIcon('GARDOOR.State', 'Garage');
            IPS_SetVariableProfileAssociation('GARDOOR.State', self::STATE_CLOSED, $this->Translate('Geschlossen'), 'Garage', 0x10B981);
            IPS_SetVariableProfileAssociation('GARDOOR.State', self::STATE_OPENING, $this->Translate('Wird geöffnet...'), 'HollowArrowUp', 0x38BDF8);
            IPS_SetVariableProfileAssociation('GARDOOR.State', self::STATE_OPEN, $this->Translate('Offen'), 'Garage', 0xEF4444);
            IPS_SetVariableProfileAssociation('GARDOOR.State', self::STATE_CLOSING, $this->Translate('Wird geschlossen...'), 'HollowArrowDown', 0xF59E0B);
            IPS_SetVariableProfileAssociation('GARDOOR.State', self::STATE_STOPPED, $this->Translate('Gestoppt'), 'Hourglass', 0x94A3B8);
        }

        if (!IPS_VariableProfileExists('GARDOOR.TravelTime')) {
            IPS_CreateVariableProfile('GARDOOR.TravelTime', 2); // Float
            IPS_SetVariableProfileIcon('GARDOOR.TravelTime', 'Clock');
            IPS_SetVariableProfileText('GARDOOR.TravelTime', '', ' s');
            IPS_SetVariableProfileDigits('GARDOOR.TravelTime', 1);
        }

        // 4. Native Child-Statusvariablen pflegen (für Detail-/Vollbildansicht)
        $this->MaintainVariable('State', $this->Translate('Torzustand'), 1, 'GARDOOR.State', 1, true);
        $this->MaintainVariable('Position', $this->Translate('Position'), 2, '~ShutterMoveStep', 2, true);
        $this->MaintainVariable('OpenDuration', $this->Translate('Offen seit'), 1, '', 3, true);
        $this->MaintainVariable('ImpulseActive', $this->Translate('Taster-Impuls'), 0, '~Switch', 4, true);

        // Child-Variable für Lüftungsstellung (in der Detailansicht einstellbar)
        $this->MaintainVariable('VentilationPosition', $this->Translate('Lüftungsstellung'), 1, '~ShutterMoveStep', 5, true);
        $this->EnableAction('VentilationPosition');

        // Child-Variable für gemessene/kalibrierte Fahrzeit
        $this->MaintainVariable('CalibratedTravelTime', $this->Translate('Gemessene Fahrzeit'), 2, 'GARDOOR.TravelTime', 6, true);
        $measuredTime = $this->ReadAttributeFloat('MeasuredCloseTimeSec');
        if ($measuredTime > 0.0) {
            $this->SetValue('CalibratedTravelTime', $measuredTime);
        }

        // Wert mit der Instanz-Konfiguration abgleichen
        $configuredVent = $this->ReadPropertyInteger('DefaultVentilationPosition');
        if ($configuredVent >= 0 && $configuredVent <= 100) {
            $this->SetValue('VentilationPosition', $configuredVent);
        }

        // Variablen für Aktualisierungsnachrichten registrieren
        $ventVarID = @$this->GetIDForIdent('VentilationPosition');
        $vars = [$switchID, $sensorID, $reachID];
        if ($ventVarID > 0) {
            $vars[] = $ventVarID;
        }
        foreach ($vars as $vID) {
            if ($vID > 0 && @IPS_VariableExists($vID)) {
                $this->RegisterReference($vID);
                $this->RegisterMessage($vID, VM_UPDATE);
            }
        }

        // 5. Initialen Zustand anhand des echten Endlagensensors abgleichen
        $this->SyncWithSensors();

        // 6. Initiale Visualisierungsdaten senden
        $this->SendTileUpdate();
    }

    /**
     * Liefert den persistenten HTML-Frame für die Visualisierung
     */
    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';
        if (!file_exists($htmlFile)) {
            return '<div style="color:red;padding:10px;">module.html nicht gefunden!</div>';
        }

        $html = file_get_contents($htmlFile);

        $payload = $this->GetTilePayload();

        // Frame-0 Vorbefüllung der HTML-Texte (Zero-Delay)
        $ventLabel    = $payload['presets']['ventilation']['label'] ?? 'Lüften';
        $customLabel  = $payload['presets']['custom']['label'] ?? 'Preset';
        $btnText      = $payload['buttonText'] ?? 'Tor bedienen';
        $stateText    = $payload['stateText'] ?? 'Geschlossen';
        $posFormatted = $payload['positionFormatted'] ?? '0 %';

        $html = str_replace(
            [
                'id="statusText">Geschlossen</span>',
                'id="btnText">Tor bedienen</span>',
                'id="posBadge">0 %</div>',
                'id="presetVentLabel">Lüften 15%</span>',
                'id="presetCustomLabel">Halb offen 50%</span>'
            ],
            [
                'id="statusText">' . htmlspecialchars($stateText) . '</span>',
                'id="btnText">' . htmlspecialchars($btnText) . '</span>',
                'id="posBadge">' . htmlspecialchars($posFormatted) . '</div>',
                'id="presetVentLabel">' . htmlspecialchars($ventLabel) . '</span>',
                'id="presetCustomLabel">' . htmlspecialchars($customLabel) . '</span>'
            ],
            $html
        );

        if (($payload['presets']['custom']['enabled'] ?? true) === false) {
            $html = str_replace('id="presetCustomBtn"', 'id="presetCustomBtn" style="display:none;"', $html);
        }

        // Initiales Datenpaket direkt übergeben
        $initialData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $script = '<script>window.__INITIAL_DATA__ = ' . $initialData . '; if (typeof handleMessage === "function") { handleMessage(window.__INITIAL_DATA__); }</script>';

        return str_replace('</body>', $script . '</body>', $html);
    }

    /**
     * Empfängt Klicks und Aktionen aus der Kachel-UI sowie native Variablenänderungen
     */
    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'triggerDoor':
                $this->TriggerDoor();
                break;

            case 'triggerPresetVentilation':
                $this->TriggerPresetVentilation();
                break;

            case 'triggerCustomPreset':
                $this->TriggerCustomPreset();
                break;

            case 'moveToPosition':
                $this->MoveToPosition((float)$Value);
                break;

            case 'VentilationPosition':
                $val = max(0, min(100, (int)$Value));
                $this->SetValue('VentilationPosition', $val);
                if ($this->ReadPropertyInteger('DefaultVentilationPosition') !== $val) {
                    @IPS_SetProperty($this->InstanceID, 'DefaultVentilationPosition', $val);
                }
                $this->SendTileUpdate();
                break;

            case 'refresh':
                $this->SendTileUpdate();
                break;

            default:
                $this->SendDebug('RequestAction', "Unbekannter Ident: $Ident", 0);
                break;
        }
    }

    /**
     * Schaltet das Relais hardwareseitig für die konfigurierte Impulsdauer ein
     */
    private function PulseRelay(): bool
    {
        $reachID = $this->ReadPropertyInteger('ReachableVariable');
        if ($reachID > 0 && @IPS_VariableExists($reachID) && !GetValueBoolean($reachID)) {
            $this->SendDebug('PulseRelay', 'Abbruch: Shelly Aktor ist offline!', 0);
            return false;
        }

        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        if ($switchID <= 0 || !@IPS_VariableExists($switchID)) {
            $this->SendDebug('PulseRelay', 'Fehler: Keine Schalter-Variable konfiguriert!', 0);
            return false;
        }

        // Relais hardwareseitig einschalten
        @RequestAction($switchID, true);
        $this->SetValue('ImpulseActive', true);

        // Automatisches Abschalten via Modul-Timer (Standard: 800 ms)
        $durationMs = max(200, $this->ReadPropertyInteger('ImpulseDurationMs'));
        $this->SetTimerInterval('ImpulseOff', $durationMs);

        return true;
    }

    /**
     * Haupt-Aktion: Löst einen sicheren, zeitgesteuerten Tasterimpuls am Torantrieb aus
     */
    public function TriggerDoor(): void
    {
        // Aktiven TargetStop-Timer sofort abbrechen
        $this->SetTimerInterval('TargetStop', 0);
        $this->WriteAttributeFloat('TargetPosition', -1.0);

        // Schutz gegen Prellen/Doppelklick: Wenn das Relais gerade noch aktiv ist, ignorieren
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        if ($switchID > 0 && @IPS_VariableExists($switchID) && GetValueBoolean($switchID)) {
            $this->SendDebug('TriggerDoor', 'Impuls läuft bereits, Klick ignoriert', 0);
            return;
        }

        if (!$this->PulseRelay()) {
            return;
        }

        // State-Machine weiterschalten
        $currentState = $this->ReadAttributeInteger('DoorState');
        $currentPos   = $this->ReadAttributeFloat('CurrentPosition');
        $lastDir      = $this->ReadAttributeInteger('LastDirection');
        $now          = microtime(true);

        switch ($currentState) {
            case self::STATE_CLOSED:
                // Aus dem geschlossenen Zustand startet immer das Öffnen
                $this->WriteAttributeInteger('DoorState', self::STATE_OPENING);
                $this->WriteAttributeFloat('MotionStartTime', $now);
                $this->WriteAttributeFloat('MotionStartPosition', 0.0);
                $this->WriteAttributeInteger('LastDirection', 1);
                $this->WriteAttributeInteger('OpenedAtTimestamp', time());
                $this->WriteAttributeBoolean('IsFullCloseRun', false);
                $this->SetTimerInterval('TravelProgress', 250);
                $this->SetTimerInterval('OpenDurationTick', 60000);
                break;

            case self::STATE_OPENING:
                // Klick während Fahrt nach oben -> Stopp
                $this->WriteAttributeInteger('DoorState', self::STATE_STOPPED);
                $this->WriteAttributeBoolean('IsFullCloseRun', false);
                $this->SetTimerInterval('TravelProgress', 0);
                $this->WriteAttributeInteger('LastDirection', 1);
                break;

            case self::STATE_OPEN:
                // Aus dem offenen Zustand startet das Schließen (voller Schließlauf für Kalibrierung vorgemerkt)
                $this->WriteAttributeInteger('DoorState', self::STATE_CLOSING);
                $this->WriteAttributeFloat('MotionStartTime', $now);
                $this->WriteAttributeFloat('MotionStartPosition', $currentPos > 0 ? $currentPos : 100.0);
                $this->WriteAttributeInteger('LastDirection', -1);
                $this->WriteAttributeBoolean('IsFullCloseRun', ($currentPos >= 98.0));
                $this->SetTimerInterval('TravelProgress', 250);
                break;

            case self::STATE_CLOSING:
                // Klick während Fahrt nach unten -> Stopp
                $this->WriteAttributeInteger('DoorState', self::STATE_STOPPED);
                $this->WriteAttributeBoolean('IsFullCloseRun', false);
                $this->SetTimerInterval('TravelProgress', 0);
                $this->WriteAttributeInteger('LastDirection', -1);
                break;

            case self::STATE_STOPPED:
                // Klick im Stillstand -> Fahrt in die Gegenrichtung
                $this->WriteAttributeBoolean('IsFullCloseRun', false);
                $newDir = ($lastDir === 1) ? -1 : 1;
                $newState = ($newDir === 1) ? self::STATE_OPENING : self::STATE_CLOSING;
                $this->WriteAttributeInteger('DoorState', $newState);
                $this->WriteAttributeFloat('MotionStartTime', $now);
                $this->WriteAttributeFloat('MotionStartPosition', $currentPos);
                $this->WriteAttributeInteger('LastDirection', $newDir);
                $this->SetTimerInterval('TravelProgress', 250);
                break;
        }

        $this->UpdateChildVariables();
        $this->SendTileUpdate();
    }

    /**
     * Fährt das Tor auf eine definierte Zielposition (0-100%) und stoppt automatisch per Impuls
     */
    public function MoveToPosition(float $targetPos): void
    {
        $targetPos = max(0.0, min(100.0, $targetPos));
        $currentState = $this->ReadAttributeInteger('DoorState');
        $currentPos   = $this->ReadAttributeFloat('CurrentPosition');

        // Bereits am Ziel?
        if (abs($currentPos - $targetPos) < 2.0 && in_array($currentState, [self::STATE_CLOSED, self::STATE_OPEN, self::STATE_STOPPED], true)) {
            $this->SendDebug('MoveToPosition', "Tor bereits auf Zielposition {$targetPos}%", 0);
            return;
        }

        // Falls das Tor gerade fährt -> manueller Stopp
        if ($currentState === self::STATE_OPENING || $currentState === self::STATE_CLOSING) {
            $this->TriggerDoor();
            return;
        }

        // Relais noch aktiv? Nicht starten, um Fehlschaltungen zu verhindern
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        if ($switchID > 0 && @IPS_VariableExists($switchID) && GetValueBoolean($switchID)) {
            $this->SendDebug('MoveToPosition', 'Relais noch aktiv, Aktion verworfen', 0);
            return;
        }

        // Eventuell aktiven TargetStop-Timer zunächst resetten
        $this->SetTimerInterval('TargetStop', 0);
        $this->WriteAttributeFloat('TargetPosition', -1.0);

        // Volles Öffnen (100%) oder volles Schließen (0%)
        if ($targetPos >= 98.0 && $currentState === self::STATE_CLOSED) {
            $this->TriggerDoor();
            return;
        }
        if ($targetPos <= 2.0 && ($currentState === self::STATE_OPEN || $currentState === self::STATE_STOPPED)) {
            $this->TriggerDoor();
            return;
        }

        $openTime  = $this->GetEffectiveTravelTimeOpen();
        $closeTime = $this->GetEffectiveTravelTimeClose();
        $impulseMs = max(200, $this->ReadPropertyInteger('ImpulseDurationMs'));
        // Mindestlaufzeit: Impulsdauer + 1000ms Pause (für zuverlässige Erkennung des 2. Impulses durch die Torsteuerung)
        $minStopDelayMs = (int)($impulseMs + 1000);
        $now       = microtime(true);

        if ($currentState === self::STATE_CLOSED) {
            // Aus geschlossenem Zustand: Startet Öffnen
            $durationSec = ($targetPos / 100.0) * $openTime;
            $durationMs  = (int)round($durationSec * 1000);

            if (!$this->PulseRelay()) {
                return;
            }

            $this->WriteAttributeInteger('DoorState', self::STATE_OPENING);
            $this->WriteAttributeFloat('MotionStartTime', $now);
            $this->WriteAttributeFloat('MotionStartPosition', 0.0);
            $this->WriteAttributeInteger('LastDirection', 1);
            $this->WriteAttributeInteger('OpenedAtTimestamp', time());
            $this->WriteAttributeFloat('TargetPosition', $targetPos);
            $this->WriteAttributeBoolean('IsFullCloseRun', false);

            $this->SetTimerInterval('TargetStop', max($minStopDelayMs, $durationMs));
            $this->SetTimerInterval('TravelProgress', 250);
            $this->SetTimerInterval('OpenDurationTick', 60000);
        } elseif ($currentState === self::STATE_OPEN) {
            // Aus offenem Zustand: Startet Schließen
            $durationSec = ((100.0 - $targetPos) / 100.0) * $closeTime;
            $durationMs  = (int)round($durationSec * 1000);

            if (!$this->PulseRelay()) {
                return;
            }

            $this->WriteAttributeInteger('DoorState', self::STATE_CLOSING);
            $this->WriteAttributeFloat('MotionStartTime', $now);
            $this->WriteAttributeFloat('MotionStartPosition', 100.0);
            $this->WriteAttributeInteger('LastDirection', -1);
            $this->WriteAttributeFloat('TargetPosition', $targetPos);
            $this->WriteAttributeBoolean('IsFullCloseRun', ($targetPos <= 2.0));

            $this->SetTimerInterval('TargetStop', max($minStopDelayMs, $durationMs));
            $this->SetTimerInterval('TravelProgress', 250);
        } elseif ($currentState === self::STATE_STOPPED) {
            // Im Stillstand (Zwischenposition)
            if ($targetPos > $currentPos) {
                // Öffnen
                $durationSec = (($targetPos - $currentPos) / 100.0) * $openTime;
                $durationMs  = (int)round($durationSec * 1000);
                $newState    = self::STATE_OPENING;
                $dir         = 1;
            } else {
                // Schließen
                $durationSec = (($currentPos - $targetPos) / 100.0) * $closeTime;
                $durationMs  = (int)round($durationSec * 1000);
                $newState    = self::STATE_CLOSING;
                $dir         = -1;
            }

            if (!$this->PulseRelay()) {
                return;
            }

            $this->WriteAttributeInteger('DoorState', $newState);
            $this->WriteAttributeFloat('MotionStartTime', $now);
            $this->WriteAttributeFloat('MotionStartPosition', $currentPos);
            $this->WriteAttributeInteger('LastDirection', $dir);
            $this->WriteAttributeFloat('TargetPosition', $targetPos);
            $this->WriteAttributeBoolean('IsFullCloseRun', false);

            $this->SetTimerInterval('TargetStop', max($minStopDelayMs, $durationMs));
            $this->SetTimerInterval('TravelProgress', 250);
        }

        $this->UpdateChildVariables();
        $this->SendTileUpdate();
    }

    /**
     * Stoppt die Torfahrt bei Erreichen der Zielposition (TargetPosition)
     */
    public function HandleTargetStop(): void
    {
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        $isRelayOn = ($switchID > 0 && @IPS_VariableExists($switchID)) ? GetValueBoolean($switchID) : false;

        $lastOff = $this->ReadAttributeFloat('LastImpulseOffTime');
        $now = microtime(true);
        $minPauseSec = 0.8; // Mindestens 800 ms Pause zwischen Impulsen für saubere Entprellung

        if ($isRelayOn || ($now - $lastOff < $minPauseSec)) {
            // Relais ist noch aktiv oder die Pause zwischen den Impulsen ist noch zu kurz
            $remainingMs = $isRelayOn ? 400 : (int)ceil(($minPauseSec - ($now - $lastOff)) * 1000);
            $this->SetTimerInterval('TargetStop', max(150, $remainingMs));
            $this->SendDebug('TargetStop', "Warte auf Relais/Pause: Reschedule um {$remainingMs} ms", 0);
            return;
        }

        $this->SetTimerInterval('TargetStop', 0);
        $targetPos = $this->ReadAttributeFloat('TargetPosition');
        $this->WriteAttributeFloat('TargetPosition', -1.0);

        // Stopp-Impuls senden
        $this->PulseRelay();

        // Zustand auf STOPPED setzen und Position kalibrieren
        $this->WriteAttributeInteger('DoorState', self::STATE_STOPPED);
        if ($targetPos >= 0.0) {
            $this->WriteAttributeFloat('CurrentPosition', $targetPos);
        }
        $this->SetTimerInterval('TravelProgress', 0);

        $this->UpdateChildVariables();
        $this->SendTileUpdate();
    }

    /**
     * Fährt die konfigurierte Lüftungsstellung an
     */
    public function TriggerPresetVentilation(): void
    {
        $ventVarID  = @$this->GetIDForIdent('VentilationPosition');
        $rawVentPos = ($ventVarID > 0) ? GetValueInteger($ventVarID) : $this->ReadPropertyInteger('DefaultVentilationPosition');
        $ventPos    = is_numeric($rawVentPos) ? (float)$rawVentPos : 15.0;
        $this->MoveToPosition($ventPos);
    }

    /**
     * Fährt das konfigurierte Custom-Preset an
     */
    public function TriggerCustomPreset(): void
    {
        $customPos = $this->ReadPropertyInteger('CustomPresetPosition');
        $this->MoveToPosition((float)$customPos);
    }

    /**
     * Schaltet das Relais nach Ablauf der Impulszeit wieder auf False
     */
    public function HandleImpulseOff(): void
    {
        $this->SetTimerInterval('ImpulseOff', 0);

        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        if ($switchID > 0 && @IPS_VariableExists($switchID)) {
            @RequestAction($switchID, false);
        }
        $this->SetValue('ImpulseActive', false);
        $this->WriteAttributeFloat('LastImpulseOffTime', microtime(true));
        $this->SendTileUpdate();
    }

    /**
     * Liefert die effektive Schließzeit (unter Berücksichtigung der automatischen Kalibrierung)
     */
    public function GetEffectiveTravelTimeClose(): float
    {
        if ($this->ReadPropertyBoolean('AutoCalibrateTravelTime')) {
            $measured = $this->ReadAttributeFloat('MeasuredCloseTimeSec');
            if ($measured >= 5.0 && $measured <= 90.0) {
                return $measured;
            }
        }
        return max(2.0, (float)$this->ReadPropertyInteger('TravelTimeCloseSec'));
    }

    /**
     * Liefert die effektive Öffnungszeit
     */
    public function GetEffectiveTravelTimeOpen(): float
    {
        return max(2.0, (float)$this->ReadPropertyInteger('TravelTimeOpenSec'));
    }

    /**
     * Taktet die Fahrzeit-Schätzung (alle 250 ms bei Bewegung)
     */
    public function HandleTravelProgress(): void
    {
        $state     = $this->ReadAttributeInteger('DoorState');
        $startPos  = $this->ReadAttributeFloat('MotionStartPosition');
        $startTime = $this->ReadAttributeFloat('MotionStartTime');
        $elapsed   = microtime(true) - $startTime;

        if ($state === self::STATE_OPENING) {
            $travelTime = $this->GetEffectiveTravelTimeOpen();
            $delta = ($elapsed / $travelTime) * (100.0 - $startPos);
            $newPos = min(100.0, $startPos + $delta);
            $this->WriteAttributeFloat('CurrentPosition', $newPos);

            if ($newPos >= 100.0) {
                // Vollständig geöffnet
                $this->WriteAttributeInteger('DoorState', self::STATE_OPEN);
                $this->SetTimerInterval('TravelProgress', 0);
            }
        } elseif ($state === self::STATE_CLOSING) {
            $travelTime = $this->GetEffectiveTravelTimeClose();
            $delta = ($elapsed / $travelTime) * $startPos;
            $newPos = max(0.0, $startPos - $delta);
            $this->WriteAttributeFloat('CurrentPosition', $newPos);

            if ($newPos <= 0.0) {
                // Rechnerisch geschlossen (wird vom Endlagensensor final bestätigt)
                $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
                if ($sensorID <= 0 || !@IPS_VariableExists($sensorID)) {
                    $this->WriteAttributeInteger('DoorState', self::STATE_CLOSED);
                    $this->WriteAttributeInteger('OpenedAtTimestamp', 0);
                    $this->SetTimerInterval('TravelProgress', 0);
                    $this->SetTimerInterval('OpenDurationTick', 0);
                } elseif ($this->IsSensorClosed()) {
                    $this->SyncWithSensors();
                    return;
                }
            }
        } else {
            // Keine Bewegung -> Timer stoppen
            $this->SetTimerInterval('TravelProgress', 0);
        }

        $this->UpdateChildVariables();
        $this->SendTileUpdate();
    }

    /**
     * Minutentakt für die Offen-Dauer
     */
    public function HandleOpenDurationTick(): void
    {
        $openedAt = $this->ReadAttributeInteger('OpenedAtTimestamp');
        if ($openedAt > 0) {
            $minutes = (int)floor((time() - $openedAt) / 60);
            $this->SetValue('OpenDuration', $minutes);

            // Push-Benachrichtigung bei Überschreitung der Warnzeit prüfen
            $this->CheckAndSendPushNotification($minutes);

            $this->SendTileUpdate();
        } else {
            $this->SetTimerInterval('OpenDurationTick', 0);
        }
    }

    /**
     * Sendet eine Push-Benachrichtigung über die konfigurierte oder automatisch ermittelte Visualisierungsinstanz
     */
    public function SendPushNotification(string $title, string $message): bool
    {
        $pushID = $this->ReadPropertyInteger('PushInstanceID');

        // Falls keine spezifische Instanz gewählt ist: Automatisch ermitteln
        if ($pushID <= 0 || !@IPS_InstanceExists($pushID)) {
            // 1. Suche nach moderner Kachelvisualisierung (Tile Visualization)
            $tileVisus = @IPS_GetInstanceListByModuleID('{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}');
            if (!empty($tileVisus)) {
                $pushID = $tileVisus[0];
            } else {
                // 2. Suche nach klassischem WebFront
                $wfcInsts = @IPS_GetInstanceListByModuleID('{3565B1F7-EB40-4610-983E-365A894C4D5B}');
                if (!empty($wfcInsts)) {
                    $pushID = $wfcInsts[0];
                } else {
                    // 3. Suche nach Notification Control
                    $ncInsts = @IPS_GetInstanceListByModuleID('{D4B231D6-8141-4B9E-9B32-82DA3AEEAB78}');
                    if (!empty($ncInsts)) {
                        $pushID = $ncInsts[0];
                    }
                }
            }
        }

        if ($pushID <= 0 || !@IPS_InstanceExists($pushID)) {
            $this->SendDebug('PushNotification', 'Abbruch: Keine gültige Visualisierungs-/Push-Instanz gefunden.', 0);
            return false;
        }

        $inst = @IPS_GetInstance($pushID);
        $moduleID = $inst['ModuleInfo']['ModuleID'] ?? '';

        try {
            if ($moduleID === '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}' && function_exists('VISU_PostNotification')) {
                // Moderne Kachel-Visualisierung (IP-Symcon 7+)
                @VISU_PostNotification($pushID, $title, $message, 'Garage', $this->InstanceID);
            } elseif ($moduleID === '{D4B231D6-8141-4B9E-9B32-82DA3AEEAB78}' && function_exists('NC_PushNotification')) {
                // Notification Control
                @NC_PushNotification($pushID, $title, $message, 'Alarm', $this->InstanceID);
            } elseif (function_exists('WFC_PushNotification')) {
                // Klassisches WebFront
                @WFC_PushNotification($pushID, $title, $message, 'alarm', $this->InstanceID);
            } elseif (function_exists('VISU_PostNotification')) {
                @VISU_PostNotification($pushID, $title, $message, 'Garage', $this->InstanceID);
            } else {
                $this->SendDebug('PushNotification', 'Keine passende Push-Funktion verfügbar.', 0);
                return false;
            }
            $this->SendDebug('PushNotification', "Push gesendet an Instanz {$pushID}: {$title} - {$message}", 0);
            return true;
        } catch (\Throwable $e) {
            $this->SendDebug('PushNotification', 'Fehler beim Senden: ' . $e->getMessage(), 0);
            return false;
        }
    }

    /**
     * Prüft, ob eine Push-Benachrichtigung wegen offener Garage gesendet werden muss
     */
    protected function CheckAndSendPushNotification(int $minutes): void
    {
        if (!$this->ReadPropertyBoolean('EnablePushNotification')) {
            return;
        }

        $warnThreshold = (int)$this->ReadPropertyInteger('WarnOpenMinutes');
        if ($warnThreshold <= 0 || $minutes < $warnThreshold) {
            return;
        }

        $now = time();
        $lastSent = (int)$this->ReadAttributeInteger('LastPushSentTimestamp');
        $repeatMinutes = (int)$this->ReadPropertyInteger('PushRepeatIntervalMinutes');

        $shouldSend = false;
        if ($lastSent === 0) {
            // Erstmalige Benachrichtigung
            $shouldSend = true;
        } elseif ($repeatMinutes > 0 && ($now - $lastSent) >= ($repeatMinutes * 60)) {
            // Wiederholung nach konfiguriertem Intervall
            $shouldSend = true;
        }

        if ($shouldSend) {
            $title = $this->Translate('Garagentor steht noch offen!');
            $text = sprintf($this->Translate('Das Garagentor ist seit %d Minuten geöffnet.'), $minutes);
            if ($this->SendPushNotification($title, $text)) {
                $this->WriteAttributeInteger('LastPushSentTimestamp', $now);
            }
        }
    }

    /**
     * Event-Sink für Variablenaktualisierungen
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        $sensorID  = $this->ReadPropertyInteger('SensorClosedVariable');
        $switchID  = $this->ReadPropertyInteger('SwitchVariable');
        $reachID   = $this->ReadPropertyInteger('ReachableVariable');
        $ventVarID = @$this->GetIDForIdent('VentilationPosition');

        if ($SenderID === $sensorID) {
            $this->SyncWithSensors();
            $this->SendTileUpdate();
        } elseif ($SenderID === $switchID || $SenderID === $reachID || ($ventVarID > 0 && $SenderID === $ventVarID)) {
            $this->SendTileUpdate();
        }
    }

    /**
     * Prüft, ob der Endlagensensor den Zustand "Geschlossen" meldet
     */
    private function IsSensorClosed(): bool
    {
        $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
        if ($sensorID <= 0 || !@IPS_VariableExists($sensorID)) {
            return false;
        }
        $raw = GetValueBoolean($sensorID);
        return $this->ReadPropertyBoolean('SensorInverted') ? $raw : !$raw;
    }

    /**
     * Synchronisiert den internen Zustand mit dem physischen Endlagensensor
     */
    private function SyncWithSensors(): void
    {
        $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
        if ($sensorID <= 0 || !@IPS_VariableExists($sensorID)) {
            return;
        }

        $isClosed = $this->IsSensorClosed();

        if ($isClosed) {
            // Tor ist physisch definitiv geschlossen
            $prevState   = $this->ReadAttributeInteger('DoorState');
            $startTime   = $this->ReadAttributeFloat('MotionStartTime');
            $isFullClose = $this->ReadAttributeBoolean('IsFullCloseRun');

            // Automatische Fahrzeit-Messung bei vollem Schließzyklus
            if ($isFullClose && $prevState === self::STATE_CLOSING && $startTime > 0.0) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed >= 5.0 && $elapsed <= 90.0) {
                    $roundedElapsed = round($elapsed, 1);
                    $this->WriteAttributeFloat('MeasuredCloseTimeSec', $roundedElapsed);
                    $this->SetValue('CalibratedTravelTime', $roundedElapsed);
                    $this->SendDebug('TravelTimeCalibration', "Voller Schließzyklus gemessen: {$roundedElapsed} s", 0);

                    if ($this->ReadPropertyBoolean('AutoCalibrateTravelTime')) {
                        $intSec = (int)round($roundedElapsed);
                        if ($this->ReadPropertyInteger('TravelTimeCloseSec') !== $intSec) {
                            @IPS_SetProperty($this->InstanceID, 'TravelTimeCloseSec', $intSec);
                            $this->SendDebug('TravelTimeCalibration', "TravelTimeCloseSec automatisch kalibriert auf: {$intSec} s", 0);
                        }
                    }
                }
            }

            $this->WriteAttributeBoolean('IsFullCloseRun', false);
            $this->WriteAttributeInteger('LastPushSentTimestamp', 0);
            $this->SetTimerInterval('TargetStop', 0);
            $this->WriteAttributeFloat('TargetPosition', -1.0);
            $this->WriteAttributeInteger('DoorState', self::STATE_CLOSED);
            $this->WriteAttributeFloat('CurrentPosition', 0.0);
            $this->WriteAttributeInteger('OpenedAtTimestamp', 0);
            $this->SetTimerInterval('TravelProgress', 0);
            $this->SetTimerInterval('OpenDurationTick', 0);
            $this->SetValue('OpenDuration', 0);
        } else {
            // Tor ist nicht geschlossen
            $state = $this->ReadAttributeInteger('DoorState');
            if ($state === self::STATE_CLOSED) {
                // Tor wurde z.B. per Handsender geöffnet
                $this->WriteAttributeInteger('DoorState', self::STATE_OPENING);
                $this->WriteAttributeFloat('MotionStartTime', microtime(true));
                $this->WriteAttributeFloat('MotionStartPosition', 0.0);
                $this->WriteAttributeInteger('OpenedAtTimestamp', time());
                $this->SetTimerInterval('TravelProgress', 250);
                $this->SetTimerInterval('OpenDurationTick', 60000);
            }
        }

        $this->UpdateChildVariables();
    }

    /**
     * Aktualisiert die nativen Child-Statusvariablen
     */
    private function UpdateChildVariables(): void
    {
        $state = $this->ReadAttributeInteger('DoorState');
        $pos   = $this->ReadAttributeFloat('CurrentPosition');
        $this->SetValue('State', $state);
        $this->SetValue('Position', round($pos, 1));
    }

    /**
     * Sendet das JSON-Datenpaket an das Frontend ohne DOM-Rebuild
     */
    public function SendTileUpdate(): void
    {
        $payload = $this->GetTilePayload();
        $this->UpdateVisualizationValue(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Erstellt das Datenpaket für die Kachel
     */
    protected function GetTilePayload(): array
    {
        $rawState = $this->ReadAttributeInteger('DoorState');
        $state    = is_numeric($rawState) ? (int)$rawState : self::STATE_CLOSED;

        $rawPos   = $this->ReadAttributeFloat('CurrentPosition');
        $pos      = is_numeric($rawPos) ? round((float)$rawPos, 0) : 0.0;

        $stateInfo = match ($state) {
            self::STATE_CLOSED  => ['key' => 'closed',  'text' => $this->Translate('Geschlossen'),     'color' => '#10B981', 'btn' => $this->Translate('Tor öffnen')],
            self::STATE_OPENING => ['key' => 'opening', 'text' => $this->Translate('Wird geöffnet...'), 'color' => '#38BDF8', 'btn' => $this->Translate('Tor stoppen')],
            self::STATE_OPEN    => ['key' => 'open',    'text' => $this->Translate('Offen'),           'color' => '#EF4444', 'btn' => $this->Translate('Tor schließen')],
            self::STATE_CLOSING => ['key' => 'closing', 'text' => $this->Translate('Wird geschlossen...'), 'color' => '#F59E0B', 'btn' => $this->Translate('Tor stoppen')],
            default             => ['key' => 'stopped', 'text' => $this->Translate('Gestoppt'),         'color' => '#94A3B8', 'btn' => $pos > 0 ? $this->Translate('Tor schließen') : $this->Translate('Tor öffnen')]
        };

        // Offen-Dauer berechnen
        $rawOpenedAt = $this->ReadAttributeInteger('OpenedAtTimestamp');
        $openedAt    = is_numeric($rawOpenedAt) ? (int)$rawOpenedAt : 0;
        $openMinutes = 0;
        $openDurationText = '';
        $isWarn = false;

        if ($state !== self::STATE_CLOSED && $openedAt > 0) {
            $openMinutes = max(0, (int)floor((time() - $openedAt) / 60));
            $openDurationText = ($openMinutes === 0) ? $this->Translate('Gerade geöffnet') : sprintf($this->Translate('Seit %d Min.'), $openMinutes);

            $warnThreshold = (int)($this->ReadPropertyInteger('WarnOpenMinutes') ?: 0);
            if ($warnThreshold > 0 && $openMinutes >= $warnThreshold) {
                $isWarn = true;
            }
        }

        // Online-Status des Shelly Aktors
        $reachID = (int)($this->ReadPropertyInteger('ReachableVariable') ?: 0);
        $isOnline = true;
        if ($reachID > 0 && @IPS_VariableExists($reachID)) {
            $isOnline = GetValueBoolean($reachID);
        }

        // Schalterzustand (Impuls aktiv)
        $switchID = (int)($this->ReadPropertyInteger('SwitchVariable') ?: 0);
        $impulseActive = false;
        if ($switchID > 0 && @IPS_VariableExists($switchID)) {
            $impulseActive = GetValueBoolean($switchID);
        }

        // Presets & Zielpositionen
        $ventVarID  = @$this->GetIDForIdent('VentilationPosition');
        $rawVentPos = ($ventVarID > 0) ? GetValueInteger($ventVarID) : $this->ReadPropertyInteger('DefaultVentilationPosition');
        $ventPos    = is_numeric($rawVentPos) ? (int)$rawVentPos : 15;
        if ($ventPos < 0 || $ventPos > 100) {
            $ventPos = 15;
        }

        $customEnabled = (bool)$this->ReadPropertyBoolean('CustomPresetEnabled');
        $customLabel   = (string)$this->ReadPropertyString('CustomPresetLabel');
        $rawCustomPos  = $this->ReadPropertyInteger('CustomPresetPosition');
        $customPos     = is_numeric($rawCustomPos) ? (int)$rawCustomPos : 50;

        $rawTarget = $this->ReadAttributeFloat('TargetPosition');
        $targetPos = is_numeric($rawTarget) ? (float)$rawTarget : -1.0;

        return [
            'state'                => $stateInfo['key'],
            'stateCode'            => $state,
            'stateText'            => $stateInfo['text'],
            'stateColor'           => $stateInfo['color'],
            'buttonText'           => $stateInfo['btn'],
            'position'             => (int)$pos,
            'positionFormatted'    => (int)$pos . ' %',
            'targetPosition'       => ($targetPos >= 0.0) ? (int)round($targetPos) : null,
            'openDurationMinutes'  => $openMinutes,
            'openDurationText'     => $openDurationText,
            'isWarning'            => $isWarn,
            'isOnline'             => $isOnline,
            'impulseActive'        => $impulseActive,
            'measuredCloseTime'    => $this->ReadAttributeFloat('MeasuredCloseTimeSec'),
            'isCalibrated'         => ($this->ReadAttributeFloat('MeasuredCloseTimeSec') > 0.0),
            'title'                => (string)$this->ReadPropertyString('TileTitle'),
            'presets'              => [
                'ventilation' => [
                    'position' => $ventPos,
                    'label'    => sprintf($this->Translate('Lüften %d%%'), $ventPos),
                    'active'   => ($targetPos >= 0.0 && abs($targetPos - $ventPos) < 1.0)
                ],
                'custom' => [
                    'enabled'  => $customEnabled,
                    'position' => $customPos,
                    'label'    => !empty($customLabel) ? sprintf('%s %d%%', $customLabel, $customPos) : sprintf($this->Translate('Preset %d%%'), $customPos),
                    'active'   => ($targetPos >= 0.0 && abs($targetPos - $customPos) < 1.0)
                ]
            ],
            'options'              => [
                'showPercentage'     => (bool)$this->ReadPropertyBoolean('ShowPercentage'),
                'showOpenDuration'   => (bool)$this->ReadPropertyBoolean('ShowOpenDuration'),
                'showReachableBadge' => (bool)$this->ReadPropertyBoolean('ShowReachableBadge')
            ]
        ];
    }
}
