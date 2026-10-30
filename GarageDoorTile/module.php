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
        $this->RegisterPropertyInteger('ImpulseDurationMs', 800);  // 800 ms Taster-Haltezeit
        $this->RegisterPropertyInteger('TravelTimeOpenSec', 16);   // 16s Fahrzeit Öffnen
        $this->RegisterPropertyInteger('TravelTimeCloseSec', 16);  // 16s Fahrzeit Schließen
        $this->RegisterPropertyInteger('WarnOpenMinutes', 30);     // Warnung ab 30 Min. offen

        // Darstellung
        $this->RegisterPropertyString('TileTitle', 'Garagentor');
        $this->RegisterPropertyBoolean('ShowPercentage', true);
        $this->RegisterPropertyBoolean('ShowOpenDuration', true);
        $this->RegisterPropertyBoolean('ShowReachableBadge', true);

        // Modul-Timer
        $this->RegisterTimer('ImpulseOff', 0, 'GARDOOR_HandleImpulseOff($_IPS[\'TARGET\']);');
        $this->RegisterTimer('TravelProgress', 0, 'GARDOOR_HandleTravelProgress($_IPS[\'TARGET\']);');
        $this->RegisterTimer('OpenDurationTick', 0, 'GARDOOR_HandleOpenDurationTick($_IPS[\'TARGET\']);');

        // Modul-Attribute für persistente Zustandsspeicherung
        $this->RegisterAttributeInteger('DoorState', self::STATE_CLOSED);
        $this->RegisterAttributeFloat('CurrentPosition', 0.0);
        $this->RegisterAttributeFloat('MotionStartTime', 0.0);
        $this->RegisterAttributeFloat('MotionStartPosition', 0.0);
        $this->RegisterAttributeInteger('OpenedAtTimestamp', 0);
        $this->RegisterAttributeInteger('LastDirection', 1); // 1 = nach oben, -1 = nach unten
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

        $vars = [$switchID, $sensorID, $reachID];
        foreach ($vars as $vID) {
            if ($vID > 0 && @IPS_VariableExists($vID)) {
                $this->RegisterReference($vID);
                $this->RegisterMessage($vID, VM_UPDATE);
            }
        }

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

        // 4. Native Child-Statusvariablen pflegen (für Detail-/Vollbildansicht)
        $this->MaintainVariable('State', $this->Translate('Torzustand'), 1, 'GARDOOR.State', 1, true);
        $this->MaintainVariable('Position', $this->Translate('Position'), 2, '~ShutterMoveStep', 2, true);
        $this->MaintainVariable('OpenDuration', $this->Translate('Offen seit'), 1, '', 3, true);
        $this->MaintainVariable('ImpulseActive', $this->Translate('Taster-Impuls'), 0, '~Switch', 4, true);

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

        // Initiales Datenpaket direkt übergeben
        $initialData = json_encode($this->GetTilePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $script = '<script>window.__INITIAL_DATA__ = ' . $initialData . '; if (typeof handleMessage === "function") { handleMessage(window.__INITIAL_DATA__); }</script>';

        return str_replace('</body>', $script . '</body>', $html);
    }

    /**
     * Empfängt Klicks und Aktionen aus der Kachel-UI
     */
    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'triggerDoor':
                $this->TriggerDoor();
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
     * Haupt-Aktion: Löst einen sicheren, zeitgesteuerten Tasterimpuls am Torantrieb aus
     */
    public function TriggerDoor(): void
    {
        $reachID = $this->ReadPropertyInteger('ReachableVariable');
        if ($reachID > 0 && @IPS_VariableExists($reachID) && !GetValueBoolean($reachID)) {
            $this->SendDebug('TriggerDoor', 'Abbruch: Shelly Aktor ist offline!', 0);
            return;
        }

        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        if ($switchID <= 0 || !@IPS_VariableExists($switchID)) {
            $this->SendDebug('TriggerDoor', 'Fehler: Keine Schalter-Variable konfiguriert!', 0);
            return;
        }

        // 1. Relais hardwareseitig einschalten
        @RequestAction($switchID, true);
        $this->SetValue('ImpulseActive', true);

        // 2. Automatisches Abschalten via Modul-Timer (Standard: 800 ms)
        $durationMs = max(200, $this->ReadPropertyInteger('ImpulseDurationMs'));
        $this->SetTimerInterval('ImpulseOff', $durationMs);

        // 3. State-Machine weiterschalten
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
                $this->SetTimerInterval('TravelProgress', 250);
                $this->SetTimerInterval('OpenDurationTick', 60000);
                break;

            case self::STATE_OPENING:
                // Klick während Fahrt nach oben -> Stopp
                $this->WriteAttributeInteger('DoorState', self::STATE_STOPPED);
                $this->SetTimerInterval('TravelProgress', 0);
                $this->WriteAttributeInteger('LastDirection', 1);
                break;

            case self::STATE_OPEN:
                // Aus dem offenen Zustand startet das Schließen
                $this->WriteAttributeInteger('DoorState', self::STATE_CLOSING);
                $this->WriteAttributeFloat('MotionStartTime', $now);
                $this->WriteAttributeFloat('MotionStartPosition', $currentPos > 0 ? $currentPos : 100.0);
                $this->WriteAttributeInteger('LastDirection', -1);
                $this->SetTimerInterval('TravelProgress', 250);
                break;

            case self::STATE_CLOSING:
                // Klick während Fahrt nach unten -> Stopp
                $this->WriteAttributeInteger('DoorState', self::STATE_STOPPED);
                $this->SetTimerInterval('TravelProgress', 0);
                $this->WriteAttributeInteger('LastDirection', -1);
                break;

            case self::STATE_STOPPED:
                // Klick im Stillstand -> Fahrt in die Gegenrichtung
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
        $this->SendTileUpdate();
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
            $travelTime = max(2.0, (float)$this->ReadPropertyInteger('TravelTimeOpenSec'));
            $delta = ($elapsed / $travelTime) * (100.0 - $startPos);
            $newPos = min(100.0, $startPos + $delta);
            $this->WriteAttributeFloat('CurrentPosition', $newPos);

            if ($newPos >= 100.0) {
                // Vollständig geöffnet
                $this->WriteAttributeInteger('DoorState', self::STATE_OPEN);
                $this->SetTimerInterval('TravelProgress', 0);
            }
        } elseif ($state === self::STATE_CLOSING) {
            $travelTime = max(2.0, (float)$this->ReadPropertyInteger('TravelTimeCloseSec'));
            $delta = ($elapsed / $travelTime) * $startPos;
            $newPos = max(0.0, $startPos - $delta);
            $this->WriteAttributeFloat('CurrentPosition', $newPos);

            if ($newPos <= 0.0) {
                // Rechnerisch geschlossen (wird vom Endlagensensor final bestätigt)
                $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
                if ($sensorID <= 0 || !@IPS_VariableExists($sensorID) || $this->IsSensorClosed()) {
                    $this->WriteAttributeInteger('DoorState', self::STATE_CLOSED);
                    $this->WriteAttributeInteger('OpenedAtTimestamp', 0);
                    $this->SetTimerInterval('TravelProgress', 0);
                    $this->SetTimerInterval('OpenDurationTick', 0);
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
            $this->SendTileUpdate();
        } else {
            $this->SetTimerInterval('OpenDurationTick', 0);
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

        $sensorID = $this->ReadPropertyInteger('SensorClosedVariable');
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        $reachID  = $this->ReadPropertyInteger('ReachableVariable');

        if ($SenderID === $sensorID) {
            $this->SyncWithSensors();
            $this->SendTileUpdate();
        } elseif ($SenderID === $switchID || $SenderID === $reachID) {
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
        $state = $this->ReadAttributeInteger('DoorState');
        $pos   = round($this->ReadAttributeFloat('CurrentPosition'), 0);

        $stateInfo = match ($state) {
            self::STATE_CLOSED  => ['key' => 'closed',  'text' => $this->Translate('Geschlossen'),     'color' => '#10B981', 'btn' => $this->Translate('Tor öffnen')],
            self::STATE_OPENING => ['key' => 'opening', 'text' => $this->Translate('Wird geöffnet...'), 'color' => '#38BDF8', 'btn' => $this->Translate('Tor stoppen')],
            self::STATE_OPEN    => ['key' => 'open',    'text' => $this->Translate('Offen'),           'color' => '#EF4444', 'btn' => $this->Translate('Tor schließen')],
            self::STATE_CLOSING => ['key' => 'closing', 'text' => $this->Translate('Wird geschlossen...'), 'color' => '#F59E0B', 'btn' => $this->Translate('Tor stoppen')],
            default             => ['key' => 'stopped', 'text' => $this->Translate('Gestoppt'),         'color' => '#94A3B8', 'btn' => $this->Translate('Tor bewegen')]
        };

        // Offen-Dauer berechnen
        $openedAt = $this->ReadAttributeInteger('OpenedAtTimestamp');
        $openMinutes = 0;
        $openDurationText = '';
        $isWarn = false;

        if ($state !== self::STATE_CLOSED && $openedAt > 0) {
            $openMinutes = max(0, (int)floor((time() - $openedAt) / 60));
            $openDurationText = ($openMinutes === 0) ? $this->Translate('Gerade geöffnet') : sprintf($this->Translate('Seit %d Min.'), $openMinutes);

            $warnThreshold = $this->ReadPropertyInteger('WarnOpenMinutes');
            if ($warnThreshold > 0 && $openMinutes >= $warnThreshold) {
                $isWarn = true;
            }
        }

        // Online-Status des Shelly Aktors
        $reachID = $this->ReadPropertyInteger('ReachableVariable');
        $isOnline = true;
        if ($reachID > 0 && @IPS_VariableExists($reachID)) {
            $isOnline = GetValueBoolean($reachID);
        }

        // Schalterzustand (Impuls aktiv)
        $switchID = $this->ReadPropertyInteger('SwitchVariable');
        $impulseActive = false;
        if ($switchID > 0 && @IPS_VariableExists($switchID)) {
            $impulseActive = GetValueBoolean($switchID);
        }

        return [
            'state'                => $stateInfo['key'],
            'stateCode'            => $state,
            'stateText'            => $stateInfo['text'],
            'stateColor'           => $stateInfo['color'],
            'buttonText'           => $stateInfo['btn'],
            'position'             => (int)$pos,
            'positionFormatted'    => (int)$pos . ' %',
            'openDurationMinutes'  => $openMinutes,
            'openDurationText'     => $openDurationText,
            'isWarning'            => $isWarn,
            'isOnline'             => $isOnline,
            'impulseActive'        => $impulseActive,
            'title'                => $this->ReadPropertyString('TileTitle'),
            'options'              => [
                'showPercentage'     => $this->ReadPropertyBoolean('ShowPercentage'),
                'showOpenDuration'   => $this->ReadPropertyBoolean('ShowOpenDuration'),
                'showReachableBadge' => $this->ReadPropertyBoolean('ShowReachableBadge')
            ]
        ];
    }
}
