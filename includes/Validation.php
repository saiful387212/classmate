<?php
/**
 * Validation Class
 */

class Validation {
    
    private $errors = [];
    private $data = [];
    private $rules = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    public function setRules($rules) {
        $this->rules = $rules;
        return $this;
    }
    
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $field => $ruleSet) {
            $value = isset($this->data[$field]) ? $this->data[$field] : null;
            
            // Skip validation if field is optional and empty
            if ($this->isOptional($ruleSet) && empty($value)) {
                continue;
            }
            
            $rules = explode('|', $ruleSet);
            
            foreach ($rules as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParam = isset($ruleParts[1]) ? $ruleParts[1] : null;
                
                $this->applyRule($field, $value, $ruleName, $ruleParam);
            }
        }
        
        return empty($this->errors);
    }
    
    private function isOptional($ruleSet) {
        return strpos($ruleSet, 'optional') !== false;
    }
    
    private function applyRule($field, $value, $rule, $param) {
        $fieldName = ucfirst(str_replace('_', ' ', $field));
        
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = "$fieldName is required";
                }
                break;
                
            case 'email':
                if (!empty($value) && !Security::validateEmail($value)) {
                    $this->errors[$field][] = "$fieldName must be a valid email";
                }
                break;
                
            case 'min':
                if (strlen($value) < $param) {
                    $this->errors[$field][] = "$fieldName must be at least $param characters";
                }
                break;
                
            case 'max':
                if (strlen($value) > $param) {
                    $this->errors[$field][] = "$fieldName must not exceed $param characters";
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field][] = "$fieldName must be a number";
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field][] = "$fieldName must be an integer";
                }
                break;
                
            case 'float':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    $this->errors[$field][] = "$fieldName must be a decimal number";
                }
                break;
                
            case 'date':
                if (!empty($value) && !Security::validateDate($value)) {
                    $this->errors[$field][] = "$fieldName must be a valid date";
                }
                break;
                
            case 'match':
                if (!empty($value) && $value !== $this->data[$param]) {
                    $this->errors[$field][] = "$fieldName does not match";
                }
                break;
                
            case 'unique':
                // Check uniqueness in database
                $db = Database::getInstance();
                $table = $param;
                $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
                $stmt->execute([$value]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $this->errors[$field][] = "$fieldName already exists";
                }
                break;
                
            case 'exists':
                // Check existence in database
                list($table, $column) = explode(',', $param);
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
                $stmt->execute([$value]);
                $count = $stmt->fetchColumn();
                
                if ($count == 0) {
                    $this->errors[$field][] = "$fieldName does not exist";
                }
                break;
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError($field = null) {
        if ($field !== null) {
            return isset($this->errors[$field][0]) ? $this->errors[$field][0] : null;
        }
        
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        
        return null;
    }
    
    public function hasError($field = null) {
        if ($field !== null) {
            return isset($this->errors[$field]) && !empty($this->errors[$field]);
        }
        
        return !empty($this->errors);
    }
    
    public function getValidatedData() {
        $validated = [];
        
        foreach ($this->data as $key => $value) {
            if (isset($this->rules[$key])) {
                $validated[$key] = Security::sanitize($value);
            }
        }
        
        return $validated;
    }
}
?>