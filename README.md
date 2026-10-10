# RedSkyBlockX

RedSkyBlockX is an experimental SkyBlock plugin source for Axolotl-PM. It contains island creation, member and permission management, a configurable block generator, and island commands.

[![CI](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml)

## Status and compatibility

The plugin is under development and has no validated distributable PHAR. Do not install a PHAR produced by the current workflow on a production server. The artifact check rejects the build because it lacks a required Commando class.

`plugin.yml` declares Axolotl-PM API `5.0.0`. Source fixtures run in CI with PHP 8.2, but a complete plugin runtime test has not passed on Axolotl-PM. Neither the declared API nor the CI PHP version establishes compatibility with the full Axolotl-PM 5.x line.

The source uses [Commando](https://github.com/CortexPE/Commando), [InvMenu](https://github.com/Muqsit/InvMenu), and [SimplePacketHandler](https://github.com/Muqsit/SimplePacketHandler). Item and block names are parsed with Axolotl-PM's native parsers. The remaining dependency lock and legacy build configuration are still being migrated and validated.

## Configuration and commands

The source templates are [`config.yml`](resources/config.yml), [`skyblock.json`](resources/skyblock.json), and [`messages.yml`](resources/messages.yml). They define generator contents, starting items, island settings, world state, and player messages. The plugin stores island records under its `Players` data directory. Back up that directory and the associated worlds before any experimental test. The current migration has not been validated against real player data.

The main command is `/skyblock`, with `/is`, `/sb`, `/island`, and `/isle` aliases declared in source. The command tree contains island creation, visiting, invitations, membership, settings, and administration. Use `/skyblock help` to inspect subcommands in a test environment after a valid build becomes available. Permissions are declared in [`plugin.yml`](plugin.yml).

## Building and development

[DevTools](https://github.com/NhanAZ/DevTools) is the intended tool for building and validating a standalone plugin PHAR. The current dependency graph cannot yet be shaded safely into a distributable artifact. In particular, Commando uses SimplePacketHandler, whose dynamic packet class reference requires a reviewed solution. The legacy Pharynx workflow also omits `CortexPE/Commando/PacketHooker.php`. Do not treat a successful archive command as proof that the plugin can boot.

The [CI workflow](.github/workflows/ci.yml) runs focused source fixtures and PHPStan, builds an archive, and rejects it if required classes are missing. The workflow pins its GitHub Actions references to specific revisions. Its run title uses GitHub's default commit or pull request text so individual executions remain distinguishable. The latest results and source revision are available in [GitHub Actions](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions).

Changes to island persistence need tests on disposable files and worlds. Do not point development tests at a live server's `Players` directory, world data, or plugin configuration. The repository's current fixtures cover zone-tool cleanup, duplicate island names, island-file reads, master-world lookup, and message formatting. These are source-level checks, not connected-player or gameplay tests.

## License, credits, and support

The repository retains its [GPL version 2 license text](LICENSE) and upstream attribution to [RedCraftGH/RedSkyBlock](https://github.com/RedCraftGH/RedSkyBlock). The current dependency graph includes separately licensed components, and distribution rights for a combined PHAR still need review. No new license or relicensing is claimed here.

Report source issues through [GitHub Issues](https://github.com/NhanAZ-Plugins/RedSkyBlockX/issues). The previous README also linked a [Discord invite](https://discord.gg/j2X83ujT6c) and a [donation page](https://www.paypal.com/paypalme/nhanaz).
