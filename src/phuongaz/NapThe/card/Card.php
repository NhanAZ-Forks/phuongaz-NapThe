<?php

namespace phuongaz\NapThe\card;

use phuongaz\NapThe\Loader;

Class Card{

	private array $data;

	public function __construct(array $data){
		$this->data = $data;
	}

    public function getChargeUrl(): string
    {   
        return 'http://'.Loader::getDriver().'/chargingws/v2?';
    }

    public function createDataPost(string $command) :string {
    	$dataPost = [];
        $dataPost['request_id'] = $this->data['request_id'];
        $dataPost['code'] = $this->data['code'];
        $dataPost['partner_id'] = $this->data['partner_id'];
        $dataPost['serial'] = $this->data['serial'];
        $dataPost['telco'] = $this->data['telco'];
        $dataPost['command'] = $command;  
        $dataPost['amount'] = $this->data['amount'];
        $dataPost['sign'] = $this->getSign();
        return http_build_query($dataPost);
    }

    public function getTelco() :string{
    	return $this->data['telco'];
    }

    public function getAmount() :int{
    	return $this->data['amount'];
    }

    public function getSign(): string{
        $data = [];
        $data[] = $this->data['partner_key'];
        $data[] = $this->data['code']; 
        $data[] = $this->data['serial'];
        ksort($data);
        $sign = "";
        foreach ($data as $item) {
            $sign .= $item;
        }
        return md5($sign);
    }

}