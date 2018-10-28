DROP TABLE IF EXISTS `#__rimages_externalimages`;

CREATE TABLE `#__rimages_externalimages` (
  `imgid` VARCHAR(40) NOT NULL,
  `timestamp` DATETIME NOT NULL,
  PRIMARY KEY (`imgid`)
)
  ENGINE =MyISAM
  AUTO_INCREMENT =0
  DEFAULT CHARSET =utf8;
