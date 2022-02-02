<?php

use Sameday\SamedayClient;
use Sameday\PersistentData\SamedayPersistentDataInterface;

class SamedayPersistenceDataHandler implements SamedayPersistentDataInterface
{
    const KEYS = [
        SamedayClient::KEY_TOKEN => 'SAMEDAY_TOKEN',
        SamedayClient::KEY_TOKEN_EXPIRES => 'SAMEDAY_TOKEN_EXPIRES_AT',
    ];

    const OC_SETTING_SAMEDAY_CODE = "sameday";

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
     * @return mixed string
     */
    public function get($key)
    {
        $this->loader->model('extension/shipping/sameday');
        $model = $this->registry->get('model_extension_shipping_sameday');
        $key = $this->getKeyFormat($key);

        return $model->getSettingValue($key);
    }

    /**
     * @param string $key
     *
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->loader->model('setting/setting');
        $model = $this->registry->get('model_setting_setting');
        $key = $this->getKeyFormat($key);
        $data[$key] = $value;
        $model->addAdditionalSetting($this->prefix . self::OC_SETTING_SAMEDAY_CODE, $data);
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