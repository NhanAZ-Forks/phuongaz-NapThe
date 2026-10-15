<?php

declare(strict_types=1);

namespace phuongaz\NapThe\card;

final class Result{
	private function __construct(
		private readonly string $requestId,
		private readonly int $status,
	){
	}

	public static function fromResponse(string $body, int $httpStatus, string $expectedRequestId, string $expectedCallbackSignature) : ?self{
		if($httpStatus < 200 || $httpStatus >= 300){
			return null;
		}
		try{
			$data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			return null;
		}
		if(
			!is_array($data) ||
			!isset($data["request_id"], $data["status"], $data["callback_sign"]) ||
			!is_string($data["request_id"]) ||
			$data["request_id"] !== $expectedRequestId ||
			!is_string($data["callback_sign"]) ||
			preg_match('/^[a-fA-F0-9]{32}$/D', $data["callback_sign"]) !== 1 ||
			!hash_equals($expectedCallbackSignature, strtolower($data["callback_sign"]))
		){
			return null;
		}
		$status = filter_var($data["status"], FILTER_VALIDATE_INT);
		if(!is_int($status)){
			return null;
		}
		return new self($data["request_id"], $status);
	}

	public function getRequestId() : string{
		return $this->requestId;
	}

	public function getStatus() : int{
		return $this->status;
	}

	public function isSuccessful() : bool{
		return $this->status === 1;
	}

	public function isPending() : bool{
		return $this->status === 99;
	}

	public function getSafeMessage() : string{
		return match($this->status){
			1 => "Card accepted by the provider",
			2 => "The provider reported a denomination mismatch",
			3 => "The provider rejected the card",
			4 => "The provider is temporarily unavailable",
			99 => "The provider is still processing this request",
			100 => "The provider rejected the request",
			default => "The provider returned an unknown status",
		};
	}
}
