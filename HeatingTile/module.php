<?php

declare(strict_types=1);

if (!defined('VM_UPDATE')) {
    define('VM_UPDATE', 10603);
}

/**
 * HeatingTile
 *
 * Kompakte, rein lesende Kachel-Visualisierung (HTML-SDK) fuer einen
 * Fussbodenheizungs-Heizkreis: Raumtemperatur, aktueller Sollwert,
 * Basissollwert und Stellgroesse (0-100%).
 *
 * Es werden keine Werte auf den KNX-Bus geschrieben.
 *
 * @author Thomas Hellstern
 */
class HeatingTile extends IPSModule
{
    public const STATE_HEATING   = 0;
    public const STATE_HOLDING   = 1;
    public const STATE_IDLE      = 2;
    public const STATE_OVERSHOOT = 3;

    private const DELTA_TOLERANCE = 0.3;
    private const ACTUATOR_MIN    = 5.0;

    public function Create(): void
    {
        parent::Create();

        // IP-Symcon HTML-SDK Kachelvisualisierung
        $this->SetVisualizationType(1);

        $this->RegisterPropertyInteger('RoomTemperatureVariable', 0);
        $this->RegisterPropertyInteger('SetpointVariable', 0);
        $this->RegisterPropertyInteger('BaseSetpointVariable', 0);
        $this->RegisterPropertyInteger('ActuatorVariable', 0);

        $this->RegisterPropertyString('TileTitle', 'Fussbodenheizung');
        $this->RegisterPropertyBoolean('ShowBaseSetpoint', true);
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        if (!IPS_VariableProfileExists('HEATTILE.State')) {
            IPS_CreateVariableProfile('HEATTILE.State', 1);
            IPS_SetVariableProfileIcon('HEATTILE.State', 'Temperature');
            IPS_SetVariableProfileAssociation('HEATTILE.State', self::STATE_HEATING,   $this->Translate('Heizt'),   'Flame',       0xEF4444);
            IPS_SetVariableProfileAssociation('HEATTILE.State', self::STATE_HOLDING,   $this->Translate('Haelt'),   'Okay',        0x10B981);
            IPS_SetVariableProfileAssociation('HEATTILE.State', self::STATE_IDLE,      $this->Translate('Aus'),     'Sleep',       0x64748B);
            IPS_SetVariableProfileAssociation('HEATTILE.State', self::STATE_OVERSHOOT, $this->Translate('Zu warm'), 'Climate',     0xF59E0B);
        }

        $this->MaintainVariable('State', $this->Translate('Heizzustand'), 1, 'HEATTILE.State', 1, true);

        $variables = [
            $this->ReadPropertyInteger('RoomTemperatureVariable'),
            $this->ReadPropertyInteger('SetpointVariable'),
            $this->ReadPropertyInteger('BaseSetpointVariable'),
            $this->ReadPropertyInteger('ActuatorVariable'),
        ];

        $valid = 0;
        foreach ($variables as $varID) {
            if ($varID > 0 && @IPS_VariableExists($varID)) {
                $this->RegisterMessage($varID, VM_UPDATE);
                $this->RegisterReference($varID);
                $valid++;
            }
        }

        $this->SetStatus($valid === 4 ? 102 : 104);
        $this->SendTileUpdate();
    }

    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';
        if (!file_exists($htmlFile)) {
            return '<div style="color:red;padding:10px;">module.html nicht gefunden!</div>';
        }

        $html = file_get_contents($htmlFile);
        $payload = $this->GetTilePayload();
        $initialData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $script = '<script>window.__INITIAL_DATA__ = ' . $initialData . ';'
                . ' if (typeof handleMessage === "function") { handleMessage(window.__INITIAL_DATA__); }</script>';

        return str_replace('</body>', $script . '</body>', $html);
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'refresh':
                $this->SendTileUpdate();
                break;

            default:
                $this->SendDebug('RequestAction', "Unbekannter Ident: $Ident", 0);
                break;
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }
        $this->SendTileUpdate();
    }

    public function SendTileUpdate(): void
    {
        $this->UpdateVisualizationValue(json_encode(
            $this->GetTilePayload(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /**
     * Liest eine Variable defensiv (fehlende/ungueltige IDs liefern null)
     */
    private function ReadVariableFloat(int $VariableID): ?float
    {
        if ($VariableID <= 0 || !@IPS_VariableExists($VariableID)) {
            return null;
        }
        $value = @GetValue($VariableID);
        if ($value === false || $value === null) {
            return null;
        }
        return (float)$value;
    }

    /**
     * Normalisiert die Stellgroesse auf 0-100%.
     * KNX DPT 5 kommt je nach Profil als 0-100 oder als Rohbyte 0-255 an.
     */
    private function NormalizeActuator(?float $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        if ($raw < 0.0) {
            return 0.0;
        }
        if ($raw > 100.0) {
            return min(100.0, $raw / 255.0 * 100.0);
        }
        return $raw;
    }

    protected function GetTilePayload(): array
    {
        $roomTempID = $this->ReadPropertyInteger('RoomTemperatureVariable');
        $setpointID = $this->ReadPropertyInteger('SetpointVariable');
        $baseID     = $this->ReadPropertyInteger('BaseSetpointVariable');
        $actuatorID = $this->ReadPropertyInteger('ActuatorVariable');

        $roomTemp = $this->ReadVariableFloat($roomTempID);
        $setpoint = $this->ReadVariableFloat($setpointID);
        $base     = $this->ReadVariableFloat($baseID);
        $actuator = $this->NormalizeActuator($this->ReadVariableFloat($actuatorID));

        // Fehlende Bindungen sammeln
        $missing = [];
        if ($roomTemp === null) { $missing[] = $this->Translate('Raumtemperatur'); }
        if ($setpoint === null) { $missing[] = $this->Translate('Sollwert'); }
        if ($actuator === null) { $missing[] = $this->Translate('Stellgroesse'); }

        $state = $this->DeriveState($roomTemp, $setpoint, $actuator);

        $stateMap = [
            self::STATE_HEATING   => ['key' => 'heating',   'text' => $this->Translate('Heizt'),   'color' => '#EF4444'],
            self::STATE_HOLDING   => ['key' => 'holding',   'text' => $this->Translate('Haelt'),   'color' => '#10B981'],
            self::STATE_IDLE      => ['key' => 'idle',      'text' => $this->Translate('Aus'),     'color' => '#64748B'],
            self::STATE_OVERSHOOT => ['key' => 'overshoot', 'text' => $this->Translate('Zu warm'), 'color' => '#F59E0B'],
        ];
        $stateInfo = $stateMap[$state] ?? $stateMap[self::STATE_IDLE];

        $this->SetValue('State', $state);

        $delta = null;
        if ($roomTemp !== null && $setpoint !== null) {
            $delta = round($roomTemp - $setpoint, 1);
        }

        return [
            'title'        => $this->ReadPropertyString('TileTitle'),
            'configured'   => count($missing) === 0,
            'missing'      => $missing,
            'roomTemp'     => $roomTemp,
            'roomTempText' => $roomTemp === null ? '–' : number_format($roomTemp, 1, ',', '') . ' °C',
            'setpoint'     => $setpoint,
            'setpointText' => $setpoint === null ? '–' : number_format($setpoint, 1, ',', '') . ' °C',
            'baseSetpointText' => $base === null ? '–' : number_format($base, 1, ',', '') . ' °C',
            'delta'        => $delta,
            'deltaText'    => $delta === null ? '–' : ($delta > 0 ? '+' : '') . number_format($delta, 1, ',', '') . ' K',
            'actuator'     => $actuator === null ? 0 : round($actuator, 1),
            'actuatorText' => $actuator === null ? '–' : (int)round($actuator) . ' %',
            'state'        => $stateInfo['key'],
            'stateText'    => $stateInfo['text'],
            'stateColor'   => $stateInfo['color'],
            'showBase'     => $this->ReadPropertyBoolean('ShowBaseSetpoint'),
            'updatedAt'    => date('H:i'),
        ];
    }

    /**
     * Leitet den Heizzustand aus Stellgroesse und Temperaturdelta ab.
     */
    private function DeriveState(?float $roomTemp, ?float $setpoint, ?float $actuator): int
    {
        if ($roomTemp === null || $setpoint === null || $actuator === null) {
            return self::STATE_IDLE;
        }

        $delta = $roomTemp - $setpoint;

        if ($actuator >= self::ACTUATOR_MIN) {
            if ($roomTemp < $setpoint - self::DELTA_TOLERANCE) {
                return self::STATE_HEATING;
            }
            if (abs($delta) <= self::DELTA_TOLERANCE) {
                return self::STATE_HOLDING;
            }
        }

        if ($actuator < self::ACTUATOR_MIN) {
            return self::STATE_IDLE;
        }

        if ($delta > self::DELTA_TOLERANCE) {
            return self::STATE_OVERSHOOT;
        }

        return self::STATE_IDLE;
    }
}
