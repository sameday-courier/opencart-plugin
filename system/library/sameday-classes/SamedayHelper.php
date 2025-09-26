<?php

use Sameday\Exceptions\SamedaySDKException;
use Sameday\SamedayClient;

class SamedayHelper
{
    /**
     * @var string $prefix
     */
    private $prefix;

    /**
     * @var Registry $registry
     */
    private $registry;

    /**
     * @var array $samedayConfigs
     */
    private $samedayConfigs;
    const CASH_ON_DELIVERY_CODE = 'cod';
    const SAMEDAY_6H_SERVICE = '6H';
    const DEFAULT_SAMEDAY_SERVICE = '24';
    const LOCKER_NEXT_DAY_SERVICE = 'LN';
    const SAMEDAY_PUDO_SERVICE = 'PP';
    const DEFAULT_SAMEDAY_CROSSBORDER_SERVICE = 'XB';
    const LOCKER_NEXT_DAY_CROSSBORDER_SERVICE = 'XL';
    const OOH_SERVICE_CODE = 'OOH';

    const OOH_TYPES = [
        0 => self::LOCKER_NEXT_DAY_SERVICE,
        1 => self::SAMEDAY_PUDO_SERVICE,
    ];

    const SAMEDAY_IN_USE_SERVICES = [
        self::SAMEDAY_6H_SERVICE,
        self::DEFAULT_SAMEDAY_SERVICE,
        self::LOCKER_NEXT_DAY_SERVICE,
        self::SAMEDAY_PUDO_SERVICE,
        self::DEFAULT_SAMEDAY_CROSSBORDER_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE,
    ];

    const ELIGIBLE_SAMEDAY_SERVICES = [
        self::SAMEDAY_6H_SERVICE,
        self::DEFAULT_SAMEDAY_SERVICE,
        self::LOCKER_NEXT_DAY_SERVICE
    ];

    const ELIGIBLE_SAMEDAY_SERVICES_CROSSBORDER = [
        self::DEFAULT_SAMEDAY_CROSSBORDER_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE
    ];

    const OOH_SERVICES = [
        self::LOCKER_NEXT_DAY_SERVICE,
        self::SAMEDAY_PUDO_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE,
    ];

    const ELIGIBLE_TO_LOCKER = [
        self::LOCKER_NEXT_DAY_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE,
        self::SAMEDAY_PUDO_SERVICE,
    ];

    const OOH_SERVICES_LABELS = [
        self::API_HOST_LOCALE_RO => 'Ridicare Sameday Point/Easybox',
        self::API_HOST_LOCALE_BG => 'вземете от Sameday Point/Easybox',
        self::API_HOST_LOCALE_HU => 'Felvenni től Sameday Point/Easybox',
    ];

    const SAMEDAY_COUNTRIES = [
        ['value' => 187, 'label' => 'Romania'],
        ['value' => 34, 'label' => 'Bulgaria'],
        ['value' => 237, 'label' => 'Hungary'],
    ];

    const AFTER_48_HOURS = 172800;

    // PDO stands for Personal Delivery Option and is an additional tax that apply to Service
    const SERVICE_OPTIONAL_TAX_PDO_CODE = 'PDO';

    const API_PROD = 0;
    const API_DEMO = 1;

    const API_HOST_LOCALE_RO = 'RO';
    const API_HOST_LOCALE_HU = 'HU';
    const API_HOST_LOCALE_BG = 'BG';

    const SAMEDAY_ELIGIBLE_CURRENCIES = [
        self::API_HOST_LOCALE_RO => 'RON',
        self::API_HOST_LOCALE_HU => 'HUF',
        self::API_HOST_LOCALE_BG => 'BGN',
    ];

    /**
     * @return string[][]
     */
    public static function getEnvModes(): array
    {
        return [
            self::API_HOST_LOCALE_RO => [
                self::API_PROD => 'https://api.sameday.ro',
                self::API_DEMO => 'https://sameday-api.demo.zitec.com',
            ],
            self::API_HOST_LOCALE_HU => [
                self::API_PROD => 'https://api.sameday.hu',
                self::API_DEMO => 'https://sameday-api-hu.demo.zitec.com',
            ],
            self::API_HOST_LOCALE_BG => [
                self::API_PROD => 'https://api.sameday.bg',
                self::API_DEMO => 'https://sameday-api-bg.demo.zitec.com',
            ]
        ];
    }

    /**
     * @return string[]
     */
    private static function getEAWBInstances(): array
    {
        return [
            self::API_HOST_LOCALE_RO => 'https://eawb.sameday.ro/',
            self::API_HOST_LOCALE_HU => 'https://eawb.sameday.hu/',
            self::API_HOST_LOCALE_BG => 'https://eawb.sameday.bg/',
        ];
    }

    /**
     * @param $countryCode
     *
     * @return string
     */
    public static function getEAWBInstanceUrlByCountry($countryCode): string
    {
        return self::getEawbInstances()[$countryCode ?? self::API_HOST_LOCALE_RO];
    }

    /**
     * @return int
     */
    public function isTesting(): int
    {
        return (int) $this->samedayConfigs['sameday_testing'];
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return self::getEnvModes()[$this->getHostCountry()][$this->isTesting()];
    }

    /**
     * @param array $samedayConfigs
     * @param mixed $registry
     * @param mixed $prefix
     */
    public function __construct(array $samedayConfigs, $registry, $prefix)
    {
        $this->samedayConfigs = $samedayConfigs;

        $this->registry = $registry;

        $this->prefix = $prefix;
    }

    /**
     * @param $username
     * @param $password
     * @param $apiUrl
     *
     * @return SamedayClient
     *
     * @throws SamedaySDKException
     */
    public function initClient($username = null, $password = null, $apiUrl = null): SamedayClient
    {
        if ($username === null && $password === null && $apiUrl === null) {
            $username = $this->samedayConfigs['sameday_username'];
            $password = $this->samedayConfigs['sameday_password'];
            $apiUrl = $this->getApiUrl();
        }

        return new SamedayClient(
            $username,
            $password,
            $apiUrl,
            'opencart',
            VERSION,
            'curl',
            Samedayclasses::getSamedayPersistenceDataHandler($this->registry)
        );
    }

    /**
     * @param string $samedayCode
     *
     * @return bool
     */
    public function isEligibleToLocker(string $samedayCode): bool
    {
        return in_array($samedayCode, self::ELIGIBLE_TO_LOCKER, true);
    }

    /**
     * @param string $samedayCode
     *
     * @return bool
     */
    public function isOohDeliveryOption(string $samedayCode): bool
    {
        return in_array($samedayCode, self::OOH_SERVICES);
    }

    /**
     * @return string
     */
    public function getHostCountry(): string
    {
        return $this->samedayConfigs['sameday_host_country'] ?? self::API_HOST_LOCALE_RO;
    }

    /**
     * @param int $locationId
     *
     * @return string
     */
    public function checkOohLocationType(int $locationId): string
    {
        if ($locationId >= 500000) {
            return self::OOH_TYPES[1];
        }

        return self::OOH_TYPES[0];
    }
}