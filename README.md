# NapThe

NapThe is a PocketMine plugin for submitting prepaid cards through a configured `/chargingws/v2` provider. The manifest declares Axolotl-PM API 5.0.0 as its minimum; CI currently checks API 5.0.0 and 5.49.1 source pins, not every 5.x release. The original author credit to phuongaz is retained.

## Use

- Run `/napthe` in-game and select a provider and card value.
- Enter the serial and PIN in the form. The plugin sends one HTTPS POST request to the configured provider host.
- Only a matching provider response with status `1` can start the configured reward commands. Status `99` is checked up to six times at ten-second intervals.
- A confirmed reward for an offline player is held in `transactions.yml` and processed when the player rejoins.

Configure `partner_id`, `partner_key`, `driver`, `ratio`, `bonus`, and `commands` in the server's plugin data `setting.yml`. `driver` must be a hostname only; the plugin constructs `https://<driver>/chargingws/v2`. Requests use TLS verification and do not follow redirects. The signing rule and form-encoded request preserve this source's historical gateway contract. The provider's authenticated merchant contract and any live payment transaction have not been verified in this migration.

## Payment and data handling

The card PIN and serial are held in memory only while a request or bounded status check is active. They are not written to the plugin log, `transactions.yml`, the PHAR, or a test fixture. An uncertain HTTP response, an interrupted request, or an interrupted reward is moved to manual review; the plugin never resubmits a card after an ambiguous response. Confirmed rewards are tracked by request ID to avoid automatic duplicate command dispatch.

If an old server has `offline.yml`, valid pending reward records are imported into `transactions.yml` using deterministic IDs. The original `offline.yml` remains unchanged. The two files are not included in the PHAR. Invalid legacy rows are retained for manual review.

`ratio`, `bonus`, and reward commands affect real server economies. Review them before enabling payment submissions. The plugin cannot make arbitrary reward commands transactional; if a command fails or the server stops during reward dispatch, the request is marked for administrator review and is not automatically replayed.

## Verification limits

Offline fixtures cover request signing and fields, HTTPS host construction, response request-ID matching, pending/failure statuses, and reward calculation/command validation. The response fixtures are synthetic; no credential or card transaction is used. CI checks PHPStan max against pinned Axolotl-PM API 5.0.0 and 5.49.1 sources, builds and inspects the PHAR, then performs a server startup smoke test. A connected-client form interaction is not verified.

The repository has no root license file or separate redistribution grant recorded. No stable release asset is published.
