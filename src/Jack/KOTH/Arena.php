<?php
/*
*    /$$   /$$  /$$$$$$  /$$$$$$$$ /$$   /$$
*   | $$  /$$/ /$$__  $$|__  $$__/| $$  | $$
*   | $$ /$$/ | $$  \ $$   | $$   | $$  | $$
*   | $$$$$/  | $$  | $$   | $$   | $$$$$$$$
*   | $$  $$  | $$  | $$   | $$   | $$__  $$
*   | $$\  $$ | $$  | $$   | $$   | $$  | $$
*   | $$ \  $$|  $$$$$$/   | $$   | $$  | $$
*   |__/  \__/ \______/    |__/   |__/  |__/
*  
*   Copyright (C) 2019 Jackthehack21 (Jack Honour/Jackthehaxk21/JaxkDev)
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
*   Twitter :: @JaxkDev
*   Discord :: Jackthehaxk21#8860
*   Email   :: gangnam253@gmail.com
*/

declare(strict_types=1);
namespace Jack\KOTH;

use Jack\KOTH\Tasks\Prestart;
use Jack\KOTH\Tasks\Gametimer;

use pocketmine\Player;
use pocketmine\level\Position;

use pocketmine\utils\TextFormat as C;


/*

NOTES:
- so if king dies out of box or goes out of box, its race to the box, 
  if killed in box from someone inside box that killer is king
  if someone outside the box kills him next to box is king
  if multple people in box when next king selection, person closest to middle is crowned.

- EventHandler handles all events from pmmp, then passed here.

*/

class Arena{

    public const STATUS_NOT_READY = 0;
    public const STATUS_READY = 1;
    public const STATUS_STARTED = 2;
    public const STATUS_FULL = 3;

    public $statusList = [
        self::STATUS_NOT_READY => "Not Ready/Setup",
        self::STATUS_READY => "Ready",
        self::STATUS_STARTED => "Started",
        self::STATUS_FULL => "Full"
    ];

    private $plugin;
    public $spawns = []; //[[12,50,10],[],[],[]] list of spawn points.
    public $spawnCounter;
    public $hill = []; //[[20,20],[30,20]] two points corner to corner. (X,Z) no y.
    public $players = []; //list of all players currently ingame. (lowercase names)
    public $minPlayers;
    public $maxPlayers;
    public $name;
    public $started;
    public $time;
    public $countDown;
    public $world;

    public $oldKing;
    public $king;
    public $playersInBox;

    public $timerTask;
    public $status;

    public function __construct(Main $plugin, string $name, int $min, int $max, int $time, int $count, array $hill, array $spawns, string $world){
        $this->plugin = $plugin;
        $this->hill = $hill;
        $this->minPlayers = $min;
        $this->maxPlayers = $max;
        $this->name = $name;
        $this->spawns = $spawns;
        $this->spawnCounter = 0;
        $this->started = false;
        $this->time = $time;
        $this->countDown = $count;
        $this->world = $world;

        $this->king = null;
        $this->playersInBox = [];
        $this->timerTask = null;

        $this->checkStatus();
    }

    public function getFriendlyStatus() : string{
        return $this->statusList[$this->status];
    }

    public function getStatus() : int{
        return $this->status;
    }

    public function getName() : string{
        return $this->name;
    }

    public function broadcastMessage(string $msg) : void{
        foreach($this->players as $player){
            $this->plugin->getServer()->getPlayerExact($player)->sendMessage($msg);
        }
    }

    public function broadcastWinner(string $player) : void{
        //todo get config.
        $this->broadcastMessage($this->plugin->prefix.$player."Has won the game !");
    }

    public function broadcastQuit(Player $player, string $reason) : void{
        //todo get config.
        $this->broadcastMessage($this->plugin->prefix.$player->getName()." Has left the game, reason: ".$reason);
    }

    public function broadcastJoin(Player $player) : void{
        //todo get config.
        $this->broadcastMessage($this->plugin->prefix.$player->getName()." Has joined the game !");
    }

    public function checkStatus(bool $save = true) : void{
        if(count($this->hill) === 2 && count($this->spawns) >= 1){
            $this->status = self::STATUS_READY;
        } else {
            $this->status = self::STATUS_NOT_READY;
            if($save === true) $this->plugin->saveArena();
            return;
        }
        if($this->started === true){
            $this->status = self::STATUS_STARTED;
        }
        if(count($this->players) >= $this->maxPlayers){
            $this->status = self::STATUS_FULL;
            if($save === true) $this->plugin->saveArena();
            return;
        }
        if($save === true) $this->plugin->saveArena();
    }

    public function spawnPlayer(Player $player, $random = false) : void{
        if(strtolower($player->getLevel()->getName()) !== strtolower($this->world)){
            if(!$this->plugin->getServer()->isLevelGenerated($this->world)) {
                //todo config msg.
                //world does not exist
                $player->sendMessage("This arena is corrupt.");
                return;
            }
            if(!$this->plugin->getServer()->isLevelLoaded($this->world)) {
                $this->plugin->getServer()->loadLevel($this->world);
            }

        }
        if($random === true){
            $old = array_rand($this->spawns);
            $pos = new Position($old[0], $old[1], $old[2], $this->plugin->getServer()->getLevelByName($this->world)); //x,y,z,level;
            $player->teleport($pos);
        } else {
            if($this->spawnCounter >= count($this->spawns)){
                $this->spawnCounter = 0; //reset
            }
            $old = $this->spawns[$this->spawnCounter];
            $pos = new Position($old[0], $old[1], $old[2], $this->plugin->getServer()->getLevelByName($this->world)); //x,y,z,level;
            $player->teleport($pos);
            $this->spawnCounter++;
        }
    }

    public function freezeAll(bool $freeze) : void{
        foreach($this->players as $name){
            $this->plugin->getServer()->getPlayerExact($name)->setImmobile($freeze);
        }
    }

    public function startTimer() : void{
        //start pre timer.
        //schedule repeating task, delay of 20ticks to do 1 second countdown.
        $this->timerTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new Prestart($this->plugin, $this, $this->countDown),20);
    }

    public function startGame() : void{
        /** @noinspection PhpUndefinedMethodInspection */
        $this->timerTask->cancel();
        $this->started = true;
        $this->checkStatus();
        $this->broadcastMessage("Game started");
        $this->timerTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new Gametimer($this->plugin, $this),20);
        //start next timer task.
        //broadcast the games started, start task to keep track of king.
    }

    public function reset() : void{
        //todo tp all players.
        $this->players = [];
        $this->started = false;
        $this->king = null;

        $this->checkStatus();
    }

    public function endGame() : void{
        $this->freezeAll(true);
        $old = $this->oldKing; //in case of no king.
        $king = $this->king; //in case of a change in this tiny blind spot.
        /** @noinspection PhpUndefinedMethodInspection */
        $this->timerTask->cancel();
        //todo events.
        if($king !== null){
            /** @noinspection PhpStrictTypeCheckingInspection */
            $this->setWinner($king);
        } else {
            if($old === null){
                $this->setWinner("Null");
                return;
            }
            /** @noinspection PhpStrictTypeCheckingInspection */
            $this->setWinner($old);
        }
        $this->reset();
        $this->checkStatus();
    }

    public function setWinner(string $king) : void{
        if($king === "Null"){
            $this->broadcastMessage($this->plugin->prefix.C::RED."GAME OVER, No one managed to claim the hill and win the game. Better luck next time.");
            $this->freezeAll(false);
            return;
        }
        $this->broadcastWinner($king);
        //todo give rewards based on config.
        //todo particles fireworks and more for king, and X second delay before un freezing.
        $this->freezeAll(false);
    }

    public function getPlayers() : array{
        return $this->players;
    }

    public function playersInBox() : array{
        $pos1 = [];
        $pos1["x"] = $this->hill[0][0];
        $pos1["z"] = $this->hill[0][2];
        $pos2 = [];
        $pos2["x"] = $this->hill[1][0];
        $pos2["z"] = $this->hill[1][2];
        $minX = min($pos2["x"],$pos1["x"]);
        $maxX = max($pos2["x"],$pos2["x"]);
        $minZ = min($pos2["z"],$pos1["z"]);
        $maxZ = max($pos2["z"],$pos2["z"]);
        $list = [];

        foreach($this->players as $playerName){
            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if(($minX <= $player->getFloorX() && $player->getFloorX() <= $maxX && $minZ <= $player->getFloorZ() && $player->getFloorZ() <= $maxZ)){
                $list[] = $playerName;
            }
        }
        return $list;
    }

    public function removeKing() : void{
        if($this->king === null) return;
        $this->broadcastMessage("The king has fallen.");
        //todo config.
        $this->oldKing = $this->king;
        $this->king = null;
        $this->changeking();
    }

    public function changeKing() : void{
        if($this->king !== null){
            $this->oldKing = $this->king;
            $this->king = null;
        }
        if($this->checkNewKing() === false){
            $this->broadcastMessage($this->plugin->prefix.C::GOLD."No one has claimed the throne, who will be king of the hill...");
            return;
        }
    }

    public function checkNewKing() : bool{
        if(count($this->playersInBox()) === 0){
            return false;
        } else {
            $player = $this->playersInBox()[array_rand($this->playersInBox())]; //todo closest to middle.
            $this->broadcastMessage($player." Has claimed the throne, how long will it last...");
            $this->king = $player;
            //todo update HUD etc.
            return true;
        }
    }


    /**
     * @param Player $player
     * @param string $reason
     * 
     * @return void
     */
    public function removePlayer(Player $player, string $reason) : void{
        if($this->king === $player->getLowerCaseName()){
            $this->removeKing();
        }
        unset($this->players[array_search(strtolower($player->getName()), $this->players)]);
        $this->broadcastQuit($player, $reason);
        $this->checkStatus();
    }

    /**
     * NOTE: Returns false if player cannot join.
     * 
     * @param Player $player
     * 
     * @return bool
     */
    public function addPlayer(Player $player) : bool{
        if(count($this->players) >= $this->maxPlayers){
            return false;
        }
        if($this->plugin->getArenaByPlayer(strtolower($player->getName())) !== null){
            return false;
        }
        switch($this->status){
            case self::STATUS_NOT_READY:
                $player->sendMessage($this->plugin->prefix.C::RED."This arena has not been setup.");
                return false;
            case self::STATUS_FULL:
                $player->sendMessage($this->plugin->prefix.C::RED."This arena is full.");
                return false;
        }
        $player->setGamemode(0);
        $this->players[] = strtolower($player->getName());
        $this->broadcastJoin($player);
        $this->spawnPlayer($player);
        if(count($this->players) >= $this->minPlayers && $this->timerTask === null){
            $this->startTimer();
        }
        $this->checkStatus();
        return true;
    }
}