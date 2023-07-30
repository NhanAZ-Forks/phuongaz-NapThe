<?php

namespace phuongaz\NapThe\card;

Class Result{

	private Card $card;
	private ?array $result;

	public function __construct(Card $card, ?array $result){
		$this->card = $card;
		$this->result = $result;
	}

	public function checkError() :bool{
		return is_null($this->result);
	}

	public function getCard() :Card{
		return $this->card;
	}

	public function getResult() :array{
		return $this->result;
	}

	public function getRequestId() :string{
		return $this->getResult()['request_id'];
	}

	public function getStatus() :int{
		return $this->getResult()['status'];
	}

	public function getMessage():string{
		return $this->getResult()['message'];
	}

	public function isPending() :bool{
		return ($this->getStatus() == 99);
	}

    public function mapStatustoString() :string{
    	$data = $this->getStatus();
    	switch($data){
    		case 1:
    			$status = 'Thẻ thành công đúng mệnh giá';
    			break;
    		case 2:
    			$status = 'Thẻ thành công sai mệnh giá';
    			break;
    		case 3:
    			$status = 'Thẻ lỗi';
    			break;
    		case 4:
    			$status = 'Hệ thống bảo trì';
    			break;
    		case 99:
    			$status = 'Thẻ chờ xử lý vui lòng chờ khoảng 10 giây';
    			break;
    		case 100:
    			$status = 'Gửi thẻ thất bại'." | Lỗi:§e ".$this->getMessage();
    			break;
    		default:
				$status = 'UNKNOWN';
				break;
    	}
    	return $status;
    }
}