<?php

use Sameday\Objects\Locker\LockerObject;
use Sameday\Objects\ParcelStatusHistory\ExpeditionObject;
use Sameday\Objects\ParcelStatusHistory\SummaryObject;
use Sameday\Objects\PickupPoint\PickupPointObject;
use Sameday\Objects\Service\OptionalTaxObject;
use Sameday\Objects\Service\ServiceObject;

trait SamedayTraitAdminModel {

    /**
     * @var SamedayVersionValidator
     */
    private $samedayVersionValidator;

    public function __construct($registry){
        parent::__construct($registry);
        $this->samedayVersionValidator = Samedayclasses::getSamedayVersionValidator();
    }
    /**
     * @return void
     */
    public function install()
    {
        $this->createAwbTable();
        $this->createServiceTable();
        $this->createPickUpPointTable();
        $this->createPackageTable();
        $this->createLockerTable();
        $this->createCountiesTable();
        $this->createCitiesTable();
        if($this->samedayVersionValidator->isOc4()){
            $this->createOrdersLockerTable();
        }
        $this->createBulkAwbTable();

    }

    /**
     * @return void
     */
    public function uninstall()
    {
        $this->dropAwbTable();
        $this->dropServiceTable();
        $this->dropPickUpPointTable();
        $this->dropPackageTable();
        $this->dropLockerTable();
        $this->dropCountiesTable();
        $this->dropCitiesTable();
        if($this->samedayVersionValidator->isOc4()){
            $this->dropOrdersLockerTable();
        }
        $this->dropBulkAwbTable();
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function saveAwb(array $data)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_awb(
                order_id,
                awb_number,
                parcels,
                awb_cost
            ) VALUES (
                '{$this->db->escape($data['order_id'])}',
                '{$this->db->escape($data['awb_number'])}',
                '{$this->db->escape($data['parcels'])}',
                '{$this->db->escape($data['awb_cost'])}'
            )";

        $this->db->query($query);
    }

    public function updateAwbParcels(int $orderId, string $serializedParcels): void
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . 'sameday_awb SET parcels = \''
            . $this->db->escape($serializedParcels) . '\'
            WHERE order_id = ' . (int)$orderId
        );
    }

    /**
     * @param int $orderId
     * @param array $service
     * @param int $lockerId
     * @param string $lockerAddress
     *
     * @return void
     */
    public function updateShippingMethodAfterPostAwb(
        int $orderId,
        array $service,
        int $lockerId = null,
        string $lockerAddress = null
    ) {
        $shippingCode = sprintf(
            'sameday.%s.%s',
            $this->db->escape($service['sameday_code']),
            $this->db->escape($service['sameday_id'])
        );

        if (null !== $lockerId && null !== $lockerAddress) {
            $shippingCode .= sprintf('.%s.%s', $this->db->escape($lockerId), $this->db->escape($lockerAddress));
        }

        if($this->samedayVersionValidator->isOc4()){
            $shippingMethodData = json_decode($this->db->query("SELECT shipping_method FROM " . DB_PREFIX . "order WHERE order_id = " . $orderId)->row['shipping_method']);

            $shippingMethodData->sameday_name = $service['name'];
            $shippingMethodData->code = $shippingCode;

            $shippingMethod = new SamedayShippingMethod(
                $shippingMethodData->sameday_name,
                $shippingMethodData->name,
                $shippingMethodData->code,
                $shippingMethodData->service_id,
                $shippingMethodData->title,
                $shippingMethodData->cost,
                $shippingMethodData->tax_class_id,
                $shippingMethodData->text
            );

            $this->updateShippingMethod($shippingMethod, $orderId);
        }else{
            $this->db->query('
            UPDATE ' . DB_PREFIX . "order SET 
                shipping_method='{$this->db->escape($service['name'])}',
                shipping_code='{$this->db->escape($shippingCode)}'
            WHERE 
                order_id = '{$this->db->escape($orderId)}'
            ");
        }
    }

    public function updateShippingMethod(SamedayShippingMethod $shippingMethod, $orderId): void{
        $this->db->query('
            UPDATE ' . DB_PREFIX . "order SET 
                shipping_method='" . $shippingMethod->toJson() . "' 
            WHERE 
                order_id = '{$this->db->escape($orderId)}'
        ");
    }

    /**
     * @param string $hostCountry
     *
     * @return array
     */
    public function getCounties(string $hostCountry): array
    {
        $table = DB_PREFIX . "country";
        $isoCode = $hostCountry;

        $query = sprintf("SELECT country_id FROM %s WHERE iso_code_2='%s'", $table, $this->db->escape($isoCode));

        $result = $this->db->query($query);
        if (empty($result->row)) {
            return array();
        }

        $query = 'SELECT * FROM ' . DB_PREFIX . "zone WHERE country_id='{$result->row['country_id']}'";

        return $this->db->query($query)->rows;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        $rows = $this->db->query(sprintf(
            "SELECT * FROM %s WHERE testing='%s'",
            DB_PREFIX . "sameday_service",
            $this->getConfig('sameday_testing')
        ))->rows;

        foreach ($rows as $k => $row) {
            if (!array_key_exists('sameday_code', $row)) {
                $rows[$k]['sameday_code'] = '';
            }
        }

        return $rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getService(int $id)
    {
        $table = DB_PREFIX . "sameday_service";
        $id = $this->db->escape($id);

        $query = "SELECT * FROM $table WHERE id=$id";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $samedayId
     *
     * @return array
     */
    public function getServiceSameday(int $samedayId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE sameday_id='%s' AND testing='%s'",
                DB_PREFIX . "sameday_service",
                $this->db->escape($samedayId),
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param string $samedayCode
     *
     * @return array
     */
    public function getSamedayServiceByCode(string $samedayCode): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE sameday_code='%s' AND testing='%s'",
                DB_PREFIX . "sameday_service",
                $this->db->escape($samedayCode),
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @return void
     */
    public function ensureSamedayServiceCodeColumn(): void
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'sameday_code'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query(
            sprintf(
                "ALTER TABLE %s ADD `sameday_code` VARCHAR(255) DEFAULT '' NOT NULL",
                DB_PREFIX . "sameday_service"
            )
        );
    }

    /**
     * @return void
     */
    public function ensureSamedayServiceOptionalTaxColumn(): void
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'service_optional_taxes'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query(
            sprintf(
                "ALTER TABLE %s ADD `service_optional_taxes` TEXT DEFAULT NULL",
                DB_PREFIX . "sameday_service"
            )
        );
    }

    /**
     * @param int $id
     * @param ServiceObject $serviceObject
     *
     * @return void
     */
    public function editService(int $id, ServiceObject $serviceObject): void
    {
        $serviceOptionalTaxes = $this->db->escape($this->buildServiceOptionalTaxes($serviceObject->getOptionalTaxes()));
        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET
                sameday_id='{$this->db->escape($serviceObject->getId())}',
                sameday_name='{$this->db->escape($serviceObject->getName())}',
                sameday_code='{$this->db->escape($serviceObject->getCode())}',
                service_optional_taxes='$serviceOptionalTaxes'
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param int $id
     * @param int $status
     *
     * @return void
     */
    public function updateServiceStatus(int $id, int $status): void
    {
        $this->db->query(
            sprintf(
                "UPDATE %s SET status='%s' WHERE id = '%s'",
                DB_PREFIX . "sameday_service",
                $status,
                $id
            )
        );
    }

    /**
     * @param ServiceObject $service
     *
     * @return void
     */
    public function addService(ServiceObject $service): void
    {
        $testing = $this->getConfig("sameday_testing");
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_service (
                sameday_id, 
                sameday_name, 
                sameday_code,
                testing, 
                status,
                service_optional_taxes
            ) VALUES (
                '{$this->db->escape($service->getId())}', 
                '{$this->db->escape($service->getName())}', 
                '{$this->db->escape($service->getCode())}', 
                '$testing',
                0,
                '{$this->db->escape($this->buildServiceOptionalTaxes($service->getOptionalTaxes()))}'
            )";

        $this->db->query($query);
    }

    /**
     * @param int $id
     * @param array $postFields
     *
     * @return void
     */
    public function updateService(int $id, array $postFields): void
    {
        if ('' === $priceFree = $this->db->escape($postFields['price_free'] ?? '')) {
            $priceFree =  'NULL';
        }

        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET 
                name='{$this->db->escape($postFields['name'])}',
                status='{$this->db->escape($postFields['status'])}', 
                price='{$this->db->escape($postFields['price'])}',
                price_free=" . $priceFree . "
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteService(int $id): void
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_service WHERE id='{$this->db->escape($id)}'";

        $this->db->query($query);
    }

    /**
     * @return array
     */
    public function getPickupPoints(): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `testing`='%s'",
                DB_PREFIX . "sameday_pickup_point",
                $this->getConfig('sameday_testing')
            )
        )->rows;
    }

    /**
     * @return array
     */
    public function getLockers(): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `testing`='%s'",
                DB_PREFIX . "sameday_locker",
                $this->getConfig('sameday_testing')
            )
        )->rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getPickupPoint(int $id): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `id` = %d",
                DB_PREFIX . "sameday_pickup_point",
                $id
            )
        )->row;
    }

    /**
     * @param int $samedayId
     *
     * @return array
     */
    public function getPickupPointSameday(int $samedayId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `sameday_id` = '%s' AND `testing` = '%s'",
                DB_PREFIX . "sameday_pickup_point",
                $samedayId,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param int $lockerId
     *
     * @return array
     */
    public function getLockerSameday(int $lockerId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `locker_id`='%s' AND `testing`='%s'",
                DB_PREFIX . "sameday_locker",
                $lockerId,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getLocker(int $id): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `locker_id` = '%s' AND `testing` = '%s'",
                DB_PREFIX . "sameday_locker",
                $id,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param PickupPointObject $pickupPointObject
     *
     * @return void
     */
    public function addPickupPoint(PickupPointObject $pickupPointObject): void
    {
        $testing = $this->getConfig('sameday_testing');
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_pickup_point (sameday_id, 
                sameday_alias, 
                testing, 
                city, 
                county, 
                address,
                default_pickup_point,
                contactPersons
            ) VALUES (
                '{$this->db->escape($pickupPointObject->getId())}', 
                '{$this->db->escape($pickupPointObject->getAlias())}', 
                '$testing', 
                '{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                '{$this->db->escape($pickupPointObject->getCounty()->getName())}', 
                '{$this->db->escape($pickupPointObject->getAddress())}',  
                '{$this->db->escape($pickupPointObject->isDefault())}',    
                '{$this->db->escape(serialize($pickupPointObject->getContactPersons()))}')";

        $this->db->query($query);
    }

    /**
     * @param LockerObject $lockerObject
     *
     * @return void
     */
    public function addLocker(LockerObject $lockerObject): void
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_locker (
                locker_id,
                name,
                county,
                city,
                address,
                lat,
                lng,
                postal_code,
                boxes,
                testing
            ) VALUES (
                '{$this->db->escape($lockerObject->getId())}',
                '{$this->db->escape($lockerObject->getName())}',
                '{$this->db->escape($lockerObject->getCounty())}',
                '{$this->db->escape($lockerObject->getCity())}',
                '{$this->db->escape($lockerObject->getAddress())}',
                '{$this->db->escape($lockerObject->getLat())}',
                '{$this->db->escape($lockerObject->getLong())}',
                '{$this->db->escape($lockerObject->getPostalCode())}',
                '{$this->db->escape(serialize($lockerObject->getBoxes()))}',
                '{$this->getConfig('sameday_testing')}')";

        $this->db->query($query);
    }

    /**
     * @param PickupPointObject $pickupPointObject
     * @param int $pickupPointId
     *
     * @return void
     */
    public function updatePickupPoint(PickupPointObject $pickupPointObject, int $pickupPointId): void
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_pickup_point SET 
                sameday_alias='{$this->db->escape($pickupPointObject->getAlias())}',
                city='{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                county='{$this->db->escape($pickupPointObject->getCounty()->getName())}',
                address='{$this->db->escape($pickupPointObject->getAddress())}',
                default_pickup_point='{$this->db->escape($pickupPointObject->isDefault())}'
                WHERE id='{$pickupPointId}'"
        );
    }

    /**
     * @param LockerObject $lockerObject
     * @param int $lockerId
     *
     * @return void
     */
    public function updateLocker(LockerObject $lockerObject, int $lockerId): void
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_locker SET 
                name='{$this->db->escape($lockerObject->getName())}',
                city='{$this->db->escape($lockerObject->getCity())}', 
                county='{$this->db->escape($lockerObject->getCounty())}',
                address='{$this->db->escape($lockerObject->getAddress())}',
                lat='{$this->db->escape($lockerObject->getLat())}',
                lng='{$this->db->escape($lockerObject->getLong())}',
                postal_code='{$this->db->escape($lockerObject->getPostalCode())}',
                boxes='{$this->db->escape(serialize($lockerObject->getBoxes()))}'
                WHERE id='{$lockerId}'"
        );
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deletePickupPoint(int $id): void
    {
        $table = DB_PREFIX . "sameday_pickup_point";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id = $id";

        $this->db->query($query);
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteLocker(int $id): void
    {
        $table = DB_PREFIX . "sameday_locker";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id=$id";

        $this->db->query($query);
    }

    /**
     * @param string $awbNumber
     *
     * @return void
     */
    public function deleteAwb(string $awbNumber): void
    {
        $table = DB_PREFIX . "sameday_awb";
        $awbNumber = $this->db->escape($awbNumber);

        $query = sprintf("DELETE FROM $table WHERE awb_number='%s'", $awbNumber);

        $this->db->query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getAwbForOrderId(int $orderId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `order_id` = '%s'",
                DB_PREFIX . "sameday_awb",
                $orderId
            )
        )->row;
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int, array> Rows keyed by order_id
     */
    public function getAwbByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        $query = $this->db->query(
            'SELECT order_id, awb_number FROM ' . DB_PREFIX . 'sameday_awb WHERE order_id IN (' . implode(',', $orderIds) . ')'
        );

        $rows = [];
        foreach ($query->rows as $row) {
            $rows[(int)$row['order_id']] = $row;
        }

        return $rows;
    }

    /**
     * @param int $orderId
     * @param string $awbParcel
     * @param SummaryObject $summary
     * @param array $history
     * @param ExpeditionObject $expedition
     *
     * @return void
     */
    public function refreshPackageHistory(
        int $orderId,
        string $awbParcel,
        SummaryObject $summary,
        array $history,
        ExpeditionObject $expedition
    ): void {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_package (
                order_id,
                awb_parcel,
                summary, 
                history, 
                expedition_status
            ) VALUES (
                '{$this->db->escape($orderId)}',
                '{$this->db->escape($awbParcel)}', 
                '{$this->db->escape(serialize($summary))}', 
                '{$this->db->escape(serialize($history))}',
                '{$this->db->escape(serialize($expedition))}'
            ) ON DUPLICATE KEY UPDATE
                summary='{$this->db->escape(serialize($summary))}',
                history='{$this->db->escape(serialize($history))}',
                expedition_status='{$this->db->escape(serialize($expedition))}'
            ";

        $this->db->query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getPackagesForOrderId(int $orderId): array
    {
        $rows = $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `order_id` = '%s'",
                DB_PREFIX . "sameday_package",
                $orderId
            )
        )->rows;


        foreach ($rows as $k => $row) {
            $rows[$k]['summary'] = unserialize($row['summary']);
            $rows[$k]['history'] = unserialize($row['history']);
            $rows[$k]['expedition_status'] = unserialize($row['expedition_status']);
            $rows[$k]['sync'] = unserialize($row['sync']);
        }

        return $rows;
    }

    /**
     * @param array $serviceTaxes
     *
     * @return string
     */
    private function buildServiceOptionalTaxes(array $serviceTaxes): string
    {
        $data = [];
        /** @var OptionalTaxObject $serviceTax */
        foreach ($serviceTaxes as $serviceTax) {
            $data[] = [
                'id' => $serviceTax->getId(),
                'code' => $serviceTax->getCode(),
                'type' => $serviceTax->getPackageType()
            ];
        }

        return json_encode($data);
    }

    /**
     * @param string $county
     *
     * @return void
     */
    public function addCounty(string $county): void
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_counties (
                county_id,
                county_name,
                county_code
            ) VALUES (
                '{$this->db->escape($county->getId())}',
                '{$this->db->escape($county->getName())}',
                '{$this->db->escape($county->getCode())}')";

        $this->db->query($query);
    }

    /**
     * @param mixed $city
     * @param int $zone_id
     *
     * @return void
     */
    public function addCity($city, int $zone_id): void
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_cities (
                city_id,
                city_name,
                county_code,
                zone_id
            ) VALUES (
                '{$this->db->escape($city->city_id)}',
                '{$this->db->escape($city->city_name)}',
                '{$this->db->escape($city->county_code)}',
                '{$this->db->escape($zone_id)}'
            )";

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function addCodCode(): void
    {
        $value = json_encode(['cod']);

        $query = "INSERT INTO " . DB_PREFIX . "setting 
    SET store_id = 0, 
        code = 'shipping_sameday', 
        `key` = 'shipping_sameday_cod', 
        value = '" . $this->db->escape($value) . "'";
        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function removeCodCode(): void
    {
        $query = "DELETE FROM " . DB_PREFIX . "setting WHERE `key` = 'shipping_sameday_cod'";
        $this->db->query($query);
    }

    /**
     * @return void
     */
    public function checkCodSetting(): void
    {
        $value = json_encode(['cod']);
        $codKey = $this->getKey('sameday_cod');
        $code = $this->getPrefix() . 'sameday';
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting 
        WHERE `key` = '" . $this->db->escape($codKey) . "'");

        if ((int)$query->row['total'] === 0) {
            $value = json_encode(['cod']);
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET code = '" . $this->db->escape($code) . "', 
            `key` = '" . $this->db->escape($codKey) . "', value = '" . $this->db->escape($value) . "'");
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    public function updateCod($data): void
    {
        $codKey = $this->getKey('sameday_cod');
        $query = "UPDATE " . DB_PREFIX . "setting SET 
        value='". $this->db->escape($data) ."' WHERE `key`='" . $this->db->escape($codKey) . "'";
        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createAwbTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_awb (
                id INT(11) NOT NULL AUTO_INCREMENT,
                order_id INT(11) NOT NULL,
                awb_number VARCHAR(255),
                parcels TEXT,
                awb_cost DOUBLE(10, 2),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createServiceTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_service (
                id INT(11) NOT NULL AUTO_INCREMENT,
                sameday_id INT(11) NOT NULL,
                sameday_name VARCHAR(255),
                sameday_code VARCHAR(255),
                testing TINYINT(1),
                name VARCHAR(255),
                price DOUBLE(10, 2),
                price_free DOUBLE(10, 2),
                status INT(11),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createLockerTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_locker (
                id INT(11) NOT NULL AUTO_INCREMENT,
                locker_id INT(11),
                name VARCHAR(255),
                county VARCHAR(255),
                city VARCHAR(255),
                address VARCHAR(255),
                lat VARCHAR(255),
                lng VARCHAR(255),
                postal_code VARCHAR(255),
                boxes TEXT,
                testing TINYINT(1),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createPickUpPointTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_pickup_point (
                id INT(11) NOT NULL AUTO_INCREMENT,
                sameday_id INT(11) NOT NULL,
                sameday_alias VARCHAR(255),
                testing TINYINT(1),
                city VARCHAR(255),
                county VARCHAR(255),
                address VARCHAR(255),
                contactPersons TEXT,
                default_pickup_point TINYINT(1),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createPackageTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_package (
                order_id INT(11) NOT NULL,
                awb_parcel VARCHAR(255),
                summary TEXT,
                history TEXT,
                expedition_status TEXT,
                sync TEXT,
                PRIMARY KEY (order_id, awb_parcel)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createCountiesTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_counties (
                id INT(11) NOT NULL AUTO_INCREMENT,
                county_id INT(11),
                county_name VARCHAR(255),
                county_code VARCHAR(255),
                PRIMARY KEY (id)
            )  ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    public function createCitiesTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_cities (
                id INT(11) NOT NULL AUTO_INCREMENT,
                city_id INT(11),
                city_name VARCHAR(255),
                county_code VARCHAR(255),
                zone_id INT(11),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    public function createOrdersLockerTable(): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_orders_locker (
                order_id INT(11) NOT NULL,
                locker TEXT NOT NULL
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    public function createBulkAwbTable(): void
    {
        $table = DB_PREFIX . 'sameday_orders_bulk_awb';

        $this->db->query('
            CREATE TABLE IF NOT EXISTS ' . $table . ' (
                order_id INT(11) NOT NULL,
                status INT(11) NOT NULL,
                feedback TEXT NOT NULL,
                UNIQUE KEY order_id (order_id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ');

        $index = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'order_id'");
        if (!$index->num_rows) {
            try {
                $this->db->query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY order_id (order_id)');
            } catch (\Throwable $e) {
                // Ignore: legacy duplicate rows may block UNIQUE until cleaned manually.
            }
        }
    }

    /**
     * @param int $countryId
     * @param string $countryCode
     *
     * @return null|array
     */
    public function getZoneId(int $countryId, string $countryCode)
    {
        $result = $this->db->query(
            sprintf(
                "SELECT zone_id FROM %s WHERE country_id = '%s' AND code = '%s'",
                DB_PREFIX . "zone",
                $countryId,
                $countryCode
            )
        )->row;

        return $result['zone_id'] ?? null;
    }

    /**
     * @return void
     */
    public function truncateNomenclator(): void
    {
        $this->db->query('TRUNCATE TABLE ' . DB_PREFIX . 'sameday_cities');
    }

    /**
     * @param string $isoCode
     *
     * @return array
     */
    public function getZone(string $isoCode): array
    {

        $table = DB_PREFIX . "country";

        $query = "SELECT * FROM $table WHERE iso_code_2 = '$isoCode'";

        return $this->db->query($query)->row;
    }

    /**
     * @return void
     */
    private function dropPickUpPointTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_pickup_point';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropCountiesTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_counties';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropCitiesTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_cities';

        $this->db->query($query);
    }

    private function dropOrdersLockerTable(): void
    {
        $query  = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_orders_locker';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropAwbTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_awb';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropServiceTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_service';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropPackageTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_package';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropLockerTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_locker';

        $this->db->query($query);
    }

    private function dropBulkAwbTable(): void
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_orders_bulk_awb';

        $this->db->query($query);
    }

    /**
     * @param string $key
     *
     * @return null|string
     */
    public function getConfig(string $key)
    {
        return $this->config->get($this->getKey($key));
    }

    /**
     * @param string $code
     * @param string $key
     * @param string $value
     *
     * @return null
     */
    public function editConfig(string $code, string $key, string $value)
    {
        return $this->config->set($code, $key, $value);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getKey(string $key): string
    {
        return $this->getPrefix() . $key;
    }

    /**
     * @param array $inputs
     *
     * @return array
     */
    public static function sanitizeInputs(array $inputs): array
    {
        foreach ($inputs as $key => $value) {
            $inputs[$key] = self::sanitizeInput($value);
        }

        return $inputs;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public static function sanitizeInput(string $input): string
    {
        return stripslashes(strip_tags(str_replace(["'", "\""], '&#39;', $input)));
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        if (strpos(VERSION, '2') === 0) {
            return '';
        }

        return 'shipping_';
    }

    /**
     * @param string $code
     * @param array $data
     * @param int $store_id
     *
     * @return void
     */
    public function addAdditionalSetting(string $code, array $data, int $store_id = 0): void
    {
        foreach ($data as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $this->db->query(sprintf(
                    "DELETE FROM %s WHERE `store_id` = %d AND `code` = '%s' AND `key` = '%s'",
                    DB_PREFIX . "setting",
                    $store_id,
                    $this->db->escape($code),
                    $this->db->escape($key)
                ));

                $queryFormat = "INSERT INTO %s SET `store_id` = %d, `code` = '%s', `key` = '%s', `value` = '%s'" ;
                if (is_array($value)) {
                    $value = $this->db->escape(json_encode($value, true));
                    $queryFormat .= ", `serialized` = 1";
                }

                $this->db->query(
                    sprintf(
                        $queryFormat,
                        DB_PREFIX . "setting",
                        $store_id,
                        $this->db->escape($code),
                        $this->db->escape($key),
                        $value
                    )
                );
            }
        }
    }

    /**
     * @return bool
     */
    public function citiesCheck(): bool
    {
        return $this->db->query(
                sprintf("SHOW TABLES LIKE '%s'", DB_PREFIX . "sameday_cities")
            )->num_rows > 0;
    }

    /**
     * @param string $isoCode
     *
     * @return mixed
     */
    public function getCountryByCode(string $isoCode): mixed
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE iso_code_2 = '$isoCode'");

        return $query->row['country_id'];
    }

    /**
     * @param int $country_id
     * @param array $county
     *
     * @return void
     */
    public function addZoneCounty(int $country_id, array $county): void
    {
        $county_name = $this->db->escape($county['county']);
        $county_code = $this->db->escape($county['code']);

        $zone = $this->db->query(sprintf(
            "SELECT * FROM %s WHERE `country_id` = '%s' AND `name` = '%s'",
            DB_PREFIX . "zone",
            $country_id,
            $county_name
        ));

        if ($zone->num_rows === 0) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "zone` SET 
                `country_id` = '$country_id', 
                `name` = '$county_name', 
                `code` = '$county_code', 
                `status` = 1
            ");
        } else {
            if ($zone->row['code'] === '') {
                $this->db->query(sprintf(
                    "UPDATE %s SET `code` = '%s' WHERE `zone_id` = '%d'",
                    DB_PREFIX . 'zone',
                    $county_code,
                    (int) $zone->row['zone_id']
                ));
            }
        }
    }

    public function getLockerByOrderId($orderId)
    {
        $query = $this->db->query(sprintf(
            "SELECT locker FROM %s WHERE `order_id` = %d",
            DB_PREFIX . "sameday_orders_locker",
            $orderId
        ));

        return $query->row['locker'];
    }

    public function deleteBulkAwbByOrderId(int $orderId): void
    {
        $this->db->query(
            'DELETE FROM ' . DB_PREFIX . 'sameday_orders_bulk_awb WHERE order_id = ' . (int)$orderId
        );
    }

    /**
     * Remove bulk AWB rows for orders that do not have a generated AWB in oc_sameday_awb.
     *
     * @return int[] Deleted order_id values
     */
    public function clearBulkAwbWithoutGenerated(): array
    {
        $query = $this->db->query(
            'SELECT b.order_id FROM ' . DB_PREFIX . 'sameday_orders_bulk_awb b
            LEFT JOIN ' . DB_PREFIX . 'sameday_awb a
                ON a.order_id = b.order_id AND TRIM(a.awb_number) <> \'\'
            WHERE a.order_id IS NULL'
        );

        $orderIds = array_map('intval', array_column($query->rows, 'order_id'));
        if ($orderIds === []) {
            return [];
        }

        $this->db->query(
            'DELETE b FROM ' . DB_PREFIX . 'sameday_orders_bulk_awb b
            LEFT JOIN ' . DB_PREFIX . 'sameday_awb a
                ON a.order_id = b.order_id AND TRIM(a.awb_number) <> \'\'
            WHERE a.order_id IS NULL'
        );

        return $orderIds;
    }

    public function ensureBulkAwbEntry(int $orderId): void
    {
        $orderId = (int)$orderId;
        $exists = $this->db->query(
            'SELECT order_id FROM ' . DB_PREFIX . 'sameday_orders_bulk_awb WHERE order_id = ' . $orderId . ' LIMIT 1'
        );

        if (!$exists->num_rows) {
            $this->bulkEntry($orderId);
        }
    }

    public function bulkEntry($order)
    {
        $query = "INSERT INTO " . DB_PREFIX . "sameday_orders_bulk_awb SET order_id = " . (int)$order . ", status = 0, feedback = ''";
        $this->db->query($query);
    }

    public function getDefaultPickupPointId()
    {
        $query = "SELECT sameday_id FROM " . DB_PREFIX . "sameday_pickup_point WHERE testing = " . (int)$this->getConfig('sameday_testing') . " AND default_pickup_point = 1 LIMIT 1";
        $result = $this->db->query($query);

        if (empty($result->row['sameday_id'])) {
            return null;
        }

        return $result->row['sameday_id'];
    }

    public function updateBulkFeedback($payload, $order_id)
    {
        $this->ensureBulkAwbEntry((int)$order_id);

        $status = 2;
        if (is_object($payload) && method_exists($payload, 'getAwbNumber')) {
            $status = 1;
        } elseif (is_array($payload) && !empty($payload['awb_number'])) {
            $status = 1;
        } elseif (is_array($payload) && isset($payload['errors'])) {
            $status = 2;
        }

        $query = "UPDATE " . DB_PREFIX . "sameday_orders_bulk_awb SET "
            . "feedback = '" . $this->db->escape(serialize($payload)) . "', "
            . "status = " . (int)$status . " "
            . "WHERE order_id = " . (int)$order_id;
        $this->db->query($query);
    }

    public function getFeedbacks(){
        $query = "SELECT * FROM " . DB_PREFIX . "sameday_orders_bulk_awb";
        return $this->db->query($query)->rows;
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int, array> Rows keyed by order_id
     */
    public function getBulkAwbByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        $query = $this->db->query(
            'SELECT * FROM ' . DB_PREFIX . 'sameday_orders_bulk_awb WHERE order_id IN (' . implode(',', $orderIds) . ')'
        );

        $rows = [];
        foreach ($query->rows as $row) {
            $rows[(int)$row['order_id']] = $row;
        }

        return $rows;
    }

    /**
     * @param array $row Bulk AWB table row (order_id, status, feedback)
     *
     * @return array{status: int, awb_number: ?string, error: ?string, label: string}
     */
    public function formatBulkAwbRow(array $row): array
    {
        $status = (int)($row['status'] ?? 0);
        $parsed = $this->parseBulkFeedback((string)($row['feedback'] ?? ''), $status);

        return array_merge(['status' => $status], $parsed);
    }

    /**
     * @param string $feedback Serialized API payload or empty while pending
     * @param int $status
     *
     * @return array{awb_number: ?string, error: ?string, label: string}
     */
    public function parseBulkFeedback(string $feedback, int $status = 0): array
    {
        if ($feedback === '') {
            return [
                'awb_number' => null,
                'error' => null,
                'label' => 'Pending',
            ];
        }

        $payload = @unserialize($feedback, ['allowed_classes' => true]);
        if ($payload === false && $feedback !== serialize(false)) {
            return [
                'awb_number' => null,
                'error' => null,
                'label' => 'Pending',
            ];
        }

        if (is_object($payload) && method_exists($payload, 'getAwbNumber')) {
            $awbNumber = (string)$payload->getAwbNumber();
            if ($awbNumber !== '') {
                return [
                    'awb_number' => $awbNumber,
                    'error' => null,
                    'label' => $awbNumber,
                ];
            }
        }

        if (is_array($payload)) {
            if (!empty($payload['awb_number'])) {
                $awbNumber = (string)$payload['awb_number'];
                return [
                    'awb_number' => $awbNumber,
                    'error' => null,
                    'label' => $awbNumber,
                ];
            }

            $error = $this->formatBulkFeedbackErrors($payload);
            if ($error !== null) {
                return [
                    'awb_number' => null,
                    'error' => $error,
                    'label' => $error,
                ];
            }
        }

        if ($status === 2) {
            return [
                'awb_number' => null,
                'error' => 'AWB generation failed',
                'label' => 'AWB generation failed',
            ];
        }

        return [
            'awb_number' => null,
            'error' => null,
            'label' => 'Pending',
        ];
    }

    /**
     * @param array $payload
     *
     * @return string|null
     */
    private function formatBulkFeedbackErrors(array $payload): ?string
    {
        $errors = $payload['errors'] ?? $payload;
        if (!is_array($errors)) {
            return null;
        }

        $messages = [];
        foreach ($errors as $error) {
            if (is_string($error)) {
                $messages[] = $error;
                continue;
            }
            if (!is_array($error)) {
                continue;
            }
            $key = isset($error['key']) ? implode('.', (array)$error['key']) : '';
            foreach ((array)($error['errors'] ?? []) as $message) {
                $messages[] = ($key !== '' ? $key . ': ' : '') . $message;
            }
        }

        return $messages !== [] ? implode('; ', $messages) : null;
    }
    // End of file

}