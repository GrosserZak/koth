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



use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerMoveEvent,PlayerRespawnEvent,PlayerQuitEvent};;

use pocketmine\utils\TextFormat as C;

use Jack\KOTH\Main;

class EventHandler implements Listener{

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        if($this->plugin->inGame($playerName) === true){
            //notify players in that arena that a player has left, adjust scoreboard.
            $arena = $this->plugin->getArenaByPlayer($playerName);
            $arena->removePlayer("Disconnected from server."); //arg0 is reason.
            foreach($arena->getPlayers() as $ePlayer){
                $ePlayer->sendMessage("Test");
            }
        }
    }

    public function onRespawn(PlayerRespawnEvent $event){
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        if($this->plugin->inGame($playerName) === true){
            //Respawn player in different spawn location.
            $arena = $this->plugin->getArenaByPlayer($playerName);
            $arena->spawnPlayer(true); //arg0 is spawn randomly? bool.
            foreach($arena->getPlayers() as $ePlayer){
                $ePlayer->sendMessage("Test, player joined.");
            }
        }
    }

    public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $from = $event->getFrom();
        $to = $event->getTo();
        var_dump($from);
        var_dump($to);
    }

}