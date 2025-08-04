<?php
namespace local_trainings\helper_tests;

use navigation_node;

trait navigation_trait_helper {

    /** 
     * Recursive search for a key in the children of a navigation node.
     *
     * @param navigation_node $node
     * @param string $key
     * @return bool
     */
    protected function navigation_node_contains_key(navigation_node $node, string $key): bool {
        if ($node->key === $key) {
            return true;
        }
        foreach ($node->children as $child) {
            if ($this->navigation_node_contains_key($child, $key)) {
                return true;
            }
        }
        return false;
    }
}