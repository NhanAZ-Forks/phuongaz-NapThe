<?php

declare(strict_types=1);

use phuongaz\NapThe\RewardPolicy;
use phuongaz\NapThe\card\Gateway;
use phuongaz\NapThe\card\Result;

$repo = dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($repo) : void{
	$prefix = "phuongaz\\NapThe\\";
	if(!str_starts_with($class, $prefix)){
		return;
	}
	$relative = str_replace("\\", DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
	$file = $repo . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "phuongaz" . DIRECTORY_SEPARATOR . "NapThe" . DIRECTORY_SEPARATOR . $relative . ".php";
	if(is_file($file)){
		require $file;
	}
});

$assert = static function(bool $condition, string $message) : void{
	if(!$condition){
		throw new RuntimeException($message);
	}
};

$assert(Gateway::endpoint("thesieure.com") === "https://thesieure.com/chargingws/v2", "Hostname must be upgraded to HTTPS");
$assert(Gateway::endpoint("example.invalid/path") === null, "A configured path must be rejected");
$assert(Gateway::endpoint("http://example.invalid") === null, "An explicit insecure scheme must be rejected");
$assert(Gateway::endpoint("user:pass@example.invalid") === null, "Embedded URL credentials must be rejected");

$payload = Gateway::buildPayload("charging", "FIXTURE-REQUEST-0001", "VIETTEL", 10000, "TEST-SERIAL", "TEST-CODE", "", "");
$assert(($payload["request_id"] ?? null) === "FIXTURE-REQUEST-0001", "The payment request ID must be preserved");
$assert(($payload["command"] ?? null) === "charging", "The charge command must be explicit");
$assert(($payload["sign"] ?? null) === md5("TEST-CODETEST-SERIAL"), "The historical signature input order must be deterministic");
$assert(!isset($payload["partner_key"]), "The partner key must never be included as a payload field");

$success = Result::fromResponse('{"request_id":"FIXTURE-REQUEST-0001","status":1,"message":"fixture"}', 200, "FIXTURE-REQUEST-0001");
$pending = Result::fromResponse('{"request_id":"FIXTURE-REQUEST-0001","status":99,"message":"fixture"}', 200, "FIXTURE-REQUEST-0001");
$assert($success?->isSuccessful() === true, "Only status 1 is a successful charge response");
$assert($pending?->isPending() === true, "Status 99 must remain pending");
$assert(Result::fromResponse('{"request_id":"OTHER-REQUEST","status":1}', 200, "FIXTURE-REQUEST-0001") === null, "A response for a different request must be rejected");
$assert(Result::fromResponse('{"request_id":"FIXTURE-REQUEST-0001","status":1}', 503, "FIXTURE-REQUEST-0001") === null, "Non-success HTTP status must be rejected");
$assert(Result::fromResponse("not-json", 200, "FIXTURE-REQUEST-0001") === null, "Malformed JSON must be rejected");

$reward = RewardPolicy::calculate(10000, 1000, 1.5);
$assert($reward === 15.0, "Configured ratio and bonus should calculate the historical reward");
$assert(RewardPolicy::calculate(10000, 0, 1.5) === null, "Zero ratios must not calculate a reward");
$assert(RewardPolicy::command("givemoney {player} {money}", "FixturePlayer", 15.0) === "givemoney FixturePlayer 15", "Only validated placeholders should be substituted");
$assert(RewardPolicy::command("givemoney {player}\nstop", "FixturePlayer", 15.0) === null, "Newline command injection must be rejected");
$assert(RewardPolicy::command("givemoney {player}", "Fixture Player", 15.0) === null, "Unsafe player names must be rejected");

fwrite(STDOUT, "Passed offline gateway, response, and reward policy fixtures\n");
