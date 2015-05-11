<?php

namespace CloudDrive;

class Filter
{
    protected $equalityFilters = [
        'name',
        'status',
        'labels',
        'createDate',
        'modifiedDate',
        'description',
        'parentIds',
        'isRoot',
        'kind',
    ];

    protected $filter = [];

    protected $prefixFilters = [
        'contentType',
        'name',
    ];

    protected $rangeFilters = [
        'contentDate',
        'modifiedDate',
        'createdDate',
    ];

    public function __construct()
    {

    }

    public function addEqualityFilter($field, $conditions)
    {
        if (!in_array($field, $this->equalityFilters)) {
            // @TODO throw exception
        }

        $filter = '';
        if (is_array($conditions)) {
            foreach ($conditions as &$cond) {
                $cond = $this->escapeValue($cond);
            }

            if (isset($conditions['OR'])) {
                $filter = implode(' OR ', $conditions['OR']);
            } else if ($conditions['AND']) {
                $filter = implode(' AND ', $conditions['AND']);
            }

            $filter = "($filter)";
        } else {
            $filter = $this->escapeValue($conditions);
        }

        $this->filter[$field] = $filter;

        return $this;
    }

    public function addRangeFilter($field, array $range)
    {
        if (!in_array($field, $this->rangeFilters)) {
            // @TODO throw exception
        }

        $filter = implode(' TO ', $range);

        $this->filter[$field] = "[$filter]";

        return $this;
    }

    public function buildString()
    {
        $retval = [];
        foreach ($this->filter as $field => $conditions) {
            $retval[] = "$field:$conditions";
        }

        return implode(' AND ', $retval);
    }

    protected function escapeValue($value)
    {
        return addcslashes($value, "+-&|!(){}[]^'\"~*?:\\ ");
    }
}
