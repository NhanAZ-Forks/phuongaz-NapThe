<?php

namespace phuongaz\NapThe;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Config;
use phuongaz\NapThe\card\Result;
/*use phuongaz\coin\Coin;*/

class Loader extends PluginBase{
	use SingletonTrait;

	private static Config $dataoffline;

	private static Config $setting;

	private static string $logcard_path;

	public function onLoad():void{
		self::setInstance($this);
	}

	public function onEnable():void{
		$this->saveResource('thedung.txt');
		$this->saveResource('offline.yml');
		$this->saveResource('setting.yml');
		self::$logcard_path = $this->getDataFolder();
		self::$dataoffline = new Config($this->getDataFolder(). 'offline.yml', Config::YAML);
		self::$setting = new Config($this->getDataFolder(). 'setting.yml', Config::YAML);
		Server::getInstance()->getCommandMap()->register("napthe", new NapTheCommand());
		Server::getInstance()->getPluginManager()->registerEvents(new EventListener(),$this);
	}

	public static function getSetting():Config{
		return self::$setting;
	}

	public static function successCard(Player $player, Result $result) :void{
		$telco = $result->getCard()->getTelco();
		$amount = $result->getCard()->getAmount();
		$ratio = self::getSetting()->get('ratio');
		$bonus = self::getSetting()->get('bonus');
		$coin = (int)$amount/(int)$ratio * $bonus;
		$player->sendMessage('§l§fBạn đã nạp thành công thẻ §e'.$telco.'§f mệnh giá §e'.$amount. ' §fnhận được §e'. $coin. ' §fLcoin');
		Server::getInstance()->broadcastMessage('§l§f[§aLOCM-DONATE§f] §l§fNgười chơi§e '. $player->getName(). ' §fVừa nạp thành công thẻ§e '.$telco. ' §fmệnh giá§e '. $amount);
		/*Coin::getInstance()->addCoin($player, $coin);*/
        $sender = new ConsoleCommandSender();
        foreach(self::getSetting()['commands'] as $cmd) {
            $cmd = str_replace("{player}", $player->getName(), $cmd);
            $cmd = str_replace("{money}", $coin, $cmd);
            Server::getInstance()->getCommandMap()->dispatch($sender, $cmd);
        }
		self::logCard($player, $amount, $telco);
	}

	public static function getOfflineData(): Config{
		return self::$dataoffline;
	}

	public static function addOfflineData(string $name, Result $result) :void{
		$data = [];
		$data['amount'] = $result->getCard()->getAmount();
		$data['telco'] = $result->getCard()->getTelco();
		self::getOfflineData()->set($name, $data);
		self::getOfflineData()->save();
	}

	public static function logCard(Player $player, int $amount, string $telco, $type = 'thedung.txt'):void{
		$file = self::$logcard_path . $type;
		$data = $player->getName() .'|'.$amount.'|'.date("H:i:s d-m-Y"). '|'. $telco;
        $fh = fopen($file,"a") or die("cant open file");
        fwrite($fh,$data);
        fwrite($fh,"\r\n");
        fclose($fh);

	}

	public static function getDriver() :string{
		return self::getSetting()->get('driver');
	}

	public static function getPartnerKey() :string{
		return self::getSetting()->get('partner_key');
	}

	public static function getPartnerId() :string{
		return self::getSetting()->get('partner_id');
	}
}