<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/23/15
 * Time: 5:19 PM
 */

namespace CloudDrive\Cache;

use CloudDrive\Account;
use CloudDrive\Cache;
use CloudDrive\Node;
use ORM;

abstract class SQL implements Cache
{
    /**
     * Delete all nodes from the cache.
     *
     * @return mixed
     */
    public function deleteAllNodes()
    {
        return ORM::for_table('nodes')->delete_many();
    }

    /**
     * Delete node with the given ID from the cache.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function deleteNodeById($id)
    {
        $node = ORM::for_table('nodes')->find_one($id);
        if ($node) {
            return $node->delete();
        }

        return true;
    }

    /**
     * Find the node by the given ID in the cache.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function findNodeById($id)
    {
        $result = ORM::for_table('nodes')->select('raw_data')->where('id', $id)->find_one();
        if ($result) {
            return new Node(
                json_decode($result['raw_data'], true)
            );
        }

        return null;
    }

    /**
     * Find the node by the given MD5 in the cache.
     *
     * @param string $md5
     *
     * @return mixed
     */
    public function findNodeByMd5($md5)
    {
        $node = ORM::for_table('nodes')->select('raw_data')->where('md5', $md5)->find_one();
        if (!$node) {
            return null;
        }

        return new Node(
            json_decode($node->as_array()['raw_data'], true)
        );
    }

    /**
     * Retrieve all node matching the given name in the cache.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function findNodesByName($name)
    {
        $nodes = ORM::for_table('nodes')->select('raw_data')->where('name', $name)->find_many();
        foreach ($nodes as &$node) {
            $node = new Node(
                json_decode($node->as_array()['raw_data'], true)
            );
        }

        return $nodes;
    }

    /**
     * Retrieve all nodes who have the given node as their parent.
     *
     * @param Node $node
     *
     * @return mixed
     */
    public function getNodeChildren(Node $node)
    {
        $results = ORM::for_table('nodes')
            ->select('raw_data')
            ->where_like('parents', "%{$node['id']}")
            ->find_many();

        foreach ($results as &$result) {
            $result = new Node(
                json_decode($result['raw_data'], true)
            );
        }

        return $results;
    }

    /**
     * Retrieve the config for the account matched with the given email.
     *
     * @param string $email
     *
     * @return mixed
     */
    public function loadAccountConfig($email)
    {
        $config = ORM::for_table('configs')->where('email', $email)->find_one();
        if (!$config) {
            return null;
        }

        return $config->as_array();
    }

    /**
     * Save the config for the provided account.
     *
     * @param Account $account
     *
     * @return mixed
     */
    public function saveAccountConfig(Account $account)
    {
        $config = ORM::for_table('configs')->where('email', $account->getEmail())->find_one();
        if (!$config) {
            $config = ORM::for_table('configs')->create();
        }

        $config->email = $account->getEmail();
        $config->token_type = $account->getToken()['token_type'];
        $config->expires_in = $account->getToken()['expires_in'];
        $config->refresh_token = $account->getToken()['refresh_token'];
        $config->access_token = $account->getToken()['access_token'];
        $config->last_authorized = $account->getToken()['last_authorized'];
        $config->content_url = $account->getContentUrl();
        $config->metadata_url = $account->getMetadataUrl();
        $config->checkpoint = $account->getCheckpoint();
        $config->save();
    }

    /**
     * Save the given node into the cache.
     *
     * @param Node $node
     *
     * @return mixed
     */
    public function saveNode(Node $node)
    {
        if (!$node['name'] && $node['isRoot'] === true) {
            $node['name'] = 'ROOT';
        }

        $n = ORM::for_table('nodes')->find_one($node['id']);
        if (!$n) {
            $n = ORM::for_table('nodes')->create();
        }

        $n->id = $node['id'];
        $n->name = $node['name'];
        $n->kind = $node['kind'];
        $n->md5 = $node['contentProperties']['md5'];
        $n->status = $node['status'];
        $n->parents = implode(',', $node['parents']);
        $n->created = $node['createdDate'];
        $n->modified = $node['modifiedDate'];
        $n->raw_data = json_encode($node);
        $n->save();
    }
}
