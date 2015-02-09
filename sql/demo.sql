CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `code` varchar(63) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_957A6479A0D96FBF` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(127) DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8 NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` int(11) unsigned DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dataset_user` (`user_id`),
  CONSTRAINT `dataset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `geonames` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `tgn` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `bag` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `gg` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `erfgeo` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_dataset` (`dataset_id`),
  CONSTRAINT `record_ibfk_1` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `multiples` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) DEFAULT NULL,
  `geonames` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `tgn` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `bag` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `gg` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `erfgeo` TEXT CHARACTER SET utf8 DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `multiple_record` (`record_id`),
  CONSTRAINT `multiple_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;