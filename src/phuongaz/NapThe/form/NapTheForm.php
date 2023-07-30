<?php

namespace phuongaz\NapThe\form;

use pocketmine\Player;
use pocketmine\Server;
use phuongaz\NapThe\Loader;
use phuongaz\NapThe\CardHandler;
use phuongaz\NapThe\card\{Result, Card};
use jojoe77777\FormAPI\{SimpleForm, CustomForm};

class NapTheForm{

	private static array $telcos = ['Viettel', 'Vietnamobi', 'Vinaphone', 'Mobifone', 'Zing', 'Gate'];
	private static array $amount = [10000 => "10.000 VND", 20000 => "20.000 VND", 50000 => "50.000 VND", 100000 => "100.000 VND", 200000 => "200.000 VND", 500000 => "500.000 VND"];

	public function SimpleForm(Player $player):void{
		$form = new SimpleForm(function(Player $player, ?int $data){
			if(is_null($data)) return;
			$this->CustomForm($player, self::$telcos[$data]);
		});
		$form->setTitle('§l§f[§aLOCM-DONATE§f]');
		foreach(self::$telcos as $telco){
			$form->addButton("§l§f•§0 ".$telco. " §f•");
		}
		$form->sendToPlayer($player);
	}

	public function CustomForm(Player $player, string $telco, $content = '') :void{
		$form = new CustomForm(function(Player $player, ?array $data) use ($telco){
			if(is_null($data)) {
				$this->SimpleForm($player);
				return;
			}
			$amount = array_keys(self::$amount)[$data[1]];
			if(isset($data[2]) and isset($data[3])){
				if($telco !== 'Zing' and is_numeric($data[2]) and is_numeric($data[3])){
					$seri = $data[2];
					$code = $data[3];
					$data_c = [];
					$data_c['request_id'] = $player->getName()."|".mt_rand(1,1911111111);
					$data_c['code'] = $code;
					$data_c['partner_id'] = (string)Loader::getPartnerId();
					$data_c['partner_key'] = Loader::getPartnerKey();
					$data_c['serial'] = $seri;
					$data_c['telco'] = $telco;
					$data_c['amount'] = $amount;
					$card = new Card($data_c);
					$handle = new CardHandler();
					$result = $handle->postCard($card, 'charging');
					$this->invoke($player, $result);
				} else $this->CustomForm($player, $telco, "Mã thẻ và seri phải là số");
			} return;
		});
		$form->setTitle("§l§fLOẠI THẺ:§e ".$telco);
		$form->addLabel("§l§f".$content);
		$form->addDropDown("§lMệnh giá §f(§eSai mệnh giá mất thẻ§f)", array_values(self::$amount));
		$form->addInput("§lNhập mã Seri", "(mã seri)");
		$form->addInput("§lNhập mã pin", "(mã pin)");
		$form->sendToPlayer($player);
	}

	public function invoke(Player $player, Result $result) :void{
		if($result->checkError()){
			$mess = "§l§cHệ thống đang xảy ra lỗi vui lòng thử lại sau.";
			$this->CustomForm($player, $result->getCard()->getTelco(), $mess);
			return;
		}
		$status = $result->getStatus();
		if($status == 1){
			Loader::successCard($player, $result);
		}
		if($status == 99){
			$handle = new CardHandler();
			$handle->handlePendingCard($result->getCard());
		}
		$this->CustomForm($player, $result->getCard()->getTelco(), $result->mapStatustoString());
	}
}