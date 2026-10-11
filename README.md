# RedSkyBlockX

RedSkyBlockX is an experimental SkyBlock plugin source for Axolotl-PM. It contains island creation, member and permission management, a configurable block generator, and island commands.

[![CI](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml)

## Status and compatibility

The plugin is under development. CI builds an internal PHAR and tests startup on an isolated Axolotl-PM server, but it does not upload or release that PHAR while distribution rights for the combined dependencies remain unresolved. Do not install an unreviewed build on a production server.

`plugin.yml` declares Axolotl-PM API `5.36.0`, the minimum declared by the pinned Commando dependency. Source fixtures run in CI with PHP 8.2, and the build workflow tests startup against a pinned server release. The declared API and a single runtime smoke test do not establish compatibility with the full Axolotl-PM 5.x line. See [GitHub Actions](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions) for the exact revision tested.

The source uses [Commando](https://github.com/CortexPE/Commando), [InvMenu](https://github.com/Muqsit/InvMenu), and [SimplePacketHandler](https://github.com/Muqsit/SimplePacketHandler). Item and block names are parsed with Axolotl-PM's native parsers. Composer locks the source revisions used by static analysis and the internal build.

## Configuration and commands

The source templates are [`config.yml`](resources/config.yml), [`skyblock.json`](resources/skyblock.json), and [`messages.yml`](resources/messages.yml). They define generator contents, starting items, island settings, world state, and player messages. The plugin stores island records under its `Players` data directory. Loading an island does not immediately rewrite its JSON file, and later saves retain fields that this version does not recognize. Back up that directory and the associated worlds before any experimental test. The current migration has not been validated against real player data.

The main command is `/skyblock`, with `/is`, `/sb`, `/island`, and `/isle` aliases declared in source. The command tree contains island creation, visiting, invitations, membership, settings, and administration. Use `/skyblock help` to inspect subcommands in an isolated test environment. Permissions are declared in [`plugin.yml`](plugin.yml).

## Building and development

[DevTools](https://github.com/NhanAZ/DevTools) is the preferred tool when a dependency graph meets its supported formats. This project currently locks a development revision of Commando, so CI uses a checksum-pinned Pharynx binary to shade the three locked library sources. It verifies required PHAR contents and boots the plugin on a pinned Axolotl-PM runtime. A successful startup does not prove island gameplay or connected-player behavior.

The [CI workflow](.github/workflows/ci.yml) runs focused source fixtures and PHPStan, builds the plugin, verifies required classes, and checks isolated startup. It pins tool and source revisions. Its run title uses GitHub's default commit or pull request text so individual executions remain distinguishable. The latest results and source revision are available in [GitHub Actions](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions).

Changes to island persistence need tests on disposable files and worlds. Do not point development tests at a live server's `Players` directory, world data, or plugin configuration. The repository's current fixtures cover zone-tool cleanup, duplicate island names, island-file reads, master-world lookup, and message formatting. These are source-level checks, not connected-player or gameplay tests.

## License, credits, and support

The repository retains its [GPL version 2 license text](LICENSE) and upstream attribution to [RedCraftGH/RedSkyBlock](https://github.com/RedCraftGH/RedSkyBlock). The current dependency graph includes separately licensed components, and distribution rights for a combined PHAR still need review. No new license or relicensing is claimed here.

Report source issues through [GitHub Issues](https://github.com/NhanAZ-Plugins/RedSkyBlockX/issues). The previous README also linked a [Discord invite](https://discord.gg/j2X83ujT6c) and a [donation page](https://www.paypal.com/paypalme/nhanaz).
