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
namespace Jackthehack21\KOTH\Extensions;

use Jackthehack21\KOTH\Main;
use Jackthehack21\KOTH\Tasks\ExtensionReleasesTask;
use pocketmine\command\CommandSender;
use Throwable;

class ExtensionManager
{
    /** @var Main */
    private $plugin;

    public $prefix = "[Extensions] : ";

    /** @var array[]|BaseExtension[][]|int[][] */
    private $extensions = [];
    // [0 => [BaseExtension,0]]; arg 1 (0) is its status, so 0-disabled,1-loaded,2-enabled,3-unknown.

    private $extensionReleases = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->plugin->getServer()->getAsyncPool()->submitTask(new ExtensionReleasesTask("https://raw.githubusercontent.com/jackthehack21/koth-extensions/release/release.json"));
    }

    /**
     * @return array
     */
    public function getExtensionReleases(): array
    {
        return $this->extensionReleases;
    }

    /**
     * @param array $data
     */
    public function setExtensionReleases(array $data): void
    {
        $this->extensionReleases = $data;
    }

    public function handleCommand(CommandSender $sender, array $args) : bool{
        return true;
    }

    /**
     * @param string $fileName
     */
    public function handleDownloaded(string $fileName): void{
        if(file_exists($this->plugin->getDataFolder())) {
            $manifest = json_decode(file_get_contents($this->plugin->getDataFolder() . "extensions/manifest.json"), true);
            if ($manifest === null) {
                $manifest = ["version" => 0, "verified_extensions" => []];
            }
        } else {
            $manifest = ["version" => 0, "verified_extensions" => []];
        }
        $manifest["verified_extensions"][] = rtrim($fileName, ".php");
        file_put_contents($this->plugin->getDataFolder() . "extensions/manifest.json", json_encode($manifest));

        $this->loadExtension($fileName, true);
        $this->enableExtension(rtrim($fileName, ".php"));
    }

    /**
     * @param string $fileName
     * @param bool $mustBeVerified
     * @return bool
     *
     * @internal
     */
    public function loadExtension(string $fileName, bool $mustBeVerified) : bool{
        if(substr($fileName, -4) === ".php") {
            $name = rtrim($fileName, ".php");
            $path = $this->plugin->getDataFolder() . "extensions/${name}";
            $namespace = "Jackthehack21\\KOTH\\Extensions\\${name}";

            try{

                if($mustBeVerified) {
                    if(!file_exists($this->plugin->getDataFolder()."extensions/manifest.json")) return false;
                    $manifest = json_decode(file_get_contents($this->plugin->getDataFolder() . "extensions/manifest.json"), true);
                    if ($manifest === null) {
                        $this->plugin->debug($this->prefix . "manifest.json for extensions is corrupt, file is deleted now but all installed extensions via /koth extensions will have to be re-installed, or in config.yml set allow_unknown_extensions to true.");
                        unlink($this->plugin->getDataFolder() . "extensions/manifest.json");
                        return false;
                    }

                    if (!in_array($name, $manifest["verified_extensions"])) {
                        return false;
                    }
                }

                /** @noinspection PhpIncludeInspection */
                include_once $path . ".php";

                if (!is_a($namespace, BaseExtension::class, true)) {
                    $this->plugin->debug($this->prefix . "Failed to load extension '${name}' as class is not valid/found.");
                    return false;
                }

                foreach ($this->extensions as $extension) {
                    if ($extension[0]->getExtensionData()->getName() === $name) {
                        $this->plugin->debug($this->prefix . "Failed to load extension '${name}' as the extension already exists. (or has same name)");
                        return false;
                    }
                }

                $this->extensions[] = [new $namespace($this->plugin), 0];

                for($i = 0; $i < count($this->extensions); $i++){
                    if($this->extensions[$i][0]->getExtensionData()->getName() === $name){
                        if($this->extensions[$i][0]->onLoad()){
                            $this->extensions[$i][1] = 1;
                            $this->plugin->debug($this->prefix . "Extension '${name}' successfully loaded.");
                            return true;
                        }
                    }
                }
            } catch (Throwable $error){
                $this->plugin->getLogger()->error($this->prefix . "While loading extension '${name}' this error occurred:");
                $this->plugin->getLogger()->logException($error);
                return false;
            }

            $this->plugin->debug($this->prefix . "Extension '${name}' added to extensions list, but failed to load.");
            return true;
        }
        return false;
    }

    /**
     * @param bool $allowUnknown
     */
    public function loadExtensions(bool $allowUnknown = false) : void{
        $this->plugin->debug($this->prefix."Loading ".($allowUnknown ? "all":"only verified")." extensions...");
        if(!is_dir($this->plugin->getDataFolder()."extensions")) @mkdir($this->plugin->getDataFolder()."extensions");
        $count = 0;
        $content = scandir($this->plugin->getDataFolder()."extensions/");
        for($i = 0; $i < count($content); $i++){
            if($this->loadExtension($content[$i], !$allowUnknown) === true){
                $count++;
            }
        }

        //todo in way future order of load.

        $this->plugin->debug($this->prefix."Successfully loaded ${count} extensions.");
        return;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function enableExtension(string $name): bool{
        for($i = 0; $i < count($this->extensions); $i++){
            try {
                if ($this->extensions[$i][1] != 1) continue;
                if ($this->extensions[$i][0]->getExtensionData()->getName() !== $name) continue;
                if ($this->extensions[$i][0]->onEnable() === false) {
                    $this->plugin->debug($this->prefix . "Extension '${name}' failed to enable.");
                    return false;
                } else {
                    $this->plugin->debug($this->prefix . "Extension '${name}' Enabled.");
                    $this->plugin->getServer()->getPluginManager()->registerEvents($this->extensions[$i][0], $this->plugin);
                    $this->extensions[$i][1] = 2;
                    return true;
                }
            } catch (Throwable $error){
                $this->plugin->getLogger()->error($this->prefix . "While enabling extension '${name}' this error occurred: ");
                $this->plugin->getLogger()->logException($error);
                return false;
            }
        }
        return false;
    }

    public function enableExtensions() : void{
        $this->plugin->debug($this->prefix."Enabling all extensions...");
        $count = 0;
        for($i = 0; $i < count($this->extensions); $i++){
            if($this->enableExtension($this->extensions[$i][0]->getExtensionData()->getName()) === true){
                $count++;
            }
        }
        $this->plugin->debug($this->prefix."Successfully enabled ${count} extensions.");
        return;
    }

    public function disableExtensions() : void{
        $this->plugin->debug($this->prefix."Disabling Extensions...");
        for($i = 0; $i < count($this->extensions); $i++){
            if($this->extensions[$i][1] == 0) continue;
            $this->extensions[$i][0]->onDisable();
            $this->extensions[$i][1] = 0;
        }
        $this->plugin->debug($this->prefix."All extensions now disabled.");
        $this->extensions = [];
        return;
    }
}