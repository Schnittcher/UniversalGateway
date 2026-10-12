<?php

declare(strict_types=1);

class UniversalGateway extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('Variables', '[]');
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Alte Registrierungen entfernen, sonst bleiben entfernte oder geänderte Paare aktiv
        foreach ($this->GetMessageList() as $SenderID => $Messages) {
            foreach ($Messages as $Message) {
                $this->UnregisterMessage($SenderID, $Message);
            }
        }

        foreach ($this->getVariablePairs() as $Pair) {
            foreach ([$Pair['VariableID1'], $Pair['VariableID2']] as $VariableID) {
                if ($VariableID > 0 && IPS_VariableExists($VariableID)) {
                    $this->RegisterMessage($VariableID, VM_UPDATE);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $VariableID . ' existiert nicht und wird ignoriert', 0);
                }
            }
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->SendDebug(__FUNCTION__, 'SenderID ' . $SenderID . ' Message: ' . $Message, 0);
        switch ($Message) {
            case VM_UPDATE:
                $AssociatedVariable = $this->getAssociatedVariable($SenderID);
                $this->SendDebug(__FUNCTION__, 'SenderID ' . $SenderID . ' AssociatedVariable: ' . $AssociatedVariable, 0);
                if ($AssociatedVariable > 0 && IPS_VariableExists($AssociatedVariable)) {
                    $AssociatedValue = GetValue($AssociatedVariable);
                    $SenderValue = GetValue($SenderID);
                    $this->SendDebug(__FUNCTION__, 'AssociatedValue ' . json_encode($AssociatedValue) . ' SenderValue: ' . json_encode($SenderValue), 0);
                    if ($AssociatedValue != $SenderValue) {
                        $this->SendDebug(__FUNCTION__, 'RequestAction', 0);
                        RequestAction($AssociatedVariable, $SenderValue);
                    }
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__, $SenderID . ' Message: ' . $Message, 0);
                break;
        }
    }

    /**
     * Liefert die konfigurierten Variablenpaare mit ganzzahligen Variablen-IDs.
     */
    private function getVariablePairs(): array
    {
        $Variables = json_decode($this->ReadPropertyString('Variables'), true);
        if (!is_array($Variables)) {
            return [];
        }
        $Pairs = [];
        foreach ($Variables as $Variable) {
            $Pairs[] = [
                'VariableID1' => (int) ($Variable['VariableID1'] ?? 0),
                'VariableID2' => (int) ($Variable['VariableID2'] ?? 0)
            ];
        }
        return $Pairs;
    }

    private function getAssociatedVariable(int $VariableID): int
    {
        foreach ($this->getVariablePairs() as $Pair) {
            if ($Pair['VariableID1'] == $VariableID) {
                return $Pair['VariableID2'];
            } elseif ($Pair['VariableID2'] == $VariableID) {
                return $Pair['VariableID1'];
            }
        }
        return 0;
    }
}
