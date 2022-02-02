<?php

use Sameday\SamedayClient;
use Sameday\PersistentData\SamedayPersistentDataInterface;

class SamedayPersistenceDataHandler implements SamedayPersistentDataInterface
{
    protected const KEYS = [
        SamedayClient::KEY_TOKEN => 'SAMEDAY_TOKEN',
        SamedayClient::KEY_TOKEN_EXPIRES => 'SAMEDAY_TOKEN_EXPIRES_AT'
    ];
    protected const OC_SETTING_SAMEDAY_CODE = "shipping_sameday";

    protected $registry;
    protected $loader;
    protected $prefix;

    public function __construct($registry, $prefix) {
        $this->registry = $registry;
        $this->prefix = $prefix;
        $this->loader = new Loader($this->registry);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function get($key): ?string
    {
        $this->loader->model('setting/setting');
        $model = $this->registry->get('model_setting_setting');
        $key = $this->getKeyFormat($key);
        return $model->getSettingValue($key);
    }

    /**
     * @param string $key
     *
     * @param mixed $value
     */
    public function set($key, $value): void
    {
        $this->loader->model('setting/setting');
        $model = $this->registry->get('model_setting_setting');
        $key = $this->getKeyFormat($key);
        $data[$key] = $value;
        $model->addAdditionalSetting(self::OC_SETTING_SAMEDAY_CODE, $data);
    }

    /**
     * @param $key
     * @return string
     */
    private function getKeyFormat($key)
    {
        return $this->prefix.strtolower(self::KEYS[$key]);
    }
}