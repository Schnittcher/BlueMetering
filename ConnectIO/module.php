<?php

declare(strict_types=1);
    class ConnectIO extends IPSModule
    {
        private $backend = 'https://app.bluemetering.de/backend';

        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RegisterPropertyString('apiKey', '');
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
        }

        public function ForwardData($JSONString)
        {
            $this->SendDebug(__FUNCTION__, $JSONString, 0);
            $data = json_decode($JSONString, true);
            switch ($data['Buffer']['Command']) {
                case 'getLocations':
                    $result = $this->sendRequest('public/api/meteringlocation', json_encode($data['Buffer']['Params']));
                    break;
                case 'getMeteringValues':
                    $meloID = $data['Buffer']['Params']['meloId'];
                    unset($data['Buffer']['Params']['meloId']);
                    $result = $this->sendRequest('public/api/metering/' . $meloID, json_encode($data['Buffer']['Params']));
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'No Command', 0);
                    return;
            }
            return json_encode($result);
        }

        private function sendRequest(string $endpoint, string $params, string $method = 'GET')
        {
            $apiKey = $this->ReadPropertyString('apiKey');

            if ($apiKey == '') {
                $this->LogMessage($this->Translate('You need to save the API key in the configuration.'), KL_ERROR);
                return;
            }
            if ($endpoint == '') {
                $this->LogMessage($this->Translate('No endpoint was specified.'), KL_ERROR);
                return;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->backend . '/' . $endpoint);
            //curl_setopt($ch, CURLOPT_USERAGENT, 'Symcon');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: */*',
                'Apikey: ' . $apiKey,
                'Internalapikey: ' . $apiKey,
            ]);

            if ($method == 'POST' || $method == 'PUT' || $method == 'DELETE') {
                if ($method == 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                }
                if (in_array($method, ['PUT', 'DELETE'])) {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
            if ($method == 'GET') {
                $query = http_build_query(json_decode($params));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_URL, $this->backend . '/' . $endpoint . '?' . $query);
                $this->SendDebug('URL', $this->backend . '/' . $endpoint.$query, 0);
            }

            $apiResult = curl_exec($ch);
            $this->SendDebug(__FUNCTION__ . ' :: Result', $apiResult, 0);
            $headerInfo = curl_getinfo($ch);
            if ($headerInfo['http_code'] == 200) {
                $this->SetStatus(102);
                return json_decode($apiResult, true);
            } else {
                $this->LogMessage('sendRequest Error - Curl Error:' . curl_error($ch) . 'HTTP Code: ' . $headerInfo['http_code'], KL_ERROR);
                return new stdClass();
            }
            curl_close($ch);
        }
    }