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

    const API_PROD = 0;
    const API_DEMO = 1;

    const API_HOST_LOCALE_RO = 'RO';
    const API_HOST_LOCAL_HU = 'HU';

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
        ];
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
            Samedayclasses::getSamedayPersistenceDataHandler($this->registry, $this->prefix)
        );
    }
}