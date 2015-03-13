SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for apps_list
-- ----------------------------
DROP TABLE IF EXISTS `apps_list`;
CREATE TABLE `apps_list` (
  `app_id` int(11) DEFAULT NULL,
  `name` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for request_limit
-- ----------------------------
DROP TABLE IF EXISTS `request_limit`;
CREATE TABLE `request_limit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) DEFAULT NULL,
  `count` int(2) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for reviews_cache
-- ----------------------------
DROP TABLE IF EXISTS `reviews_cache`;
CREATE TABLE `reviews_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `app` int(10) DEFAULT NULL,
  `offset` int(6) DEFAULT NULL,
  `filter` varchar(20) DEFAULT NULL,
  `language` varchar(20) DEFAULT NULL,
  `day_range` char(4) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `items` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for search_cache
-- ----------------------------
DROP TABLE IF EXISTS `search_cache`;
CREATE TABLE `search_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `language` varchar(20) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `search` varchar(250) DEFAULT NULL,
  `items` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for shared_files_cache
-- ----------------------------
DROP TABLE IF EXISTS `shared_files_cache`;
CREATE TABLE `shared_files_cache` (
  `id` varchar(100) NOT NULL,
  `file_type` char(255) DEFAULT NULL,
  `direct_url` varchar(255) DEFAULT NULL,
  `game` varchar(255) DEFAULT NULL,
  `filled` char(255) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for users_cache
-- ----------------------------
DROP TABLE IF EXISTS `users_cache`;
CREATE TABLE `users_cache` (
  `link` varchar(255) DEFAULT NULL,
  `profile_id` varchar(100) DEFAULT NULL,
  `profile_name` varchar(140) DEFAULT NULL,
  `real_name` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `lang` varchar(20) DEFAULT NULL,
  `filled` tinyint(1) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for youtube_cache
-- ----------------------------
DROP TABLE IF EXISTS `youtube_cache`;
CREATE TABLE `youtube_cache` (
  `link` varchar(250) DEFAULT NULL,
  `items` text,
  `datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
