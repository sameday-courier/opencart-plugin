<?php

use Sameday\SamedayClient;
use Sameday\PersistentData\SamedayPersistentDataInterface;

class SamedayPersistenceDataHandler implements SamedayPersistentDataInterface
{
    const KEYS = [
        SamedayClient::KEY_TOKEN => 'sameday_token',
        SamedayClient::KEY_TOKEN_EXPIRES => 'sameday_token_expire_at',
    ];

    const OC_SETTING_SAMEDAY_CODE = "sameday";

    protected $registry;
    protected $loader;
    /**
     * @var \SamedayVersionValidator
     */
    protected $versionValidator;

    public function __construct($registry, SamedayVersionValidator $versionValidator)
    {
        $this->registry = $registry;
        $this->loader = ($versionValidator->isOc4()) ?
            new \Opencart\System\Engine\Loader($this->registry) :
            new Loader($this->registry);
        $this->modelPath = $versionValidator->buildModelPath();
        $this->magicMethod = $versionValidator->buildMagicMethod();
    }

    /**
     * @param $key
     * @return mixed
     *
     * @throws Exception
     */
    public function get($key)
    {
        return $this->getModel()->getConfig(self::KEYS[$key]);
    }

    /**
     * @param string $key
     *
     * @param mixed $value
     *
     * @throws Exception
     */
    public function set($key, $value)
    {
        $this->getModel()->addAdditionalSetting(
            $this->getKeyFormat(self::OC_SETTING_SAMEDAY_CODE),
            [$this->getKeyFormat(self::KEYS[$key]) => $value]
        );
    }

    /**
     * @param $key
     *
     * @return string
     * @throws Exception
     */
    private function getKeyFormat($key): string
    {
        return $this->getModel()->getPrefix() . $key;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function getModel()
    {
//        $this->loader->model('extension/sameday/shipping/sameday');
        $this->loader->model($this->modelPath);

        return $this->registry->get($this->magicMethod);
    }
}