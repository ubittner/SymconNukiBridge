<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpMissingReturnTypeInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class NukiSplitterBridgeAPI extends IPSModule
{
    //Helper
    use NukiBridgeAPI;
    use Helper_callback;
    use Helper_webHook;
    //Constants
    private const LIBRARY_GUID = '{C761F228-6964-E7B7-A8F4-E90DC334649A}';
    private const CORE_WEBHOOK_GUID = '{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}';
    private const NUKI_DEVICE_DATA_GUID = '{02DF61B7-859A-0460-5D52-E2FA4F4FEC5A}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties
        $this->RegisterPropertyString('BridgeIP', '');
        $this->RegisterPropertyInteger('BridgePort', 8080);
        $this->RegisterPropertyBoolean('UseEncryption', false);
        $this->RegisterPropertyString('BridgeID', '');
        $this->RegisterPropertyInteger('Timeout', 5000);
        $this->RegisterPropertyInteger('ExecutionTimeout', 60);
        $this->RegisterPropertyBoolean('UseCallback', false);
        $this->RegisterPropertyString('SocketIP', (count(Sys_GetNetworkInfo()) > 0) ? Sys_GetNetworkInfo()[0]['IP'] : '');
        $this->RegisterPropertyInteger('SocketPort', 3777);
        $this->RegisterPropertyInteger('CallbackID', 0);

        ##### Attribute
        $this->RegisterAttributeString('BridgeAPIToken', '');
    }

    public function Destroy()
    {
        //Unregister WebHook
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/nuki/bridge/' . $this->InstanceID);
        }

        // Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Validate configuration
        if ($this->ValidateConfiguration()) {
            $this->ManageCallback();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
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

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Version info
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $formData['elements'][2]['caption'] = 'ID: ' . $this->InstanceID . ', Version: ' . $library['Version'] . '-' . $library['Build'] . ', ' . date('d.m.Y', $library['Date']);
        //API token button
        $bridgeIP = $this->ReadPropertyString('BridgeIP');
        $bridgePort = $this->ReadPropertyInteger('BridgePort');
        $enabled = true;
        if (empty($bridgeIP) || $bridgePort == 0) {
            $enabled = false;
        }
        $formData['elements'][6]['enabled'] = $enabled;
        return json_encode($formData);
    }

    public function ForwardData($JSONString)
    {
        $this->SendDebug(__FUNCTION__, 'JSON String: ' . $JSONString, 0);
        $data = json_decode($JSONString);
        switch ($data->Buffer->Command) {
            case 'GetPairedDevices':
                $result = $this->GetPairedDevices();
                break;

            case 'GetLockState':
                $params = (array) $data->Buffer->Params;
                $result = $this->GetLockState($params['nukiId'], $params['deviceType']);
                break;

            case 'SetLockAction':
                $params = (array) $data->Buffer->Params;
                $result = $this->SetLockAction($params['nukiId'], $params['deviceType'], $params['lockAction']);
                break;

            default:
                $this->SendDebug(__FUNCTION__, 'Invalid command: ' . $data->Buffer->Command, 0);
                $result = '';
                break;
        }
        return $result;
    }

    public function UpdateToken(string $NewToken): void
    {
        if (!empty($NewToken)) {
            $this->WriteAttributeString('BridgeAPIToken', $NewToken);
            $this->ReloadForm();
            $this->ValidateConfiguration();
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function ValidateConfiguration(): bool
    {
        $status = 102;
        $result = true;
        //Check token
        if (empty($this->ReadAttributeString('BridgeAPIToken'))) {
            $status = 104;
            $result = false;
        }
        //Check bridge data
        if (empty($this->ReadPropertyString('BridgeIP')) || $this->ReadPropertyInteger('BridgePort') == 0) {
            $status = 200;
            $result = false;
        }
        $this->SetStatus($status);
        return $result;
    }
}