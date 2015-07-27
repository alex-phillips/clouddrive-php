<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/27/15
 * Time: 3:56 PM
 */

namespace CloudDrive\Cache;

use ORM;

class MySQL extends SQL
{
    public function __construct($host, $database, $username, $password)
    {
        ORM::configure("mysql:host=$host;dbname=$database");
        ORM::configure('username', $username);
        ORM::configure('password', $password);
        ORM::get_db()->exec('
            CREATE TABLE IF NOT EXISTS configs (
              id INT(11) NOT NULL auto_increment,
              email VARCHAR(32),
              token_type VARCHAR(16),
              expires_in INT(12),
              refresh_token TEXT,
              access_token TEXT,
              last_authorized INT(12),
              content_url MEDIUMTEXT,
              metadata_url MEDIUMTEXT,
              checkpoint TEXT,
              primary KEY (id)
            );
        ');
        ORM::get_db()->exec('
            CREATE TABLE IF NOT EXISTS nodes (
              id VARCHAR(255) NOT NULL,
              name VARCHAR(128),
              kind VARCHAR(16),
              md5 VARCHAR(128),
              status VARCHAR(16),
              parents VARCHAR(128),
              created DATETIME,
              modified DATETIME,
              raw_data LONGTEXT,
              primary KEY (id)
            );
        ');
    }
}
