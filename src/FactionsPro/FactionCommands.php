<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;
use onebone\economyapi\EconomyAPI;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if ($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			$create = $this->plugin->prefs->get("CreateCost");
			$claim = $this->plugin->prefs->get("ClaimCost");
			$oclaim = $this->plugin->prefs->get("OverClaimCost");
			$allyr = $this->plugin->prefs->get("AllyCost");
			$allya = $this->plugin->prefs->get("AllyPrice");
			$home = $this->plugin->prefs->get("SetHomeCost");
            $player = $sender->getPlayer()->getName();
            if (strtolower($command->getName('guilda') or $command->getName('g'))) {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage("Digite /g ajuda Ou /guilda ajuda para listar os comandos"));
                    return true;
                }
                if (count($args == 2)) {

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda war <guilda name:tp>"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($player);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return;
                                }
                            }
                            $sender->sendMessage("Sua Guilda Precisa estar em Guerra para Usares este comando");
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("Apenas Numeros ou Letras Podem ser Usados"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("Deves estar em uma guilda para Usar esse comando"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Apenas o Lider da Guilda pode começar uma Guerra"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Sua Guilda Não é Inimiga de $args[1]"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($player);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("Uma Guerra Contra $factionName e $sFaction Começou!");
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("$sFaction Quer começar uma Guerra, '/guilda war $sFaction' Para começar!");
                                        $sender->sendMessage("Guerra de guilda requested");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("O Lider da Guilda Não Está Online no Momento.");
                            return true;
                        }
                    }

                    /////////////////////////////// CREATE ///////////////////////////////

                    if ($args[0] == "criar") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usagem: /guilda criar <nome da guilda>"));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Esse nome está Banido"));
                            return true;
                        }
                        if ($this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Já Existe"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage("Esse Nome é muito longo, Tente denovo o Maximo de Letras é ". $this->plugin->prefs->get("MaxFactionNameLength")));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("Deves Sair da Guilda Antes de realizar esse Comando"));
                            return true;
                        } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $create)) {
                            $factionName = $args[1];
							$player = strtolower($player);
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $player);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                            $this->plugin->updateTag($sender->getName());
                            $sender->sendMessage($this->plugin->formatMessage("Guilda Criada com Sucesso ! Digite '/guilda desc' agora.", true));
							$sender->sendMessage($this->plugin->formatMessage("Guilda criada com sucesso for §6$$create", true));
                            return true;
                        } else {
						
						switch($r){
							case EconomyAPI::RET_INVALID:
							
								$sender->sendMessage($this->plugin->formatMessage("Erro! Não Podes criar uma Guilda, Precisas ter $create Coins Para Criar a Guilda."));
								break;
							case EconomyAPI::RET_CANCELLED:
						
								$sender->sendMessage($this->plugin->formatMessage("ERRO!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("ERRO!"));
								break;
						}
					  }
                    }

                    /////////////////////////////// INVITE ///////////////////////////////

                    if ($args[0] == "invite") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda invite <player>"));
                            return true;
                        }
                        if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player))) {
                            $sender->sendMessage($this->plugin->formatMessage("Guilda está Cheia, Expulse alguns                       Membros para Liberar Vagas na Guilda"));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("o Jogador não está online"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($invited) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("o Jogador Já está Atualmente em uma Guilda"));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))) {
                                $sender->sendMessage($this->plugin->formatMessage("Apenas o Lider e os Co-Lideres podem Convidar Jogadores para a Guilda"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $player) {

                            $sender->sendMessage($this->plugin->formatMessage("Não Podes Convidar-te a ti mesmo"));
                            return true;
                        }

                        $factionName = $this->plugin->getPlayerFaction($player);
                        $invitedName = $invited->getName();
                        $rank = "Member";

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited", true));
                        $invited->sendMessage($this->plugin->formatMessage("Foste Convidado para a Guilda $factionName. Digita '/guilda accept' ou '/guilda deny' no Chat para Aceitar ou Declinar o Convite", true));
                    }

                    /////////////////////////////// LEADER ///////////////////////////////

                    if ($args[0] == "leader") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usagem: /guilda leader <jogador>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Estar em uma Guilda para Usar Este Comando"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Ser Lider da Guilda Para Usar esse Comando"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Primeiro convida o Jogador para a Guilda!"));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Jogador não Está Online"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {

                            $sender->sendMessage($this->plugin->formatMessage("Não Podes transferir o Rank de Lider para ti mesmo"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $player);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();


                        $sender->sendMessage($this->plugin->formatMessage("Já não és mais Lider", true));
                        $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("És Agora o Lider da Guilda $factionName!", true));
                        $this->plugin->updateTag($sender->getName());
                        $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    }

                    /////////////////////////////// PROMOTE ///////////////////////////////

                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda promote <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("Tens de estar em uma Guilda"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("O Jogador não está nessa Guilda!"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("Não Podes te promover a ti mesmo!"));
                            return true;
                        }

                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("O Jogador ja é Co-Lider"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("$args[1] Promovido a Co-Lider", true));

                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("Foste promovido a Co-lider da Guilda $factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// DEMOTE ///////////////////////////////

                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda demote <player>"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Tar em Uma Guilda para Isso"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Ser Lider para isso"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("O Jogador Não Está nessa Guilda!"));
                            return true;
                        }

                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("Não te Podes Rebaixar a ti mesmo!"));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("o Jogador ja é um Membro!"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("$args[1] Foi Demotado com Sucesso", true));
                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("Foste rebaixado a Membro da $factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// KICK ///////////////////////////////

                    if ($args[0] == "kick") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda kick <player>"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Tens de Estar em uma Guilda para Isso!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Tens de Ser Lider para Isso!"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("O Jogador não está na Guilda"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("Não te podes expulsar a ti mesmo!"));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("Expulsaste da Guilda o Jogador $args[1]", true));
                        $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage("Foste Expulso da Guilda $factionName", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// INFO ///////////////////////////////

                    if (strtolower($args[0]) == 'info') {
                        if (isset($args[1])) {
                            if (!(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
                                $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe"));
                                $sender->sendMessage($this->plugin->formatMessage("Tenha A Certeza Que o Nome está Corretamente Escrito"));
                                return true;
                            }
                            $faction = $args[1];
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $money = $this->plugin->getFactionMoney($faction);
                            $message = $array["message"];
                            $leader = $this->plugin->getLeader($faction);
                            $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformacões §l§b«" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuildas §8: " . TextFormat::GREEN . "§d$faction" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aLeader §8: " . TextFormat::YELLOW . "§d$leader" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPlayers §8: " . TextFormat::LIGHT_PURPLE . "§d$numPlayers" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPontosDeGuilda §8: " . TextFormat::RED . "§d$power" . " " . TextFormat::RESET);     
                $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aMoneyDaGuilda §8: " . TextFormat::RED . "§d$money" . " " . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aDescrição §8: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§d$message" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformacões §l§b«" . TextFormat::RESET);
                        } else {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("Precisas Estar em uma Guilda Para Isso!"));
                                return true;
                            }
                            $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $money = $this->plugin->getFactionMoney($faction);
                            $message = $array["message"];
                            $leader = $this->plugin->getLeader($faction);
                            $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformacões §l§b«" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuildas §8: " . TextFormat::GREEN . "§d$faction" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aLeader §8: " . TextFormat::YELLOW . "§d$leader" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPlayers §8: " . TextFormat::LIGHT_PURPLE . "§d$numPlayers" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPontosDeGuilda §8: " . TextFormat::RED . "§d$power" . " " . TextFormat::RESET);     
                $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aMoneyDeGuilda §8: " . TextFormat::RED . "§d$money" . " " . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aDescrição §8: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§d$message" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformacões §l§b«" . TextFormat::RESET);
                    return true;
                        }
                    }
/*Help Commands*/
                    if (strtolower($args[0]) == "ajuda") {
                        if (!isset($args[1]) || $args[1] == 1) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c1§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda about §l§b»§r §aMostra qualquer Informacão relacionada a guilda!\n§l§c»§r §e/guilda accept §l§b»§r §aAceita Convite De Guilda!\n§l§c»§r §e/guilda criar <nome> §l§b»§r §aCria a tua Própria Guilda!\n§l§c»§r §e/guilda del §l§b»§r §aDeleta a Tua Guilda!\n§l§c»§r §e/guilda demote <jogador> §l§b»§r §aRebaixar os Co-Lider para Rank Membro!\n§l§c»§r §e/guilda deny §l§b»§r §aRecusar Convite da Guilda!");
                            return true;
                        }
                        if ($args[1] == 2) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c2§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda ajuda <pagina> §l§b»§r §aMostra os Comandos de Guilda!\n§l§c»§r §e/guilda info §l§b»§r §aMostra os Dados da Tua Guilda!\n§l§c»§r §e/guilds info <guilda> §l§b»§r §aMostra os Dados de Outra Guilda!\n§l§c»§r §e/guilda invite <player> §l§b»§r §aConvida um Jogador para a Guilda!\n§l§c»§r §e/guilda kick <player> §l§b»§r §aRemove um Membro da sua Guilda!\n§l§c»§r §e/guilda leader <player> §l§b»§r §aDê a um Membro sua posição de Lider de Guilda!\n§l§c»§r §e/guilda leave §l§b»§r §aSaia da Sua Guilda!");
                            return true;
                        }
                        if ($args[1] == 3) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c3§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda members - {Membros + Status} §l§b»§r §aMostra os Membros da guilda!\n§l§c»§r §e/guilda assistants - {Co-Lideres + Status} §l§b»§r §aMostra os Co-Lider da Guilda!\n§l§c»§r §e/guilda ourleaders - {Leader + Status} §l§b»§r §aMostra o Lider de Guilda!\n§l§c»§r §e/guilda allies §l§b»§r §aMostra Seus Aliados!\n§l§c»§r §e/guilda claim\n§l§c»§r §e/guilda unclaim\n§l§c»§r §e/guilda pos\n§l§c»§r §e/guilda overclaim\n/guilda say <mensagen>");
                            return true;
                        }
                        if ($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c4§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda desc §l§b»§r §aAtualiza a Descrição da Guilda!\n§l§c»§r §e/guilda promote <player> §l§b»§r §aPromove Membro a Co-Lider!\n§l§c»§r §e/guilda ally <guilda> §l§b»§r §aPede uma Aliança a outra Guilda!\n§l§c»§r §e/guilda unally <guilda> §l§b»§r §aRetira essa Guilda de seus aliados!\n§l§c»§r §e/guilda allyok §l§b»§r §aAceitar Pedido de Aliança!\n§l§c»§r §e/guilda allyno §l§b»§r §aPara Recusar pedido de Aliança!\n§l§c»§r §e/guilda allies <guilda> §l§b»§r §aMostra quais Guildas são Aliadas!");
                            return true;
                        }
                        if ($args[1] == 5) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c5§f/§f§c5§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda membersof <guilda> §l§b»§r §aMostra a Lista de membros na Guilda !\n§l§c»§r §e/guilda assistantsof <guilda> §l§b»§r §aMostra os Co-Lideres da Guilda!\n§l§c»§r §e/guilda leadersof <guilda> §l§b»§r §aMostra o Lider da guilda!\n§l§c»§r §e/guilda search <player> §l§b»§r §aMostra em qual guilda está o Jogador!\n§l§c»§r §e/guilda top §l§b»§r §aMostra o Ranking de Guildas \n§l§c»§r §e/guilda efinfo §l§b»§r §aFuturamente \n§l§c»§r §e/guilds sethome\n§l§c»§r §e/guilda unsethome\n§l§c»§r §e/guilda home");
                            return true;

                        }
                        if ($args[1] == 6){
                            $sender->isOP();
                            $sender->sendMessage(Textformat::GOLD. "Comandos Apenas para Staff\n/guilda forcedelete <guilda>\n/guilda addgp\n/guilda forceunclaim <guilda>\n/guilda addmoney");
                            return true;
                        }else{
                            $sender->sendMessage("ERR :P");
                            return true;
                        }
                    }
                }
                if (count($args == 1)) {



					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {//
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma Guilda para Usar"));
							return true;
						}
                        if($this->plugin->prefs->get("OfficersCanClaim")){
                            if(!$this->plugin->isLeader($player) || !$this->plugin->isOfficer($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cApenas Lider e Co-Lideres Podem claimar"));
							    return true;
						    }
                        } else {
                            if(!$this->plugin->isLeader($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cPrecisas ser Lider para Claimar"));
							    return true;
						    }
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser Lider para Usar Isso."));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("Apenas podes Claimar no Mundo Factions: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }
                        
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("§aTua Guilda já Claimou essa Área."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§bPrecisam §e$needed_players §bmais Jogadores para claimar"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Tua Guilda Não tem Poder suficiente para Claimar"));
							$sender->sendMessage($this->plugin->formatMessage("§e"."$needed_power" . " §3poder é requirido!. a tua guilda tem apenas §a$faction_power §3poder."));
                            return true;
                        }
						elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $claim)){
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                            
							return true;
						}
                        
						$sender->sendMessage($this->plugin->formatMessage("§bPegando Coordenadas...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("§aTerra claimada com Sucesso §6$$claim§a.", true));
					}
					else {
						// $r is an error code
						switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3Não tem dinheiro suficiente para claimar! Precisa §6$$claim"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
						}
					}
					}
                    //position
                    if(strtolower($args[0]) == 'pos'){
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        $fac = $this->plugin->factionFromPoint($x,$z);
                        $power = $this->plugin->getFactionPower($fac);
                        if(!$this->plugin->isInPlot($sender)){
                            $sender->sendMessage($this->plugin->formatMessage("§bThis area is unclaimed. Use §e/guilda claim §bto claim", true));
							return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§3Essa Área está claimada pela Guilda §a$fac §3com §e$power §3power"));
                    }
                    
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma Guilda para Usar esse Comando!"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas ser Lider para Usares Isso"));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§3Precisas de §e$needed_players §3membros para overclaim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Tua Guilda não tem poder suficiente para Isso! Ganhe Poder matando outros Jogadores!!"));
							$sender->sendMessage($this->plugin->formatMessage("§e$needed_power" . "§3 poder é requirido mas a tua guilda tem apenas §e$faction_power §3poder"));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("§bPegando as Coordenadas...", true));
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if($this->plugin->prefs->get("EnableOverClaim")){
                            if($this->plugin->isInPlot($sender)){
                                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if($this->plugin->inOwnPlot($sender)){
                                    $sender->sendMessage($this->plugin->formatMessage("§aTua Guilda já Claimou essa Área"));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("§3teu poder é demasiado baixo para overclaim §b$faction_victim"));
                                        return true;
                                    } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $oclaim))
									   {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
					$sender->sendMessage($this->plugin->formatMessage("§aTua Guilda fez Overclaim com sucesso na Área da Guilda §b$faction_victim §afor §6$$oclaim", true));
                                        return true;
                                    }
									else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3Não tem dinheiro suficiente para claimar! Need §6$oclaim"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
						}
					}
                                    
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cTu Não estás em uma Área claimada"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cPermissões Insuficientes!"));
                            return true;
                        }
                        
					}
                    
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma Guilda para Isso"));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas ser Lider para isso"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aÁrea abandonado com sucesso", true));
					}
					/////////////////////////////// SETHOME ///////////////////////////////
					
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma guilda para Isso"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas ser o Lider para Setar Home"));
							return true;
						}
                        
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($player));
                        $needed_power = $this->plugin->prefs->get("PowerNeededToSetOrUpdateAHome");
                        if($faction_power < $needed_power){
                            $sender->sendMessage($this->plugin->formatMessage("§3Tua guilda Não tem poder Suficiente para setar Home. Ganhe poder matando Jogadores!!"));
                            $sender->sendMessage($this->plugin->formatMessage("§e $needed_power §3poder é requerido Para setar Home. Tua guilda tem §e$faction_power §3poder."));
							return true;
                        }
						elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $home)){
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("Home de guilda setada por $home Coins", true));
                        }
						else {

						    switch($r){
							case EconomyAPI::RET_INVALID:

								$sender->sendMessage($this->plugin->formatMessage("Error! Tu Precisas $home Coins Para Setar Home!"));
								break;
							case EconomyAPI::RET_CANCELLED:
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("Error!"));
								break;
						}
					}
					}
					
					/////////////////////////////// UNSETHOME ///////////////////////////////
						
					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma Guilda para Isso"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas ser Lider para retirar a Home"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aHome Retirada com Sucesso", true));
					}
					
					/////////////////////////////// HOME ///////////////////////////////
						
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPrecisas estar em uma Guilda para Isso"));
                            return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("§bTeleportado para a Home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("A Home de Guilda ainda não foi Setada"));
				        }
				    }
                    //TOP10 Leaderboards
                    if (strtolower($args[0]) == 'top') {
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    //force unclaim
                    if(strtolower($args[0] == "forceunclaim")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("/guilda forceunclaim <guilda>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cA Guilda não existe"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§cPermissões Insuficientes"));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("§bTerra da Guilda §a$args[1]§b sem claim"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        
                    }
                    //forcedelete
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda forcedelete <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda foi deletada com sucesso."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser Staff para Isso."));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("A guilda foi deletada com sucesso !", true));
                    }
                    //Add Guilds Points
                    if (strtolower($args[0]) == 'addgp') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda addgp <guilda> <PontosDeGuilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Essa Guilda Não Existe."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser Staff para Isso."));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Adicionados com Sucesso $args[2] PontosDeGuilda para $args[1]", true));
                    }
                    if (strtolower($args[0]) == 'addmoney') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda addmoney <guilda> <MoneyDeGuilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda requisitada não Existe."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Ser Staff para Isso."));
                            return true;
                        }
                        $this->plugin->addFactionMoney($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Adicionado com Sucesso $args[2] MoneyDeGuilda to $args[1]", true));
                    }
                    //Stalk A player
                    if (strtolower($args[0]) == 'search') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda search <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("o Player Citado não Existe ou a guilda Não Existe."));
                            $sender->sendMessage($this->plugin->formatMessage("Tenha certeza de digitar o Nickname Corretamente."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-", true));
                    }

                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "desc") {
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Tens de estar em uma Guilda para Isso!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser Lider para Isso"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Digite a Descrição no chat, não será visivel para os outros players.", true));
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                    }

                    /////////////////////////////// ACCEPT ///////////////////////////////

                    if (strtolower($args[0]) == "accept") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Ainda não foste convidado para nenhuma guilda"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $faction = $array["faction"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($player));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Entraste na Guilda $faction", true));
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player entrou na guilda", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Convite Expirou"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
                        }
                    }

                    /////////////////////////////// DENY ///////////////////////////////

                    if (strtolower($args[0]) == "deny") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Nenhuma guilda te Convidou"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Convite rejeitado", true));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player recusou seu convite"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Convite Expirou"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
                        }
                    }

                    /////////////////////////////// DELETE ///////////////////////////////

                    if (strtolower($args[0]) == "del") {
                        if ($this->plugin->isInFaction($player) == true) {
                            if ($this->plugin->isLeader($player)) {
                                $faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                                $sender->sendMessage($this->plugin->formatMessage("Guilda deletada com Sucesso.", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("Não és o lider da Guilda!"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Tu Não estás em uma Guilda!"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($player) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $faction = $this->plugin->getPlayerFaction($player);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("Saíste com sucesso da guilda $faction", true));

                            $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Precisar dar Liderança da Guilda a Alguem ou deletar a Guilda"));
                        }
                    }

                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "members")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma guilda para Isso"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda membersof <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "assistants")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "assistantsof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda assistantsof <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não existe"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "ourleaders")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leadersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda leadersof <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe!"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma guilda para usar o /guilda say"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "Member";
                        $f = $this->plugin->getPlayerFaction($player);

                        if ($this->plugin->isOfficer($player)) {
                            $rank = "Assistant";
                        } else if ($this->plugin->isLeader($player)) {
                            $rank = "Leader";
                        }
                        $message = " ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "" . TextFormat::AQUA . " <$rank> " . TextFormat::GREEN . "<$player> " . "-> " .TextFormat::ITALIC . TextFormat::DARK_AQUA . $message .  TextFormat::RESET);
  
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemy")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda enemy <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("A Tua guilda Não se pode declarar como inimiga"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Tua guilda ja é Inimiga da Guilda $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("O Lider dessa Guilda está Offline"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("És agora inimigo com $args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("o Lider da $fac declarou a tua guilda como um Inimigo enemy", true));
                    }
                    if (strtolower($args[0] == "ally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda ally <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não Existe"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda Não se pode Aliar a si mesma"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Tua guilda está aliada com $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("O Lider da guilda está Offline"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Vagas para Aliados foram preenchidas!", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Sua guilda tem o maximo de Aliados", false));
                            return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("Pedido de Aliança enviado para $args[1]!\nEspere pela resposta do lider...", true));
                        $leader->sendMessage($this->plugin->formatMessage("o Lider da Guilda $fac pediu uma Aliança\nDigite /guilda allyok para aceitar ou /guilda allyno Para recusar.", true));
                    }
                    if (strtolower($args[0] == "endally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilda endally <guilda>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("A Guilda não Existe"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Nao Podes quebrar aliança contigo mesmo"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Sua guilda Não é aliada da Guilda $args[1]"));
                            return true;
                        }

                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac, $args[1]);
                        $this->plugin->deleteAllies($args[1], $fac);
                        $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("A Tua Guilda $fac Não é mais aliada com $args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("o Lider da guilda $fac quebrou a aliança com sua guilda $args[1]", false));
                        }

                    }
                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("A Guilda não Existe"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o lider para Isso"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Sua guilda ainda não recebeu nenhum convite de Aliança "));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->setAllies($requested_fac, $sender_fac);
                            $this->plugin->setAllies($sender_fac, $requested_fac);
                            $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
                            $sender->sendMessage($this->plugin->formatMessage("Tua guilda convidou para aliança com  $requested_fac", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player da $sender_fac aceitou o seu pedido de Aliança!", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Convite Expirou"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Sua Guilda Não Possui nenhun convite de Aliança"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Tua Guilda rejeitou com sucesso o convite de Aliança.", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player da Guilda $sender_fac Rejeitou a Aliança!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Convite Expirou"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }


///////////////////////////////////////
                    ///////////////EFFFECTS?//////////////////////////
                    $amp = 0;
                    $strengthperkill = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    $lvl = array($strengthperkill*100,$strengthperkill*500,$strengthperkill*1000,$strengthperkill*5000);
                    if(strtolower($args[0]) == 'setef'){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("/guilda setef<speed:str:pulo:haste:res:vida>"));
							return true;
                        }
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("Precisas Estar em uma Guilda!"));
							return true;
                        }
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Precisas ser o Lider para Isso."));
							return true;
						}
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        $strengthperkill = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                        if($factionstrength < $lvl[0]){
                            $needed_power = $lvl[0];
							$sender->sendMessage($this->plugin->formatMessage("Sua Guilda Não Contem Pontos de Guilda Suficientes."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power Pontos de Guilda São requiridos mas sua guilda tem apenas $factionstrength Points De Guilda."));
							return true;
                        }
                        if(!(in_array(strtolower($args[1]),array("speed","str","pulo","haste","res","vida")))){
                            $sender->sendMessage($this->plugin->formatMessage("O Efeito '$args[1]' não Está Disponivel."));
                            $sender->sendMessage($this->plugin->formatMessage("/guilda setef <speed:str:pulo:haste:res:vida>"));
							return true;
                        }
                        $this->plugin->addEffectTo($this->plugin->getPlayerFaction($player),strtolower($args[1]));
                        $this->plugin->updateTagsAndEffectsOf($factionname);
                        $sender->sendMessage($this->plugin->formatMessage("Efeitos De Guilda aplicados com Sucesso.",true));
                        return true;
                    }
                    if(strtolower($args[0]) == 'efinfo'){
                        for($i=0;$i<4;$i++){
                            $s = $i + 1;
                            $sender->sendMessage($this->plugin->formatMessage("Efeito de Guilda Nivel $s desbloqueia no Nivel $lvl[$i] GP",true));
                        }
                        return true;
                    }
                    if(strtolower($args[0]) == 'getef'){
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("Precisas estar em uma Guilda para Isso!"));
							return true;
                        }
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        if($this->plugin->getEffectOf($factionname) == "none"){
                            $sender->sendMessage($this->plugin->formatMessage("Seu efeito de Guilda Não Está ativo. Ative o Efeito digitando /guilda setef <efeito>"));
                            return true;
                        }
                        $sender->removeAllEffects();
                        for($i=0;$i<4;$i++){
                            if($factionstrength >= $lvl[$i]){
                                $amp = $i;
                            }
                        }
                        switch($this->plugin->getEffectOf($factionname)){
                            case "speed":
                                $sender->addEffect(Effect::getEffect(1)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "str":
                                $sender->addEffect(Effect::getEffect(5)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "pulo":
                                $sender->addEffect(Eddect::getEffect(8)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "haste":
                                $sender->addEffect(Effect::getEffect(3)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "res":
                                $sender->addEffect(Effect::getEffect(11)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "vida":
                                $sender->addEffect(Effect::getEffect(21)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                        }  
                        $sender->sendMessage($this->plugin->formatMessage("Efeitos Setados!", true));
                        return true;
                        }







                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "§l§b»§r\n FacMythical.\n §eBe O Mais Inovador Servidor de Factions da 0.15.0!\n §eEntre, saia, invada e domine!\n §eComeçe Agora usando! : /guilda ajuda [pagina]\n§l§b« ");
                        $sender->sendMessage(TextFormat::GOLD . "\n\n§aINOVAÇÃO SEMPRE.");
                    }
                    //Obrigado Por Jogarem
                    //Rumo ao Topo
                    //Metas, Pegar 50 Players, Inovar a Versão 0.15.10 do Mcpe
                }
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Por favor use o Comando no Jogo"));
        }
    }

}
