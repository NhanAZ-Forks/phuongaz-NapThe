<?php

declare(strict_types=1);

namespace phuongaz\NapThe\card;

use InvalidArgumentException;

final class Card{
	public function __construct(
		private readonly string $requestId,
		private readonly string $playerName,
		private readonly string $playerUuid,
		private readonly string $telco,
		private readonly int $amount,
		private readonly string $serial,
		private readonly string $code,
		private readonly string $partnerId,
		private readonly string $partnerKey,
	){
		if(preg_match('/^[A-Za-z0-9_-]{16,80}$/D', $requestId) !== 1){
			throw new InvalidArgumentException("Invalid payment request ID");
		}
		if(preg_match('/^[A-Za-z0-9_]{1,16}$/D', $playerName) !== 1){
			throw new InvalidArgumentException("Invalid player name");
		}
		if($amount < 1 || $serial === "" || $code === "" || $telco === ""){
			throw new InvalidArgumentException("Invalid card details");
		}
	}

	public function getRequestId() : string{
		return $this->requestId;
	}

	public function getPlayerName() : string{
		return $this->playerName;
	}

	public function getPlayerUuid() : string{
		return $this->playerUuid;
	}

	public function getTelco() : string{
		return $this->telco;
	}

	public function getAmount() : int{
		return $this->amount;
	}

	/**
	 * Raw PIN, serial, partner ID and partner key are intentionally never persisted or logged.
	 *
	 * @return array<string, int|string>
	 */
	public function getRequestData(string $command) : array{
		return Gateway::buildPayload(
			$command,
			$this->requestId,
			$this->telco,
			$this->amount,
			$this->serial,
			$this->code,
			$this->partnerId,
			$this->partnerKey,
		);
	}

	public function getExpectedResponseSignature() : string{
		return Gateway::responseSignature($this->partnerKey, $this->code, $this->serial);
	}
}
