<?php

/** @noinspection HttpUrlsUsage */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait NukiBridgeAPI
{
    private string $apiVersion = '1.13.1';

    /**
     * Enables the API.
     *
     * Enables the api (if not yet enabled) and returns the api token.
     * If no api token has yet been set, a new (random) one is generated.
     *
     * When issuing this API-call the bridge turns on its LED for 30 seconds.
     * The button of the bridge has to be pressed within this timeframe.
     * Otherwise, the bridge returns a negative success and no token.
     *
     * @return string
     */
    public function EnableAPI(): string
    {
        $endpoint = '/auth';
        $data = $this->SendDataToBridge($endpoint);
        if (!empty($data)) {
            $result = json_decode($data, true);
            if (is_array($result)) {
                if (array_key_exists('httpCode', $result)) {
                    $httpCode = $result['httpCode'];
                    $this->SendDebug(__FUNCTION__, 'Result http code: ' . $httpCode, 0);
                    if ($httpCode != 200) {
                        $this->SendDebug(__FUNCTION__, 'Abort, result http code: ' . $httpCode . ', must be 200!', 0);
                        return $data;
                    }
                }
                if (array_key_exists('body', $result)) {
                    $body = $result['body'];
                    if (array_key_exists('token', $body)) {
                        $token = $body['token'];
                        $this->SendDebug(__FUNCTION__, 'Token: ' . $token, 0);
                        $this->WriteAttributeString('BridgeAPIToken', $token);
                        if (!empty($token)) {
                            $this->UpdateFormField('GetTokenInfo', 'caption', 'The token was successfully retrieved!');
                            $this->UpdateFormField('GetTokenButton', 'visible', false);
                        }
                    }
                }
            } else {
                $this->UpdateFormField('GetTokenInfo', 'caption', 'An error has occurred!');
            }
        }
        $this->ValidateConfiguration();
        return $data;
    }

    /**
     * Toggles the authorization.
     *
     * Enables or disables the authorization via /auth and the publication of the local IP and port to the discovery URL.
     *
     * @param bool $Enable
     *
     * @return string
     */
    public function ToggleConfigAuth(bool $Enable): string
    {
        $endpoint = '/configAuth?enable=' . intval($Enable) . '&';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Returns a list of all paired devices.
     *
     * @return string
     */
    public function GetPairedDevices(): string
    {
        $endpoint = '/list?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Gets the state of the device.
     *
     * @param int $NukiID
     * @param int $DeviceType
     * @return string
     */
    public function GetLockState(int $NukiID, int $DeviceType = 0): string
    {
        /*
         * Warning
         *  /lockstate gets the current state directly from the device and so should not be used for constant polling to avoid draining the batteries too fast.
         *  /list can be used to get regular updates on the state, as is it cached on the bridge.
         */

        /*
         * Device Types
         * 0    Nuki Smart Lock 1.0/2.0
         * 2    Nuki Opener
         * 3    Nuki Smart Door
         * 4    Nuki Smart Lock 3.0 (Pro)
         */

        $endpoint = '/lockState?nukiId=' . $NukiID . '&deviceType=' . $DeviceType . '&';
        return $this->SendDataToBridge($endpoint);

        /*
         * Example-Response
         * {
         *      “mode”: 2,
         *      “state”: 1,
         *      “stateName”: “locked”,
         *      “batteryCritical”: false,
         *      “batteryCharging": false,
         *      “batteryChargeState": 85,
         *      “keypadBatteryCritical”: false,
         *      “ringactionTimestamp”: 2020-04-27T16:13:00+00:00”,
         *      “ringactionState”: false,
         *      “doorsensorState”: 2,
         *      “doorsensorStateName”: “door closed”,
         *      “success”: true
         * }
         */

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

        /*
         * Errors
         * HTTP 401 Returned if the given token is invalid or a hashed token parameter is missing.
         * HTTP 404 Returned if the given Nuki device is unknown
         * HTTP 503 Returned if the given Nuki device is offline
         */
    }

    /**
     * Sets the lock action of a device.
     *
     * @param int $NukiID
     * @param int $DeviceType
     * @param int $Action
     * @return string
     */
    public function SetLockAction(int $NukiID, int $DeviceType, int $Action): string
    {
        /*
         * Device Types
         * 0    Nuki Smart Lock 1.0/2.0
         * 2    Nuki Opener
         * 3    Nuki Smart Door
         * 4    Nuki Smart Lock 3.0 (Pro)
         */

        /*
         * Lock Actions Smart Lock
         * 1   unlock
         * 2   lock
         * 3   unlatch
         * 4   lock ‘n’ go
         * 5   lock ‘n’ go with unlatch
         */

        /*
         * Lock Actions Opener
         * 1    activate rto
         * 2    deactivate rto
         * 3    electric strike actuation
         * 4    activate continuous mode
         * 5    deactivate continuous mode
         */

        $endpoint = '/lockAction?nukiId=' . $NukiID . '&deviceType=' . $DeviceType . '&action=' . $Action . '&';
        return $this->SendDataToBridge($endpoint);

        /*
         * Example-Response
         * {
         *      “success”: true,
         *      “batteryCritical”: false
         * }
         */

        /*
         * Errors
         * HTTP 400 Returned if the given action is invalid
         * HTTP 401 Returned if the given token is invalid or a hashed token parameter is missing.
         * HTTP 404 Returned if the given SNuki device is unknown
         * HTTP 503 Returned if the given Nuki device is offline
         */
    }

    /**
     * Unpairs a device from the bridge.
     *
     * @param int $NukiID
     * @param int $DeviceType
     * @return string
     */
    public function UnpairDevice(int $NukiID, int $DeviceType = 0): string
    {
        $endpoint = '/unpair?nukiId=' . $NukiID . '&deviceType=' . $DeviceType . '&';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Gets information of the bridge.
     *
     * Returns all smart locks and openers in range and some device information of the bridge itself.
     *
     * @return string
     */
    public function GetBridgeInfo(): string
    {
        $endpoint = '/info?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Registers a new callback url.
     *
     * @return string
     */
    public function AddCallback(): string
    {
        $data = '';
        $callbackIP = $this->ReadPropertyString('SocketIP');
        $callbackPort = $this->ReadPropertyInteger('SocketPort');
        if (!empty($callbackIP) && !empty($callbackPort)) {
            $endpoint = '/callback/add?url=http%3A%2F%2F' . $callbackIP . '%3A' . $callbackPort . '%2Fhook%2Fnuki%2Fbridge%2F' . $this->InstanceID . '%2F&';
            $data = $this->SendDataToBridge($endpoint);
        }
        if (empty($callbackIP) || empty($callbackPort)) {
            echo $this->Translate('Please enter the IP address of the IP-Symcon server and the port for the Webhook!');
        }
        return $data;
    }

    /**
     * Returns all registered url callbacks.
     *
     * @return string
     */
    public function ListCallback(): string
    {
        $endpoint = '/callback/list?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Removes a previously added callback.
     *
     * @param int $CallbackID
     *
     * @return string
     */
    public function DeleteCallback(int $CallbackID): string
    {
        $endpoint = '/callback/remove?id=' . $CallbackID . '&';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Retrieves the log of the bridge.
     *
     * @return string
     */
    public function GetBridgeLog(): string
    {
        $endpoint = '/log?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Clears the log of the bridge.
     */
    public function ClearBridgeLog(): string
    {
        $endpoint = '/clearlog?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Immediately checks for a new firmware update and installs it.
     */
    public function UpdateBridgeFirmware(): string
    {
        $endpoint = '/fwupdate?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Reboots the bridge.
     */
    public function RebootBridge(): string
    {
        $endpoint = '/reboot?';
        return $this->SendDataToBridge($endpoint);
    }

    /**
     * Performs a factory reset.
     */
    public function FactoryResetBridge(): string
    {
        $endpoint = 'factoryReset?';
        return $this->sendDataToBridge($endpoint);
    }

    /**
     * Sends data to the bridge.
     *
     * @param string $Endpoint
     *
     * @return string
     */
    public function SendDataToBridge(string $Endpoint): string
    {
        $result = [];
        $token = $this->ReadAttributeString('BridgeAPIToken');
        $bridgeIP = $this->ReadPropertyString('BridgeIP');
        $bridgePort = $this->ReadPropertyInteger('BridgePort');
        if (empty($bridgeIP) || $bridgePort == 0) {
            $this->SendDebug(__FUNCTION__, 'Please check your bridge configuration!', 0);
            return json_encode($result);
        }
        if ($Endpoint != '/auth') {
            if (empty($token)) {
                $this->SendDebug(__FUNCTION__, 'Token is missing', 0);
                return json_encode($result);
            }
        }
        switch ($Endpoint) {
            case '/auth':
                $url = 'http://' . $bridgeIP . ':' . $bridgePort . $Endpoint;
                break;

            default:
                $url = 'http://' . $bridgeIP . ':' . $bridgePort . $Endpoint . 'token=' . $token;
                if ($this->ReadPropertyBoolean('UseEncryption')) {
                    $timestamp = gmdate("Y-m-d\TH:i:s\Z");
                    $randomNumber = random_int(0, 65535);
                    $data = $timestamp . ',' . $randomNumber . ',' . $token;
                    $hash = hash('sha256', $data);
                    $token = 'ts=' . $timestamp . '&rnr=' . $randomNumber . '&hash=' . $hash;
                    $url = 'http://' . $bridgeIP . ':' . $bridgePort . $Endpoint . $token;
                    $this->SendDebug(__FUNCTION__, $url, 0);
                }
        }
        $this->SendDebug(__FUNCTION__, $url, 0);
        $body = '';
        //Enter semaphore
        if (!IPS_SemaphoreEnter('Nuki_' . $this->InstanceID . '_SendDataToBridge', 5000)) {
            $this->SendDebug(__FUNCTION__, 'Abort, Semaphore reached!', 0);
            return json_encode(['httpCode' => 503, 'body' => []]);
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL               => $url,
            CURLOPT_HEADER            => true,
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_FAILONERROR       => true,
            CURLOPT_CONNECTTIMEOUT_MS => $this->ReadPropertyInteger('Timeout')]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!curl_errno($ch)) {
            $this->SendDebug(__FUNCTION__, 'Response http code: ' . $httpCode, 0);
            switch ($httpCode) {
                case 200:  # OK
                    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    $header = substr($response, 0, $header_size);
                    $body = json_decode(substr($response, $header_size), true);
                    $this->SendDebug(__FUNCTION__, 'Response header: ' . $header, 0);
                    $this->SendDebug(__FUNCTION__, 'Response body: ' . json_encode($body), 0);
                    break;

            }
        } else {
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
        }
        curl_close($ch);
        //Leave semaphore
        IPS_SemaphoreLeave('Nuki_' . $this->InstanceID . '_SendDataToBridge');
        $result = ['httpCode' => $httpCode, 'body' => $body];
        $this->SendDebug(__FUNCTION__, 'Result: ' . json_encode($result), 0);
        return json_encode($result);
    }
}