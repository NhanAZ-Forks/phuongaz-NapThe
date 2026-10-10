# NapThe legacy prototype

This private repository preserves phuongaz's prepaid card donation plugin for the PocketMine-MP API `3.21.0` generation. It is not verified for Axolotl-PM 5.x or a current payment provider. Do not use it with real cards or credentials.

## Historical behavior

- `/napthe` opens a FormAPI based card entry flow. The source imports `jojoe77777/FormAPI`, but `plugin.yml` does not declare the dependency.
- `resources/setting.yml` selects a provider hostname, ratio, bonus and console commands. The README previously listed several provider sites, but their current compatibility has not been verified.
- The code submits card numbers and the partner signature through an HTTP GET URL. This does not protect those values in transit.
- A pending card is checked by an in-memory task. An offline result is intended to be stored in `offline.yml` and processed when the player joins.

## Migration and safety limits

The source references `offline.yml` and `thedung.txt` as bundled resources, but neither file exists in `resources/`. The offline join handler also reads an `amount` setting that is absent from the bundled configuration and uses an undefined `telco` variable. Coin integration is commented out. Configured console commands may grant rewards, but their execution and duplicate handling have not been tested.

Any revival needs a permitted Axolotl-PM 5 port, HTTPS requests with provider verified parameters, durable payment state, idempotent fulfillment, safe command validation, and isolated tests without live cards. Keep old player data and credentials private during migration. [DevTools](https://github.com/NhanAZ/DevTools) has not built a verified PHAR from this source.

The repository has no root license file. Preserve phuongaz's author credit and resolve distribution rights before publishing a modified build. For project support, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
