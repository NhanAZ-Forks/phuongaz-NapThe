<?php

declare(strict_types=1);

namespace phuongaz\NapThe\card;

final class Gateway{
	/**
	 * Convert the historical hostname setting into the HTTPS v2 endpoint.
	 * Schemes, credentials, custom ports, paths, queries and fragments are rejected.
	 */
	public static function endpoint(string $driver) : ?string{
		$driver = trim($driver);
		if($driver === "" || str_contains($driver, "://")){
			return null;
		}
		$parts = parse_url("https://" . $driver);
		if(!is_array($parts) || !isset($parts["host"]) || isset($parts["user"]) || isset($parts["pass"]) || isset($parts["port"]) || isset($parts["path"]) || isset($parts["query"]) || isset($parts["fragment"])){
			return null;
		}
		$host = strtolower($parts["host"]);
		if(preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host) !== 1){
			return null;
		}

		return "https://" . $host . "/chargingws/v2";
	}

	public static function signature(string $partnerKey, string $code, string $serial) : string{
		// This preserves the historical gateway contract used by this plugin and the NhanAZ NapThe integration.
		return md5($partnerKey . $code . $serial);
	}

	/** @return array<string, int|string> */
	public static function buildPayload(
		string $command,
		string $requestId,
		string $telco,
		int $amount,
		string $serial,
		string $code,
		string $partnerId,
		string $partnerKey,
	) : array{
		if(!in_array($command, ["charging", "check"], true)){
			throw new \InvalidArgumentException("Unsupported card gateway command");
		}

		return [
			"request_id" => $requestId,
			"code" => $code,
			"partner_id" => $partnerId,
			"serial" => $serial,
			"telco" => $telco,
			"amount" => $amount,
			"command" => $command,
			"sign" => self::signature($partnerKey, $code, $serial),
		];
	}
}
