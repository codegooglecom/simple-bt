<?php

define ('TR_ROOT', dirname(realpath(__FILE__)));
require_once (TR_ROOT .'/common.php');

$announce_interval = $cfg['announce_interval'];

if (!$cache->used || ($cache->get('next_cleanup') < TIMENOW) || !empty($_GET[$tr_cfg['run_gc_key']]))
{
	cleanup();
}

// Recover info_hash
if (isset($_GET['?info_hash']) && !isset($_GET['info_hash']))
{
	$_GET['info_hash'] = $_GET['?info_hash'];
}

// Input var names
// String
$input_vars_str = array(
	'info_hash',
	'peer_id',
	'ipv4',
	'ipv6',
	'event',
);
// Numeric
$input_vars_num = array(
	'port',
	'numwant',
	'compact',
);

// Init received data
// String
foreach ($input_vars_str as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (string) $_GET[$var_name] : null;
}
// Numeric
foreach ($input_vars_num as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (float) $_GET[$var_name] : null;
}

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Похоже, к нам зашли через браузер.
	// Вежливо отправим человека на инструкцию по псевдотрекеру.
	//echo ;
	//die;
	msg_die("Invalid info_hash: '$info_hash' length ".strlen($info_hash)." <meta http-equiv=refresh content=0;url=http://re-tracker.ru/>");
}
if (!isset($peer_id) || strlen($peer_id) != 20)
{
	msg_die('Invalid peer_id');
}
if (!isset($port) || $port < 0 || $port > 0xFFFF)
{
	msg_die('Invalid port');
}

// IP
$ip = $_SERVER['REMOTE_ADDR'];

if (!$cfg['ignore_reported_ip'] && isset($_GET['ip']) && $ip !== $_GET['ip'])
{
	if (!$cfg['verify_reported_ip'])
	{
		$ip = $_GET['ip'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
	{
		foreach ($matches[0] as $x_ip)
		{
			if ($x_ip === $_GET['ip'])
			{
				if (!$cfg['allow_internal_ip'] && preg_match("#^(10|172\.16|192\.168)\.#", $x_ip))
				{
					break;
				}
				$ip = $x_ip;
				break;
			}
		}
	}
}

// Check that IP format is valid
if (!$iptype = verify_ip($ip))
{
	msg_die("Invalid IP: $ip");
}
// Convert IP to HEX format
//$ip_sql = encode_ip($ip);

// ----------------------------------------------------------------------------
// Start announcer
//
$info_hash_hex = bin2hex($info_hash);
$info_hash_sql = rtrim($db->escape($info_hash), ' ');

// Peer unique id
$peer_hash = md5(
	rtrim($info_hash, ' ') . $peer_id . $ip . $port
);

// Get cached peer info from previous announce (last peer info)
$lp_info = $cache->get(PEER_HASH_PREFIX . $peer_hash);

// Drop fast announce
if ($lp_info && (!isset($event) || $event !== 'stopped'))
{
	drop_fast_announce($lp_info);
}

// It's seeder?
$seeder  = ($left == 0) ? 1 : 0;

// Stopped event
if ($event === 'stopped')
{
	$db->query("DELETE FROM $tracker_tbl WHERE peer_hash = '$peer_hash'");
	$cache->rm(PEER_HASH_PREFIX . $peer_hash);
	die();
}

$ipv6 = ($iptype == 'ipv6') ? encode_ip($ip) : ((verify_ip($ipv6) == 'ipv6') ? encode_ip($ipv6) : null);
$ipv4 = ($iptype == 'ipv4') ? encode_ip($ip) : ((verify_ip($ipv4) == 'ipv4') ? encode_ip($ipv4) : null);

$sql_data = array(
	'peer_hash'    => $peer_hash,
	'info_hash'    => $info_hash_sql,	
	'ip'           => $ipv4,
	'ipv6'         => $ipv6,
	'port'         => $port,
	'seeder'       => $seeder,
	'update_time'  => TIMENOW,
);

$columns = $values = $dupdate = array();

foreach ($sql_data as $column => $value)
{
	$columns[] = $column;
	$values[]  = "'" . $db->escape($value) . "'";
}

$columns_sql = implode(', ', $columns);
$values_sql = implode(', ', $values);

// Update peer info
$db->query("REPLACE INTO $tracker_tbl ($columns_sql) VALUES ($values_sql)");

// Store peer info in cache
$lp_info = array(
	'seeder'      => (int) $seeder,
	'update_time' => (int) TIMENOW,
);

$lp_info_cached = $cache->set(PEER_HASH_PREFIX . $peer_hash, $lp_info, PEER_HASH_EXPIRE);

unset($sql_data, $columns, $values, $columns_sql, $values_sql);

// Select peers
$output = $cache->get(PEERS_LIST_PREFIX . $info_hash_hex);

if (!$output)
{
	$limit = (int) (($numwant > $cfg['peers_limit']) ? $cfg['peers_limit'] : $numwant);
	
	$rowset = $db->fetch_rowset("
		SELECT ip, ipv6, port, seeder
		FROM $tracker_tbl
		WHERE info_hash = '$info_hash_sql'
		ORDER BY ". $db->random_fn ."
		LIMIT 300
	");
	
	$peerset  = array();
	$peerset6 = array();
	$seeders  = $leechers = $peers = 0;

	foreach ($rowset as $peer)
	{
		$peers++;
		
		if($peer['seeder'])
		{
			$seeders++;
		}
		unset($peer['seeder']);
		
		if(!empty($peer['ip']) && empty($peer['ipv6']))
		{
			$peer['ip'] = decode_ip($peer['ip']);
			unset($peer['ipv6']);
			$peerset[] = $peer;
		}
		if(!empty($peer['ipv6']))
		{
			$peer['ip'] = decode_ip($peer['ipv6']);
			unset($peer['ipv6']);
			$peerset6[] = $peer;
		}		
	}
	$leechers = $peers - $seeders;
	
	$output = array(
		'interval'     => (int) $announce_interval, // tracker config: announce interval (sec?)
		'min interval' => (int) 1, // tracker config: min interval (sec?)
		'peers'        => $peerset,
		'peers6'       => $peerset6,
		'complete'     => (int) $seeders,
		'incomplete'   => (int) $leechers,
	);
	
	$peers_list_cached = $cache->set(PEERS_LIST_PREFIX . $info_hash_hex, $output, PEERS_LIST_EXPIRE);
}

// Generate output
$compact_mode = ($cfg['compact_always'] || !empty($compact));

if ($compact_mode)
{
	$peers = '';

	foreach ($output['peers'] as $peer)
	{
		$peers .= pack('Nn', ip2long($peer['ip']), $peer['port']);
	}
	
	$output['peers'] = $peers;
	
	
	$peers6 = '';

	foreach ($output['peers6'] as $peer)
	{
		$peers6 .= pack('H32n', encode_ip($peer['ip']), $peer['port']);
	}
	
	$output['peers6'] = $peers6;	
}

// Return data to client
echo bencode($output);

exit;
