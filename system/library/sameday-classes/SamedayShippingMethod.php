<?php

class SamedayShippingMethod{
    public $sameday_name;
    public $name;
    public $code;
    public $service_id;
    public $title;
    public $cost;
    public $tax_class_id;
    public $text;

    public function __construct(string $sameday_name, string $name, string $code, int $service_id, string $title, string $cost, int $tax_class_id, string $text) {
        $this->sameday_name = $sameday_name;
        $this->name = $name;
        $this->code = $code;
        $this->service_id = $service_id;
        $this->title = $title;
        $this->cost = $cost;
        $this->tax_class_id = $tax_class_id;
        $this->text = $text;
    }

    public function toJson(){
        return json_encode([
            'sameday_name' => $this->sameday_name,
            'name' => $this->name,
            'code' => $this->code,
            'service_id' => $this->service_id,
            'title' => $this->title,
            'cost' => $this->cost,
            'tax_class_id' => $this->tax_class_id,
            'text' => $this->text
        ]);
    }
}