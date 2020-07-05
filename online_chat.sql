-- phpMyAdmin SQL Dump
-- version phpStudy 2014
-- http://www.phpmyadmin.net
--
-- 主机: localhost
-- 服务器版本: 5.5.53
-- PHP 版本: 5.6.27

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- 数据库: `online_chat`
--

-- --------------------------------------------------------

--
-- 表的结构 `chat_consult_time`
--

CREATE TABLE IF NOT EXISTS `chat_consult_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `to_id` int(11) NOT NULL DEFAULT '0' COMMENT '咨询师id',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-未开始，1-咨询中，2-暂停中，3-已完成，4-已取消',
  `duration_count` int(11) NOT NULL DEFAULT '0' COMMENT '剩余时长，时长计数',
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT '时长',
  `free_duration_count` int(11) NOT NULL DEFAULT '0' COMMENT '免费剩余时长，时长计数',
  `free_duration` int(11) NOT NULL DEFAULT '0' COMMENT '免费时长',
  `total_duration` int(11) NOT NULL DEFAULT '0' COMMENT '总计时长',
  `delayed_duration_total` int(11) NOT NULL DEFAULT '0' COMMENT '延时时长',
  `delayed_num` int(11) NOT NULL DEFAULT '0' COMMENT '延时次数',
  `ctime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，其他-代表已删除（删除的时间戳）',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='咨询计时' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_mail_list`
--

CREATE TABLE IF NOT EXISTS `chat_mail_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `uids` text COMMENT '所有的好友uid，用逗号隔开',
  `rids` text COMMENT '加入所有群聊的id，用逗号隔开',
  `soft_delete` int(11) DEFAULT '0' COMMENT '软删除，0-正常，其他已删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `soft_delete` (`soft_delete`,`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='通讯录' AUTO_INCREMENT=1 ;

--
-- 表的结构 `chat_message`
--

CREATE TABLE IF NOT EXISTS `chat_message` (
  `mid` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `tmp` tinyint(1) DEFAULT '0' COMMENT '发送消息的人，是否是临时用户；该字段是客户聊天有用；0-不是，1-是',
  `chat_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '聊天类型，0-普通聊天，1-聊天室，2-客服，3-咨询',
  `to_id` int(11) NOT NULL DEFAULT '0' COMMENT '发给谁的id，可以是uid,rid',
  `msg_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-文本，1-图片，2-语音，3-视频，4-富文本，5-文件，10-客服欢迎消息',
  `msg` varchar(255) NOT NULL DEFAULT '' COMMENT '内容',
  `ctime` int(11) NOT NULL DEFAULT '0' COMMENT '发消息时间',
  `uuid` char(32) DEFAULT NULL COMMENT '消息唯一标识符',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，1-都删除，用户id-这个用户删除了',
  PRIMARY KEY (`mid`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `chat_type` (`uid`,`chat_type`,`to_id`),
  KEY `chat_type_2` (`to_id`,`chat_type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COMMENT='用户一对一聊天消息' AUTO_INCREMENT=1507 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_message_text`
--

CREATE TABLE IF NOT EXISTS `chat_message_text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) DEFAULT NULL COMMENT '消息id',
  `content` text COMMENT '内容',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COMMENT='消息的大文本存放表' AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_room`
--

CREATE TABLE IF NOT EXISTS `chat_room` (
  `rid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '聊天室名称',
  `head_img` varchar(100) NOT NULL DEFAULT '' COMMENT '聊天室头像',
  `last_uid` bigint(20) DEFAULT '0' COMMENT '最近发送消息的uid',
  `last_time` int(11) DEFAULT NULL COMMENT '最近消息时间',
  `last_msg_uuid` char(32) DEFAULT NULL COMMENT '最新消息的uuid',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，其他-代表已删除（删除的时间戳）',
  PRIMARY KEY (`rid`),
  KEY `soft_delete` (`soft_delete`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='聊天室' AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_room_user`
--

CREATE TABLE IF NOT EXISTS `chat_room_user` (
  `room_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '群里id',
  `user_id` int(11) DEFAULT NULL COMMENT '11',
  `ctime` int(11) DEFAULT NULL COMMENT '加入群里时间',
  PRIMARY KEY (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='聊天室用户' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_session`
--

CREATE TABLE IF NOT EXISTS `chat_session` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `chat_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-普通聊天，1-聊天室，2-客服，3-咨询',
  `to_id` int(11) NOT NULL DEFAULT '0' COMMENT '和谁聊的id',
  `last_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近更新的时间',
  `last_msg_uuid` char(32) DEFAULT '0' COMMENT '最近消息的uuid',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，其他-代表已删除（删除的时间戳）',
  PRIMARY KEY (`sid`),
  UNIQUE KEY `uid` (`soft_delete`,`uid`,`chat_type`,`to_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='聊天列表' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_tmp_user`
--

CREATE TABLE IF NOT EXISTS `chat_tmp_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `ip_addr` varchar(20) NOT NULL DEFAULT '',
  `ctime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `online` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-不在线，1-在线',
  `last_heartbeat_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近心跳时间',
  `session_id` varchar(64) NOT NULL DEFAULT '' COMMENT '会话id',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，其他-代表已删除（删除的时间戳）',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='临时用户' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- 表的结构 `chat_user`
--

CREATE TABLE IF NOT EXISTS `chat_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '用户名',
  `head_img` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `online` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-不在线，1-在线',
  `last_login_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近登录时间',
  `app_uid` varchar(64) NOT NULL DEFAULT '' COMMENT '应用的uid',
  `user_type` tinyint(3) NOT NULL DEFAULT '0' COMMENT '用户类型，0-聊天用户，1-客服，2-咨询师',
  `last_heartbeat_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近心跳时间',
  `soft_delete` int(11) NOT NULL DEFAULT '0' COMMENT '软删除，0-正常，其他-代表已删除（删除的时间戳）',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `app_uid` (`soft_delete`,`app_uid`,`user_type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='用户表' AUTO_INCREMENT=10003 ;

CREATE DEFINER=`root`@`localhost` EVENT `updateUserOnline` ON SCHEDULE EVERY 1 MINUTE STARTS '2020-06-17 20:13:45' ON COMPLETION NOT PRESERVE ENABLE DO begin
  update chat_user set online=0 where online=1 and last_heartbeat_time<unix_timestamp()-30;
  update chat_tmp_user set online=0 where online=1 and last_heartbeat_time<unix_timestamp()-30;
end;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
