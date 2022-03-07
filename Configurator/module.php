<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

class NukiConfiguratorBridgeAPI extends IPSModule
{
    //Constants
    private const LIBRARY_GUID = '{C761F228-6964-E7B7-A8F4-E90DC334649A}';
    private const NUKI_BRIDGE_GUID = '{37EAA787-55CE-E2B4-0799-2196F90F5E4C}';
    private const NUKI_BRIDGE_DATA_GUID = '{E187AEEA-487B-ED93-403D-B7D51322A4DF}';
    private const NUKI_SMARTLOCK_GUID = '{A956C3A4-C5E5-E892-58E7-71813024E5C7}';
    private const NUKI_OPENER_GUID = '{2F33704C-4B0B-38B3-7EB0-65DF0630C41D}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('CategoryID', 0);

        //Connect to parent (Nuki Bridge Splitter)
        $this->ConnectParent(self::NUKI_BRIDGE_GUID);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
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

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $formData['elements'][2]['caption'] = 'ID: ' . $this->InstanceID . ', Version: ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']);
        $pairedDevices = json_decode($this->GetPairedDevices(), true);
        //Get all device instances first, first key is the UID and next key [0] is the instance id.
        $connectedInstanceIDs = [];
        foreach (IPS_GetInstanceListByModuleID(self::NUKI_SMARTLOCK_GUID) as $instanceID) {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                // Add the instance ID to a list for the given address. Even though addresses should be unique, users could break things by manually editing the settings
                $connectedInstanceIDs[IPS_GetProperty($instanceID, 'SmartLockUID')][] = $instanceID;
            }
        }
        foreach (IPS_GetInstanceListByModuleID(self::NUKI_OPENER_GUID) as $instanceID) {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                // Add the instance ID to a list for the given address. Even though addresses should be unique, users could break things by manually editing the settings
                $connectedInstanceIDs[IPS_GetProperty($instanceID, 'OpenerUID')][] = $instanceID;
            }
        }
        $values = [];
        $location = $this->GetCategoryPath($this->ReadPropertyInteger(('CategoryID')));
        if (!is_null($pairedDevices)) {
            foreach ($pairedDevices as $pairedDevice) {
                if (array_key_exists('deviceType', $pairedDevice)) {
                    $deviceType = $pairedDevice['deviceType'];
                    $deviceName = $pairedDevice['name'];
                    $nukiID = $pairedDevice['nukiId'];
                    switch ($deviceType) {
                        case 0: # Nuki Smart Lock 1.0/2.0
                        case 3: # Nuki Smart Door
                        case 4: # Nuki Smart Lock 3.0 (Pro)
                            switch ($deviceType) {
                                case 0:
                                    $productDesignation = 'Smart Lock 1.0/2.0';
                                    break;

                                case 3:
                                    $productDesignation = 'Smart Door ';
                                    break;

                                case 4:
                                    $productDesignation = 'Smart Lock 3.0 (Pro)';
                                    break;

                                default:
                                    $productDesignation = $this->Translate('Unknown');
                            }
                            $instanceID = $this->GetDeviceInstances($nukiID, 0);
                            $this->SendDebug(__FUNCTION__ . ' Smart Lock ID ', json_encode($nukiID), 0);
                            $this->SendDebug(__FUNCTION__ . ' Smart Lock Instance ID ', json_encode($instanceID), 0);
                            $value = [
                                'DeviceID' => $nukiID,
                                'DeviceType' => $deviceType,
                                'ProductDesignation' => $productDesignation,
                                'create' => [
                                    'moduleID' => self::NUKI_SMARTLOCK_GUID,
                                    'name' => $deviceName . ' (Bridge API)',
                                    'configuration' => [
                                        'SmartLockUID' => (string)$nukiID,
                                        'SmartLockName' => (string)$deviceName
                                    ],
                                    'location' => $location
                                ]
                            ];
                            break;

                        case 2: # Nuki Opener
                            $instanceID = $this->GetDeviceInstances($nukiID, 2);
                            $this->SendDebug(__FUNCTION__ . ' Opener ID ', json_encode($nukiID), 0);
                            $this->SendDebug(__FUNCTION__ . ' Opener Instance ID ', json_encode($instanceID), 0);
                            $value = [
                                'DeviceID' => $nukiID,
                                'DeviceType' => $deviceType,
                                'ProductDesignation' => 'Opener',
                                'create' => [
                                    'moduleID' => self::NUKI_OPENER_GUID,
                                    'name' => $deviceName . ' (Bridge API)',
                                    'configuration' => [
                                        'OpenerUID' => (string)$nukiID,
                                        'OpenerName' => (string)$deviceName
                                    ],
                                    'location' => $location
                                ]
                            ];
                            break;

                    }
                    if (isset($connectedInstanceIDs[$nukiID])) {
                        $value['name'] = IPS_GetName($connectedInstanceIDs[$nukiID][0]);
                        $value['instanceID'] = $connectedInstanceIDs[$nukiID][0];
                    } else {
                        $value['name'] = $pairedDevice['name'];
                        $value['instanceID'] = 0;
                    }
                    $values[] = $value;
                }
            }
        }
        foreach ($connectedInstanceIDs as $deviceUID => $instanceIDs) {
            foreach ($instanceIDs as $index => $instanceID) {
                //The first entry for each device UID was already added as valid value
                $device = false;
                foreach ($pairedDevices as $pairedDevice) {
                    if ($pairedDevice['nukiId'] == $deviceUID) {
                        $device = true;
                    }
                }
                if ($index === 0 && $device) {
                    continue;
                }
                //However, if a device UID is not a found device UID or has multiple instances, they are erroneous
                $values[] = [
                    'DeviceID' => $deviceUID,
                    'DeviceType' => '',
                    'ProductDesignation' => $this->Translate('Device not found!'),
                    'name' => IPS_GetName($instanceID),
                    'instanceID' => $instanceID
                ];
            }
        }
        $formData['actions'][0]['values'] = $values;
        return json_encode($formData);
    }

    public function OLD_GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version info
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $formData['elements'][2]['caption'] = 'ID: ' . $this->InstanceID . ', Version: ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']);
        $pairedDevices = json_decode($this->GetPairedDevices(), true);
        $this->SendDebug(__FUNCTION__ . ' Paired devices: ', json_encode($pairedDevices), 0);
        $values = [];
        $location = $this->GetCategoryPath($this->ReadPropertyInteger(('CategoryID')));
        //Paired devices
        if (!empty($pairedDevices)) {
            foreach ($pairedDevices as $device) {
                if (array_key_exists('deviceType', $device)) {
                    $deviceType = $device['deviceType'];
                    $deviceName = $device['name'];
                    $nukiID = $device['nukiId'];
                    switch ($deviceType) {
                        case 0: # Nuki Smart Lock 1.0/2.0
                        case 3: # Nuki Smart Door
                        case 4: # Nuki Smart Lock 3.0 (Pro)
                            switch ($deviceType) {
                                case 0:
                                    $productDesignation = 'Smart Lock 1.0/2.0';
                                    break;

                                case 3:
                                    $productDesignation = 'Smart Door ';
                                    break;

                                case 4:
                                    $productDesignation = 'Smart Lock 3.0 (Pro)';
                                    break;

                                default:
                                    $productDesignation = $this->Translate('Unknown');
                            }
                            $instanceID = $this->GetDeviceInstances($nukiID, 0);
                            $this->SendDebug(__FUNCTION__ . ' Smart Lock ID ', json_encode($nukiID), 0);
                            $this->SendDebug(__FUNCTION__ . ' Smart Lock Instance ID ', json_encode($instanceID), 0);
                            $values[] = [
                                'DeviceID' => $nukiID,
                                'DeviceType' => $deviceType,
                                'ProductDesignation' => $productDesignation,
                                'name' => $deviceName,
                                'instanceID' => $instanceID,
                                'create' => [
                                    'moduleID' => self::NUKI_SMARTLOCK_GUID,
                                    'name' => $deviceName . ' (Bridge API)',
                                    'configuration' => [
                                        'SmartLockUID' => (string)$nukiID,
                                        'SmartLockName' => (string)$deviceName
                                    ],
                                    'location' => $location
                                ]
                            ];
                            break;

                        case 2: # Nuki Opener
                            $instanceID = $this->GetDeviceInstances($nukiID, 2);
                            $this->SendDebug(__FUNCTION__ . ' Opener ID ', json_encode($nukiID), 0);
                            $this->SendDebug(__FUNCTION__ . ' Opener Instance ID ', json_encode($instanceID), 0);
                            $values[] = [
                                'DeviceID' => $nukiID,
                                'DeviceType' => $deviceType,
                                'ProductDesignation' => 'Opener',
                                'name' => $deviceName,
                                'instanceID' => $instanceID,
                                'create' => [
                                    'moduleID' => self::NUKI_OPENER_GUID,
                                    'name' => $deviceName . ' (Bridge API)',
                                    'configuration' => [
                                        'OpenerUID' => (string)$nukiID,
                                        'OpenerName' => (string)$deviceName
                                    ],
                                    'location' => $location
                                ]
                            ];
                            break;

                    }
                }
            }
        }
        $formData['actions'][0]['values'] = $values;
        return json_encode($formData);
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function GetCategoryPath(int $CategoryID): array
    {
        if ($CategoryID === 0) {
            return [];
        }
        $path[] = IPS_GetName($CategoryID);
        $parentID = IPS_GetObject($CategoryID)['ParentID'];
        while ($parentID > 0) {
            $path[] = IPS_GetName($parentID);
            $parentID = IPS_GetObject($parentID)['ParentID'];
        }
        return array_reverse($path);
    }

    private function GetPairedDevices(): string
    {
        if (!$this->HasActiveParent()) {
            return '';
        }
        $data = [];
        $buffer = [];
        $data['DataID'] = self::NUKI_BRIDGE_DATA_GUID;
        $buffer['Command'] = 'GetPairedDevices';
        $buffer['Params'] = '';
        $data['Buffer'] = $buffer;
        $data = json_encode($data);
        $result = json_decode($this->SendDataToParent($data), true);
        $devices = '{}';
        if (array_key_exists('body', $result)) {
            $this->SendDebug(__FUNCTION__, 'Actual data: ' . json_encode($result['body']), 0);
            $body = $result['body'];
            if (!empty($body)) {
                $devices = json_encode($body);
            }
        }
        return $devices;
    }

    private function GetDeviceInstances($DeviceUID, $DeviceType)
    {
        $instanceID = 0;
        switch ($DeviceType) {
            case 2: # Opener
                $moduleID = self::NUKI_OPENER_GUID;
                $propertyUIDName = 'OpenerUID';
                break;

            default: # Smart Lock
                $moduleID = self::NUKI_SMARTLOCK_GUID;
                $propertyUIDName = 'SmartLockUID';
        }
        $instanceIDs = IPS_GetInstanceListByModuleID($moduleID);
        foreach ($instanceIDs as $id) {
            if (IPS_GetProperty($id, $propertyUIDName) == $DeviceUID) {
                $instanceID = $id;
            }
        }
        return $instanceID;
    }
}