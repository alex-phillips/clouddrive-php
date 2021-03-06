<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 11:08 AM
 */

namespace CloudDrive\Cache;

use CloudDrive\Cache;
use ORM;
use SQLite3;

class SQLite extends SQL
{
    /**
     * Construct a new SQLite cache object. The database will be saved in the
     * provided `cacheDir` under the name `$email.db`.
     *
     * @param string $email    The email for the account
     * @param string $cacheDir The directory to save the database in
     */
    public function __construct($email, $cacheDir)
    {
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $cacheDir = rtrim($cacheDir, '/');

        if (!file_exists("$cacheDir/$email.db")) {
            $db = new SQLite3("$cacheDir/$email.db");
            $db->exec(
                'CREATE TABLE nodes(
                    id VARCHAR PRIMARY KEY NOT NULL,
                    name VARCHAR NOT NULL,
                    kind VARCHAR NOT NULL,
                    md5 VARCHAR,
                    status VARCHAR,
                    created DATETIME NOT NULL,
                    modified DATETIME NOT NULL,
                    raw_data TEXT NOT NULL
                );
                CREATE INDEX node_id on nodes(id);
                CREATE INDEX node_name on nodes(name);
                CREATE INDEX node_md5 on nodes(md5);'
            );
            $db->exec(
                'CREATE TABLE configs
                (
                    id INTEGER PRIMARY KEY,
                    email VARCHAR NOT NULL,
                    token_type VARCHAR,
                    expires_in INT,
                    refresh_token TEXT,
                    access_token TEXT,
                    last_authorized INT,
                    content_url VARCHAR,
                    metadata_url VARCHAR,
                    checkpoint VARCHAR
                );
                CREATE INDEX config_email on configs(email);'
            );
            $db->exec(
                'CREATE TABLE nodes_nodes
                (
                    id INTEGER PRIMARY KEY,
                    id_node VARCHAR NOT NULL,
                    id_parent VARCHAR NOT NULL,
                    UNIQUE (id_node, id_parent)
                );
                CREATE INDEX nodes_id_node on nodes_nodes(id_node);
                CREATE INDEX nodes_id_parent on nodes_nodes(id_parent);'
            );
        }

        ORM::configure("sqlite:$cacheDir/$email.db");
    }
}
