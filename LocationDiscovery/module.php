<?php

declare(strict_types=1);
    class LocationDiscovery extends IPSModule
    {
        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RequireParent('{6D19D525-1EDF-EA2E-F61A-0BBA162F1DAE}');
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

        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            $Locations = $this->getLocations();

            if (!array_key_exists('items', $Locations)) {
                $Locations = [];
            } else {
                $Locations = $Locations['items'];
            }

            $Values = [];
            $AddValue = [];

            foreach ($Locations as $key => $Location) {
                $Values[] = [
                    'MeloId'                => $Location['meloId'],
                    'Medium'                => $Location['medium'],
                    'Address'               => $Location['address']['street'] . ' ' . $Location['address']['streetNumber'] . ' '. $Location['address']['zip'] . ' ' . $Location['address']['city'],
                    'create'                => [
                        'moduleID'      => '{52C23D6C-635D-701B-F987-F5A774F87616}',
                        'configuration' => [
                            'MeloID'      => $Location['meloId']
                        ],
                        'name'     => $Location['meloId'] . ' -' . $Location['medium']
                    ]
                ];
            }
            $Form['actions'][0]['values'] = $Values;
            return json_encode($Form);
        }

        public function ReceiveData($JSONString)
        {
            $data = json_decode($JSONString);
            IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
        }

        public function getLocations()
        {
            $Data = [];
            $Buffer = [];

            $Data['DataID'] = '{5CD6DDD2-9D13-14D5-B9E7-AEC3FC07E3ED}';
            $Buffer['Command'] = 'getLocations';
            $Buffer['Params'] = [
                'page'     => 0,
                'pageSize' => 1000
            ];
            $Data['Buffer'] = $Buffer;
            $Data = json_encode($Data);
            $result = json_decode($this->SendDataToParent($Data), true);
            if (!$result) {
                return [];
            }
            return $result;
        }
    }