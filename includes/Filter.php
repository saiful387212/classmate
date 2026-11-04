<?php
/**
 * Filter Builder Class - Builds SQL WHERE clauses from filter arrays
 */

class FilterBuilder {
    private $conditions = [];
    private $params = [];
    private $searchFields = [];
    
    /**
     * Add a simple equality filter
     */
    public function addEqual($field, $value, $tableAlias = null) {
        if ($value !== null && $value !== '') {
            $fieldName = $tableAlias ? $tableAlias . '.' . $field : $field;
            $placeholder = ':' . str_replace('.', '_', $field) . '_' . count($this->params);
            $this->conditions[] = $fieldName . ' = ' . $placeholder;
            $this->params[$placeholder] = $value;
        }
        return $this;
    }
    
    /**
     * Add a LIKE filter for search
     */
    public function addLike($field, $value, $tableAlias = null) {
        if ($value !== null && $value !== '') {
            $fieldName = $tableAlias ? $tableAlias . '.' . $field : $field;
            $placeholder = ':' . str_replace('.', '_', $field) . '_like_' . count($this->params);
            $this->conditions[] = $fieldName . ' LIKE ' . $placeholder;
            $this->params[$placeholder] = '%' . $value . '%';
        }
        return $this;
    }
    
    /**
     * Add search across multiple fields
     */
    public function addSearch($searchTerm, $fields, $tableAlias = null) {
        if ($searchTerm !== null && $searchTerm !== '') {
            $searchParts = [];
            foreach ($fields as $field) {
                $fieldName = $tableAlias ? $tableAlias . '.' . $field : $field;
                $placeholder = ':search_' . str_replace('.', '_', $field) . '_' . count($this->params);
                $searchParts[] = $fieldName . ' LIKE ' . $placeholder;
                $this->params[$placeholder] = '%' . $searchTerm . '%';
            }
            if (!empty($searchParts)) {
                $this->conditions[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }
        return $this;
    }
    
    /**
     * Add a date range filter
     */
    public function addDateRange($field, $from, $to, $tableAlias = null) {
        $fieldName = $tableAlias ? $tableAlias . '.' . $field : $field;
        
        if ($from !== null && $from !== '') {
            $placeholder = ':from_' . str_replace('.', '_', $field) . '_' . count($this->params);
            $this->conditions[] = $fieldName . ' >= ' . $placeholder;
            $this->params[$placeholder] = $from;
        }
        
        if ($to !== null && $to !== '') {
            $placeholder = ':to_' . str_replace('.', '_', $field) . '_' . count($this->params);
            $this->conditions[] = $fieldName . ' <= ' . $placeholder;
            $this->params[$placeholder] = $to;
        }
        
        return $this;
    }
    
    /**
     * Add an IN clause filter
     */
    public function addIn($field, $values, $tableAlias = null) {
        if (!empty($values)) {
            $fieldName = $tableAlias ? $tableAlias . '.' . $field : $field;
            $placeholders = [];
            foreach ($values as $index => $value) {
                $placeholder = ':in_' . str_replace('.', '_', $field) . '_' . $index . '_' . count($this->params);
                $placeholders[] = $placeholder;
                $this->params[$placeholder] = $value;
            }
            $this->conditions[] = $fieldName . ' IN (' . implode(', ', $placeholders) . ')';
        }
        return $this;
    }
    
    /**
     * Add a custom condition
     */
    public function addCustom($condition, $params = []) {
        if ($condition) {
            $this->conditions[] = $condition;
            foreach ($params as $key => $value) {
                $this->params[$key] = $value;
            }
        }
        return $this;
    }
    
    /**
     * Get the WHERE clause
     */
    public function getWhere() {
        if (empty($this->conditions)) {
            return '';
        }
        return ' WHERE ' . implode(' AND ', $this->conditions);
    }
    
    /**
     * Get the parameters
     */
    public function getParams() {
        return $this->params;
    }
    
    /**
     * Get the conditions array
     */
    public function getConditions() {
        return $this->conditions;
    }
    
    /**
     * Check if any filters are applied
     */
    public function hasFilters() {
        return !empty($this->conditions);
    }
    
    /**
     * Reset all filters
     */
    public function reset() {
        $this->conditions = [];
        $this->params = [];
        return $this;
    }
}