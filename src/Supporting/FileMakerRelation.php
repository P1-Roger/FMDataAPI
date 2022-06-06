<?php

namespace INTERMediator\FileMakerServer\RESTAPI\Supporting;

use Iterator;

/**
 * Class FileMakerRelation is the record set of queried data. This class implements Iterator interface.
 * The object of this class is going to be generated by the FileMakerLayout class,
 * and you shouldn't call the constructor of this class.
 *
 * @package INTER-Mediator\FileMakerServer\RESTAPI
 * @link https://github.com/msyk/FMDataAPI GitHub Repository
 * @property string $<<field_name>> The field value named as the property name.
 * @property FileMakerRelation $<<portal_name>> FileMakerRelation object associated with the property name.
 *    The table occurrence name of the portal can be the 'portal_name,' and also the object name of the portal.
 * @version 26
 * @author Masayuki Nii <nii@msyk.net>
 * @copyright 2017-2022 Masayuki Nii (Claris FileMaker is registered trademarks of Claris International Inc. in the U.S. and other countries.)
 */
class FileMakerRelation implements Iterator
{
    /**
     * @var null
     * @ignore
     */
    private $data = null;
    /**
     * @var null
     * @ignore
     */
    private $dataInfo = null;
    /**
     * @var null|string
     * @ignore
     */
    private $result = null; // OK for output from API, RECORD, PORTAL, PORTALRECORD
    /**
     * @var int|null
     * @ignore
     */
    private $errorCode = null;
    /**
     * @var int
     * @ignore
     */
    private $pointer = 0;
    /**
     * @var null
     * @ignore
     */
    private $portalName = null;
    /**
     * @var CommunicationProvider The instance of the communication class.
     * @ignore
     */
    private $restAPI = null;

    /**
     * FileMakerRelation constructor.
     *
     * @param array<object> $responseData
     * @param object $infoData
     * @param string $result
     * @param int $errorCode
     * @param string $portalName
     * @param CommunicationProvider $provider
     *
     * @ignore
     */
    public function __construct($responseData, $infoData,
                                $result = "PORTAL", $errorCode = 0, $portalName = null, $provider = null)
    {
        $this->data = $responseData;
        $this->dataInfo = $infoData;
        $this->result = $result;
        $this->errorCode = $errorCode;
        $this->portalName = $portalName;
        $this->restAPI = $provider;
        if ($errorCode === 0 && $portalName && is_array($infoData)) {
            foreach ($infoData as $pdItem) {
                if (property_exists($pdItem, 'portalObjectName') && $pdItem->portalObjectName == $portalName ||
                    !property_exists($pdItem, 'portalObjectName') && $pdItem->table == $portalName) {
                    $this->dataInfo = $pdItem;
                }
            }
        }
    }

    /**
     * @ignore
     */
    public function getDataInfo()
    {
        return $this->dataInfo;
    }

    /**
     * Get the table occurrence name of query to get this relation.
     *
     * @return string  The table occurrence name.
     */
    public function getTargetTable()
    {
        return ($this->dataInfo) ? $this->dataInfo->table : null;
    }

    /**
     * Get the total record count of query to get this relation. Portal relation doesn't have this information and returns NULL.
     *
     * @return integer  The total record count.
     */
    public function getTotalCount()
    {
        return ($this->dataInfo && property_exists($this->dataInfo, 'totalRecordCount')) ?
            $this->dataInfo->totalRecordCount : null;
    }

    /**
     * Get the founded record count of query to get this relation. If the relation comes from getRecord() method,
     * this method returns 1.
     *
     * @return integer  The founded record count.
     */
    public function getFoundCount()
    {
        return ($this->dataInfo) ? $this->dataInfo->foundCount : null;
    }

    /**
     * Get the returned record count of query to get this relation. If the relation comes from getRecord() method,
     * this method returns 1.
     *
     * @return integer  The rreturned record count.
     */
    public function getReturnedCount()
    {
        return ($this->dataInfo) ? $this->dataInfo->returnedCount : null;
    }

    /**
     * If the portal name is different with the name used as the portal referencing name, this method can set it.
     *
     * @param string $name The portal name.
     */
    public function setPortalName($name): void
    {
        $this->portalName = $name;
    }

    /**
     * The record pointer goes back to previous record. This does not care the range of pointer value.
     */
    public function previous(): void
    {
        $this->pointer--;
    }

    /**
     * The record pointer goes forward to previous record. This does not care the range of pointer value.
     */
    public function next(): void
    {
        $this->pointer++;
    }

    /**
     * The record pointer goes to first record.
     */
    public function last(): void
    {
        $this->pointer = count($this->data) - 1;
    }

    /**
     * The record pointer goes to the specified record.
     *
     * @param int $position The position of the record. The first record is 0.
     */
    public function moveTo($position): void
    {
        $this->pointer = $position - 1;
    }

    /**
     * Count the number of records.
     * This method is defined in the Iterator interface.
     *
     * @return int The number of records.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @param $key
     *
     * @return FileMakerRelation|string|null
     * @ignore
     */
    public function __get($key)
    {
        return $this->field($key);
    }

    /**
     * Return the array of field names.
     *
     * @return array List of field names
     */
    public function getFieldNames(): array
    {
        $list = [];
        if (isset($this->data)) {
            switch ($this->result) {
                case 'OK':
                    if (isset($this->data[$this->pointer])
                        && isset($this->data[$this->pointer]->fieldData)
                    ) {
                        foreach ($this->data[$this->pointer]->fieldData as $key => $val) {
                            array_push($list, $key);
                        }
                    }
                    break;
                case 'PORTAL':
                    if (isset($this->data[$this->pointer])) {
                        foreach ($this->data[$this->pointer] as $key => $val) {
                            array_push($list, $key);
                        }
                    }
                    break;
                case 'RECORD':
                    if (isset($this->data->fieldData)) {
                        foreach ($this->data->fieldData as $key => $val) {
                            array_push($list, $key);
                        }
                    }
                    break;
                default:
            }
        }

        return $list;
    }

    private function getNumberedRecord($num)
    {
        $value = null;
        if (isset($this->data) && isset($this->data[$num])) {
            $tmpInfo = $this->getDataInfo();
            $dataInfo = null;
            if ($tmpInfo !== null && is_object($tmpInfo)) {
                $dataInfo = clone $tmpInfo;
                $dataInfo->returnedCount = 1;
            }
            $value = new FileMakerRelation(
                $this->data[$num], $dataInfo, ($this->result == "PORTAL") ? "PORTALRECORD" : "RECORD",
                $this->errorCode, $this->portalName, $this->restAPI);
        }
        return $value;
    }

    /**
     * Returns the fiest record of the query result.
     *
     * @return FileMakerRelation|null The record set of the record.
     */
    public function getFirstRecord()
    {
        return $this->getNumberedRecord(0);
    }

    /**
     * Returns the last record of the query result.
     *
     * @return FileMakerRelation|null The record set of the record.
     */
    public function getLastRecord()
    {
        return $this->getNumberedRecord(count($this->data) - 1);
    }

    /**
     * Returns the array of the query result. Usually iterating by using foreach is a better way.
     *
     * @return array|null The FileMakerRelation objects of the records.
     */
    public function getRecords()
    {
        $records = [];
        foreach ($this as $record) {
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Export to array
     *
     * @return void
     */
    public function toArray(): array
    {
        if (isset($this->data)) {
            switch ($this->result) {
                case 'OK':
                    if (isset($this->data[$this->pointer])
                        && isset($this->data[$this->pointer]->fieldData)) {
                        return json_decode(json_encode($this->data[$this->pointer]->fieldData));
                    }
                    break;
                case 'PORTAL':
                    if (isset($this->data[$this->pointer])) {
                        return json_decode(json_encode($this->data[$this->pointer]));
                    }
                    break;
                case 'RECORD':
                    if (isset($this->data->fieldData)) {
                        return json_decode(json_encode($this->data->fieldData));
                    }
                    break;
            }
        }

        return [];
    }

    /**
     * Return the array of portal names.
     *
     * @return array List of portal names
     */
    public function getPortalNames()
    {
        $list = [];
        if (isset($this->data)) {
            foreach ($this->data as $key) {
                if (property_exists($key, 'portalData')) {
                    foreach ($key->portalData as $name => $val) {
                        array_push($list, $name);
                    }
                }
            }
        }

        return $list;
    }

    /**
     * The field value of the first parameter. Or the FileMakerRelation object associated with the the first paramenter.
     *
     * @param string $name The field or portal name.
     * The table occurrence name of the portal can be the portal name, and also the object name of the portal.
     * @param string $toName The table occurrence name of the portal as the prefix of the field name.
     *
     * @return string|FileMakerRelation The field value as string, or the FileMakerRelation object of the portal.
     * @throws Exception The field specified in parameters doesn't exist.
     * @see FMDataAPI::setFieldHTMLEncoding() Compatible mode for FileMaker API for PHP.
     *
     */
    public function field($name, $toName = null)
    {
        $toName = is_null($toName) ? "" : "{$toName}::";
        $fieldName = "{$toName}$name";
        $value = null;
        if (isset($this->data)) {
            switch ($this->result) {
                case "OK":
                    if (isset($this->data[$this->pointer])) {
                        if (isset($this->data[$this->pointer]->fieldData) &&
                            isset($this->data[$this->pointer]->fieldData->$name)
                        ) {
                            $value = $this->data[$this->pointer]->fieldData->$name;
                        } else if (isset($this->data[$this->pointer]->portalData) &&
                            isset($this->data[$this->pointer]->portalData->$name)
                        ) {
                            $value = new FileMakerRelation(
                                $this->data[$this->pointer]->portalData->$name,
                                property_exists($this->data[$this->pointer], 'portalDataInfo') ? $this->data[$this->pointer]->portalDataInfo : null,
                                "PORTAL", 0, null, $this->restAPI);
                        }
                    }
                    break;
                case "PORTAL":
                    if (isset($this->data[$this->pointer]) &&
                        isset($this->data[$this->pointer]->$fieldName)
                    ) {
                        $value = $this->data[$this->pointer]->$fieldName;
                    }
                    break;
                case "RECORD":
                    if (isset($this->data->fieldData) && isset($this->data->fieldData->$name)) {
                        $value = $this->data->fieldData->$name;
                    } else if (isset($this->data->portalData) && isset($this->data->portalData->$name)) {
                        $value = new FileMakerRelation(
                            $this->data->portalData->$name,
                            property_exists($this->data, 'portalDataInfo') ? $this->data->portalDataInfo : null,
                            "PORTAL", 0, $name, $this->restAPI);
                    } else if (isset($this->data->fieldData->$fieldName)) {
                        $value = $this->data->fieldData->$fieldName;
                    }
                    break;
                case "PORTALRECORD":
                    $convinedName = "{$this->portalName}::{$fieldName}";
                    if (isset($this->data->$fieldName)) {
                        $value = $this->data->$fieldName;
                    } else if (isset($this->data->$convinedName)) {
                        $value = $this->data->$convinedName;
                    }
                    break;
                default:
            }
        }
        if (is_null($value)) {
            throw new \Exception("Field {$fieldName} doesn't exist.");
        }
        if ($this->restAPI && $this->restAPI->fieldHTMLEncoding && !is_object($value)) {
            $value = htmlspecialchars($value);
        }
        return $value;
    }

    /**
     * Return the value of special field recordId in the current pointing record.
     *
     * @return int The value of special field recordId.
     */
    public function getRecordId()
    {
        $value = null;
        switch ($this->result) {
            case "OK":
                if (isset($this->data[$this->pointer])) {
                    if (isset($this->data[$this->pointer]->recordId)
                    ) {
                        $value = $this->data[$this->pointer]->recordId;
                    }
                }
                break;
            case "PORTAL":
                if (isset($this->data[$this->pointer]) &&
                    isset($this->data[$this->pointer]->recordId)
                ) {
                    $value = $this->data[$this->pointer]->recordId;
                }
                break;
            case "RECORD":
            case "PORTALRECORD":
                if (isset($this->data) && isset($this->data->recordId)) {
                    $value = $this->data->recordId;
                }
                break;
        }

        return $value;
    }

    /**
     * Return the value of special field modId in the current pointing record.
     *
     * @return int The value of special field modId.
     */
    public function getModId()
    {
        $value = null;
        switch ($this->result) {
            case "OK":
                if (isset($this->data[$this->pointer])) {
                    if (isset($this->data[$this->pointer]->modId)
                    ) {
                        $value = $this->data[$this->pointer]->modId;
                    }
                }
                break;
            case "PORTAL":
                if (isset($this->data[$this->pointer]) &&
                    isset($this->data[$this->pointer]->modId)
                ) {
                    $value = $this->data[$this->pointer]->modId;
                }
                break;
            case "RECORD":
            case "PORTALRECORD":
                if (isset($this->data) && isset($this->data->modId)) {
                    $value = $this->data->modId;
                }
                break;
        }

        return $value;
    }

    /**
     * Return the base64 encoded data in container field with streaming interface. The access with
     * streaming url depends on the setCertValidating(_) call, and it can work on self-signed certificate as a default.
     * Thanks to 'base64bits' as https://github.com/msyk/FMDataAPI/issues/18.
     *
     * @param string $name The container field name.
     * The table occurrence name of the portal can be the portal name, and also the object name of the portal.
     * @param string $toName The table occurrence name of the portal as the prefix of the field name.
     *
     * @return string The base64 encoded data in container field.
     */
    public function getContainerData($name, $toName = null)
    {
        $fieldValue = $this->field($name, $toName);
        if (strpos($fieldValue, "https://") !== 0) {
            throw new \Exception("The field '{$name}' is not field name or container field.");
        }
        try {
            return $this->restAPI->accessToContainer($fieldValue);
        } catch (\Exception $e) {
            throw $e;
        }

        return null;
    }

    /**
     * Return the current element. This method is implemented for Iterator interface.
     *
     * @return FileMakerRelation|null The record set of the current pointing record.
     */
    public function current(): ?FileMakerRelation
    {
        $value = null;
        if (isset($this->data) &&
            isset($this->data[$this->pointer])
        ) {
            $tmpInfo = $this->getDataInfo();
            $dataInfo = null;
            if ($tmpInfo !== null && is_object($tmpInfo)) {
                $dataInfo = clone $tmpInfo;
                $dataInfo->returnedCount = 1;
            }
            $value = new FileMakerRelation(
                $this->data[$this->pointer], $dataInfo,
                ($this->result == "PORTAL") ? "PORTALRECORD" : "RECORD",
                $this->errorCode, $this->portalName, $this->restAPI);
        }

        return $value;
    }

    /**
     * Return the key of the current element. This method is implemented for Iterator interface.
     *
     * @return integer The current number as the record pointer.
     */
    public function key(): int
    {
        return $this->pointer;
    }

    /**
     * Checks if current position is valid. This method is implemented for Iterator interface.
     *
     * @return bool Returns true on existing the record or false on not existing.
     */
    public function valid(): bool
    {
        if (isset($this->data) &&
            isset($this->data[$this->pointer])
        ) {
            return true;
        }

        return false;
    }

    /**
     * Rewind the Iterator to the first element. This method is implemented for Iterator interface.
     */
    public function rewind(): void
    {
        $this->pointer = 0;
    }
}