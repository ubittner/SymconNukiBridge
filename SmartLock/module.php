<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

class NukiSmartLockBridgeAPI extends IPSModuleStrict
{
    //Constants
    private const string LIBRARY_GUID = '{C761F228-6964-E7B7-A8F4-E90DC334649A}';
    private const string MODULE_PREFIX = 'NUKISLB';
    private const string NUKI_BRIDGE_GUID = '{37EAA787-55CE-E2B4-0799-2196F90F5E4C}';
    private const string NUKI_BRIDGE_DATA_GUID = '{E187AEEA-487B-ED93-403D-B7D51322A4DF}';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        ##### Properties

        $this->RegisterPropertyString('SmartLockUID', '');
        $this->RegisterPropertyString('SmartLockName', '');
        $this->RegisterPropertyBoolean('UseAutomaticUpdate', true);
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        $this->RegisterPropertyBoolean('UseDoorSensor', false);
        $this->RegisterPropertyBoolean('UseKeypad', false);
        $this->RegisterPropertyBoolean('UseActivityLog', true);
        $this->RegisterPropertyInteger('ActivityLogMaximumEntries', 10);

        ##### Variables

        //Smart Lock
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.SmartLock';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Lock'), 'LockClosed', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Unlock'), 'LockOpen', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 2, $this->Translate('Unlatch'), 'Door', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, $this->Translate("Lock 'n' Go"), 'Lock', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 4, $this->Translate("Lock 'n' Go with unlatch"), 'Door', 0x00FF00);
        $this->RegisterVariableInteger('SmartLock', 'Smart Lock', $profile, 100);
        $this->EnableAction('SmartLock');

        //Device state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DeviceState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Uncalibrated'), 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Locked'), 'LockClosed', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, $this->Translate('Unlocking'), 'LockOpen', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 3, $this->Translate('Unlocked'), 'LockOpen', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 4, $this->Translate('Locking'), 'LockClosed', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 5, $this->Translate('Unlatched'), 'Door', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 6, $this->Translate("Unlocked (Lock 'n' Go)"), 'LockOpen', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 7, $this->Translate('Unlatching'), 'Door', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 253, $this->Translate('-'), 'Information', -1);
        IPS_SetVariableProfileAssociation($profile, 254, $this->Translate('Motor blocked'), 'Warning', 0xFF0000);
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

        //Battery charge
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BatteryCharge';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileValues($profile, 0, 100, 1);
        IPS_SetVariableProfileText($profile, '', '%');
        IPS_SetVariableProfileIcon($profile, 'Battery');
        $this->RegisterVariableInteger('BatteryCharge', $this->Translate('Battery charge'), $profile, 240);

        //Battery charging
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BatteryCharging';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Battery');
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Inactive'), '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Active'), '', 0x00FF00);
        $this->RegisterVariableBoolean('BatteryCharging', $this->Translate('Battery charging'), $profile, 260);

        ##### Attribute

        $this->RegisterAttributeInteger('DeviceType', 99999);

        ##### Timer

        $this->RegisterTimer('UpdateDeviceState', 0, self::MODULE_PREFIX . '_UpdateSmartLockState(' . $this->InstanceID . ');');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();

        $profiles = ['SmartLock', 'DeviceState', 'BatteryState', 'BatteryCharge', 'BatteryCharging', 'DoorState', 'KeypadBatteryState'];
        foreach ($profiles as $profile) {
            $this->DeleteProfile($profile);
        }
    }

    public function ApplyChanges(): void
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Register FM Connect message
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);

        //Never delete this line!
        parent::ApplyChanges();

        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        ##### Maintain variables

        //Door sensor
        if ($this->ReadPropertyBoolean('UseDoorSensor')) {
            //Door state
            $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DoorState';
            if (!IPS_VariableProfileExists($profile)) {
                IPS_CreateVariableProfile($profile, 1);
            }
            IPS_SetVariableProfileIcon($profile, '');
            IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Unavailable'), 'Warning', -1);
            IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Deactivated'), 'Warning', 0x0000FF);
            IPS_SetVariableProfileAssociation($profile, 2, $this->Translate('Door closed'), 'Door', 0xFF0000);
            IPS_SetVariableProfileAssociation($profile, 3, $this->Translate('Door opened'), 'Door', 0x00FF00);
            IPS_SetVariableProfileAssociation($profile, 4, $this->Translate('Door state unknown'), 'Warning', -1);
            IPS_SetVariableProfileAssociation($profile, 5, $this->Translate('Calibrating'), 'Gear', 0xFFFF00);
            IPS_SetVariableProfileAssociation($profile, 99999, $this->Translate('Unknown'), 'Warning', 0xFF00000);
            $id = @$this->GetIDForIdent('DoorState');
            $this->MaintainVariable('DoorState', $this->Translate('Door state'), 1, $profile, 300, true);
            if (!$id) {
                $this->SetValue('DoorState', 99999);
            }
        } else {
            $this->MaintainVariable('DoorState', $this->Translate('Door state'), 1, '', 0, false);
            $this->DeleteProfile('DoorState');
        }

        //Keypad
        if ($this->ReadPropertyBoolean('UseKeypad')) {
            //Keypad battery state
            $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.KeypadBatteryState';
            if (!IPS_VariableProfileExists($profile)) {
                IPS_CreateVariableProfile($profile, 0);
            }
            IPS_SetVariableProfileIcon($profile, 'Battery');
            IPS_SetVariableProfileAssociation($profile, false, 'OK', '', 0x00FF00);
            IPS_SetVariableProfileAssociation($profile, true, $this->Translate('Low battery'), '', 0xFF0000);
            $this->MaintainVariable('KeypadBatteryState', $this->Translate('Keypad battery state'), 0, $profile, 400, true);
        } else {
            $this->MaintainVariable('KeypadBatteryState', $this->Translate('Keypad battery state'), 0, '', 0, false);
            $this->DeleteProfile('KeypadBatteryState');
        }

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

        if ($this->HasActiveParent()) {
            $this->DetermineDeviceType(false);
            $this->UpdateSmartLockState();
        }
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
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case FM_CONNECT:
                $this->DetermineDeviceType(false);
                $this->UpdateSmartLockState();
                break;

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
            if ($this->ReadPropertyString('SmartLockUID') != $nukiID) {
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
        if ($Ident == 'SmartLock') {
            $this->SetSmartLockAction($Value);
        }
    }

    #################### Public methods

    public function DetermineDeviceType(bool $Force): int
    {
        $nukiID = $this->ReadPropertyString('SmartLockUID');
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

    public function SetSmartLockAction(int $Action): bool
    {
        $nukiID = $this->ReadPropertyString('SmartLockUID');
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
        $success = false;
        $actualValue = $this->GetValue('SmartLock');
        $this->SetValue('SmartLock', $Action);
        /*
         * Lock Actions Smart Lock
         * 1   unlock
         * 2   lock
         * 3   unlatch
         * 4   lock ‘n’ go
         * 5   lock ‘n’ go with unlatch
         */
        switch ($Action) {
            case 0: # Lock
                $smartLockAction = 2;
                break;

            case 1: # Unlock
                $smartLockAction = 1;
                break;

            case 2: # Unlatch
                $smartLockAction = 3;
                break;

            case 3: # Lock 'n' Go
                $smartLockAction = 4;
                break;

            case 4: # Lock 'n' Go with unlatch
                $smartLockAction = 5;
                break;

            default:
                $this->SendDebug(__FUNCTION__, 'Unknown action: ' . $Action, 0);
                return false;
        }
        $data = [];
        $buffer = [];
        $data['DataID'] = self::NUKI_BRIDGE_DATA_GUID;
        $buffer['Command'] = 'SetLockAction';
        $buffer['Params'] = ['nukiId' => (int) $nukiID, 'deviceType' => $deviceType, 'lockAction' => $smartLockAction];
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $this->SendDebug(__FUNCTION__, 'Send data: ' . $data, 0);
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
                                $this->SetValue('SmartLock', $actualValue);
                                $this->SetUpdateTimer();
                                return false;
                            }
                        }
                    }
                }
            }
            if (array_key_exists('body', $result)) {
                $body = $result['body'];
                if (is_array($body)) {
                    $this->SendDebug(__FUNCTION__, 'Result body: ' . json_encode($body), 0);
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
                    case 0: # Lock
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The Smart Lock was locked.'));
                        $this->SetValue('DeviceState', 1);
                        break;

                    case 1: # Unlock
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The Smart Lock was unlocked.'));
                        $this->SetValue('DeviceState', 3);
                        break;

                    case 2: # Unlatch
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The Smart Lock was unlatched.'));
                        $this->SetValue('DeviceState', 3);
                        break;

                    case 3: # Lock 'n' Go
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The Smart Lock was unlocked. (lock ‘n’ go).'));
                        $this->SetValue('DeviceState', 1);
                        break;

                    case 4: # Lock 'n' Go with unlatch
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The Smart Lock was unlatched. (lock ‘n’ go).'));
                        $this->SetValue('DeviceState', 1);
                        break;
                }
            }
        }
        if (!$success) {
            //Revert
            $this->SetValue('SmartLock', $actualValue);
            $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The last switching command was not confirmed successfully.'));
        }
        $this->SetUpdateTimer();
        return $success;
    }

    public function UpdateSmartLockState(): void
    {
        $this->SetTimerInterval('UpdateDeviceState', 0);
        $this->GetSmartLockState();
        $this->SetUpdateTimer();
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
        if (empty($this->ReadPropertyString('SmartLockUID'))) {
            $status = 200;
            $result = false;
            $this->SendDebug(__FUNCTION__, 'Please enter the Nuki ID of the Smart Lock!', 0);
        }
        $this->SetStatus($status);
        return $result;
    }

    private function DeleteProfile(string $ProfileName): void
    {
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $ProfileName;
        if (@IPS_VariableProfileExists($profile)) {
            IPS_DeleteVariableProfile($profile);
        }
    }

    private function GetDeviceTypeDescription(): string
    {
        return match ($this->ReadAttributeInteger('DeviceType')) {
            0       => 'Nuki Smart Lock 1.0/2.0',
            4       => 'Nuki Smart Lock 3.0 (Pro)',
            default => $this->Translate('Unknown'),
        };
    }

    private function GetSmartLockState(): void
    {
        $nukiID = $this->ReadPropertyString('SmartLockUID');
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
        $smartLockData = json_decode($Data, true);
        /*
         * Lock States Smart Lock
         * 0    uncalibrated
         * 1    locked
         * 2    unlocking
         * 3    unlocked
         * 4    locking
         * 5    unlatched
         * 6    unlocked (lock ‘n’ go)
         * 7    unlatching
         * 253  -
         * 254  motor blocked
         * 255  undefined
         */
        //State
        $deviceState = 255;
        $actualDeviceState = $this->GetValue('DeviceState');
        $actionText = '';
        if (array_key_exists('state', $smartLockData)) {
            $deviceState = $smartLockData['state'];
        }
        $this->SetValue('DeviceState', $deviceState);
        if ($deviceState != $actualDeviceState) {
            switch ($deviceState) {
                case 1: # locked
                    $actionText = $this->Translate('The Smart Lock was locked.');
                    break;

                case 3: # unlocked
                    $actionText = $this->Translate('The Smart Lock was unlocked.');
                    break;

                case 5: # unlatched
                    $actionText = $this->Translate('The Smart Lock was unlatched.');
                    break;

                case 6: # unlocked (lock ‘n’ go)
                    $actionText = $this->Translate('The Smart Lock was unlocked. (lock ‘n’ go).');
                    break;
            }
        }
        if (!empty($actionText) && $this->ReadPropertyBoolean('UseActivityLog')) {
            if ($deviceState != $actualDeviceState && $actualDeviceState != 99999) {
                $this->UpdateLog(date('d.m.Y H:i:s'), $actionText);
            }
        }
        //Battery
        $batteryState = false;
        $batteryCharge = 0;
        $batteryCharging = false;
        $actualBatteryState = $this->GetValue('BatteryState');
        if (array_key_exists('batteryCritical', $smartLockData)) {
            $batteryState = (bool) $smartLockData['batteryCritical'];
        }
        if (array_key_exists('batteryChargeState', $smartLockData)) {
            $batteryCharge = $smartLockData['batteryChargeState'];
        }
        if (array_key_exists('batteryCharging', $smartLockData)) {
            $batteryCharging = (bool) $smartLockData['batteryCharging'];
        }
        $this->SetValue('BatteryState', $batteryState);
        if ($this->ReadPropertyBoolean('UseActivityLog')) {
            if ($batteryState != $actualBatteryState) {
                if ($batteryState) {
                    $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The battery of the smart lock is low!'));
                }
            }
        }
        $this->SetValue('BatteryCharge', $batteryCharge);
        $this->SetValue('BatteryCharging', $batteryCharging);
        //Door sensor
        /*
         * Doorsensor States
         * 1    deactivated
         * 2    door closed
         * 3    door opened
         * 4    door state unknown
         * 5    calibrating
         * 16	uncalibrated
         * 240	removed
         * 255	unknown
         */
        if ($this->ReadPropertyBoolean('UseDoorSensor')) {
            $doorState = 0;
            if (array_key_exists('doorsensorState', $smartLockData)) {
                $doorState = $smartLockData['doorsensorState'];
            }
            $actualDoorState = $this->GetValue('DoorState');
            $this->SetValue('DoorState', $doorState);
            $actionText = '';
            if ($this->ReadPropertyBoolean('UseActivityLog')) {
                switch ($doorState) {
                    case 2:
                        $actionText = $this->Translate('The door was closed.');
                        break;

                    case 3:
                        $actionText = $this->Translate('The door was opened.');
                        break;

                    case 4:
                        $actionText = $this->Translate('The door state is unknown!');
                        break;

                    case 240:
                        $actionText = $this->Translate('The door sensor was removed!');
                        break;
                }
                if (!empty($actionText)) {
                    if ($doorState != $actualDoorState && $actualDoorState != 99999) {
                        $this->UpdateLog(date('d.m.Y H:i:s'), $actionText);
                    }
                }
            }
        }
        //Keypad
        if ($this->ReadPropertyBoolean('UseKeypad')) {
            $keypadBatteryState = false;
            $actualkeypadBatteryState = $this->GetValue('KeypadBatteryState');
            if (array_key_exists('keypadBatteryCritical', $smartLockData)) {
                $keypadBatteryState = (bool) $smartLockData['keypadBatteryCritical'];
            }
            $this->SetValue('KeypadBatteryState', $keypadBatteryState);
            if ($this->ReadPropertyBoolean('UseActivityLog')) {
                if ($keypadBatteryState != $actualkeypadBatteryState) {
                    if ($keypadBatteryState) {
                        $this->UpdateLog(date('d.m.Y H:i:s'), $this->Translate('The battery of the keypad is low!'));
                    }
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