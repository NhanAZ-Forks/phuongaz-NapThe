<?php

namespace phuongaz\NapThe;

use phuongaz\NapThe\card\Card;
use phuongaz\NapThe\card\Result;
use phuongaz\NapThe\task\PendingCardTask;
use pocketmine\utils\Internet;

Class CardHandler{

    public function postCard(Card $card, string $command) :Result{
        $url = $card->getChargeUrl();
        $dataPost = $card->createDataPost($command);
        $result = Internet::getURL($url.$dataPost);
        return new Result($card, json_decode($result, true));
    }

    public function handlePendingCard(Card $card) :void{
        $task = new PendingCardTask($card);
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask($task, 20);
    }
}