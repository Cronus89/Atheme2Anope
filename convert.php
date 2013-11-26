<?php
/* Atheme to Anope Database Convertor
 * Created by Thomas Edwards (C) 2013
 * Bug Fixes and Advice from Cronus and Adam
 * Tested by Myself and Cronus
 * Created for Anope IRC Services
 * 
 * Created around Atheme 7.0.2
 * and Anope 1.9.9
 *
 * Contact: thomas.edwards@ilkotech.co.uk
 *
 * Usage:
 *	Place in folder with your Atheme Database.
 *	Name the database atheme.db then run
 *	'php convert.php' to produce an Anope Database
 *	named anope.db, New passwords will be viewable
 *	in anope_email.txt
 *
 * Requires: PHP5
 */

$ircd = 'inspircd';
echo "Atheme (7.0.2) to Anope Database Convertor..\n";
echo "Version 0.1, All passwords will be reset.\n\n";
sleep(1);
$start = time();
echo "Loading Atheme Database. Make sure it is named 'atheme.db'!\n";
if (!file_exists("atheme.db")) {
	die("\tWhoopsie!\nI couldn't locate your atheme database. Is it in this directory and named 'atheme.db'?\n");
}
echo "Found the atheme database!\n\tLoading DB. This could take a few seconds!\n";
$atheme_db = explode("\n",file_get_contents("atheme.db"));
// Skim over the database. See if its worth converting!
$objects = 0;
foreach ($atheme_db as $line) {
	if ($line != "") {
		$dat = explode(chr(32),$line);
		if (preg_match("/(MU|MDU|MC|MN|CA|MDC|BOT|ME)/i",$dat[0])) {
			$objects++;
		}
	}
}
if ($objects == 0) {
	die("There are zero recognised database objects!\n");
}
echo "Found {$objects} recognised data objects!\n";

// Functions for certain things.
function gen($len = 10) {
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$pass = "";
	for ($i = 0; $i < $len; $i++) {
		$str = str_split($chars);
		$pass .= $str[array_rand($str)];
	}
	return $pass;
}

echo "Loaded 'atheme.db' with ".count($atheme_db)." lines.\n";

$errors = array();

// Lets convert.
echo "Reading Database...\n";

// Init some variables.
$bots = array();
$chans = array();
$nicks = array();
$access = array();
$memos = array();
$alias = array();
$founders = array();

$debug = 0;

// Lets read the data.
foreach ($atheme_db as $line) {
	if ($line != "") {
		$data = explode(chr(32),$line);
		if (preg_match("/(MU|MDU|MC|MN|CA|MDC|BOT|DBV|ME)/i",$data[0])) {
			if ($data[0] == "DBV") {
				echo "Atheme Database v{$data[1]}\n";
				$aver = $data[1];
			} else if ($data[0] == "MU") {
				// User Registration
				$udat = array();
				$udat['nick'] = $data[2]; // Their primary nickname.
				$udat['pass'] = $data[3]; // Password.
				$udat['email'] = $data[4]; // Email
				$udat['stamp_1'] = $data[5]; // Registration Stamp??
				$udat['stamp_2'] = $data[6]; // Seen Stamp??
				$nicks[$data[2]] = $udat;
			} else if ($data[0] == "MDU") {
				// Some form of host.
				$host_info = explode(":",$data[2]);
				if (isset($host_info[1]) && $host_info[1] == "private") {
					if (isset($host_info[2])) {
						if ($host_info[2] == "vhost") {
							$nicks[$data[1]]['last_host'] = $data[3];
						} else if ($host_info[2] == "actual") {
							$nicks[$data[1]]['access'] = $data[3];
						}
					} else if ($host_info[1] == "usercloak") {
						$nicks[$data[1]]['vhost'] = $data[3];
					}
				}
			} else if ($data[0] == "MN") {
				// Nick Alias.
				// Possible syntax: MN CORENICK ALIASNICK CORESTAMP ALIASSTAMP
				$nicks[$data[1]]['last_seen'] = $data[4];
				$adat = array();
				$adat['owner'] = $data[1];
				$adat['alias'] = $data[2];
				$adat['reg_stamp'] = $data[3];
				$adat['seen_stamp'] = $data[4];
				$alias[] = $adat;
				
			} else if ($data[0] == "MC") {
				// Channel Create.
				$cdat = array();
				$cdat['name'] = $data[1];
				$cdat['create'] = $data[2]; // Create Stamp?
				$cdat['used'] = $data[3];
				
				$cdat['mlock_on']		= (isset($data[5]) && is_numeric($data[5]))?	$data[5] : 0;
				$cdat['mlock_off']		= (isset($data[6]) && is_numeric($data[6]))?	$data[6] : 0;
				$cdat['mlock_limit']	= (isset($data[7]) && is_numeric($data[7]))?	$data[7] : 0;
				$cdat['mlock_key']		= (isset($data[8]) && strlen($data[8]))?		$data[8] : NULL;
				
				$chans[$data[1]] = $cdat;
			} else if ($data[0] == "CA") {
				// Channel access. Tricky one.
				$adat = array();
				$adat['channel'] = $data[1];
				$adat['nick'] = $data[2];
				$adat['modes'] = $data[3];
				$adat['stamp'] = $data[4];
				$adat['setter'] = $data[5]; // Person who added this.
				if (preg_match("/F/",$data[3])) {
					if (!isset($founders[$data[1]])) {
						$debug++;
						$founders[$data[1]][] = $data[2];
					} else {
						// We already have our founder.
						$debug++;
						$access[] = $adat;
					}
				} else {
					$access[] = $adat;
				}
			} else if ($data[0] == "MDC") {
				// Extra channel info.
				$extra = explode(":",$data[2]);
				if ($extra[0] == "private") {
					// Private Channel data.
					$xdata = array();
					if ($extra[1] == "botserv" && $extra[2] == "bot-assigned") {
						$xdata['bot'] = $data[3];
					} else if ($extra[1] == "topic") {
						if ($extra[2] == "setter") {
							$xdata['topic_setter'] = $data[3];
						} else if ($extra[2] == "text") {
							$xdata['topic_text'] = implode(" ",array_slice($data,2));
						} else if ($extra[2] == "ts") {
							$xdata['topic_ts'] = $data[3];
						}
					}
					if (isset($chans[$data[1]])) {
						$chans[$data[1]] = array_merge($chans[$data[1]],$xdata);
					} else {
						$chans[$data[1]] = $xdata;
					}
				}
				
			} else if ($data[0] == "BOT") {
				// Easy one to add. Bot.
				$bdat = array();
				$bdat['nick'] = $data[1];
				$bdat['user'] = $data[2];
				$bdat['host'] = $data[3];
				$bdat['stamp'] = $data[5];
				// Realname
				$rname = implode(chr(32),array_slice($data,6));
				$bdat['real'] = $rname;
				$bots[$data[1]] = $bdat;
			} else if ($data[0] == "ME") {
				// Memo!
				$mdat = array();
				$mdat['dest'] = $data[1];
				$mdat['from'] = $data[2];
				$mdat['stamp'] = $data[3];
				$mdat['read'] = $data[4];
				$msg = implode(chr(32),array_slice($data,5));
				$mdat['msg'] = $msg;
				$memos[] = $mdat;
			}
		}
	}
}
echo "Finished parsing Atheme v{$aver} DB!\n\tFound:\n";
echo "\t\t".count($nicks)." Nicknames\n";
echo "\t\t".count($alias)." Aliases\n";
echo "\t\t".count($chans)." Channels\n";
echo "\t\t".count($bots)." Botserv Bots\n";
echo "\t\t".count($access)." Access Rules\n";
echo "\t\t".count($memos)." Memos\n";
echo "\nConverting to Anope 1.9.* Format. Output shall be 'anope.db'\n";

$output = array();

// Now we add data to the "output stack"... Fancy words.

// First BotServ!
foreach ($bots as $b) {
	$output[] = "OBJECT BotInfo";
	$output[] = "DATA nick {$b['nick']}";
	$output[] = "DATA user {$b['user']}";
	$output[] = "DATA host {$b['host']}";
	$output[] = "DATA realname {$b['real']}";
	$output[] = "DATA created {$b['stamp']}";
	$output[] = "DATA oper_only 0"; // 0 by default
	$output[] = "END";
}

// Next NickCore
$emails = array();
foreach ($nicks as $n) {
	$password = gen();
	$emails[] = array("nick"=>$n['nick'],"password"=>$password,"email"=>$n['email']);
	$output[] = "OBJECT NickCore";
	$output[] = "DATA display {$n['nick']}";
	$output[] = "DATA pass md5:".md5($password);
	$output[] = "DATA email {$n['email']}";
	$output[] = "DATA language";
	if (isset($n['access'])) {
		$output[] = "DATA access {$n['access']}";
	}
	// Rest is just default really.
	$output[] = "DATA memomax 20";
	$output[] = "DATA MEMO_SIGNON 1";
	$output[] = "DATA MEMO_RECEIVE 1";
	$output[] = "DATA HIDE_EMAIL 1";
	$output[] = "DATA HIDE_MASK 1";
	$output[] = "DATA NS_PRIVATE 1";
	$output[] = "DATA AUTOOP 1";
	$output[] = "DATA NS_SECURE 1";
	$output[] = "END";
}

// Next NickAlias for the main nicknames.
foreach ($nicks as $n) {
	$output[] = "OBJECT NickAlias";
	$output[] = "DATA nick {$n['nick']}";
	$output[] = "DATA last_quit";
	$output[] = "DATA last_realname";
	if (isset($n['last_host'])) {
		$output[] = "DATA last_usermask {$n['last_host']}";
	}
	if (isset($n['vhost'])) {
		$output[] = "DATA vhost_host {$n['vhost']}";
	}
	if (isset($n['access'])) {
		$output[] = "DATA last_realhost {$n['access']}";
	}
	$output[] = "DATA time_registered {$n['stamp_1']}";
	$output[] = "DATA last_seen {$n['stamp_2']}";
	$output[] = "DATA nc {$n['nick']}";
	$output[] = "END";
}

// Then the extra nicknames
// Next NickAlias for the main nicknames.
foreach ($alias as $n) {
	if (strtolower($n['owner']) != strtolower($n['alias'])) {
		$output[] = "OBJECT NickAlias";
		$output[] = "DATA nick {$n['alias']}";
		$output[] = "DATA time_registered {$n['reg_stamp']}";
		$output[] = "DATA last_seen {$n['seen_stamp']}";
		$output[] = "DATA nc {$n['owner']}";
		$output[] = "END";
	}
}

// Next ChannelInfo to add channels
foreach ($chans as $c) {
	// Keep in mind we're using just default values here and dont know the users
	// Anope configuration
	$output[] = "OBJECT ChannelInfo";
	$output[] = "DATA name {$c['name']}";
	
	if (isset($founders[$c['name']][0])) {
		$output[] = "DATA founder {$founders[$c['name']][0]}"; // Nailed it.
	} else {
		$errors[] = "Unable to find the founder for {$c['name']}!";
	}
	$output[] = "DATA description";
	$output[] = "DATA time_registered {$c['create']}";
	if (isset($c['used'])) {
		$output[] = "DATA last_used {$c['used']}";
	}
	if (isset($c['topic_text'])) {
		$output[] = "DATA last_topic {$c['topic_text']}";
	}
	if (isset($c['topic_setter'])) {
		$output[] = "DATA last_topic_setter {$c['topic_setter']}";
	}
	if (isset($c['topic_ts'])) {
		$output[] = "DATA last_topic_time {$c['topic_ts']}";
	}
	$output[] = "DATA bantype 2";
	$output[] = "DATA levels ACCESS_CHANGE 10 ACCESS_LIST 3 AKICK 10 ASSIGN 10001 AUTOHALFOP 4 AUTOOP 5 AUTOOWNER 9999 AUTOPROTECT 10 AUTOVOICE 3 BADWORDS 10 BAN 4 FANTASIA 3 FOUNDER 10001 GETKEY 5 GREET 5 HALFOP 5 HALFOPME 4 INFO 9999 INVITE 5 KICK 4 MEMO 10 MODE 9999 NOKICK 1 OP 5 OPME 5 OWNER 10001 OWNERME 9999 PROTECT 9999 PROTECTME 10 SAY 5 SET 9999 SIGNKICK 9999 TOPIC 5 UNBAN 4 VOICE 4 VOICEME 3"; 
	if (isset($c['bot'])) {
		$output[] = "DATA bi {$c['bot']}";
	}
	$output[] = "DATA banexpire 0";
	$output[] = "DATA memomax 20";
	$output[] = "DATA BS_GREET 1";
	$output[] = "DATA BS_FANTASY 1";
	$output[] = "DATA PEACE 1";
	$output[] = "DATA SECUREFOUNDER 1";
	$output[] = "DATA CS_SECURE 1";
	$output[] = "DATA SIGNKICK 1";
	$output[] = "DATA KEEPTOPIC 1";
	$output[] = "END";
	
	$output	= array_merge($output, translateModeLock($c));

}

// Next ChannelAccess, Add access!
foreach ($access as $a) {
	// Keep in mind we're using just default values here and dont know the
	// Anope configuration
	
	// This is the access level. We'll go with anopes basics.
	if (preg_match("/(q)/",$a['modes'])) {
		// Owner/Founder!
		$level = 9999;
	} else if (preg_match("/a/",$a['modes'])) {
		// Admin!
		$level = 10;
	} else if (preg_match("/o/",$a['modes'])) {
		// Operator!
		$level = 5;
	} else if (preg_match("/h/",$a['modes'])) {
		// Half-Op!
		$level = 4;
	} else if (preg_match("/v/i",$a['modes'])) {
		// Voice!
		$level = 3;
	} else {
		// No access at all but we'll add them just to be kind.
		$level = 1;
	}
	
	$output[] = "OBJECT ChanAccess";
	$output[] = "DATA provider access/access";
	$output[] = "DATA ci {$a['channel']}";
	$output[] = "DATA mask {$a['nick']}";
	$output[] = "DATA creator {$a['setter']}";
	$output[] = "DATA last_seen {$a['stamp']}";
	$output[] = "DATA created {$a['stamp']}";
	$output[] = "DATA data {$level}";
	$output[] = "END";
}

// Finally Memos!
foreach ($memos as $m) {
	$output[] = "OBJECT Memo";
	$output[] = "DATA owner {$m['dest']}";
	$output[] = "DATA time {$m['stamp']}";
	$output[] = "DATA sender {$m['from']}";
	$output[] = "DATA text {$m['msg']}";
	// Flip the status. Atheme checks if its read, Anope checks if its unread
	if ($m['read'] == "0") {
		$output[] = "DATA unread 1";
	} else {
		$output[] = "DATA unread 0";
	}
	$output[] = "DATA receipt 0";
	$output[] = "END";
}

// Save the config to anope.db
echo "\nSaving to 'anope.db'.. This could take a while..\n";
file_put_contents("anope.db",implode("\n",$output));
echo "Generating EMail List file.\n";
if (count($emails) > 0) {
	$em = array();
	foreach ($emails as $email) {
		$em[] = "{$email['email']} {$email['nick']} {$email['password']}";
	}
	file_put_contents("anope_list.txt",implode("\n",$em));
	echo "\nA list of users and their new passwords has been written to 'anope_emails.txt'\n";
	echo "You can use this to give them their new passwords.\n";
}
$s = time() - $start;
echo "\n\tDone! Conversion took {$s} seconds!\n";
$count = count($bots)+count($chans)+count($nicks)+count($access)+count($memos)+count($alias);
echo "I used {$count} objects out of the detected {$objects}!\n\n";
echo "I also encountered ".count($errors)." errors.\n";
foreach ($errors as $err) {
	echo "\t{$err}\n";
}
if (count($chans) !== count($founders)) {
	$c = json_encode($chans);
	$f = json_encode($founders);
	$out = array();
	$out[] = "CHANNELS:\n";
	$out[] = base64_encode($c);
	$out[] = "\nFOUNDERS:\n";
	$out[] = base64_encode($f);
	file_put_contents("error_dump_".time().".txt",implode($out));
	echo "There is a founder (".count($founders).") vs channel (".count($chans).") mismatch! Please report error_dump_".time().".txt to tmfksoft@gmail.com\n";
}

function translateModeLock ($channel) {
	global	$ircd;
	
	$ircd	= (isset($ircd) && strlen($ircd))?	strtolower($ircd) : 'generic';
	$out	= Array();
	
	$locked	= Array();
	
	/* IRCd independent channel modes */
	if ($channel['mlock_on'] & 0x00000001)	$locked[] = Array("INVITE","ON");
	if ($channel['mlock_on'] & 0x00000008)	$locked[] = Array("MODERATED","ON");
	if ($channel['mlock_on'] & 0x00000010)	$locked[] = Array("NOEXTERNAL","ON");
	if ($channel['mlock_on'] & 0x00000040)	$locked[] = Array("PRIVATE","ON");
	if ($channel['mlock_on'] & 0x00000080)	$locked[] = Array("SECRET","ON");
	if ($channel['mlock_on'] & 0x00000100)	$locked[] = Array("TOPIC","ON");

	if ($channel['mlock_off'] & 0x00000001)	$locked[] = Array("INVITE","OFF");
	if ($channel['mlock_off'] & 0x00000008)	$locked[] = Array("MODERATED","OFF");
	if ($channel['mlock_off'] & 0x00000010)	$locked[] = Array("NOEXTERNAL","OFF");
	if ($channel['mlock_off'] & 0x00000040)	$locked[] = Array("PRIVATE","OFF");
	if ($channel['mlock_off'] & 0x00000080)	$locked[] = Array("SECRET","OFF");
	if ($channel['mlock_off'] & 0x00000100)	$locked[] = Array("TOPIC","OFF");
	
	// Keys
	if (($channel['mlock_on'] & 0x00000002) && strlen($channel['mlock_key']))	$locked[] = Array("KEY","ON",$channel['mlock_key']);
	//if ($channel['mlock_off'] & 0x00000002)	$locked[] = Array("KEY","OFF");

	// Limits
	if (($channel['mlock_on'] & 0x00000004) && strlen($channel['mlock_limit']))	$locked[] = Array("LIMIT","ON",$channel['mlock_limit']);
	//if ($channel['mlock_off'] & 0x00000004)	$locked[] = Array("LIMIT","OFF");
	
	if (($ircd == 'unreal') || ($ircd == 'inspircd')) {
		if ($channel['mlock_on'] & 0x00001000)	$locked[] = Array("BLOCKCOLOR","ON");
		if ($channel['mlock_on'] & 0x00002000)	$locked[] = Array("REGMODERATED", "ON");
		if ($channel['mlock_on'] & 0x00004000)	$locked[] = Array("REGISTEREDONLY","ON");
		if ($channel['mlock_on'] & 0x00008000)	$locked[] = Array("OPERONLY","ON");
		if ($channel['mlock_on'] & 0x00020000)	$locked[] = Array("NOKICK","ON");
		if ($channel['mlock_on'] & 0x00040000)	$locked[] = Array("STRIPCOLOR","ON");
		if ($channel['mlock_on'] & 0x00080000)	$locked[] = Array("NOKNOCK","ON");
		if ($channel['mlock_on'] & 0x00100000)	$locked[] = Array("NOINVITE","ON");
		if ($channel['mlock_on'] & 0x00200000)	$locked[] = Array("NOCTCP","ON");
		if ($channel['mlock_on'] & 0x00400000)	$locked[] = Array("AUDITORIUM","ON");
		if ($channel['mlock_on'] & 0x00800000)	$locked[] = Array("SSL","ON");
		if ($channel['mlock_on'] & 0x01000000)	$locked[] = Array("NONICK","ON");

		if ($channel['mlock_off'] & 0x00001000)	$locked[] = Array("BLOCKCOLOR","OFF");
		if ($channel['mlock_off'] & 0x00002000)	$locked[] = Array("REGMODERATED", "OFF");
		if ($channel['mlock_off'] & 0x00004000)	$locked[] = Array("REGISTEREDONLY","OFF");
		if ($channel['mlock_off'] & 0x00008000)	$locked[] = Array("OPERONLY","OFF");
		if ($channel['mlock_off'] & 0x00020000)	$locked[] = Array("NOKICK","OFF");
		if ($channel['mlock_off'] & 0x00040000)	$locked[] = Array("STRIPCOLOR","OFF");
		if ($channel['mlock_off'] & 0x00080000)	$locked[] = Array("NOKNOCK","OFF");
		if ($channel['mlock_off'] & 0x00100000)	$locked[] = Array("NOINVITE","OFF");
		if ($channel['mlock_off'] & 0x00200000)	$locked[] = Array("NOCTCP","OFF");
		if ($channel['mlock_off'] & 0x00400000)	$locked[] = Array("AUDITORIUM","OFF");
		if ($channel['mlock_off'] & 0x00800000)	$locked[] = Array("SSL","OFF");
		if ($channel['mlock_off'] & 0x01000000)	$locked[] = Array("NONICK","OFF");
	}
	
	if ($ircd == 'unreal') {
		if ($channel['mlock_on'] & 0x00010000)	$locked[] = Array("ADMINONLY","ON");
		//if ($channel['mlock_on'] & 0x02000000)	$locked[] = Array("JOINFLOOD","ON");
		if ($channel['mlock_on'] & 0x04000000)	$locked[] = Array("FILTER","ON");
		//if ($channel['mlock_on'] & 0x80000000)	$locked[] = Array("PERM","ON");

		if ($channel['mlock_off'] & 0x00010000)	$locked[] = Array("ADMINONLY","OFF");
		//if ($channel['mlock_off'] & 0x02000000)	$locked[] = Array("JOINFLOOD","OFF");
		if ($channel['mlock_off'] & 0x04000000)	$locked[] = Array("FILTER","OFF");
		//if ($channel['mlock_off'] & 0x80000000)	$locked[] = Array("PERM","OFF");
	}
	
	if ($ircd == 'inspircd') {
		if ($channel['mlock_on'] & 0x00010000)	$locked[] = Array("NONOTICE","ON");
		if ($channel['mlock_on'] & 0x02000000)	$locked[] = Array("FILTER","ON");
		if ($channel['mlock_on'] & 0x04000000)	$locked[] = Array("BLOCKCAPS","ON");
		if ($channel['mlock_on'] & 0x08000000)	$locked[] = Array("PERM","ON");
		//if ($channel['mlock_on'] & 0x10000000)	$locked[] = Array("IMMUNE","ON");
		if ($channel['mlock_on'] & 0x20000000)	$locked[] = Array("DELAYEDJOIN","ON");
	
		if ($channel['mlock_off'] & 0x00010000)	$locked[] = Array("NONOTICE","OFF");
		if ($channel['mlock_off'] & 0x02000000)	$locked[] = Array("FILTER","OFF");
		if ($channel['mlock_off'] & 0x04000000)	$locked[] = Array("BLOCKCAPS","OFF");
		if ($channel['mlock_off'] & 0x08000000)	$locked[] = Array("PERM","OFF");
		//if ($channel['mlock_off'] & 0x10000000)	$locked[] = Array("IMMUNE","OFF");
		if ($channel['mlock_off'] & 0x20000000)	$locked[] = Array("DELAYEDJOIN","OFF");
	}
	
	foreach ($locked as $key => $data) {
		$out[]	= "OBJECT ModeLock";
		$out[]	= "DATA ci $channel[name]";
	
		if ($data[1] == "ON")
			$out[]	= "DATA set 1";
		else
			$out[]	= "DATA set 0";

		$out[]	= "DATA name $data[0]";
	
		if (isset($data[2]) && strlen($data[2]))
			$out[]	= "DATA param $data[2]";
		else
			$out[]	= "DATA param";

		$out[]	= "DATA setter Unknown";
		$out[]	= "DATA created $channel[create]";
		$out[]	= "END";
	}

	return $out;
}

?>