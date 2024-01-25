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

    const LOCKER_NEXT_DAY_CODE = 'LN';

    const LOCKER_NEXT_DAY_CODE_CROSSBORDER = 'XL';

    const ELIGIBLE_SAMEDAY_SERVICES = ['6H', '24', 'LN'];

    const ELIGIBLE_SAMEDAY_SERVICES_CROSSBORDER = ['XB', 'XL'];

    const ELIGIBLE_TO_LOCKER = ['LN', 'XL'];

    const AFTER_48_HOURS = 172800;

    // PDO stands for Personal Delivery Option and is an additional tax that apply to Service
    const SERVICE_OPTIONAL_TAX_PDO_CODE = 'PDO';

    const API_PROD = 0;
    const API_DEMO = 1;

    const API_HOST_LOCALE_RO = 'RO';
    const API_HOST_LOCAL_HU = 'HU';
    const API_HOST_LOCAL_BG = 'BG';

    const SAMEDAY_ELIGIBLE_CURRENCIES = [
        self::API_HOST_LOCALE_RO => 'RON',
        self::API_HOST_LOCAL_HU => 'HUF',
        self::API_HOST_LOCAL_BG => 'BGN',
    ];

    public static function getEnvModes(): array
    {
        return [
            self::API_HOST_LOCALE_RO => [
                self::API_PROD => 'https://api.sameday.ro',
                self::API_DEMO => 'https://sameday-api.demo.zitec.com',
            ],
            self::API_HOST_LOCAL_HU => [
                self::API_PROD => 'https://api.sameday.hu',
                self::API_DEMO => 'https://sameday-api-hu.demo.zitec.com',
            ],
            self::API_HOST_LOCAL_BG => [
                self::API_PROD => 'https://api.sameday.bg',
                self::API_DEMO => 'https://sameday-api-bg.demo.zitec.com',
            ]
        ];
    }

    private static function getEAWBInstances(): array
    {
        return [
            self::API_HOST_LOCALE_RO => 'https://eawb.sameday.ro/',
            self::API_HOST_LOCAL_HU => 'https://eawb.sameday.hu/',
            self::API_HOST_LOCAL_BG => 'https://eawb.sameday.bg/',
        ];
    }

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
        // Default host will be always RO
        $countryHost = $this->samedayConfigs['sameday_host_country'] ?? self::API_HOST_LOCALE_RO;

        return self::getEnvModes()[$countryHost][$this->isTesting()];
    }

    /**
     * @param $samedayConfigs
     * @param $registry
     * @param $prefix
     */
    public function __construct($samedayConfigs, $registry, $prefix)
    {
        $this->samedayConfigs = $samedayConfigs;

        $this->registry = $registry;

        $this->prefix = $prefix;
    }

    /**
     * @param null $username
     * @param null $password
     * @param null $testing
     *
     * @return SamedayClient
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
}