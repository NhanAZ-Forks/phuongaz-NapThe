<?php

namespace phuongaz\NapThe\task;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use phuongaz\NapThe\card\Card;
use phuongaz\NapThe\card\Result;
use phuongaz\NapThe\CardHandler;
use phuongaz\NapThe\Loader;
use phuongaz\NapThe\form\NapTheForm;
use pocketmine\Server;
class PendingCardTask extends Task{

	private Card $card;

	private int $timeout = 10;

	public function __construct(Card $card){
		$this->card = $card;
	}

	public function getCard():Card{
		return $this->card;
	}

	public function onRun(int $currentTick) :void{
		if($this->timeout == 0){
			$handler = new CardHandler();
			$result = $handler->postCard($this->getCard(), 'check');
			if($result->isPending()){
				$handler->handlePendingCard($this->getCard());
				$this->sendMessage($result, '§l§eThẻ vẫn đang chờ duyệt vui lòng đợi thêm ít giây');
				$this->getHandler()->cancel();
			}
			if($result->getStatus() !== 99){
				$id = $result->getRequestId();
				$name = explode("|", $id)[0];
				if($result->getStatus() == 1){
					if(($player = Server::getInstance()->getPlayer($name)) !== null){
						Loader::successCard($player, $result);
						$this->getHandler()->cancel();
					}else{
						Loader::addOfflineData($name, $result);
						$this->getHandler()->cancel();
					}
					return;
				}
				$form = new NapTheForm();
				if(($player = Server::getInstance()->getPlayer($name)) !== null){
					$form->invoke($player, $result);
					$player->sendMessage($result->mapStatustoString());
				}
				$this->getHandler()->cancel();
			}
			$this->timeout = 10;
		}
		--$this->timeout;
	}

	public function sendMessage(?Result $result, string $message):void{
		$id = $result->getRequestId();
		$name = explode("|", $id)[0];
		if(($player = Server::getInstance()->getPlayer($name)) !== null){
			$player->sendMessage($message);
		}
	}
}