-- MySQL dump 10.16  Distrib 10.2.12-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: whmcs6
-- ------------------------------------------------------
-- Server version	10.2.12-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `mod_ipblmonitor`
--

DROP TABLE IF EXISTS `mod_ipblmonitor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor` (
  `id` int(11) NOT NULL,
  `notifyemails` text DEFAULT NULL,
  `checkfrequency` int(11) DEFAULT NULL,
  `licensekey` varchar(250) DEFAULT NULL,
  `adminusername` varchar(250) DEFAULT NULL,
  `smtp_host` varchar(250) DEFAULT NULL,
  `enable_notifyemails` enum('yes','no') NOT NULL DEFAULT 'no',
  `localkey` text DEFAULT NULL,
  `smtp_port` int(11),
  `smtp_user` varchar(250) DEFAULT NULL,
  `smtp_password` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mod_ipblmonitor_black_ips`
--

DROP TABLE IF EXISTS `mod_ipblmonitor_black_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor_black_ips` (
  `ip` varchar(250) NOT NULL,
  `rbl_server` varchar(250) NOT NULL,
  `host_name` varchar(250) DEFAULT NULL,
  `rdns` varchar(250) DEFAULT NULL,
  `lastcheck` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT NULL,
  `rbl_response_txt` varchar(250) DEFAULT 'NULL',
  `rbl_response_a` varchar(250) DEFAULT 'NULL',
  `jid` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mod_ipblmonitor_black_ips_ip_index` (`ip`),
  KEY `mod_ipblmonitor_black_ips_mod_ipblmonitor_groups_id_fk` (`gid`),
  KEY `mod_ipblmonitor_black_ips_mod_ipblmonitor_ips_id_fk` (`bid`),
  KEY `mod_ipblmonitor_black_ips_rbl_server_index` (`rbl_server`),
  KEY `mod_ipblmonitor_black_ips_mod_ipblmonitor_rblchecker_id_fk` (`jid`),
  CONSTRAINT `mod_ipblmonitor_black_ips_mod_ipblmonitor_groups_id_fk` FOREIGN KEY (`gid`) REFERENCES `mod_ipblmonitor_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mod_ipblmonitor_black_ips_mod_ipblmonitor_ips_id_fk` FOREIGN KEY (`bid`) REFERENCES `mod_ipblmonitor_ips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mod_ipblmonitor_black_ips_mod_ipblmonitor_rblchecker_id_fk` FOREIGN KEY (`jid`) REFERENCES `mod_ipblmonitor_rblchecker` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1696 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mod_ipblmonitor_groups`
--

DROP TABLE IF EXISTS `mod_ipblmonitor_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mod_ipblmonitor_groups_name_uindex` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mod_ipblmonitor_ips`
--

DROP TABLE IF EXISTS `mod_ipblmonitor_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor_ips` (
  `ip_start` varchar(100) NOT NULL,
  `ip_end` varchar(100) DEFAULT NULL,
  `netmask` varchar(50) DEFAULT NULL,
  `gid` int(11) DEFAULT NULL,
  `lastscan` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_scan_runned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `mod_ipblmonitor_ips_mod_ipblmonitor_groups_id_fk` (`gid`),
  CONSTRAINT `mod_ipblmonitor_ips_mod_ipblmonitor_groups_id_fk` FOREIGN KEY (`gid`) REFERENCES `mod_ipblmonitor_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mod_ipblmonitor_rbl`
--

DROP TABLE IF EXISTS `mod_ipblmonitor_rbl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor_rbl` (
  `dnszone` varchar(250) NOT NULL,
  `status` enum('good','bad') NOT NULL DEFAULT 'good',
  `lastcheck` timestamp NULL DEFAULT NULL,
  `enabled` enum('yes','no') NOT NULL DEFAULT 'yes',
  `description` text DEFAULT NULL,
  `url` varchar(250) DEFAULT NULL,
  `zone_ns_servers` text DEFAULT NULL,
  `ipv4` enum('yes','no') DEFAULT NULL,
  `ipv6` enum('yes','no') DEFAULT NULL,
  `domain` enum('yes','no') DEFAULT NULL,
  `name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`dnszone`),
  KEY `mod_ipblmonitor_rbl_status_index` (`status`),
  KEY `mod_ipblmonitor_rbl_enabled_index` (`enabled`),
  KEY `mod_ipblmonitor_rbl_name_index` (`dnszone`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mod_ipblmonitor_rblchecker`
--

DROP TABLE IF EXISTS `mod_ipblmonitor_rblchecker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mod_ipblmonitor_rblchecker` (
  `id` varchar(250) NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip` varchar(250) DEFAULT NULL,
  `status` enum('runned','finished') DEFAULT 'runned',
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-02-25 17:20:19
