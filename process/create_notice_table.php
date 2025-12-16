<?php
require_once dirname(__DIR__) . '/config.php';

// Check connection
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "CREATE TABLE IF NOT EXISTS `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '공지 제목',
  `content` text NOT NULL COMMENT '공지 내용',
  `author_id` int(11) NOT NULL COMMENT '작성자 ID',
  `is_important` tinyint(1) DEFAULT 0 COMMENT '중요 공지 여부',
  `view_count` int(11) DEFAULT 0 COMMENT '조회수',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_important` (`is_important`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($link, $sql)) {
    echo "Table 'notices' created successfully.\n";
} else {
    echo "Error creating table: " . mysqli_error($link) . "\n";
}

mysqli_close($link);
