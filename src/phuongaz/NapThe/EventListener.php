<?php

namespace phuongaz\NapThe;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Server;
use pocketmine\command\ConsoleCommandSender;
#use onebone\economyapi\EconomyAPI;

Class EventListener implements Listener{

	public function onJoin(PlayerJoinEvent $event) :Void{
		$data = Loader::getOfflineData();
		$player = $event->getPlayer();
		if($data->exists($player->getName())){
			$old_data = $data->get($player->getName());
			$ratio = Loader::getSetting()->get('ratio');
            $bonus = Loader::getSetting()->get('bonus');
            $amount = Loader::getSetting()->get('amount');
			$coin = (int)$amount/(int)$ratio * $bonus;
			$player->sendMessage('§l§fBạn đã nạp thành công thẻ§e '.$telco.' §fmệnh giá§e '.$amount);
			Server::getInstance()->broadcastMessage('§l§f[§aLOCM-DONATE§f] §l§fNgười chơi§e '. $player->getName(). ' §fVừa nạp thành công thẻ§e '.$telco. ' §fmệnh giá§e '. $amount);
			/*Coin::getInstance()->addCoin($player, $coin);*/
            $sender = new ConsoleCommandSender();
            foreach(Loader::getSetting()['commands'] as $cmd) {
                $cmd = str_replace("{player}", $player->getName(), $cmd);
                $cmd = str_replace("{money}", $coin, $cmd);
                Server::getInstance()->getCommandMap()->dispatch($sender, $cmd);
            }
			Loader::logCard($player, $amount, $telco);
			$data->remove($player->getName());
			$data->save();
		}
	}
}