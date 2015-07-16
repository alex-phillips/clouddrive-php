<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 11:08 AM
 */

namespace CloudDrive\Cache;

use PDO;
use SQLite3;
use CloudDrive\Account;
use CloudDrive\Cache;
use CloudDrive\Node;

class SQLite implements Cache
{
    /**
     * The database handle
     *
     * @var \PDO
     */
    private $db;

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

        if (substr($cacheDir, -1) !== '/') {
            $cacheDir .= '/';
        }

        if (!file_exists("{$cacheDir}{$email}.db")) {
            $db = new SQLite3("{$cacheDir}{$email}.db");
            $db->exec(
                'CREATE TABLE nodes(
                    id VARCHAR PRIMARY KEY NOT NULL,
                    name VARCHAR NOT NULL,
                    kind VARCHAR NOT NULL,
                    md5 VARCHAR,
                    status VARCHAR,
                    parents VARCHAR,
                    created DATETIME NOT NULL,
                    modified DATETIME NOT NULL,
                    raw_data TEXT NOT NULL
                );'
            );
            $db->exec(
                'CREATE TABLE config
                (
                    email VARCHAR PRIMARY KEY NOT NULL,
                    token_type VARCHAR,
                    expires_in INT,
                    refresh_token TEXT,
                    access_token TEXT,
                    last_authorized INT,
                    content_url VARCHAR,
                    metadata_url VARCHAR,
                    checkpoint VARCHAR
                );'
            );
        }

        $this->db = new PDO("sqlite:{$cacheDir}{$email}.db");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAllNodes()
    {
        return $this->db->prepare("DELETE FROM nodes WHERE 1=1;")
            ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteNodeById($id)
    {
        return $this->db->prepare("DELETE FROM nodes WHERE id = :id;")
            ->execute(
                [
                    ':id' => $id,
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function findNodeById($id)
    {
        $stmt = $this->db->prepare('SELECT raw_data FROM nodes WHERE id = :id;');
        $stmt->execute([
            ':id' => $id,
        ]);

        $results = $stmt->fetchAll();
        if (count($results) > 1) {
            throw new \Exception("Multiple nodes returned with ID $id: " . json_decode($results));
        }

        if (!$results) {
            return null;
        }

        return new Node(json_decode($results[0][0], true));
    }

    /**
     * {@inheritdoc}
     */
    public function findNodeByMd5($md5)
    {
        $stmt = $this->db->prepare('SELECT raw_data FROM nodes WHERE md5 = :md5;');
        $stmt->execute([
            ':md5' => $md5,
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) > 1) {
            throw new \Exception("Multiple nodes returned with same MD5: $md5: " . json_decode($results));
        }

        if (!$results) {
            return null;
        }

        return new Node(json_decode($results[0]['raw_data'], true));
    }

    /**
     * {@inheritdoc}
     */
    public function findNodesByName($name)
    {
        $stmt = $this->db->prepare('SELECT raw_data FROM nodes WHERE name = :name;');
        $stmt->execute([
            ':name' => $name,
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            $result = new Node(json_decode($result['raw_data'], true));
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeChildren(Node $node)
    {
        $stmt = $this->db->prepare("SELECT raw_data FROM nodes WHERE parents LIKE '%{$node['id']}';");
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$result) {
            $result = new Node(json_decode($result['raw_data']));
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function loadAccountConfig($email)
    {
        $stmt = $this->db->prepare('SELECT * FROM config WHERE email = :email');
        $stmt->execute([
            ':email' => $email,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function saveAccountConfig(Account $account)
    {
        $sql = <<<__SQL__
            INSERT OR REPLACE INTO config
                (email, token_type, expires_in, refresh_token, access_token, last_authorized, content_url, metadata_url, checkpoint)
            VALUES
                (:email, :token_type, :expires_in, :refresh_token, :access_token, :last_authorized, :content_url, :metadata_url, :checkpoint);
__SQL__;

        $stmt = $this->db->prepare($sql);

        $token = $account->getToken();
        $result = $stmt->execute([
            ":email"           => $account->getEmail(),
            ":token_type"      => $token["token_type"],
            ":expires_in"      => $token["expires_in"],
            ":refresh_token"   => $token["refresh_token"],
            ":access_token"    => $token["access_token"],
            ":last_authorized" => $token["last_authorized"],
            ":content_url"     => $account->getContentUrl(),
            ":metadata_url"    => $account->getMetadataUrl(),
            ":checkpoint"      => $account->getCheckpoint(),
        ]);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function saveNode(Node $node)
    {
        if (!$node['name'] && $node['isRoot'] === true) {
            $node['name'] = 'ROOT';
        }

        $sql = <<<__SQL__
            INSERT OR REPLACE INTO nodes
                (id, name, kind, md5, status, parents, created, modified, raw_data)
            VALUES
                (:id, :name, :kind, :md5, :status, :parents, :created, :modified, :raw_data);
__SQL__;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute(
            [
                ":id"       => $node['id'],
                ":name"     => $node['name'],
                ":kind"     => $node['kind'],
                ":md5"      => $node['contentProperties']['md5'],
                ":status"   => $node['status'],
                ":parents"  => implode(',', $node['parents']),
                ":created"  => $node['createdDate'],
                ":modified" => $node['modifiedDate'],
                ":raw_data" => json_encode($node),
            ]
        );
    }
}
