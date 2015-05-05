<?php

namespace LoginSecurity;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\scheduler\ServerScheduler;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use LoginSecurity\Task;
use LoginSecurity\Task2;

use LoginSecurity\BaseEvent;
use LoginSecurity\PlayerLoginEvent;
use LoginSecurity\PlayerLogoutEvent;

class LoginSecurity extends PluginBase implements Listener{
	
	private $login = array();
	
	public function onEnable(){
		if(!is_dir($this->getPluginDir())){
			@mkdir($this->getServer()->getDataPath()."plugins/hoyinm14mc_plugins");
			mkdir($this->getPluginDir());
		}
	  $this->cfg = new Config($this->getPluginDir()."config.yml", Config::YAML, array());
	  $c = $this->configFile()->getAll();
	  if(!isset($c["login-timeout"])){
	  	$this->configFile()->set("login-timeout", 25);
	  }
	  if(!isset($c["min-password-length"])){
	  	$this->configFile()->set("min-password-length", 6);
	  }
	  $this->configFile()->save();
	  if(!is_numeric($this->cfg->get("login-timeout"))){
	  	$this->getLogger()->error("'login-timeout'/'min-password-length' in ".$this->getPluginDir()."config.yml must be numeric!");
	  	$this->getServer()->getPluginManager()->disablePlugin($this);
	  }
		if(!is_dir($this->getPluginDir()."data")){
			mkdir($this->getPluginDir()."data");
		}
		$this->data = new Config($this->getPluginDir()."data/data.yml", Config::YAML, array());
		$this->ip = new Config($this->getPluginDir()."data/ip.yml", Config::YAML, array());
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new Task($this), 20 * 6);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GREEN."Loaded Successfully!");
	}
	
	public function getPluginDir(){
		return $this->getServer()->getDataPath()."plugins/hoyinm14mc_plugins/LoginSecurity/";
	}
	
	public function configFile(){
		return $this->cfg;
	}
	
	//HAHA high security~
	private function salt($pw){
		return sha1(md5($this->salt2($pw).$pw.$this->salt2($pw)));
	}
	private function salt2($word){
		return hash('sha256', $word);
	}
	
	public function isLoggedIn(Player $player){
		return in_array($player->getName(), $this->login);
	}
	
	public function isRegistered(Player $player){
		$t = $this->data->getAll();
		return isset($t[$player->getName()]["ip"]);
	}
	
	public function auth(Player $player, $method){	
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerLoginEvent($this, $player, $method));
		
		if($event->isCancelled()){
			return false;
		}
		
		$this->login[$event->getPlayer()->getName()] = $event->getPlayer()->getName();
	}
	
	public function login(Player $player, $password){
		$t = $this->data->getAll();
		if(md5($password.$this->salt($password)) != $t[$player->getName()]["password"]){
			$player->sendMessage(TextFormat::RED."Wrong password!");
			return false;
		}
		
		$this->auth($player, 0);
		$player->sendMessage(TextFormat::GREEN."You are now logged in.");
	}
	
	public function logout(Player $player){
		
		if(!$this->isLoggedIn($player)){
			$player->sendMessage(TextFormat::YELLOW."You are not logged in!");
			return false;
		}
		
		$this->getServer()->getPluginManager()->callEvent($event = new PlayerLogoutEvent($this, $player, $method));
		
		unset($this->login[$player->getName()]);
		$player->sendMessage(TextFormat::GREEN."You have logged out.");
	}
	
	public function register(Player $player, $pw1){
		$t = $this->data->getAll();
		$t[$player->getName()]["password"] = md5($pw1.$this->salt($pw1));
		$this->data->setAll($t);
		$this->data->save();
	}
	
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		$t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$this->login($event->getPlayer(), $event->getMessage());
				$event->setCancelled(true);
			}else{
				if(!isset($t[$event->getPlayer()->getName()]["password"])){
					if(strlen($event->getMessage()) < $this->configFile()->get("min-password-length")){
			      $event->getPlayer()->sendMessage(TextFormat::RED."The password is not long enough!");
			    }else{
     			$this->register($event->getPlayer(), $event->getMessage());
					  $event->getPlayer()->sendMessage(TextFormat::YELLOW."Type your password again to confirm.");
     		}
					$event->setCancelled(true);
				}
				if(!isset($t[$event->getPlayer()->getName()]["confirm"]) && isset($t[$event->getPlayer()->getName()]["password"])){
					$t[$event->getPlayer()->getName()]["confirm"] = $event->getMessage();
					$this->data->setAll($t);
					$this->data->save();
					if(md5($event->getMessage().$this->salt($event->getMessage())) != $t[$event->getPlayer()->getName()]["password"]){
						$event->getPlayer()->sendMessage(TextFormat::YELLOW."Confirm password ".TextFormat::RED."INCORRECT".TextFormat::YELLOW."!\n".TextFormat::WHITE."Please type your password in chat to start register.");
						$event->setCancelled(true);
						unset($t[$event->getPlayer()->getName()]);
						$this->data->setAll($t);
						$this->data->save();
					}else{
						$event->getPlayer()->sendMessage(TextFormat::WHITE."Confirm password ".TextFormat::GREEN."CORRECT".TextFormat::YELLOW."!\n".TextFormat::WHITE."Your password is '".TextFormat::AQUA.TextFormat::BOLD.$event->getMessage().TextFormat::WHITE.TextFormat::RESET."'");
						$event->setCancelled(true);
					}
				}
				if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"]) && isset($t[$event->getPlayer()->getName()]["password"])){
					if($event->getMessage() != "yes" && $event->getMessage() != "no"){
					   $event->getPlayer()->sendMessage(TextFormat::YELLOW."If you want to login with your every last joined ip everytime, type '".TextFormat::WHITE."yes".TextFormat::YELLOW."'. Else, type '".TextFormat::WHITE."no".TextFormat::YELLOW."'");
					   $event->setCancelled(true);
					}else{
						 $t[$event->getPlayer()->getName()]["ip"] = $event->getMessage();
						 unset($t[$event->getPlayer()->getName()]["confirm"]);
						 $this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
						 $this->data->setAll($t);
						 $this->data->save();
						 $event->getPlayer()->sendMessage(TextFormat::GREEN."You are now registered!\n".TextFormat::YELLOW."Type your password in chat to login.\nYou may use '/email <email>' to enter your email.");
						 $time = $this->configFile()->get("login-timeout");
						 $this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), ($time * 20));
						 $event->setCancelled(true);
					}
				}
			}
		}else{
			$pw = $t[$event->getPlayer()->getName()]["password"];
			$event->getMessage();
			if(!empty($event->getMessage())){
			  if(strpos(md5($event->getMessage().$this->salt($event->getMessage())), $pw) !== false && $msg{0} != "/"){
				  $event->getPlayer()->sendMessage("Do not tell your password to other people!");
				  $event->setCancelled(true);
		  	}
			}
		}
	}
	
	public function onJoin(PlayerJoinEvent $event){
		$t = $this->data->getAll();
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if($p->getAddress() === $event->getPlayer()->getAddress() && $this->isLoggedIn($p)){
				$event->getPlayer()->kick(TextFormat::WHITE."[Grief Protector] \n".TextFormat::RED."There is another account that has same IP address with you!");
				$p->kick(TextFormat::WHITE."[Grief Protector] \n".TextFormat::RED."There is another account that has same IP address with you!");
			}
		}
		if($this->isRegistered($event->getPlayer())){
			if($t[$event->getPlayer()->getName()]["ip"] == "yes"){
				if($event->getPlayer()->getAddress() == $this->ip->get($event->getPlayer()->getName())){
					$this->auth($event->getPlayer(), 1);
					$event->getPlayer()->sendMessage(TextFormat::WHITE."[IP] §2Welcome back! §6We remember you!!\n".TextFormat::GREEN."You are now logged in.");
				}else{
					$event->getPlayer()->sendMessage(TextFormat::WHITE."Please type your password in chat to login.");
					$this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
				  $this->ip->save();
					$event->getPlayer()->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$event->getPlayer()->getName().TextFormat::GREEN."\nPlease login to play!");
					$this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), (15 * 20));
				}
			}else{
				$event->getPlayer()->sendMessage(TextFormat::WHITE."Please type your password in chat to login.");
				$this->getServer()->getScheduler()->scheduleDelayedTask(new Task2($this, $event->getPlayer()), (30 * 20));
				$this->ip->set($event->getPlayer()->getName(), $event->getPlayer()->getAddress());
				$this->ip->save();
				$event->getPlayer()->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$event->getPlayer()->getName().TextFormat::GREEN."\nPlease login to play!");
			}
		}else{
			$event->getPlayer()->sendMessage("Please type your password in chat to start register.");
		}
	}
	
	public function onPlayerMove(PlayerMoveEvent $event){
		$t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type your email into chat!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onQuit(PlayerQuitEvent $event){
		$t = $this->data->getAll();
		if($this->isLoggedIn($event->getPlayer())){
			$this->logout($event->getPlayer());
		}
		if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()])){
			unset($t[$event->getPlayer()->getName()]);
			$this->data->setAll($t);
			$this->data->save();
		}
	}
	
	//COMMANDS
	public function onCommand(CommandSender $issuer, Command $cmd, $label, array $args){
		switch($cmd->getName()){
			case "unregister":
			  if($issuer->hasPermission("loginsecurity.command.unregister")){
			  	if($issuer instanceof Player){
			  		$t = $this->data->getAll();
			  		unset($t[$issuer->getName()]);
			  		$this->data->setAll($t);
			  		$this->data->save();
			  		$issuer->sendMessage("You successfully unregistered!");
			  		$issuer->kick(TextFormat::GREEN."Re-join server to register.");
			  		return true;
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
			case "changepass":
			  $t = $this->data->getAll();
			  if($issuer->hasPermission("loginsecurity.command.changepass")){
			  	if($issuer instanceof Player){
			  		if(count($args) == 3){
			  			if(md5($args[0].$this->salt($args[0])) == $t[$issuer->getName()]["password"]){
			  				if($args[1] == $args[2]){
			  					$t[$issuer->getName()]["password"] = md5($args[1].$this->salt($args[1]));
			  					$this->data->setAll($t);
			  					$this->data->save();
			  					$issuer->sendMessage(TextFormat::GREEN."Password changed to ".TextFormat::AQUA.TextFormat::BOLD.$args[1]);
			  					return true;
			  				}else{
			  					$issuer->sendMessage(TextFormat::RED."Confirm password INCORRECT");
			  					return true;
			  				}
			  			}else{
			  				$issuer->sendMessage(TextFormat::RED."Old password INCORRECT!");
			  				return true;
			  			}
			  		}else{
			  			return false;
			  		}
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
			case "email":
			  if($issuer->hasPermission("loginsecurity.command.email")){
			  	if($issuer instanceof Player){
			  		if(isset($args[0])){
			  			if(strpos("@", $args[0])){
			  				 $t = $this->data->getAll();
			  		     $t[$issuer->getName()]["email"] = $args[0];
			  		     $this->data->setAll($t);
			  		     $this->data->save();
			  		     $issuer->sendMessage("§aEmail added successfully!\n§dAddress: §b".$args[0]);
			  		     return true;
			  			}else{
			  				$issuer->sendMessage("§cPlease enter a valid mail address!");
			  				return true;
			  			}
			  		}else{
			  			return false;
			  		}
			  	}else{
			  		$issuer->sendMessage("Please run this command in-game!");
			  		return true;
			  	}
			  }else{
			  	 $issuer->sendMessage("You have no permission for this!");
			  	 return true;
			  }
			break;
		}
	}
	
	public function onDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player && !$this->isLoggedIn($event->getEntity())){
			$event->setCancelled(true);
		}
	}
	
	public function onBlockBreak(BlockBreakEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event->getPlayer())){
			if($this->isRegistered($event->getPlayer())){
				$event->setCancelled(true);
			}else if(isset($t[$event->getPlayer()->getName()]["password"]) && !isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event->getPlayer()) && isset($t[$event->getPlayer()->getName()]["confirm"])){
				$event->getPlayer()->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event->getPlayer()->getName()])){
				$event->getPlayer()->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function onPickupItem(InventoryPickupItemEvent $event){
		 $t = $this->data->getAll();
		if(!$this->isLoggedIn($event-> getInventory()->getHolder() )){
			if($this->isRegistered($event-> getInventory()->getHolder() )){
				$event->setCancelled(true);
			}else if(isset($t[$event-> getInventory()->getHolder() ->getName()]["password"]) && !isset($t[$event-> getInventory()->getHolder() ->getName()]["confirm"])){
				$event-> getInventory()->getHolder() ->sendMessage("Type your password again to confirm!");
				$event->setCancelled(true);
			}else if(!$this->isRegistered($event-> getInventory()->getHolder() ) && isset($t[$event-> getInventory()->getHolder() ->getName()]["confirm"])){
				$event-> getInventory()->getHolder() ->sendMessage("Please type yes/no into chat!");
				$event->setCancelled(true);
			}else if(!isset($t[$event-> getInventory()->getHolder() ->getName()])){
				$event-> getInventory()->getHolder() ->sendMessage("Please type your new password into chat to register.");
				$event->setCancelled(true);
			}
		}
	}
	
	public function getLoggedIn(){
		return $this->login;
	}
	
}

namespace LoginSecurity;

use pocketmine\event\plugin\PluginEvent;
use LoginSecurity\LoginSecurity;

abstract class BaseEvent extends PluginEvent{
	
	public function __construct(LoginSecurity $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
}

namespace LoginSecurity;

use pocketmine\Player;
use pocketmine\event\Cancellable;

use LoginSecurity\LoginSecurity;
use LoginSecurity\BaseEvent;

class PlayerLoginEvent extends BaseEvent implements Cancellable{
	const PASSWORD = 0;
	const IP = 1;
	
	public function __construct(LoginSecurity $plugin, Player $player, $method){
		$this->player = $player;
		$this->method = $method;
		parent::__construct($plugin);
	}
	
	public function getPlayer(){
		return $this->player;
	}
	
	public function getMethod(){
		return $this->method;
	}
}

namespace LoginSecurity;

use pocketmine\Player;

use LoginSecurity\LoginSecurity;
use LoginSecurity\BaseEvent;

class PlayerLogoutEvent extends BaseEvent{
	
	public function __construct(LoginSecurity $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function getPlayer(){
		return $this->player;
	}
}


namespace LoginSecurity;

use pocketmine\scheduler\PluginTask;
use LoginSecurity\LoginSecurity;
use pocketmine\utils\TextFormat;

class Task extends PluginTask{
	public $plugin;
	
	public function __construct(LoginSecurity $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
		foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
			if($this->plugin->isRegistered($p) && !$this->plugin->isLoggedIn($p)){
				$p->sendMessage("Please type your password in chat to login.");
				$p->sendPopup(TextFormat::GOLD."Welcome ".TextFormat::AQUA.$p->getName().TextFormat::GREEN."\nPlease login to play!");
			}
		}
	}
}

namespace LoginSecurity;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use LoginSecurity\LoginSecurity;

class Task2 extends PluginTask{
	public $plugin;
	
	public function __construct(LoginSecurity $plugin, $player){
		$this->plugin = $plugin;
		$this->player = $player;
		parent::__construct($plugin);
	}
	
	public function onRun($tick){
			if(!$this->plugin->isLoggedIn($this->player) || !$this->plugin->isRegistered($this->player)){
				$this->player->kick(TextFormat::RED."Session expired!");
				$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
			}
	}
}
?>
