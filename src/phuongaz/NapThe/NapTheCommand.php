<?php

namespace phuongaz\NapThe;

use pocketmine\Player;
use pocketmine\command\{CommandSender, Command};
use phuongaz\NapThe\form\NapTheForm;

class NapTheCommand extends Command{

	public function __construct(){
		parent::__construct('napthe', 'Nap the locm');
	}

	public function execute(CommandSender $sender, string $label, array $args) :bool{
		if($sender instanceof Player){
			$form = new NapTheForm();
			$form->SimpleForm($sender);
		}
		return true;
	}
}