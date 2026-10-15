<?php

declare(strict_types=1);

namespace phuongaz\NapThe;

use phuongaz\NapThe\card\Card;
use phuongaz\NapThe\card\Gateway;
use phuongaz\NapThe\card\Result;
use phuongaz\NapThe\form\NapTheForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Throwable;

final class Loader extends PluginBase implements Listener{
	private Config $settings;
	private Config $transactions;
	private CardHandler $cardHandler;
	/** @var array<string, int> */
	private array $lastSubmission = [];
	private bool $gatewayAvailable = false;

	protected function onEnable() : void{
		$this->saveResource("setting.yml");
		$this->settings = new Config($this->getDataFolder() . "setting.yml", Config::YAML);
		$this->settings->setDefaults([
			"driver" => "www.the9p.com",
			"partner_key" => "",
			"partner_id" => "",
			"ratio" => 1000,
			"bonus" => 1,
			"commands" => [],
		]);
		$this->settings->save();
		$this->transactions = new Config($this->getDataFolder() . "transactions.yml", Config::YAML, ["transactions" => []]);
		$this->migrateLegacyOfflineData();
		$this->markInterruptedTransactionsForReview();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->gatewayAvailable = function_exists("curl_multi_init");
		if($this->gatewayAvailable){
			$this->cardHandler = new CardHandler($this);
			$this->getScheduler()->scheduleRepeatingTask($this->cardHandler, 1);
		}else{
			$this->getLogger()->warning("Payment requests are unavailable because PHP cURL multi support is missing.");
		}
		$this->getLogger()->info("NapThe API 5 plugin enabled. No payment request is sent until a player submits the form.");
	}

	protected function onDisable() : void{
		if(isset($this->cardHandler)){
			$this->cardHandler->close();
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "napthe"){
			return false;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage("This command can only be used in-game.");
			return true;
		}
		if(!$this->gatewayAvailable){
			$sender->sendMessage(TextFormat::RED . "The payment request service is unavailable on this server.");
			return true;
		}
		$sender->sendForm(NapTheForm::selectTelco($this));
		return true;
	}

	public function getGatewayEndpoint() : ?string{
		$driver = $this->settings->get("driver", "");
		return is_string($driver) ? Gateway::endpoint($driver) : null;
	}

	public function submitCard(Player $player, string $telco, int $amount, string $serial, string $code) : void{
		$now = time();
		$key = $player->getUniqueId()->toString();
		if(isset($this->lastSubmission[$key]) && $now - $this->lastSubmission[$key] < 5){
			$player->sendMessage(TextFormat::RED . "Please wait before submitting another card.");
			return;
		}
		$partnerId = $this->settings->get("partner_id", "");
		$partnerKey = $this->settings->get("partner_key", "");
		if(!is_string($partnerId) || trim($partnerId) === "" || !is_string($partnerKey) || trim($partnerKey) === ""){
			$player->sendMessage(TextFormat::RED . "Card payments are not configured. Contact a server administrator.");
			return;
		}
		if($this->getGatewayEndpoint() === null){
			$player->sendMessage(TextFormat::RED . "The configured payment provider must be a valid HTTPS hostname.");
			return;
		}
		if(!Gateway::supportsTelco($telco) || !in_array($amount, [10000, 20000, 50000, 100000, 200000, 500000], true) || preg_match('/^[0-9]{5,40}$/D', $serial) !== 1 || preg_match('/^[0-9]{5,40}$/D', $code) !== 1){
			$player->sendMessage(TextFormat::RED . "The card details are invalid. Check the selected value, serial and PIN.");
			return;
		}
		try{
			$requestId = bin2hex(random_bytes(16));
			$card = new Card(
				$requestId,
				$player->getName(),
				$key,
				$telco,
				$amount,
				$serial,
				$code,
				trim($partnerId),
				trim($partnerKey),
			);
		}catch(Throwable){
			$player->sendMessage(TextFormat::RED . "The card request could not be prepared.");
			return;
		}

		$this->setTransaction($requestId, [
			"state" => "checking",
			"player" => $player->getName(),
			"player_uuid" => $key,
			"amount" => $amount,
			"telco" => $telco,
			"created_at" => $now,
		]);
		if(!$this->cardHandler->submit($card)){
			$this->setTransactionState($requestId, "failed");
			$player->sendMessage(TextFormat::RED . "The card request could not be queued. No provider request was sent.");
			return;
		}
		$this->lastSubmission[$key] = $now;
		$player->sendMessage(TextFormat::YELLOW . "The card was submitted securely over HTTPS. No reward is issued until the provider confirms it.");
	}

	public function notifyPending(Card $card) : void{
		$this->getServer()->getPlayerExact($card->getPlayerName())?->sendMessage(TextFormat::YELLOW . "The provider is still processing this card. The server will check its status; no reward has been issued yet.");
	}

	public function handleGatewayResult(Card $card, Result $result) : void{
		$requestId = $card->getRequestId();
		$record = $this->getTransaction($requestId);
		if($record === null || ($record["state"] ?? null) !== "checking"){
			return;
		}
		if($result->isSuccessful()){
			$record["state"] = "reward_pending";
			$this->setTransaction($requestId, $record);
			$player = $this->getServer()->getPlayerExact($card->getPlayerName());
			if($player !== null){
				$player->sendMessage(TextFormat::GREEN . "The provider confirmed the card. The configured reward is being processed.");
				$this->processPendingRewards($player);
			}
			return;
		}

		$record["state"] = "failed";
		$this->setTransaction($requestId, $record);
		$player = $this->getServer()->getPlayerExact($card->getPlayerName());
		if($player !== null){
			$message = match($result->getStatus()){
				2 => "The provider reported a card denomination mismatch.",
				3 => "The provider rejected the card.",
				4 => "The provider is temporarily unavailable.",
				default => "The provider did not confirm the card. No reward was issued.",
			};
			$player->sendMessage(TextFormat::RED . $message);
		}
	}

	public function markManualReview(Card $card) : void{
		$requestId = $card->getRequestId();
		$record = $this->getTransaction($requestId);
		if($record === null || ($record["state"] ?? null) !== "checking"){
			return;
		}
		$record["state"] = "manual_review";
		$this->setTransaction($requestId, $record);
		$this->getLogger()->warning("A payment request needs provider reconciliation. Request ID: " . $requestId);
		$this->getServer()->getPlayerExact($card->getPlayerName())?->sendMessage(TextFormat::YELLOW . "The provider result could not be confirmed automatically. Contact an administrator with request ID " . $requestId . "; no reward was issued automatically.");
	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void{
		$this->processPendingRewards($event->getPlayer());
	}

	private function processPendingRewards(Player $player) : void{
		foreach($this->getTransactions() as $requestId => $record){
			$savedUuid = $record["player_uuid"] ?? null;
			$savedName = $record["player"] ?? null;
			if(($record["state"] ?? null) === "reward_pending" && (($savedUuid === $player->getUniqueId()->toString()) || (is_string($savedName) && strcasecmp($savedName, $player->getName()) === 0))){
				$this->dispatchReward($requestId, $record, $player);
			}
		}
	}

	/** @param array<string, mixed> $record */
	private function dispatchReward(string $requestId, array $record, Player $player) : void{
		$amount = $record["amount"] ?? null;
		$ratioValue = $this->settings->get("ratio", 1000);
		$bonusValue = $this->settings->get("bonus", 1);
		$commands = $this->settings->get("commands", []);
		if(!is_int($amount) || !is_numeric($ratioValue) || !is_numeric($bonusValue) || !is_array($commands) || $commands === []){
			$this->setTransactionState($requestId, "manual_review");
			$player->sendMessage(TextFormat::RED . "The reward configuration needs administrator review.");
			return;
		}
		$reward = RewardPolicy::calculate($amount, (int) $ratioValue, (float) $bonusValue);
		if($reward === null){
			$this->setTransactionState($requestId, "manual_review");
			$player->sendMessage(TextFormat::RED . "The reward configuration needs administrator review.");
			return;
		}

		$this->setTransactionState($requestId, "rewarding");
		foreach($commands as $template){
			if(!is_string($template) || ($command = RewardPolicy::command($template, $player->getName(), $reward)) === null || !$this->getServer()->getCommandMap()->dispatch(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $command)){
				$this->setTransactionState($requestId, "manual_review");
				$this->getLogger()->warning("A confirmed payment reward needs administrator review. Request ID: " . $requestId);
				$player->sendMessage(TextFormat::RED . "The card was confirmed, but a reward command needs administrator review. Request ID: " . $requestId);
				return;
			}
		}

		$this->setTransactionState($requestId, "completed");
		$player->sendMessage(TextFormat::GREEN . "Your card reward has been processed. Request ID: " . $requestId);
		$telco = isset($record["telco"]) && is_string($record["telco"]) ? $record["telco"] : "card";
		$this->getServer()->broadcastMessage(TextFormat::GREEN . $player->getName() . " completed a " . $telco . " card payment.");
	}

	private function migrateLegacyOfflineData() : void{
		$legacyPath = $this->getDataFolder() . "offline.yml";
		if(!is_file($legacyPath)){
			return;
		}
		$legacy = new Config($legacyPath, Config::YAML);
		$changed = false;
		foreach($legacy->getAll() as $playerName => $payment){
			$amountValue = is_array($payment) ? filter_var($payment["amount"] ?? null, FILTER_VALIDATE_INT) : false;
			if(!is_string($playerName) || !is_array($payment) || !is_int($amountValue) || !is_string($payment["telco"] ?? null) || preg_match('/^[A-Za-z0-9_]{1,16}$/D', $playerName) !== 1){
				$this->getLogger()->warning("A legacy offline payment record was retained for manual review; the source file was not modified.");
				continue;
			}
			$amount = $amountValue;
			$telco = strtoupper(trim($payment["telco"]));
			if($amount < 1 || preg_match('/^[A-Z0-9_]{2,24}$/D', $telco) !== 1){
				continue;
			}
			$requestId = "legacy-" . hash("sha256", strtolower($playerName) . "|" . $amount . "|" . $telco);
			if($this->getTransaction($requestId) !== null){
				continue;
			}
			$this->setTransaction($requestId, [
				"state" => "reward_pending",
				"player" => $playerName,
				"player_uuid" => "",
				"amount" => $amount,
				"telco" => $telco,
				"created_at" => time(),
				"source" => "legacy-offline.yml",
			]);
			$changed = true;
		}
		if($changed){
			$this->getLogger()->notice("Legacy offline payments were imported into transactions.yml. The original offline.yml was preserved unchanged.");
		}
	}

	private function markInterruptedTransactionsForReview() : void{
		$changed = false;
		foreach($this->getTransactions() as $requestId => $record){
			if(($record["state"] ?? null) === "checking" || ($record["state"] ?? null) === "rewarding"){
				$record["state"] = "manual_review";
				$this->setTransaction($requestId, $record);
				$changed = true;
			}
		}
		if($changed){
			$this->getLogger()->warning("An interrupted payment or reward was moved to manual review. Card secrets are not stored, and no provider charge was retried.");
		}
	}

	/** @return array<string, array<string, mixed>> */
	public function getTransactions() : array{
		$rows = $this->transactions->get("transactions", []);
		if(!is_array($rows)){
			return [];
		}
		$out = [];
		foreach($rows as $requestId => $record){
			if(is_string($requestId) && is_array($record)){
				$normalized = [];
				foreach($record as $key => $value){
					if(is_string($key)){
						$normalized[$key] = $value;
					}
				}
				$out[$requestId] = $normalized;
			}
		}
		return $out;
	}

	/** @return array<string, mixed>|null */
	private function getTransaction(string $requestId) : ?array{
		$transactions = $this->getTransactions();
		return $transactions[$requestId] ?? null;
	}

	/** @param array<string, mixed> $record */
	private function setTransaction(string $requestId, array $record) : void{
		$transactions = $this->getTransactions();
		$transactions[$requestId] = $record;
		$this->transactions->set("transactions", $transactions);
		$this->transactions->save();
	}

	private function setTransactionState(string $requestId, string $state) : void{
		$record = $this->getTransaction($requestId);
		if($record === null){
			return;
		}
		$record["state"] = $state;
		$this->setTransaction($requestId, $record);
	}
}
