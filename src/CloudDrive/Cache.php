<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 11:06 AM
 */

namespace CloudDrive;

/**
 * Class that handles local storage of remote node information.
 *
 * @package CloudDrive
 */
interface Cache
{
    /**
     * Delete all nodes from the cache.
     *
     * @return bool
     */
    public function deleteAllNodes();

    /**
     * Delete node with the given ID from the cache.
     *
     * @param string $id
     *
     * @return bool
     */
    public function deleteNodeById($id);

    /**
     * Find the node by the given ID in the cache.
     *
     * @param string $id
     *
     * @return \CloudDrive\Node|null
     */
    public function findNodeById($id);

    /**
     * Find the node by the given MD5 in the cache.
     *
     * @param string $md5
     *
     * @return \CloudDrive\Node|null
     */
    public function findNodeByMd5($md5);

    /**
     * Retrieve all node matching the given name in the cache.
     *
     * @param string $name
     *
     * @return array
     */
    public function findNodesByName($name);

    /**
     * Retrieve all nodes who have the given node as their parent.
     *
     * @param Node $node
     *
     * @return array
     */
    public function getNodeChildren(Node $node);

    /**
     * Retrieve the config for the account matched with the given email.
     *
     * @param string $email
     *
     * @return array
     */
    public function loadAccountConfig($email);

    /**
     * Save the config for the provided account.
     *
     * @param Account $account
     *
     * @return bool
     */
    public function saveAccountConfig(Account $account);

    /**
     * Save the given node into the cache.
     *
     * @param Node $node
     *
     * @return bool
     */
    public function saveNode(Node $node);
}
