<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

class NukiOpenerBridgeAPI extends IPSModuleStrict
{
    private const string LIBRARY_GUID = '{C761F228-6964-E7B7-A8F4-E90DC334649A}';
    private const string MODULE_PREFIX = 'NUKIOB';
    private const string NUKI_BRIDGE_GUID = '{37EAA787-55CE-E2B4-0799-2196F90F5E4C}';
    private const string NUKI_BRIDGE_DATA_GUID = '{E187AEEA-487B-ED93-403D-B7D51322A4DF}';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        ##### Properties

        $this->RegisterPropertyString('OpenerUID', '');
        $this->RegisterPropertyString('OpenerName', '');
        $this->RegisterPropertyBoolean('UseAutomaticUpdate', true);
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        $this->RegisterPropertyBoolean('UseActivityLog', true);
        $this->RegisterPropertyInteger('ActivityLogMaximumEntries', 10);

        ########## Variables

        //Door
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Door';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Door');
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Open'), '', 0x00FF00);
        $this->RegisterVariableInteger('Door', $this->Translate('Door'), $profile, 100);
        $this->EnableAction('Door');

        //Device state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DeviceState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Untrained'), 'Execute', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Online'), 'Network', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, $this->Translate('Ring to Open active'), 'Alert', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 5, $this->Translate('Open'), 'Door', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 7, $this->Translate('Opening'), 'Door', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 253, 'Boot Run', 'Repeat', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 255, $this->Translate('Undefined'), 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 99999, $this->Translate('Unknown'), 'Information', -1);
        $id = @$this->GetIDForIdent('DeviceState');
        $this->RegisterVariableInteger('DeviceState', $this->Translate('Device state'), $profile, 200);
        if (!$id) {
            $this->SetValue('DeviceState', 99999);
        }

        //Battery state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BatteryState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Battery');
        IPS_SetVariableProfileAssociation($profile, false, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, true, $this->Translate('Low battery'), '', 0xFF0000);
        $this->RegisterVariableBoolean('BatteryState', $this->Translate('Battery state'), $profile, 220);

        //Ring to open
        $id = @$this->GetIDForIdent('RingToOpen');
        $this->RegisterVariableBoolean('RingToOpen', 'Ring to Open', '~Switch', 300);
        $this->EnableAction('RingToOpen');
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('RingToOpen'), 'Alert');
        }

        //Continuous mode
        $id = @$this->GetIDForIdent('ContinuousMode');
        $this->RegisterVariableBoolean('ContinuousMode', $this->Translate('Continuous mode'), '~Switch', 320);
        $this->EnableAction('ContinuousMode');
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('ContinuousMode'), 'Door');
        }

        //Ring action state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.RingActionState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Alert');
        IPS_SetVariableProfileAssociation($profile, false, $this->Translate('Idle'), '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, true, $this->Translate('It rings'), '', 0xFF0000);
        $this->RegisterVariableBoolean('RingActionState', $this->Translate('Doorbell'), $profile, 400);

        ##### Attributes

        $this->RegisterAttributeInteger('DeviceType', 99999);
        $this->RegisterAttributeString('LastRingTimestamp', '');

        ##### Timer

        $this->RegisterTimer('UpdateDeviceState', 0, self::MODULE_PREFIX . '_UpdateOpenerState(' . $this->InstanceID . ');');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();

        $profiles = ['Door', 'DeviceState', 'BatteryState'];
        foreach ($profiles as $profile) {
            $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    public function ApplyChanges(): void
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        ########## Maintain variable

        //Activity log
        if ($this->ReadPropertyBoolean('UseActivityLog')) {
            $id = @$this->GetIDForIdent('ActivityLog');
            $this->MaintainVariable('ActivityLog', $this->Translate('Activity log'), 3, 'HTMLBox', 500, true);
            if (!$id) {
                IPS_SetIcon($this->GetIDForIdent('ActivityLog'), 'Database');
            }
        } else {
            $this->MaintainVariable('ActivityLog', $this->Translate('Activity log'), 3, '', 0, false);
        }

        if (!$this->ValidateConfiguration()) {
            return;
        }

        $this->DetermineDeviceType(false);
        $this->UpdateOpenerState();
    }

    public function GetCompatibleParents(): string
    {
        //Connect to a new or existing Nuki Bridge Splitter instance
        return json_encode([
            'type'      => 'connect',
            'moduleIDs' => [
                self::NUKI_BRIDGE_GUID
            ]
        ]);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version info
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $data['elements'][2]['caption'] = 'ID: ' . $this->InstanceID . ', Version: ' . $library['Version'] . '-' . $library['Build'] . ', ' . date('d.m.Y', $library['Date']);
        $data['actions'][0]['caption'] = $this->Translate('Device type: ') . $this->GetDeviceTypeDescription();
        return json_encode($data);
    }

    public function ReceiveData($JSONString): string
    {
        $this->SendDebug(__FUNCTION__, 'Incoming data: ' . $JSONString, 0);
        if (!$this->ReadPropertyBoolean('UseAutomaticUpdate')) {
            $this->SendDebug(__FUNCTION__, 'Abort, automatic update is disabled!', 0);
            return '';
        }
        $data = json_decode($JSONString, true);
        $buffer = $data['Buffer'];
        $this->SendDebug(__FUNCTION__, 'Buffer data: ' . json_encode($buffer), 0);
        if (array_key_exists('nukiId', $buffer)) {
            $nukiID = $buffer['nukiId'];
            if ($this->ReadPropertyString('OpenerUID') != $nukiID) {
                $this->SendDebug(__FUNCTION__, 'Abort, data is not for this device!', 0);
                return '';
            }
        }
        $this->UpdateDeviceState(json_encode($buffer));
        return '';
    }

    #################### Request Action

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Door':
                $this->OpenDoor();
                break;

            case 'RingToOpen':
                $this->ToggleRingToOpen($Value);
                break;

            case 'ContinuousMode':
                $this->ToggleContinuousMode($Value);
                break;
        }
    }

    #################### Public methods

    public function DetermineDeviceType(bool $Force): int
    {
        $nukiID = $this->ReadPropertyString('OpenerUID');
        $deviceType = $this->ReadAttributeInteger('DeviceType');
        if (empty($nukiID)) {
            return $deviceType;
        }
        $newValue = false;
        if ($deviceType == 99999 || $Force) {
            if ($this->HasActiveParent()) {
                $data = [];
                $buffer = [];
                $data['DataID'] = self::NUKI_BRIDGE_DATA_GUID;
                $buffer['Command'] = 'GetPairedDevices';
                $buffer['Params'] = '';
                $data['Buffer'] = $buffer;
                $data = json_encode($data);
                $result = json_decode($this->SendDataToParent($data), true);
                $this->SendDebug(__FUNCTION__, 'Result: ' . json_encode($result), 0);
                if (is_array($result)) {
                    if (array_key_exists('httpCode', $result)) {
                        $httpCode = $result['httpCode'];
                        $this->SendDebug(__FUNCTION__, 'Result http code: ' . $httpCode, 0);
                        if ($httpCode != 200) {
                            $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                            //Retry
                            $this->SendDebug(__FUNCTION__, 'Wait three seconds for retry...', 0);
                            IPS_Sleep(3000);
                            $result = json_decode($this->SendDataToParent($data), true);
                            if (is_array($result)) {
                                if (array_key_exists('httpCode', $result)) {
                                    $httpCode = $result['httpCode'];
                                    $this->SendDebug(__FUNCTION__, 'Second result http code: ' . $httpCode, 0);
                                    if ($httpCode != 200) {
                                        $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                                        return $deviceType;
                                    }
                                }
                            }
                        }
                    }
                    if (array_key_exists('body', $result)) {
                        $this->SendDebug(__FUNCTION__, 'Actual data: ' . json_encode($result['body']), 0);
                        $devices = $result['body'];
                        if (is_array($devices)) {
                            foreach ($devices as $device) {
                                if (array_key_exists('nukiId', $device)) {
                                    if ($device['nukiId'] == $nukiID) {
                                        if (array_key_exists('deviceType', $device)) {
                                            /*
                                             * Device Types
                                             * 0        Nuki Smart Lock 1.0/2.0
                                             * 2        Nuki Opener
                                             * 3        Nuki Smart Door
                                             * 4        Nuki Smart Lock 3.0 (Pro)
                                             * 99999    Unknown device
                                             */
                                            $newValue = true;
                                            $this->WriteAttributeInteger('DeviceType', $device['deviceType']);
                                            $deviceType = $this->ReadAttributeInteger('DeviceType');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $deviceDescription = $this->GetDeviceTypeDescription();
        if ($newValue) {
            $this->LogMessage('Nuki Smart Lock ID ' . $this->InstanceID . ', attribute DeviceType was set to: ' . $deviceType . ' = ' . $deviceDescription, KL_NOTIFY);
            $this->UpdateFormField('DeviceTypeDescription', 'caption', $this->Translate('Device type: ') . $deviceDescription);
        }
        $this->SendDebug(__FUNCTION__, 'Device type: ' . $deviceType . ' = ' . $deviceDescription, 0);
        return $deviceType;
    }

    public function UpdateOpenerState(): void
    {
        $this->SetTimerInterval('UpdateDeviceState', 0);
        $this->GetOpenerState();
        $this->SetUpdateTimer();
    }

    public function OpenDoor(): bool
    {
        return $this->SetOpenerAction(3);
    }

    public function ToggleRingToOpen(bool $State): bool
    {
        $actualValue = $this->GetValue('RingToOpen');
        $this->SetValue('RingToOpen', $State);
        $action = 2; # deactivate rto
        if ($State) {
            $action = 1; # activate rto
        }
        $result = $this->SetOpenerAction($action);
        if (!$result) {
            //Revert
            $this->SetValue('RingToOpen', $actualValue);
        }
        return $result;
    }

    public function ToggleContinuousMode(bool $State): bool
    {
        $actualValue = $this->GetValue('ContinuousMode');
        $this->SetValue('ContinuousMode', $State);
        $action = 5; # deactivate continuous mode
        if ($State) {
            $action = 4; # activate continuous mode
        }
        $result = $this->SetOpenerAction($action);
        if (!$result) {
            //Revert
            $this->SetValue('ContinuousMode', $actualValue);
        }
        return $result;
    }

    #################### Private methods

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function ValidateConfiguration(): bool
    {
        $status = 102;
        $result = true;
        //Check Nuki UID
        if (empty($this->ReadPropertyString('OpenerUID'))) {
            $status = 200;
            $result = false;
            $this->SendDebug(__FUNCTION__, 'Please enter the Nuki ID of the Opener!', 0);
        }
        $this->SetStatus($status);
        return $result;
    }

    private function GetDeviceTypeDescription(): string
    {
        return match ($this->ReadAttributeInteger('DeviceType')) {
            2       => 'Nuki Opener',
            default => $this->Translate('Unknown'),
        };
    }

    private function SetOpenerAction(int $Action): bool
    {
        $nukiID = $this->ReadPropertyString('OpenerUID');
        if (empty($nukiID)) {
            return false;
        }
        if (!$this->HasActiveParent()) {
            return false;
        }
        $deviceType = $this->DetermineDeviceType(false);
        if ($deviceType == 99999) {
            return false;
        }
        $this->SetTimerInterval('UpdateDeviceState', 0);
        /*
         * Lock Actions Opener
         * 1   activate rto
         * 2   deactivate rto
         * 3   electric strike actuation
         * 4   activate continuous mode
         * 5   deactivate continuous mode
         */
        $success = false;
        $actualRingToOpenState = $this->GetValue('RingToOpen');
        $actualContinuousModeState = $this->GetValue('ContinuousMode');
        $data = [];
        $buffer = [];
        $data['DataID'] = self::NUKI_BRIDGE_DATA_GUID;
        $buffer['Command'] = 'SetLockAction';
        $buffer['Params'] = ['nukiId' => (int) $nukiID, 'deviceType' => $deviceType, 'lockAction' => $Action];
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $this->SendDebug(__FUNCTION__, 'Data: ' . $data, 0);
        $result = json_decode($this->SendDataToParent($data), true);
        $this->SendDebug(__FUNCTION__, 'Result: ' . json_encode($result), 0);
        if (is_array($result)) {
            if (array_key_exists('httpCode', $result)) {
                $httpCode = $result['httpCode'];
                $this->SendDebug(__FUNCTION__, 'Result http code: ' . $httpCode, 0);
                if ($httpCode != 200) {
                    $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                    //Retry
                    $this->SendDebug(__FUNCTION__, 'Wait three seconds for retry...', 0);
                    IPS_Sleep(3000);
                    $result = json_decode($this->SendDataToParent($data), true);
                    if (is_array($result)) {
                        if (array_key_exists('httpCode', $result)) {
                            $httpCode = $result['httpCode'];
                            $this->SendDebug(__FUNCTION__, 'Second result http code: ' . $httpCode, 0);
                            if ($httpCode != 200) {
                                $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                                $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The last switching command was not confirmed successfully.'));
                                $this->SetUpdateTimer();
                                return false;
                            }
                        }
                    }
                }
            }
            if (array_key_exists('body', $result)) {
                $body = $result['body'];
                $this->SendDebug(__FUNCTION__, 'Actual data: ' . json_encode($body), 0);
                if (is_array($body)) {
                    if (array_key_exists('success', $body)) {
                        $success = (bool) $body['success'];
                    }
                }
            }
        }
        //Only if we have no update automatic
        if (!$this->ReadPropertyBoolean('UseAutomaticUpdate') && $this->ReadPropertyInteger('UpdateInterval') == 0) {
            if ($success) {
                switch ($Action) {
                    case 1:
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('Ring To Open was activated.'));
                        break;

                    case 2:
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('Ring To Open was deactivated.'));
                        break;

                    case 3:
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The door buzzer has been pressed and has opened the door.'));
                        break;

                    case 4:
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('Continuous mode was activated.'));
                        break;

                    case 5:
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('Continuous mode was deactivated.'));
                        break;
                }
            }
        }
        if (!$success) {
            //Revert
            switch ($Action) {
                case 1: # activate rto
                case 2: # deactivate rto
                    $this->SetValue('RingToOpen', $actualRingToOpenState);
                    break;

                case 4: # activate continuous mode
                case 5: # deactivate continuous mode
                    $this->SetValue('ContinuousMode', $actualContinuousModeState);
                    break;
            }
            $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The last switching command was not confirmed successfully.'));
        }
        $this->SetUpdateTimer();
        return $success;
    }

    private function GetOpenerState(): void
    {
        $nukiID = $this->ReadPropertyString('OpenerUID');
        if (empty($nukiID)) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }
        $deviceType = $this->DetermineDeviceType(false);
        if ($deviceType == 99999) {
            return;
        }
        $data = [];
        $buffer = [];
        $data['DataID'] = self::NUKI_BRIDGE_DATA_GUID;
        $buffer['Command'] = 'GetLockState';
        $buffer['Params'] = ['nukiId' => (int) $nukiID, 'deviceType' => $deviceType];
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $this->SendDebug(__FUNCTION__, 'Data: ' . $data, 0);
        $result = json_decode($this->SendDataToParent($data), true);
        $this->SendDebug(__FUNCTION__, 'Result: ' . json_encode($result), 0);
        if (is_array($result)) {
            if (array_key_exists('httpCode', $result)) {
                $httpCode = $result['httpCode'];
                $this->SendDebug(__FUNCTION__, 'Result http code: ' . $httpCode, 0);
                if ($httpCode != 200) {
                    $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                    //Retry
                    $this->SendDebug(__FUNCTION__, 'Wait three seconds for retry...', 0);
                    IPS_Sleep(3000);
                    $result = json_decode($this->SendDataToParent($data), true);
                    if (is_array($result)) {
                        if (array_key_exists('httpCode', $result)) {
                            $httpCode = $result['httpCode'];
                            $this->SendDebug(__FUNCTION__, 'Second result http code: ' . $httpCode, 0);
                            if ($httpCode != 200) {
                                $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                                return;
                            }
                        }
                    }
                }
            }
            if (array_key_exists('body', $result)) {
                $this->SendDebug(__FUNCTION__, 'Actual data: ' . json_encode($result['body']), 0);
                $smartLockData = $result['body'];
                if (is_array($smartLockData)) {
                    if (array_key_exists('success', $smartLockData)) {
                        if ($smartLockData['success']) {
                            $this->SendDebug(__FUNCTION__, 'Lock state retrieval has been successful', 0);
                            $this->UpdateDeviceState(json_encode($result['body']));
                        }
                    }
                }
            }
        }
    }

    private function UpdateDeviceState(string $Data): void
    {
        $this->SendDebug(__FUNCTION__, $Data, 0);
        $openerData = json_decode($Data, true);
        /*
         * Modes Opener
         * 2    door mode           Operation mode after complete setup
         * 3    continuous mode     Ring to Open permanently active
         */
        if (array_key_exists('mode', $openerData)) {
            $actualContinuousModeState = $this->GetValue('ContinuousMode');
            if ($openerData['mode'] == 3) { # Ring to Open permanently active
                $continuousMode = true;
                $actionText = $this->Translate('Continuous mode was activated.');
            } else {
                $continuousMode = false;
                $actionText = $this->Translate('Continuous mode was deactivated.');
            }
            $this->SetValue('ContinuousMode', $continuousMode);
            if ($continuousMode != $actualContinuousModeState) {
                if ($this->ReadPropertyBoolean('UseActivityLog')) {
                    $this->UpdateLog(date('d.m.Y H:i:s'), $actionText);
                }
            }
        }
        /*
         * Lock States Opener
         * 0    untrained
         * 1    online
         * 2    -
         * 3    rto active
         * 4    -
         * 5    open
         * 6    -
         * 7    opening
         * 253  boot run
         * 254  -
         * 255  undefined
         */
        //State
        $deviceState = 255;
        if (array_key_exists('state', $openerData)) {
            $deviceState = $openerData['state'];
        }
        $this->SetValue('DeviceState', $deviceState);
        $actualRingToOpenState = $this->GetValue('RingToOpen');
        $ringToOpenState = false;
        $actionText = $this->Translate('Ring To Open was deactivated.');
        if ($deviceState == 3) {
            $ringToOpenState = true;
            $actionText = $this->Translate('Ring To Open was activated.');
        }
        $this->SetValue('RingToOpen', $ringToOpenState);
        if ($ringToOpenState != $actualRingToOpenState) {
            if ($this->ReadPropertyBoolean('UseActivityLog')) {
                $this->UpdateLog(date('d.m.Y H:i:s'), $actionText);
            }
        }
        //Battery
        $batteryState = false;
        $actualBatteryState = $this->GetValue('BatteryState');
        if (array_key_exists('batteryCritical', $openerData)) {
            $batteryState = (bool) $openerData['batteryCritical'];
        }
        $this->SetValue('BatteryState', $batteryState);
        if ($this->ReadPropertyBoolean('UseActivityLog')) {
            if ($batteryState != $actualBatteryState) {
                if ($batteryState) {
                    $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The battery of the smart lock is low!'));
                }
            }
        }
        //Ring action state
        if (array_key_exists('ringactionState', $openerData)) {
            $state = (bool) $openerData['ringactionState'];
            $this->SetValue('RingActionState', $state);
            if ($state) {
                $this->SetTimerInterval('UpdateDeviceState', 30000);
            }
        }
        //Last ring
        if (array_key_exists('ringactionTimestamp', $openerData)) {
            $date = $openerData['ringactionTimestamp'];
            $lastTimestamp = $this->ReadAttributeString('LastRingTimestamp');
            if ($date != $lastTimestamp) {
                $this->WriteAttributeString('LastRingTimestamp', $date);
                if ($this->ReadPropertyBoolean('UseActivityLog')) {
                    $date = new DateTime($date);
                    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
                    $date = $date->format('d.m.Y H:i:s');
                    $this->UpdateLog($date, $this->Translate('The doorbell rang.'));
                }
            }
        }
    }

    private function UpdateLog(string $TimeStamp, string $Action): void
    {
        if (!$this->ReadPropertyBoolean('UseActivityLog')) {
            return;
        }
        $string = $this->GetValue('ActivityLog');
        if (empty($string)) {
            $entries = [];
            $this->SendDebug(__FUNCTION__, 'String is empty!', 0);
        } else {
            $entries = explode('<tr><td>', $string);
            //Remove header
            foreach ($entries as $key => $entry) {
                if ($entry == "<table style='width: 100%; border-collapse: collapse;'><tr> <td><b>" . $this->Translate('Date') . '</b></td> <td><b>' . $this->Translate('Action') . '</b></td> </tr>') {
                    unset($entries[$key]);
                }
            }
            //Remove table
            foreach ($entries as $key => $entry) {
                $position = strpos($entry, '</table>');
                if ($position > 0) {
                    $entries[$key] = str_replace('</table>', '', $entry);
                }
            }
        }
        array_unshift($entries, $TimeStamp . '</td><td>' . $Action . '</td></tr>');
        $maximumEntries = $this->ReadPropertyInteger('ActivityLogMaximumEntries') - 1;
        foreach ($entries as $key => $entry) {
            if ($key > $maximumEntries) {
                unset($entries[$key]);
            }
        }
        $newString = "<table style='width: 100%; border-collapse: collapse;'><tr> <td><b>" . $this->Translate('Date') . '</b></td> <td><b>' . $this->Translate('Action') . '</b></td> </tr>';
        foreach ($entries as $entry) {
            $newString .= '<tr><td>' . $entry;
        }
        $newString .= '</table>';
        $this->SendDebug(__FUNCTION__, 'New string: ' . $newString, 0);
        $this->SetValue('ActivityLog', $newString);
    }

    private function SetUpdateTimer(): void
    {
        $interval = 0;
        //Only if the automatic update is deactivated
        if (!$this->ReadPropertyBoolean('UseAutomaticUpdate')) {
            $interval = $this->ReadPropertyInteger('UpdateInterval') * 1000;
        }
        $this->SetTimerInterval('UpdateDeviceState', $interval);
    }
}