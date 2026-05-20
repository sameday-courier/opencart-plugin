<?php
class SamedayVersionValidator {

    public function getMajorVersion(): int {
         return explode('.', VERSION)[0];
    }

    public function isOc4(): bool{
        if($this->getMajorVersion() === 4){
            return true;
        }
        return false;
    }

    public function buildMethodPath(string $path): string{

        if ($this->isOc4()){
            return preg_replace('/\/(?=[^\/]*$)/', '.', $path);
        }
        return $path;
    }

    public function buildSamedayMethodPath(string $path): string{
        $separator = ($this->isOc4()) ? '.' : '/';
        return $this->buildModelPath() . $separator . $path;
    }

    public function buildModelPath(): string{
        if($this->isOc4()){
            return 'extension/sameday/shipping/sameday';
        }
        return 'extension/shipping/sameday';
    }

    public function buildTemplatePath($path): string{
        if($this->isOc4()){
            return $path . '_4';
        }
        $pathExplode = explode('/', $path);
        unset($pathExplode[1]);
        return implode('/', $pathExplode);
    }

    public function buildMagicMethod(){
        return ($this->isOc4()) ? 'model_extension_sameday_shipping_sameday' : 'model_extension_shipping_sameday';
    }

    public function getSamedayModel(){
        return $this->{$this->samedayVersionValidator->buildMagicMethod()};
    }

}