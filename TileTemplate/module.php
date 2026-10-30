<?php

declare(strict_types=1);

// Ensure IP-Symcon variable type constants are defined
if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
}
if (!defined('VARIABLETYPE_INTEGER')) {
    define('VARIABLETYPE_INTEGER', 1);
}
if (!defined('VARIABLETYPE_FLOAT')) {
    define('VARIABLETYPE_FLOAT', 2);
}
if (!defined('VARIABLETYPE_STRING')) {
    define('VARIABLETYPE_STRING', 3);
}

// IP-Symcon message constant for variable updates
if (!defined('VM_UPDATE')) {
    define('VM_UPDATE', 10603);
}

/**
 * TileTemplate
 *
 * Universelles Best-Practice Template für Kachel-Visualisierungen (HTML-SDK) in IP-Symcon ab Version 7.1.
 * Dient als wiederverwendbare Vorlage für eigene Widgets und Geräte-Kacheln.
 *
 * @author Thomas Hellstern
 */
class TileTemplate extends IPSModule
{
    public function Create(): void
    {
        // Niemals entfernen!
        parent::Create();

        // Aktiviert das IP-Symcon HTML-SDK für Kachelvisualisierung (1 = INSTANCE_VISUALIZATION_TYPE_HTML)
        $this->SetVisualizationType(1);

        // Variablen-Verknüpfungen
        $this->RegisterPropertyInteger('PrimaryVariable', 0);
        $this->RegisterPropertyInteger('SecondaryVariable', 0);

        // Kachel-Design & Metadaten
        $this->RegisterPropertyString('TileTitle', 'Kachel-Titel');
        $this->RegisterPropertyString('TileSubtitle', 'Status & Steuerung');
        $this->RegisterPropertyInteger('AccentColor', 0x00CDAB); // Modernes Cyan/Teal
        $this->RegisterPropertyString('TileIcon', 'fa-bolt');

        // Interaktion & Steuerung
        $this->RegisterPropertyBoolean('ShowActionButton', true);
        $this->RegisterPropertyString('ActionButtonLabel', 'Umschalten');
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Stellt sicher, dass Kachel-Visualisierung (Typ 1) aktiv ist
        $this->SetVisualizationType(1);

        // 1. Alle bisherigen Nachrichten-Registrierungen sauber entfernen
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        // 2. Alle bisherigen Objektreferenzen sauber entfernen
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        // 3. Konfigurierte Variablen registrieren
        $variables = [
            $this->ReadPropertyInteger('PrimaryVariable'),
            $this->ReadPropertyInteger('SecondaryVariable'),
        ];

        foreach ($variables as $varID) {
            if ($varID > 0 && @IPS_VariableExists($varID)) {
                $this->RegisterReference($varID);
                $this->RegisterMessage($varID, VM_UPDATE);
            }
        }

        // 4. Initialen Kachelstatus senden
        $this->SendTileUpdate();
    }

    /**
     * Liefert den statischen HTML-Inhalt der Kachel für das IP-Symcon WebFront / TileVisu
     */
    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';
        if (!file_exists($htmlFile)) {
            return '<div style="color:red;padding:10px;">Fehler: module.html nicht gefunden!</div>';
        }

        $html = file_get_contents($htmlFile);

        // Initiales Datenpaket generieren und als Startzustand injizieren
        $initialData = json_encode($this->GetVisualizationData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bootstrapScript = '<script>window.__INITIAL_DATA__ = ' . $initialData . '; if (typeof handleMessage === "function") { handleMessage(window.__INITIAL_DATA__); }</script>';

        return str_replace('</body>', $bootstrapScript . '</body>', $html);
    }

    /**
     * Wird automatisch bei Variablen-Aktualisierungen aufgerufen
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === VM_UPDATE) {
            $this->SendTileUpdate();
        }
    }

    /**
     * Verarbeitet Benutzeraktionen aus dem Frontend (über requestAction() im HTML)
     */
    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'actionButton':
                $primaryID = $this->ReadPropertyInteger('PrimaryVariable');
                if ($primaryID > 0 && @IPS_VariableExists($primaryID)) {
                    $var = IPS_GetVariable($primaryID);
                    if ($var['VariableType'] === VARIABLETYPE_BOOLEAN) {
                        $current = (bool)GetValueBoolean($primaryID);
                        $this->RequestVariableAction($primaryID, !$current);
                    } elseif ($var['VariableCustomAction'] > 0 || $var['VariableAction'] > 0) {
                        $this->RequestVariableAction($primaryID, $Value);
                    }
                }
                break;

            case 'refresh':
                $this->SendTileUpdate();
                break;

            default:
                $this->SendDebug('RequestAction', "Unbekannter Ident: $Ident mit Wert: " . json_encode($Value), 0);
                break;
        }
    }

    /**
     * Sendet das aktuelle Datenpaket flicker-frei an die Kachelvisualisierung
     */
    public function SendTileUpdate(): void
    {
        $data = $this->GetVisualizationData();
        $this->UpdateVisualizationValue(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Stellt alle relevanten Daten für die Kachel zusammen
     */
    protected function GetVisualizationData(): array
    {
        $primaryID   = $this->ReadPropertyInteger('PrimaryVariable');
        $secondaryID = $this->ReadPropertyInteger('SecondaryVariable');

        $accentColorHex = sprintf('#%06X', $this->ReadPropertyInteger('AccentColor'));

        $data = [
            'title'            => $this->ReadPropertyString('TileTitle'),
            'subtitle'         => $this->ReadPropertyString('TileSubtitle'),
            'accentColor'      => $accentColorHex,
            'icon'             => $this->ReadPropertyString('TileIcon'),
            'showActionButton' => $this->ReadPropertyBoolean('ShowActionButton'),
            'buttonLabel'      => $this->ReadPropertyString('ActionButtonLabel'),
            'primary'          => [
                'exists'    => false,
                'value'     => null,
                'formatted' => '--',
                'type'      => null
            ],
            'secondary'        => [
                'exists'    => false,
                'value'     => null,
                'formatted' => '--'
            ]
        ];

        // Hauptvariable auslesen
        if ($primaryID > 0 && @IPS_VariableExists($primaryID)) {
            $var = IPS_GetVariable($primaryID);
            $val = GetValue($primaryID);
            $formatted = GetValueFormatted($primaryID);

            $data['primary'] = [
                'exists'    => true,
                'id'        => $primaryID,
                'value'     => $val,
                'formatted' => $formatted,
                'type'      => $var['VariableType'],
                'updated'   => $var['VariableUpdated']
            ];
        }

        // Sekundärvariable auslesen
        if ($secondaryID > 0 && @IPS_VariableExists($secondaryID)) {
            $var2 = IPS_GetVariable($secondaryID);
            $val2 = GetValue($secondaryID);
            $formatted2 = GetValueFormatted($secondaryID);

            $data['secondary'] = [
                'exists'    => true,
                'id'        => $secondaryID,
                'value'     => $val2,
                'formatted' => $formatted2,
                'updated'   => $var2['VariableUpdated']
            ];
        }

        return $data;
    }

    /**
     * Hilfsmethode: Sicherer Variablen-Schreibzugriff via Standard- oder CustomAction
     */
    protected function RequestVariableAction(int $variableID, $value): void
    {
        $var = IPS_GetVariable($variableID);
        if ($var['VariableCustomAction'] > 0) {
            IPS_RunScriptEx($var['VariableCustomAction'], [
                'VARIABLE' => $variableID,
                'VALUE'    => $value,
                'SENDER'   => 'TileVisualisation'
            ]);
        } elseif ($var['VariableAction'] > 0) {
            RequestAction($variableID, $value);
        } else {
            // Keine hinterlegte Action -> Direktes SetValue als Fallback
            SetValue($variableID, $value);
        }
    }

    /**
     * Öffentliche Funktion zum manuellen Aktualisieren der Kachel
     */
    public function RefreshTile(): void
    {
        $this->SendTileUpdate();
    }
}
