# OfflinePlayerTP
[![](https://poggit.pmmp.io/shield.state/OfflinePlayerTP)](https://poggit.pmmp.io/p/OfflinePlayerTP)

A PocketMine-MP plugin that lets you teleport among offline players.

## Commands
| Command  | Description |
| ------------- | ------------- |
| `/otp <player>` | Teleport to (offline) `<player>`  |
| `/otp <sourcePlayer> <destinationPlayer>` | Teleport (offline) `<sourcePlayer>` to (offline) `<destinationPlayer>`  |
| `/otp <player> <x> <y> <z> [world] [yaw] [pitch]` | Teleport (offline) `<player>` to coordinates  |

## Permission
By default, `/otp` command requires the same permission as pocketmine's `/tp` command. However, you can configure this plugin to assign the command `offlineplayertp.command` permission through [`config.yml`](https://github.com/Muqsit/OfflinePlayerTP/blob/master/resources/config.yml#L1-L9).
