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

/**
 * The SQL abstract class is what all SQL database cache classes will inherit
 * from.
 *
 * @package CloudDrive\Cache
 */
abstract class SQL implements Cache
{
    /**
     * {@inheritdoc}
     */
    public function deleteAllNodes()
    {
        try {
            ORM::get_db()->beginTransaction();
            ORM::for_table('nodes')->delete_many();
            ORM::get_db()->exec('TRUNCATE nodes_nodes');
            ORM::get_db()->commit();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteNodeById($id)
    {
        $node = ORM::for_table('nodes')->find_one($id);
        if ($node) {
            try {
                ORM::get_db()->beginTransaction();

                $node->delete();
                ORM::for_table('nodes_nodes')
                    ->where('id_node', $id)
                    ->delete_many();

                return ORM::get_db()->commit();
            } catch (\Exception $e) {
                ORM::get_db()->rollBack();
            }

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function filterNodes(array $filters)
    {
        $nodes = ORM::for_table('nodes')
            ->select('raw_data')
            ->where_any_is($filters)
            ->find_many();

        foreach ($nodes as &$node) {
            $node = new Node(
                json_decode($node->as_array()['raw_data'], true)
            );
        }

        return $nodes;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function findNodesByMd5($md5)
    {
        $nodes = ORM::for_table('nodes')
            ->select('raw_data')
            ->where('md5', $md5)
            ->find_many();

        foreach ($nodes as &$node) {
            $node = new Node(
                json_decode($node->as_array()['raw_data'], true)
            );
        }

        return $nodes;
    }

    /**
     * {@inheritdoc}
     */
    public function findNodesByName($name)
    {
        $nodes = ORM::for_table('nodes')
            ->select('raw_data')
            ->where('name', $name)
            ->find_many();

        foreach ($nodes as &$node) {
            $node = new Node(
                json_decode($node->as_array()['raw_data'], true)
            );
        }

        return $nodes;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeChildren(Node $node)
    {
        $results = ORM::for_table('nodes')
            ->select('raw_data')
            ->join('nodes_nodes', ['nodes.id', '=', 'nodes_nodes.id_node'])
            ->where('nodes_nodes.id_parent', $node['id'])
            ->find_many();

        foreach ($results as &$result) {
            $result = new Node(
                json_decode($result['raw_data'], true)
            );
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function loadAccountConfig($email)
    {
        $config = ORM::for_table('configs')
            ->where('email', $email)
            ->find_one();

        if (!$config) {
            return null;
        }

        return $config->as_array();
    }

    /**
     * {@inheritdoc}
     */
    public function saveAccountConfig(Account $account)
    {
        $config = ORM::for_table('configs')->where('email', $account->getEmail())->find_one();
        if (!$config) {
            $config = ORM::for_table('configs')->create();
        }

        $config->set([
            'email'           => $account->getEmail(),
            'token_type'      => $account->getToken()['token_type'],
            'expires_in'      => $account->getToken()['expires_in'],
            'refresh_token'   => $account->getToken()['refresh_token'],
            'access_token'    => $account->getToken()['access_token'],
            'last_authorized' => $account->getToken()['last_authorized'],
            'content_url'     => $account->getContentUrl(),
            'metadata_url'    => $account->getMetadataUrl(),
            'checkpoint'      => $account->getCheckpoint(),
        ]);

        return $config->save();
    }

    /**
     * {@inheritdoc}
     */
    public function saveNode(Node $node)
    {
        if (!$node['name'] && $node['isRoot'] === true) {
            $node['name'] = 'Cloud Drive';
        }

        $n = ORM::for_table('nodes')->find_one($node['id']);
        if (!$n) {
            $n = ORM::for_table('nodes')->create();
        }

        $n->set([
            'id'       => $node['id'],
            'name'     => $node['name'],
            'kind'     => $node['kind'],
            'md5'      => $node['contentProperties']['md5'],
            'status'   => $node['status'],
            'parents'  => implode(',', $node['parents']),
            'created'  => $node['createdDate'],
            'modified' => $node['modifiedDate'],
            'raw_data' => json_encode($node),
        ]);

        try {
            ORM::get_db()->beginTransaction();

            $n->save();

            $parentIds = $node['parents'];
            $previousParents = ORM::for_table('nodes_nodes')
                ->where('id_node', $node['id'])
                ->find_array();

            foreach ($previousParents as $parent) {
                if ($index = array_search($parent['id_parent'], $parentIds)) {
                    unset($parentIds[$index]);
                    continue;
                } else {
                    ORM::for_table('nodes_nodes')
                        ->find_one($parent['id'])
                        ->delete();
                }
            }

            foreach ($parentIds as $parentId) {
                $p = ORM::for_table('nodes_nodes')->create();
                $p->set([
                    'id_node' => $node['id'],
                    'id_parent' => $parentId,
                ]);
                $p->save();
            }

            return ORM::get_db()->commit();
        } catch (\Exception $e) {
            ORM::get_db()->rollBack();

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function searchNodesByName($name)
    {
        $results = ORM::for_table('nodes')
            ->where_like('name', "%$name%")
            ->find_many();

        foreach ($results as &$result) {
            $result = new Node(
                json_decode($result['raw_data'], true)
            );
        }

        return $results;
    }
}
