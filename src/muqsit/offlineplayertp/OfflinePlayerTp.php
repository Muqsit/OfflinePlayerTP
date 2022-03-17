<?php

declare(strict_types=1);

namespace muqsit\offlineplayertp;

use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Server;

final class OfflinePlayerTp{

	public static function teleport(string $player_name, ?Vector3 $pos, ?string $world, ?float $yaw, ?float $pitch) : void{
		$server = Server::getInstance();

		$nbt = $server->getOfflinePlayerData($player_name) ?? throw new InvalidArgumentException("Player data of {$player_name} does not exist");
		if($pos !== null){
			$nbt->setTag("Pos", new ListTag([
				new DoubleTag($pos->x),
				new DoubleTag($pos->y),
				new DoubleTag($pos->z)
			], NBT::TAG_Double));
		}

		if($yaw !== null || $pitch !== null){
			$rotation_tag = $nbt->getListTag("Rotation");
			$nbt->setTag("Rotation", new ListTag([
				new FloatTag($yaw ?? $rotation_tag?->get(0)->getValue() ?? 0.0),
				new FloatTag($pitch ?? $rotation_tag?->get(1)->getValue() ?? 0.0)
			], NBT::TAG_Float));
		}

		if($world !== null){
			$nbt->setString("Level", $world);
		}

		$server->saveOfflinePlayerData($player_name, $nbt);
	}

	public static function getLocation(string $player_name, ?Vector3 &$pos, ?string &$world, ?float &$yaw, ?float &$pitch) : void{
		$server = Server::getInstance();

		$nbt = $server->getOfflinePlayerData($player_name) ?? throw new InvalidArgumentException("Player data of {$player_name} does not exist");

		$pos_tag = $nbt->getListTag("Pos");
		if($pos_tag !== null){
			$pos = new Vector3($pos_tag->get(0)->getValue(), $pos_tag->get(1)->getValue(), $pos_tag->get(2)->getValue());
		}

		$rotation_tag = $nbt->getListTag("Rotation");
		if($rotation_tag !== null){
			$yaw = $rotation_tag->get(0)->getValue();
			$pitch = $rotation_tag->get(1)->getValue();
		}

		$level_tag = $nbt->getTag("Level");
		if($level_tag instanceof StringTag){
			$world = $level_tag->getValue();
		}
	}

	private function __construct(){
	}
}