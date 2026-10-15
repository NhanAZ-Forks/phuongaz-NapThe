# NapThe

NapThe is a prepaid-card plugin for Axolotl-PM. It uses The9P's documented `/chargingws/v2` endpoint by default; the original author credit to phuongaz is retained. The manifest minimum is API 5.0.0. CI checks pinned API 5.0.0 and 5.49.1 source revisions, not every 5.x release.

## Use

- Run `/napthe` in-game and select a provider and card value.
- Enter the serial and PIN in the form. The plugin sends one HTTPS POST request to the configured provider host.
- Only a matching provider response with status `1` can start the configured reward commands. Status `99` is checked up to six times at ten-second intervals.
- A confirmed reward for an offline player is held in `transactions.yml` and processed when the player rejoins.

Configure `partner_id`, `partner_key`, `driver`, `ratio`, `bonus`, and `commands` in the server's plugin data `setting.yml`. The default host is `www.the9p.com`; `driver` must be a hostname only, and the plugin constructs `https://<driver>/chargingws/v2`. Requests use TLS verification and do not follow redirects. The charging and status-check signatures follow [The9P's official API documentation](https://www.the9p.com/tai-lieu-api), and provider responses must include a matching request ID and valid `callback_sign`. A custom host must implement the same documented contract before use. Existing server settings are preserved, so verify the configured host and merchant account before enabling payment submissions.

## Payment and data handling

The card PIN and serial are held in memory only while a request or bounded status check is active. They are not written to the plugin log, `transactions.yml`, the PHAR, or a test fixture. An uncertain HTTP response, an interrupted request, or an interrupted reward is moved to manual review; the plugin never resubmits a card after an ambiguous response. Confirmed rewards are tracked by request ID to avoid automatic duplicate command dispatch.

If an old server has `offline.yml`, valid pending reward records are imported into `transactions.yml` using deterministic IDs. The original `offline.yml` remains unchanged. The two files are not included in the PHAR. Invalid legacy rows are retained for manual review.

`ratio`, `bonus`, and reward commands affect real server economies. Review them before enabling payment submissions. The plugin cannot make arbitrary reward commands transactional; if a command fails or the server stops during reward dispatch, the request is marked for administrator review and is not automatically replayed.

## Verification limits

Offline fixtures cover charging and check signatures, request fields, supported card networks, HTTPS host construction, callback-signature validation, response request-ID matching, pending/failure statuses, and reward calculation/command validation. The response fixtures are synthetic; no credential or card transaction is used. CI checks PHPStan max against pinned Axolotl-PM API 5.0.0 and 5.49.1 sources, builds and inspects the PHAR, then performs a server startup smoke test. A connected-client form interaction and live payment are not verified.

The repository has no root license file. Do not treat the absence of a license file as permission to reuse the source. No real payment credential or transaction is included in this repository or its tests.
