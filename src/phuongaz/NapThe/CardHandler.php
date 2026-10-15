<?php

declare(strict_types=1);

namespace phuongaz\NapThe;

use phuongaz\NapThe\card\Card;
use phuongaz\NapThe\card\Result;
use pocketmine\scheduler\Task;

final class CardHandler extends Task{
	private const MAX_ACTIVE_REQUESTS = 5;
	private const MAX_STATUS_CHECKS = 6;
	private const STATUS_CHECK_DELAY_TICKS = 200;

	private \CurlMultiHandle $multiHandle;

	/** @var array<string, array{handle: \CurlHandle, card: Card, command: string, checks: int}> */
	private array $requests = [];

	/** @var array<string, array{card: Card, checks: int, due: int}> */
	private array $pendingChecks = [];
	private int $currentTick = 0;

	public function __construct(private readonly Loader $plugin){
		$this->multiHandle = curl_multi_init();
	}

	public function submit(Card $card) : bool{
		return $this->queueRequest($card, "charging", 0);
	}

	public function close() : void{
		foreach($this->requests as $request){
			curl_multi_remove_handle($this->multiHandle, $request["handle"]);
			curl_close($request["handle"]);
		}
		$this->requests = [];
		$this->pendingChecks = [];
		curl_multi_close($this->multiHandle);
	}

	public function onRun(int $currentTick = 0) : void{
		$this->currentTick = $currentTick;
		if($this->requests === [] && $this->pendingChecks === []){
			return;
		}

		$running = 0;
		curl_multi_exec($this->multiHandle, $running);
		while(($info = curl_multi_info_read($this->multiHandle)) !== false){
			$handle = $info["handle"] ?? null;
			if(!$handle instanceof \CurlHandle){
				continue;
			}
			$requestId = null;
			foreach($this->requests as $candidateId => $request){
				if($request["handle"] === $handle){
					$requestId = $candidateId;
					break;
				}
			}
			if($requestId === null){
				curl_multi_remove_handle($this->multiHandle, $handle);
				curl_close($handle);
				continue;
			}

			$request = $this->requests[$requestId];
			unset($this->requests[$requestId]);
			$httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			$body = curl_multi_getcontent($handle);
			$successfulTransfer = $info["result"] === CURLE_OK && is_string($body);
			curl_multi_remove_handle($this->multiHandle, $handle);
			curl_close($handle);

			$result = $successfulTransfer ? Result::fromResponse($body, $httpStatus, $requestId, $request["card"]->getExpectedResponseSignature()) : null;
			$this->handleResult($request["card"], $request["command"], $request["checks"], $result);
		}

		foreach($this->pendingChecks as $requestId => $pending){
			if($pending["due"] > $currentTick || isset($this->requests[$requestId])){
				continue;
			}
			unset($this->pendingChecks[$requestId]);
			if(!$this->queueRequest($pending["card"], "check", $pending["checks"])){
				$this->handleCheckFailure($pending["card"], $pending["checks"]);
			}
		}
	}

	private function queueRequest(Card $card, string $command, int $checks) : bool{
		$requestId = $card->getRequestId();
		if(count($this->requests) >= self::MAX_ACTIVE_REQUESTS || isset($this->requests[$requestId])){
			return false;
		}
		$endpoint = $this->plugin->getGatewayEndpoint();
		if($endpoint === null){
			return false;
		}

		$handle = curl_init($endpoint);
		if(!$handle instanceof \CurlHandle){
			return false;
		}
		$options = [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($card->getRequestData($command), "", "&", PHP_QUERY_RFC3986),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER => [
				"Accept: application/json",
				"Content-Type: application/x-www-form-urlencoded",
			],
		];
		if(!curl_setopt_array($handle, $options)){
			curl_close($handle);
			return false;
		}
		if(curl_multi_add_handle($this->multiHandle, $handle) !== CURLM_OK){
			curl_close($handle);
			return false;
		}
		$this->requests[$requestId] = [
			"handle" => $handle,
			"card" => $card,
			"command" => $command,
			"checks" => $checks,
		];
		return true;
	}

	private function handleResult(Card $card, string $command, int $checks, ?Result $result) : void{
		if($result === null){
			if($command === "check"){
				$this->handleCheckFailure($card, $checks);
			}else{
				$this->plugin->markManualReview($card);
			}
			return;
		}

		if($result->isPending()){
			if($command === "charging"){
				$this->plugin->notifyPending($card);
			}
			if($checks >= self::MAX_STATUS_CHECKS){
				$this->plugin->markManualReview($card);
				return;
			}
			$this->pendingChecks[$card->getRequestId()] = [
				"card" => $card,
				"checks" => $checks + 1,
				"due" => $this->currentTick + self::STATUS_CHECK_DELAY_TICKS,
			];
			return;
		}

		unset($this->pendingChecks[$card->getRequestId()]);
		$this->plugin->handleGatewayResult($card, $result);
	}

	private function handleCheckFailure(Card $card, int $checks) : void{
		if($checks >= self::MAX_STATUS_CHECKS){
			$this->plugin->markManualReview($card);
			return;
		}
		$this->pendingChecks[$card->getRequestId()] = [
			"card" => $card,
			"checks" => $checks + 1,
			"due" => $this->currentTick + self::STATUS_CHECK_DELAY_TICKS,
		];
	}
}
