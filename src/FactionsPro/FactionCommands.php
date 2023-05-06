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
                    $sender->sendMessage($this->plugin->formatMessage("Digite /g help Ou /guilda help para listar os comandos"));
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
                            $player->sendMessage($this->plugin->formatMessage("You were demoted to member of $factionName!", true));
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
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c1§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda about §l§b»§r §aMostra qualquer Informacão relacionada a guilda!\n§l§c»§r §e/guilda accept §l§b»§r §aAceita Convite De Guilda!\n§l§c»§r §e/guilda create <nome> §l§b»§r §aCria a tua Própria Guilda!\n§l§c»§r §e/guilda del §l§b»§r §aDeleta a Tua Guilda!\n§l§c»§r §e/guilda demote <jogadorr> §l§b»§r §aRebaixar os Co-Lider para Rank Membro!\n§l§c»§r §e/guilda deny §l§b»§r §aRecusar Convite da Guilda!");
                            return true;
                        }
                        if ($args[1] == 2) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c2§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda ajuda <pagina> §l§b»§r §aMostra os Comandos de Guilda!\n§l§c»§r §e/guilda info §l§b»§r §aMostra os Dados da Tua Guilda!\n§l§c»§r §e/guilds info <guilda> §l§b»§r §aMostra os Dados de Outra Guilda!\n§l§c»§r §e/guilda invite <player> §l§b»§r §aConvida um Jogador para a Guilda!\n§l§c»§r §e/guilda kick <player> §l§b»§r §aRemove um Membro da sua Guilda!\n§l§c»§r §e/guilda leader <player> §l§b»§r §aDê a um Membro sua posição de Lider de Guilda!\n§l§c»§r §e/guilda leave §l§b»§r §aSaia da Sua Guilda!");
                            return true;
                        }
                        if ($args[1] == 3) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dInformacões §f[§c3§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilda members - {Members + Statuses} §l§b»§r §aMostra os Membros da guilda!\n§l§c»§r §e/guilda assistants - {Assistants + Statuses} §l§b»§r §aMostra os Co-Lider da Guilda!\n§l§c»§r §e/guilda ourleaders - {Leader + Status} §l§b»§r §aMostra o Lider de Guilda!\n§l§c»§r §e/guilda allies §l§b»§r §aMostra Seus Aliados!\n§l§c»§r §e/guilda claim\n§l§c»§r §e/guilda unclaim\n§l§c»§r §e/guilda pos\n§l§c»§r §e/guilda overclaim\n/guilda say <mensagen>");
                            return true;
                        }
                        if ($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c4§f/§f§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds desc §l§b»§r §aUpdate The Guilds Description!\n§l§c»§r §e/guilds promote <player> §l§b»§r §aPromote A Members To Assistants!\n§l§c»§r §e/guilds allywith <guilds> §l§b»§r §aRequest An Alliance With A Guilds!\n§l§c»§r §e/guilds breakalliancewith <guilds> §l§b»§r §aBreak The Alliance Contract With A Guilds!\n§l§c»§r §e/guilds allyok §l§b»§r §aAccept An Alliance Request!\n§l§c»§r §e/guilds allyno §l§b»§r §aDenied An Alliance Request!\n§l§c»§r §e/guilds allies <guilds> §l§b»§r §aShows A Specific Guilds Alliance!");
                            return true;
                        }
                        if ($args[1] == 5) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c5§f/§f§c5§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds membersof <guilds> §l§b»§r §aShows The List Of A Specific Guilds Members!\n§l§c»§r §e/guilds assistantsof <guilds> §l§b»§r §aShows The List Of A Specific Guilds Assistants!\n§l§c»§r §e/guilds leadersof <guilds> §l§b»§r §aShows The Guilds Leaders List!\n§l§c»§r §e/guilds search <player> §l§b»§r §aSearch The Player Guilds!\n§l§c»§r §e/guilds leaderboards §l§b»§r §aShows Top Ranking Guilds!\n§l§c»§r §e/guilds setef §l§b»§r §aSet Effects For Guilds!\n§l§c»§r §e/guilds efinfo §l§b»§r §aShows Effects Information!\n§l§c»§r §e/guilds getef §l§b»§r §aGets The Effects You Have Setted!\n§l§c»§r §e/guilds sethome\n§l§c»§r §e/guilds unsethome\n§l§c»§r §e/guilds home");
                            return true;

                        }
                        if ($args[1] == 6){
                            $sender->isOP();
                            $sender->sendMessage(Textformat::GOLD. "Special OP Commands\n/guilds forcedelete <guilds>\n/guilds addgp\n/guilds forceunclaim <guilds>\n/guilds addmoney");
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
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to claim"));
							return true;
						}
                        if($this->plugin->prefs->get("OfficersCanClaim")){
                            if(!$this->plugin->isLeader($player) || !$this->plugin->isOfficer($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cOnly Leaders and Officers can claim"));
							    return true;
						    }
                        } else {
                            if(!$this->plugin->isLeader($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							    return true;
						    }
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("You can only claim in Guilds Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }
                        
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("§aYour guilds has already claimed this area."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§bYou need §e$needed_players §bmore players to claim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Your guilds doesn't have enough power to claim"));
							$sender->sendMessage($this->plugin->formatMessage("§e"."$needed_power" . " §3power is required. Your guilds only has §a$faction_power §3power."));
                            return true;
                        }
						elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $claim)){
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                            
							return true;
						}
                        
						$sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("§aLand successfully claimed for §6$$claim§a.", true));
					}
					else {
						// $r is an error code
						switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to Claim! Need §6$$claim"));
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
                            $sender->sendMessage($this->plugin->formatMessage("§bThis area is unclaimed. Use §e/guilds claim §bto claim", true));
							return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§3This plot is claimed by §a$fac §3with §e$power §3power"));
                    }
                    
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§3You need §e$needed_players §3more players to overclaim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Your guilds does not have enough power to claim! Get power by killing players!"));
							$sender->sendMessage($this->plugin->formatMessage("§e$needed_power" . "§3 power is required but your guilds only has §e$faction_power §3power"));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...", true));
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
                                    $sender->sendMessage($this->plugin->formatMessage("§aYour guilds has already claimed this land"));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("§3Your power level is too low to over claim §b$faction_victim"));
                                        return true;
                                    } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $oclaim))
									   {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
					$sender->sendMessage($this->plugin->formatMessage("§aYour guilds has successfully overclaimed the land of §b$faction_victim §afor §6$$oclaim", true));
                                        return true;
                                    }
									else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to Overclaim! Need §6$oclaim"));
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
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not in claimed land"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
                        }
                        
					}
                    
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aLand successfully unclaimed", true));
					}
					/////////////////////////////// SETHOME ///////////////////////////////
					
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to set home"));
							return true;
						}
                        
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($player));
                        $needed_power = $this->plugin->prefs->get("PowerNeededToSetOrUpdateAHome");
                        if($faction_power < $needed_power){
                            $sender->sendMessage($this->plugin->formatMessage("§3Your guilds doesn't have enough power set a home. Get power by killing players!"));
                            $sender->sendMessage($this->plugin->formatMessage("§e $needed_power §3power is required to set a home. Your guilds has §e$faction_power §3power."));
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
						$sender->sendMessage($this->plugin->formatMessage("Guilds home set for $home Coins", true));
                        }
						else {

						    switch($r){
							case EconomyAPI::RET_INVALID:

								$sender->sendMessage($this->plugin->formatMessage("Error! You Need $home Coins To Set A Home!"));
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
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to unset home"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aHome unset succeed", true));
					}
					
					/////////////////////////////// HOME ///////////////////////////////
						
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a guilds to do this."));
                            return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("§bTeleported to home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Guilds home has not been set"));
				        }
				    }
                    //TOP10 Leaderboards
                    if (strtolower($args[0]) == 'leaderboards') {
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    //force unclaim
                    if(strtolower($args[0] == "forceunclaim")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("/guilds forceunclaim <guilds>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThe requested guilds does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("§bLand of §a$args[1]§b unclaimed"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        
                    }
                    //forcedelete
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds forcedelete <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("Unwanted guilds was successfully deleted and their guilds plot was unclaimed!", true));
                    }
                    //Add Guilds Points
                    if (strtolower($args[0]) == 'addgp') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds addgp <guilds> <GuildsPoints>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] GuildsPoints to $args[1]", true));
                    }
                    if (strtolower($args[0]) == 'addmoney') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds addmoney <guilds> <GuildsMoneys>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->addFactionMoney($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] GuildsMoneys to $args[1]", true));
                    }
                    //Stalk A player
                    if (strtolower($args[0]) == 'search') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds search <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The selected player is not in a guilds  or doesn't exist."));
                            $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected player is ABSOLUTELY EXACT."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-", true));
                    }

                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "desc") {
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
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
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any guilds"));
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
                            $sender->sendMessage($this->plugin->formatMessage("You successfully joined $faction", true));
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the guilds", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
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
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any guilds"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Invite declined", true));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invitation"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
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
                                $sender->sendMessage($this->plugin->formatMessage("Guilds successfully disbanded.", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a guilds!"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($player) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $faction = $this->plugin->getPlayerFaction($player);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("You successfully left $faction", true));

                            $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You must delete the guilds or give leadership to someone else first"));
                        }
                    }

                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "members")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds membersof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "assistants")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "assistantsof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds assistantsof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "ourleaders")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leadersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds leadersof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to send faction messages"));
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
                    if (strtolower($args[0] == "enemywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds enemywith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not enemy with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is already enemied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested guilds is offline"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("You are now enemies with $args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac has declared your guilds as an enemy", true));
                    }
                    if (strtolower($args[0] == "allywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds allywith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not ally with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is already allied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested guilds is offline"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds has the maximum amount of allies", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has the maximum amount of allies", false));
                            return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("You requested to ally with $args[1]!\nWait for the leader's response...", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac requested an alliance.\nType /guilds allyok to accept or /guilds allyno to deny.", true));
                    }
                    if (strtolower($args[0] == "breakalliancewith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds breakalliancewith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not break alliance with itself"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is not allied with $args[1]"));
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
                        $sender->sendMessage($this->plugin->formatMessage("Your faction $fac is no longer allied with $args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("The leader of $fac broke the alliance with your guilds $args[1]", false));
                        }

                    }
                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has not been requested to ally with any guilds"));
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
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has successfully allied with $requested_fac", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has accepted the alliance!", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has not been requested to ally with any guilds"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has successfully declined the alliance request.", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
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
                            $sender->sendMessage($this->plugin->formatMessage("/guilds setef <fast:str:jump:haste:res:life>"));
							return true;
                        }
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
							return true;
                        }
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
							return true;
						}
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        $strengthperkill = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                        if($factionstrength < $lvl[0]){
                            $needed_power = $lvl[0];
							$sender->sendMessage($this->plugin->formatMessage("Your guilds doesn't have enough GuildsPoints to select an effect."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power GuildsPoints is required but your guilds has only $factionstrength GuildsPoints."));
							return true;
                        }
                        if(!(in_array(strtolower($args[1]),array("fast","str","jump","haste","res","life")))){
                            $sender->sendMessage($this->plugin->formatMessage("The '$args[1]' mode is not available."));
                            $sender->sendMessage($this->plugin->formatMessage("/guilds setef <fast:str:jump:haste:res:life>"));
							return true;
                        }
                        $this->plugin->addEffectTo($this->plugin->getPlayerFaction($player),strtolower($args[1]));
                        $this->plugin->updateTagsAndEffectsOf($factionname);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully updated your guilds's effect.",true));
                        return true;
                    }
                    if(strtolower($args[0]) == 'efinfo'){
                        for($i=0;$i<4;$i++){
                            $s = $i + 1;
                            $sender->sendMessage($this->plugin->formatMessage("Lvl $s effects unlock at $lvl[$i] GuildsPoints",true));
                        }
                        return true;
                    }
                    if(strtolower($args[0]) == 'getef'){
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
							return true;
                        }
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        if($this->plugin->getEffectOf($factionname) == "none"){
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds's effect is not set. Set it by typing /guilds setef <effect>"));
                            return true;
                        }
                        $sender->removeAllEffects();
                        for($i=0;$i<4;$i++){
                            if($factionstrength >= $lvl[$i]){
                                $amp = $i;
                            }
                        }
                        switch($this->plugin->getEffectOf($factionname)){
                            case "fast":
                                $sender->addEffect(Effect::getEffect(1)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "str":
                                $sender->addEffect(Effect::getEffect(5)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "jump":
                                $sender->addEffect(Eddect::getEffect(8)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "haste":
                                $sender->addEffect(Effect::getEffect(3)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "res":
                                $sender->addEffect(Effect::getEffect(11)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "life":
                                $sender->addEffect(Effect::getEffect(21)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                        }  
                        $sender->sendMessage($this->plugin->formatMessage("Enjoy your effect!", true));
                        return true;
                        }







                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "§l§b»§r\n eThis Server Using A Guilds System.\n §eBe The Most Powerfull Guilds In This Server!\n §eCreate,Join,Destroy A Guilds!\n §eStart Now By Using This Commands! : /guilds help [page]\n§l§b« ");
                        $sender->sendMessage(TextFormat::GOLD . "\n\n§aDevelop By GamerXzavier.");
                    }
                    //Thanks To The original authors Tethered_
                    //Thank To The Supporter
                    //Big Thanks To NeuroBinds Project Corporation For Helping 64% Of The Code!
                }
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
        }
    }

}
