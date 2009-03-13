<?php

$cfg = array();

$tracker_tbl = 'tracker';

// DB
$cfg['tr_db_type'] = 'sqlite';                   // Available db types: sqlite, mysql

// DB - MySQL
$cfg['tr_db']['mysql'] = array(
	'dbhost'   => 'localhost',
	'dbuser'   => 'root',
	'dbpasswd' => 'root',
	'dbname'   => 'retracker',
	'pconnect' => false,
	'log_name' => 'MySQL',
);

// DB - SQLite
$cfg['tr_db']['sqlite'] = array(
	'db_file_path' => 'C:\Program Files\VertrigoServ\Sqlitemanager\tr_db.sqlite',       // preferable on tmpfs
	'table_schema' => "CREATE TABLE $tracker_tbl ( 
						peer_hash BLOB(32), 
						info_hash BLOB(20), 
						ip        CHAR(8), 
						ipv6      CHAR(32), 
						port      SMALLINT(5), 
						seeder    TINYINT(1) DEFAULT '0', 
						update_time INT(11),
						PRIMARY KEY (peer_hash, info_hash)
						)",
	'table_index'  => "CREATE INDEX tracker_info_hash ON $tracker_tbl(info_hash);"
);

// Garbage collector (run this script in cron each 5 minutes with '?run_gc=1' e.g. http://yoursite.com/announce.php?run_gc=1)
$cfg['run_gc_key'] = 'run_gc';

// Tracker
$cfg['announce_interval']  = 1800;
$cfg['expire_factor']      = 2.5;
$cfg['peers_limit']        = 100; // Limit peers to select from DB
$cfg['cleanup_interval']   = 2400; // Interval to execute cleanup
$cfg['compact_always']     = false; // Enable compact mode always (don't check clien capability)
$cfg['ignore_reported_ip'] = false; // Ignore IP from GET query
$cfg['verify_reported_ip'] = false; // Verify reported IP?
$cfg['allow_internal_ip']  = true; // Allow IP from local, etc

// Cache
$cfg['cache_type'] = 'filecache'; // Available cache types: none, APC, memcached, sqlite, filecache

$cfg['cache']['memcached'] = array(
	'host'         => '127.0.0.1', 
	'port'         => 11211, 
	'pconnect'     => true, // use persistent connection
	'con_required' => true
); // exit script if can't connect

$cfg['cache']['sqlite'] = array(
	'db_file_path' => '/path/to/sqlite.cache.db', #  /dev/shm/sqlite.db
	'table_name'   => 'cache', 
	'table_schema' => 'CREATE TABLE cache (
	                     cache_name        VARCHAR(255),
	                     cache_expire_time INT,
	                     cache_value       TEXT,
	                     PRIMARY KEY (cache_name)
	                   )', 
	'pconnect'     => true, 
	'con_required' => true, 
	'log_name'     => 'CACHE'
);

$cfg['cache']['filecache']['path'] = './cache_tr/';

define('DUMMY_PEER', pack('Nn', ip2long('10.254.254.247'), 64765));

define('PEER_HASH_PREFIX', 'peer_');
define('PEERS_LIST_PREFIX', 'peers_list_');

define('PEER_HASH_EXPIRE', round($cfg['announce_interval'] * (0.85 * $cfg['expire_factor']))); // sec
define('PEERS_LIST_EXPIRE', round($cfg['announce_interval'] * 0.6)); // sec

// Misc
define('DBG_LOG', false); // Debug log
