<?php

declare(strict_types=1);

namespace phuongaz\NapThe;

final class RewardPolicy{
	public static function calculate(int $faceValue, int $ratio, float $bonus) : ?float{
		if($faceValue < 1 || $ratio < 1 || !is_finite($bonus) || $bonus < 0){
			return null;
		}
		$result = ($faceValue / $ratio) * $bonus;
		return is_finite($result) ? $result : null;
	}

	public static function format(float $amount) : string{
		return rtrim(rtrim(number_format($amount, 2, ".", ""), "0"), ".");
	}

	public static function command(string $template, string $playerName, float $reward) : ?string{
		if(preg_match('/^[A-Za-z0-9_]{1,16}$/D', $playerName) !== 1 || preg_match('/[\r\n\x00-\x1F\x7F]/', $template) === 1){
			return null;
		}
		$template = trim($template);
		if($template === "" || strlen($template) > 256 || !is_finite($reward) || $reward < 0){
			return null;
		}
		$command = str_replace(["{player}", "{money}"], [$playerName, self::format($reward)], $template);
		return $command === "" || strlen($command) > 256 ? null : $command;
	}
}
