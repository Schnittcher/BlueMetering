<?php

declare(strict_types=1);
    class MeteringController extends IPSModule
    {
        private $obisIndexes = [
            '1-1:1.29.0' => 'ConsumptionLast15Minutes',
            '1-1:1.8.0'  => 'MeterReadingPurchase'

        ];
        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RequireParent('{6D19D525-1EDF-EA2E-F61A-0BBA162F1DAE}');
            $this->RegisterPropertyString('MeloID', '');
            $this->RegisterPropertyBoolean('Active', true);
            $this->RegisterPropertyInteger('UpdateInterval', 10);

            $this->RegisterAttributeString('LastAddedValues', '{}');

            $this->RegisterVariableFloat('ConsumptionLast15Minutes', $this->Translate('Consumption last 15 minutes'), '~Electricity', 0);
            $this->RegisterVariableFloat('MeterReadingPurchase', $this->Translate('Meter reading purchase'), '~Electricity', 1);

            $this->RegisterTimer('BMC_UpdateValues', 0, 'BMC_updateValues($_IPS[\'TARGET\']);');
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

            if ($this->ReadPropertyBoolean('Active')) {
                $this->SetTimerInterval('BMC_UpdateValues', $this->ReadPropertyInteger('UpdateInterval') * 60000);
                $this->SetStatus(102);
            } else {
                $this->SetStatus(104);
            }
        }

        public function updateValues()
        {
            $lastAddedValues = json_decode($this->ReadAttributeString('LastAddedValues'), true);

            foreach ($this->obisIndexes as $key => $obisIndex) {
                $startDate = $lastAddedValues[$key];
                $allValues = $this->getValues(0, 1000, $key, $startDate);

                $lastValueKey = array_key_last($allValues['items']);
                $newLastTimestamp = $allValues['items'][$lastValueKey]['dateTimeEnd'];

                if ($startDate == $newLastTimestamp) {
                    $this->LogMessage($this->Translate('No new values.'), KL_NOTIFY);
                    return;
                }
                $this->addValuesToArchive($allValues['items']);
                $this->addLastValueTimeToAttribute($obisIndex, $allValues['items'][$lastValueKey]['dateTimeEnd']);

                $ident = $this->obisIndexes[$allValues['items'][$lastValueKey]['obisIndex']];
                $varID = $this->GetIDForIdent($ident);
                $this->reAggregateVariable($varID);

                $this->setLogginStatusg($varID, false);
                IPS_Sleep(5);

                $this->SetValue($ident, $allValues['items'][$lastValueKey]['value']);
            }
        }

        public function initializeArchive()
        {
            foreach ($this->obisIndexes as $key => $obisIndex) {
                $this->initializeArchiveForObis($key);
            }
        }

        private function getValues(int $page, int $pageSize, string $obisIndex = '', string $startDate = '', string $endDate = '')
        {
            $Data = [];
            $Buffer = [];

            $Data['DataID'] = '{5CD6DDD2-9D13-14D5-B9E7-AEC3FC07E3ED}';
            $Buffer['Command'] = 'getMeteringValues';
            $Buffer['Params'] = [
                'meloId'      => $this->ReadPropertyString('MeloID'),
                'page'        => $page,
                'pageSize'    => $pageSize,
                'obisIndex'   => $obisIndex
            ];

            if ($startDate != '') {
                $Buffer['Params']['startDateTime'] = $startDate;
            }
            if ($endDate != '') {
                $Buffer['Params']['endDateTime'] = $endDate;
            }

            $Data['Buffer'] = $Buffer;
            $Data = json_encode($Data);
            $result = json_decode($this->SendDataToParent($Data), true);
            if (!$result) {
                return [];
            }

            return $result;
        }

        private function initializeArchiveForObis(string $obisIndex)
        {
            $allValues = $this->getValues(0, 1000, $obisIndex);
            $totalPages = $allValues['totalPages'];

            $this->addValuesToArchive($allValues['items']);

            if ($totalPages > 0) {
                for ($page = 1; $page <= $totalPages - 1; $page++) {
                    $pagedResult = $this->getValues($page, 1000, $obisIndex);
                    $this->addValuesToArchive($pagedResult['items']);
                }
            }

            $lastValueKey = array_key_last($pagedResult['items']);
            $this->addLastValueTimeToAttribute($obisIndex, $pagedResult['items'][$lastValueKey]['dateTimeEnd']);

            $ident = $this->obisIndexes[$pagedResult['items'][$lastValueKey]['obisIndex']];
            $varID = $this->GetIDForIdent($ident);

            $this->reAggregateVariable($varID);

            $this->setLoggingStatus($varID, false);
            IPS_Sleep(5);

            $this->SetValue($ident, $pagedResult['items'][$lastValueKey]['value']);
        }

        private function addValuesToArchive($items)
        {
            $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];

            $ident = $this->obisIndexes[$items[0]['obisIndex']];
            $varID = $this->GetIDForIdent($ident);

            $values = [];
            $tmpValue = [];
            foreach ($items as $key => $item) {
                $date = new DateTime($item['dateTimeEnd']);
                $endTimestamp = $date->getTimestamp();
                $values[] = [
                    'TimeStamp' => $endTimestamp,
                    'Value'     => $item['value']
                ];
            }

            $this->setLoggingStatus($varID, true);
            AC_AddLoggedValues($archiveID, $varID, $values);
        }

        private function addLastValueTimeToAttribute(string $obisIndex, string $dateTime)
        {
            $lastAddedValues = json_decode($this->ReadAttributeString('LastAddedValues'), true);
            $lastAddedValues[$obisIndex] = $dateTime;

            $this->WriteAttributeString('LastAddedValues', json_encode($lastAddedValues));
        }

        private function reAggregateVariable(int $varID)
        {
            $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
            AC_ReAggregateVariable($archiveID, $varID);
        }

        private function setLoggingStatus(int $varID, bool $state)
        {
            $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
            if ($state) {
                if (!AC_GetLoggingStatus($archiveID, $varID)) {
                    AC_SetLoggingStatus($archiveID, $varID, true);
                } else {
                    if (!AC_GetLoggingStatus($archiveID, $varID)) {
                        AC_SetLoggingStatus($archiveID, $varID, false);
                    }
                }
            }
        }
    }