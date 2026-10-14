<?php

declare(strict_types=1);

namespace phuongaz\NapThe\form;

use JsonSerializable;
use phuongaz\NapThe\Loader;
use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

final class NapTheForm implements Form, JsonSerializable{
	/** @var list<array{label: string, code: string}> */
	private const TELCOS = [
		["label" => "Viettel", "code" => "VIETTEL"],
		["label" => "Vietnamobile", "code" => "VIETNAMMOBILE"],
		["label" => "Vinaphone", "code" => "VINAPHONE"],
		["label" => "Mobifone", "code" => "MOBIFONE"],
		["label" => "Zing", "code" => "ZING"],
		["label" => "Gate", "code" => "GATE"],
	];

	/** @var list<int> */
	private const AMOUNTS = [10000, 20000, 50000, 100000, 200000, 500000];

	private function __construct(
		private readonly Loader $plugin,
		private readonly string $step,
		private readonly ?string $telco = null,
	){
	}

	public static function selectTelco(Loader $plugin) : self{
		return new self($plugin, "telco");
	}

	/** @return array<string, mixed> */
	public function jsonSerialize() : array{
		if($this->step === "telco"){
			return [
				"type" => "form",
				"title" => "Nạp thẻ",
				"content" => "Chọn nhà mạng. Không gửi thẻ thật khi đang kiểm thử.",
				"buttons" => array_map(static fn(array $telco) : array => ["text" => $telco["label"]], self::TELCOS),
			];
		}

		return [
			"type" => "custom_form",
			"title" => "Nạp thẻ - " . $this->telco,
			"content" => [
				["type" => "label", "text" => "Nhập đúng mệnh giá, số serial và mã thẻ."],
				["type" => "dropdown", "text" => "Mệnh giá", "options" => ["10.000 VND", "20.000 VND", "50.000 VND", "100.000 VND", "200.000 VND", "500.000 VND"]],
				["type" => "input", "text" => "Serial", "placeholder" => "Nhập serial thẻ", "default" => ""],
				["type" => "input", "text" => "Mã thẻ", "placeholder" => "Nhập mã thẻ", "default" => ""],
			],
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data === null){
			return;
		}
		if($this->step === "telco"){
			if(!is_int($data) || !isset(self::TELCOS[$data])){
				throw new FormValidationException("Invalid telco selection");
			}
			$player->sendForm(new self($this->plugin, "card", self::TELCOS[$data]["code"]));
			return;
		}
		if(!is_array($data) || !isset($data[1], $data[2], $data[3]) || !is_int($data[1]) || !isset(self::AMOUNTS[$data[1]]) || !is_string($data[2]) || !is_string($data[3]) || $this->telco === null){
			throw new FormValidationException("Invalid card form response");
		}
		$this->plugin->submitCard($player, $this->telco, self::AMOUNTS[$data[1]], $data[2], $data[3]);
	}
}
