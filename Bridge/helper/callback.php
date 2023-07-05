<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection HttpUrlsUsage */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait Helper_callback
{
    public function ManageCallback(): void
    {
        //Get all callbacks from bridge
        $host = (string) $this->ReadPropertyString('SocketIP');
        $port = (string) $this->ReadPropertyInteger('SocketPort');
        $useCallback = $this->ReadPropertyBoolean('UseCallback');
        $url = 'http://' . $host . ':' . $port . '/hook/nuki/bridge/' . $this->InstanceID . '/';
        $result = json_decode($this->ListCallback(), true);
        if (array_key_exists('body', $result)) {
            $body = $result['body'];
            if (!empty($body) && is_array($body)) {
                if (array_key_exists('callbacks', $result['body'])) {
                    $callbacks = $result['body']['callbacks'];
                    //Check if callback already exits
                    $exists = false;
                    if (!empty($callbacks)) {
                        foreach ($callbacks as $callback) {
                            if (array_key_exists('url', $callback)) {
                                if ($url == $callback['url']) {
                                    $exists = true;
                                }
                            }
                        }
                    }
                    //Add callback
                    if ($useCallback) {
                        $this->RegisterHook('/hook/nuki/bridge/' . $this->InstanceID);
                        if (!$exists) {
                            $this->AddCallback();
                        }
                    } // Delete callback
                    else {
                        $this->UnregisterHook('/hook/nuki/bridge/' . $this->InstanceID);
                        if (!empty($callbacks)) {
                            foreach ($callbacks as $callback) {
                                if (array_key_exists('url', $callback)) {
                                    if ($url == $callback['url']) {
                                        if (array_key_exists('id', $callback)) {
                                            $this->DeleteCallback($callback['id']);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, "Callback couldn't be managed. Please check manually!", 0);
        }
    }
}