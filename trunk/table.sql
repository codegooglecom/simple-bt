DROP TABLE IF EXISTS `tracker`;
CREATE TABLE IF NOT EXISTS `tracker` (
  `peer_hash` tinyblob NOT NULL,
  `info_hash` tinyblob,
  `ip` char(8) default NULL,
  `ipv6` char(32) default NULL,
  `port` smallint(5) unsigned default NULL,
  `seeder` tinyint(1) default '0',
  `update_time` int(11) NOT NULL default '0',
  PRIMARY KEY  (`peer_hash`(20)),
  KEY `info_hash` (`info_hash`(20))
) ENGINE=MyISAM DEFAULT CHARSET=cp1251;