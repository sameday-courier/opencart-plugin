<?php
class SamedayVersionValidator {

    public function getMajorVersion(): int {
         return explode('.', VERSION)[0];
    }

    public function isOc2(): bool
    {
        return $this->getMajorVersion() === 2;
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

    /**
     * Absolute path to OC4 order list Twig (extension package layout).
     */
    public function buildOrderListTemplateFile(): string
    {
        if (!$this->isOc4() || !defined('DIR_EXTENSION')) {
            return '';
        }

        return DIR_EXTENSION . 'sameday/admin/view/template/shipping/sameday_order_list_4.twig';
    }

    /**
     * View route for bulk AWB modals partial (OC4).
     */
    public function buildOrderListModalsViewRoute(): string
    {
        return $this->buildTemplatePath('extension/sameday/shipping/sameday_order_list_modals');
    }

    /**
     * Anchor for injecting Sameday toolbar buttons on sale/order.twig (OC4).
     */
    public function getOrderToolbarInjectionSearch(): string
    {
        return '<div class="float-end">';
    }

    /**
     * HTML injected after float-end opening tag on OC4 order list page.
     */
    public function getOrderListToolbarHtml(): string
    {
        return '<button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkAwbAction" id="bulkAwbActionButton">AWB</button> '
            . '<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteAwbAction" id="bulkDeleteAwbActionButton">Remove AWB</button> '
            . '<button type="button" class="btn btn-warning" id="bulkClearErrorsButton" data-bs-toggle="tooltip" title="Remove bulk feedback for orders without a generated AWB">'
            . '<i class="fa-solid fa-eraser"></i></button> ';
    }

}