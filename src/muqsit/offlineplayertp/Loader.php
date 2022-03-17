<?php

declare(strict_types=1);

namespace muqsit\offlineplayertp;

use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\entity\Location;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use RuntimeException;
use function assert;
use function count;
use function round;
use function substr;
use function var_dump;

final class Loader extends PluginBase{

	private bool $allow_online_player_tp;

	protected function onEnable() : void{
		$this->allow_online_player_tp = $this->getConfig()->get("allow-online-player-tp", true);
		if($this->getConfig()->get("use-default-tp-permission", true)){
			$command = $this->getCommand("offlineplayertp") ?? throw new RuntimeException("Could not retrieve command: offlineplayertp");

			$manager = PermissionManager::getInstance();
			$permission_string = $command->getPermission() ?? throw new RuntimeException("Could not retrieve default permission from \"{$command->getName()}\" command");
			$permission = $manager->getPermission($permission_string) ?? throw new RuntimeException("Permission \"{$permission_string}\" does not exist");
			$manager->removePermission($permission);

			$command->setPermission(DefaultPermissionNames::COMMAND_TELEPORT);
		}
	}

	private function getRelativeDouble(float $original, string $input, float $min, float $max) : float{
		if($input[0] === "~"){
			$value = $this->getDouble(substr($input, 1), $min, $max);
			return $original + $value;
		}
		return $this->getDouble($input, $min, $max);
	}

	protected function getDouble(string $value, float $min, float $max) : float{
		$i = (double) $value;
		if($i < $min){
			$i = $min;
		}elseif($i > $max){
			$i = $max;
		}
		return $i;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		/** @var string|null $src_player */
		$src_player = null;

		/** @var string|null $dst_player */
		$dst_player = null;

		/** @phpstan-var array{float, float, float, ?string, ?float, ?float}|null $dst_pos */
		$dst_pos = null;

		switch(count($args)){
			case 1: // /otp targetPlayer
				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED . "You cannot teleport to a player as a console.");
					return true;
				}

				$src_player = $sender->getName();
				$dst_player = $args[0];
				break;
			case 2: // /otp player1 player2
				$src_player = $args[0];
				$dst_player = $args[1];
				break;
			case 4: // /otp player1 x y z
			case 5: // /otp player1 x y z world
			case 7: // /otp player1 x y z world yaw pitch
				$src_player = $args[0];

				$world = null;
				$yaw = null;
				$pitch = null;
				if($sender instanceof Player){
					$pos = $sender->getLocation();
					$x = $this->getRelativeDouble($pos->x, $args[1], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					$y = $this->getRelativeDouble($pos->y, $args[2], World::Y_MIN, World::Y_MAX);
					$z = $this->getRelativeDouble($pos->z, $args[3], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					if(isset($args[4])){
						$world = $args[4] === "~" ? $pos->getWorld()->getFolderName() : $args[4];
					}
					if(isset($args[5])){
						$yaw = $this->getRelativeDouble($pos->yaw, $args[5], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					}
					if(isset($args[6])){
						$pitch = $this->getRelativeDouble($pos->pitch, $args[6], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					}
				}else{
					$x = $this->getDouble($args[1], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					$y = $this->getDouble($args[2], World::Y_MIN, World::Y_MAX);
					$z = $this->getDouble($args[3], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					if(isset($args[4])){
						$world = $args[4];
					}
					if(isset($args[5])){
						$yaw = $this->getDouble($args[5], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					}
					if(isset($args[6])){
						$pitch = $this->getDouble($args[6], VanillaCommand::MIN_COORD, VanillaCommand::MAX_COORD);
					}
				}
				$dst_pos = [$x, $y, $z, $world, $yaw, $pitch];
				break;
			default:
				return false;
		}

		assert($src_player !== null);
		$src_player_online = $this->getServer()->getPlayerExact($src_player);
		if($src_player_online !== null && !$this->allow_online_player_tp){
			$sender->sendMessage(TextFormat::RED . "Teleportation among online players is disabled.");
			return true;
		}

		$dst_player_online = null;
		if($dst_player !== null){
			$dst_player_online = $this->getServer()->getPlayerExact($dst_player);
			if($dst_player_online !== null && !$this->allow_online_player_tp){
				$sender->sendMessage(TextFormat::RED . "Teleportation among online players is disabled.");
				return true;
			}
		}

		if($dst_pos === null){
			assert($dst_player !== null);
			if($dst_player_online !== null){
				$pos = $dst_player_online->getLocation();
				$dst_pos = [$pos->x, $pos->y, $pos->z, $pos->getWorld()->getFolderName(), $pos->yaw, $pos->pitch];
			}else{
				try{
					OfflinePlayerTp::getLocation($dst_player, $pos, $world, $yaw, $pitch);
				}catch(InvalidArgumentException){
					$sender->sendMessage(TextFormat::RED . "Player \"{$dst_player}\" has not joined the server.");
					return true;
				}
				$dst_pos = [$pos->x, $pos->y, $pos->z, $world, $yaw, $pitch];
			}
		}

		if($src_player_online !== null){
			if($dst_pos[3] !== null){
				$world = $this->getServer()->getWorldManager()->getWorldByName($dst_pos[3]);
				if($world === null){
					$sender->sendMessage(TextFormat::RED . "World \"{$dst_pos[3]}\" is not loaded.");
					return true;
				}

				$current_pos = $src_player_online->getLocation();
				$pos = new Location($dst_pos[0], $dst_pos[1], $dst_pos[2], $world, $dst_pos[4] ?? $current_pos->yaw, $dst_pos[5] ?? $current_pos->pitch);
			}else{
				$pos = new Vector3($dst_pos[0], $dst_pos[1], $dst_pos[2]);
			}

			if($src_player_online->teleport($pos)){
				if($dst_player !== null){
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success($src_player_online->getName(), $dst_player_online?->getName() ?? "(offline) " . $dst_player));
				}else{
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success_coordinates(
						$src_player_online->getName(),
						(string) round($pos->x, 2),
						(string) round($pos->y, 2),
						(string) round($pos->z, 2)
					));
				}
			}else{
				$sender->sendMessage(TextFormat::RED . "Failed to teleport to destination.");
			}
			return true;
		}

		try{
			OfflinePlayerTp::teleport($src_player, new Vector3($dst_pos[0], $dst_pos[1], $dst_pos[2]), $dst_pos[3] ?? null, $dst_pos[4] ?? null, $dst_pos[5] ?? null);
		}catch(InvalidArgumentException){
			$sender->sendMessage(TextFormat::RED . "Player \"{$src_player}\" has not joined the server.");
			return true;
		}

		if($dst_player !== null){
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success("(offline) " . $src_player, $dst_player_online?->getName() ?? "(offline) " . $dst_player));
		}else{
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success_coordinates(
				"(offline) " . $src_player,
				(string) round($dst_pos[0], 2),
				(string) round($dst_pos[1], 2),
				(string) round($dst_pos[2], 2)
			));
		}
		return true;
	}
}