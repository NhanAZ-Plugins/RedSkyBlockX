# RedSkyBlockX

RedSkyBlockX is an experimental SkyBlock plugin source for Axolotl-PM. It contains island creation, member and permission management, a configurable block generator, and island commands.

[![CI](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions/workflows/ci.yml)

## Status and compatibility

The plugin is under development. CI builds an internal PHAR and tests startup on isolated Axolotl-PM servers, but it does not publish that PHAR while distribution rights for the combined dependencies remain unresolved. Do not install an unreviewed build on a production server.

`plugin.yml` declares Axolotl-PM API `5.36.0`, the minimum declared by the pinned Commando dependency. The build workflow smoke-tests the exact PHAR on pinned Axolotl-PM 5.36.0 source and the 5.49.1 release runtime. These exact tested versions do not establish compatibility with every Axolotl-PM 5.x release. See [GitHub Actions](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions) for the exact revisions tested.

The plugin uses [Commando](https://github.com/CortexPE/Commando) for commands, [SimplePacketHandler](https://github.com/Muqsit/SimplePacketHandler) for packet interception required by Commando, and [InvMenu](https://github.com/Muqsit/InvMenu) for inventory forms. Item and block names are parsed with Axolotl-PM's native parsers. Composer locks the source revisions used by static analysis and the PHAR build.

## Configuration and commands

The source templates are [`config.yml`](resources/config.yml), [`skyblock.json`](resources/skyblock.json), and [`messages.yml`](resources/messages.yml). They define generator contents, starting items, island settings, world state, and player messages. The plugin stores island records under its `Players` data directory. Loading an island does not immediately rewrite its JSON file, and later saves retain fields that this version does not recognize. Island creation waits for every chunk covering the configured zone and starting chest; invalid island configuration stops creation before block placement. Back up the `Players` directory and associated worlds before any experimental test. The current migration has not been validated against real player data.

The main command is `/skyblock`, with `/is`, `/sb`, `/island`, and `/isle` aliases declared in source. The command tree contains island creation, visiting, invitations, membership, settings, and administration. Use `/skyblock help` to inspect subcommands in an isolated test environment. Permissions are declared in [`plugin.yml`](plugin.yml).

## Building and development

[DevTools](https://github.com/NhanAZ/DevTools) is the preferred builder when it can safely handle a project's dependency graph. Commando requires SimplePacketHandler, whose dynamic packet-class reference DevTools cannot safely shade, so this project's CI uses a checksum-pinned Pharynx build for its Composer-locked libraries. CI verifies the PHAR contents and tests startup on the declared minimum API and the separately tested Axolotl-PM runtime. These startup checks cover loading only.

A separate local test used a disposable one-block zone and a connected headless client. `/skyblock create` created an island. After a restart, `/is info` returned its saved record, a second create request was rejected, and `/is tp` teleported the player to the island. A server-side read after loading the island chunk found the expected stone, chest and starting items. This verifies one test island on one pinned server release. It does not validate larger zones, production data migration, the Bedrock game UI or distribution rights.

A second local test used two headless clients in sequence. The island owner invited another player and locked the island. The invited player accepted, visited the locked island, received the full member information, and remained listed as a member after clean shutdown. A separate test removed that member: the locked island denied a new visit before and after restart, and the saved membership was removed. A corrected build moved the former member outside the locked island both on reconnect and when removed while online. Another test banned the former member, unlocked the island and verified that a returning player was moved out while `/is visit` remained denied after restart. These checks cover one disposable world; other membership actions and game UI remain unverified.

The [CI workflow](.github/workflows/ci.yml) runs focused source fixtures and PHPStan, builds the plugin, verifies required classes, and checks isolated startup. It pins tool and source revisions. Its run title uses GitHub's default commit or pull request text so individual executions remain distinguishable. The latest results and source revision are available in [GitHub Actions](https://github.com/NhanAZ-Plugins/RedSkyBlockX/actions).

Changes to island persistence need tests on disposable files and worlds. Do not point development tests at a live server's `Players` directory, world data, or plugin configuration. The repository's current fixtures cover zone-tool cleanup, duplicate island names, island-file reads, master-world lookup, chunk preparation, and message formatting. These are source-level checks, not connected-player or gameplay tests.

## License, credits, and support

The repository retains its [GPL version 2 license text](LICENSE) and upstream attribution to [RedCraftGH/RedSkyBlock](https://github.com/RedCraftGH/RedSkyBlock). The current dependency graph includes separately licensed components, and distribution rights for a combined PHAR still need review. No new license or relicensing is claimed here.

Report source issues through [GitHub Issues](https://github.com/NhanAZ-Plugins/RedSkyBlockX/issues). The previous README also linked a [Discord invite](https://discord.gg/j2X83ujT6c) and a [donation page](https://www.paypal.com/paypalme/nhanaz).
