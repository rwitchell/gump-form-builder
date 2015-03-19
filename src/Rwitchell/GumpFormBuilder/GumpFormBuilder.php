<?php
/**
 * GumpFormBuilding -  A PHP class to help generate a form based on
 * an input of text rules separated with pipes.
 * based on code by Sean Nieuwoudt (http://twitter.com/SeanNieuwoudt)
 * @author        Robert Witchell (http://twitter.com/RobertWitchell)
 * @copyright     Copyright (c) 2013
 * @link          http://
 * @version       1.0
 */

namespace Rwitchell\GumpFormBuilder;


use Exception;

class GumpFormBuilder
{
    // Validation rules for execution
    protected $form_rules = array();
    protected $form_rules_options = array();
    protected $listOfDBColumns = array();
    protected $otherInputs;

    // Filter rules for execution
    protected $filter_rules = array();

    // Instance attribute containing errors from last run
    protected $errors = array();


    // ** ------------------------- Validation Helpers ---------------------------- ** //	

    public function viewRules()  // not used yet.
    {
        return $this->form_rules;
    }

    /**
     * Shorthand method for inline validation
     *
     * @param array $data The data to be validated
     * @param array $validators The GUMP validators
     * @return mixed True(boolean) or the array of error messages
     */
    public static function is_valid(array $data, array $validators) // not used yet.
    {
        $formBuilder = new self(); // GumpFormBuilder();

        $formBuilder->form_rules($validators);

        if ($formBuilder->run($data) === false) {
            return $formBuilder->get_readable_errors(false);
        } else {
            return true;
        }
    }

    /**
     * Magic method to generate the validation error messages
     *
     * @return string
     */
    public function __toString()
    {
        return $this->get_readable_errors(true);
    }

    /**
     * Perform XSS clean to prevent cross site scripting
     *
     * @static
     * @access public
     * @param  array $data
     * @return array
     */
    public static function xss_clean(array $data) // not used yet.
    {
        foreach ($data as $k => $v) {
            $data[$k] = filter_var($v, FILTER_SANITIZE_STRING);
        }

        return $data;
    }

    /**
     * Adds a custom validation rule using a callback function
     *
     * @access public
     * @param string   $rule
     * @param callable $callback
     * @return bool
     * @throws Exception
     */
    public static function add_validator($rule, $callback) // not used yet.
    {
        $method = 'validate_' . $rule;

        if (method_exists(__CLASS__, $method) || isset(self::$validation_methods[$rule])) {
            throw new Exception("Validator rule '$rule' already exists.");
        }

        self::$validation_methods[$rule] = $callback;

        return true;
    }


    /**
     * Getter/Setter for the validation rules
     *
     * @param array $rules
     * @return array
     */
    public function validation_rules(array $rules = array())
    {
        if (empty($rules)) {
            return $this->validation_rules;
        }

        $this->validation_rules = $rules;
    }

    /**
     * Getter/Setter for the validation rules
     *
     * @param array $rules
     * @return array
     */
    public function form_rules(array $rules = array())
    {
        if (empty($rules)) {
            return $this->form_rules;
        }

        $this->form_rules = $rules;
    }

    /**
     * Getter/Setter for the validation rules' options
     *
     * @param array $rules_options
     * @return array
     */
    public function form_rules_options(array $rules_options = array())
    {
        if (!empty($rules_options)) {
            $this->form_rules_options = $rules_options;
        } else {
            return $this->form_rules_options;
        }
    }

    /**
     * Getter/Setter for the view Restrictor
     *
     * @param array $viewOnly
     * @return array
     */
    public function form_viewOnly(array $viewOnly = array())
    {
        if (!empty($viewOnly)) {
            $this->viewOnly = $viewOnly;
        } else {
            return $this->viewOnly;
        }
    }

    /**
     * Getter/Setter for the filter rules
     *
     * @param array $rules
     * @return array
     */
    public function filter_rules(array $rules = array()) // not used yet.
    {
        if (!empty($rules)) {
            $this->filter_rules = $rules;
        } else {
            return $this->filter_rules;
        }
    }

    /**
     * Run the filtering and validation after each other
     *
     * @param array $data
     * @param bool  $check_fields
     * @return array
     */
    public function run(array $data, $check_fields = false)
    {
        //$data = $this->filter($data, $this->filter_rules());
        $this->listOfDBColumns = $data;
        $this->setPostData();


        $validated = $this->validate(
            $data, $this->form_rules()
        );

        if ($check_fields === true) {
            $this->check_fields($data);
        }

        if ($validated !== true) {
            return false;
        } else {

            // check HTML output array against nodisplay AND view()
            $htmlOnly = "";
            $otherInputs = "";

            if (count($this->viewOnly) === 0) { // No view, show all columns
                foreach ($this->html AS $fieldName => $html) {
                    $htmlOnly .= $html;
                }
            } else {
                // This will output the HTML is the same order as the DB table.
                foreach ($this->listOfDBColumns AS $blank => $fieldName) { // run through the list of DB columns
                    if (array_key_exists($fieldName, $this->html)) {  // if the current DB field matches an HTML key:
                        if (array_search($fieldName, $this->viewOnly) !== false
                        ) { // if the current DB field matches a view 
                            $htmlOnly .= $this->html[$fieldName];
                        } else {
                            $otherInputs .= $this->html[$fieldName];
                        }
                    }
                }
            }

            $this->otherInputs = $otherInputs;
            return $htmlOnly; //$data;
        }
    }

    /**
     * Ensure that the field counts match the validation rule counts
     *
     * @param array $data
     */
    private function check_fields(array $data)
    {
        $ruleset  = $this->validation_rules();
        $mismatch = array_diff_key($data, $ruleset);
        $fields   = array_keys($mismatch);

        foreach ($fields as $field) {
            $this->errors[] = array(
                'field' => $field,
                'value' => $data[$field],
                'rule'  => 'mismatch',
                'param' => null
            );
        }
    }


    /**
     * Return the error array from the last validation run
     *
     * @return array
     */
    public function errors() // not used yet.
    {
        return $this->errors;
    }

    /**
     * Perform data validation against the provided ruleSet
     *
     * @access public
     * @param  mixed $input
     * @param  array $ruleSet
     * @return mixed
     */
    public function validate(array $input, array $ruleSet)
    {
        $this->errors = array();
        $ruleSet = array_change_key_case($ruleSet, CASE_UPPER);

        // run through all possible inputs (DB table columns)
        foreach ($input as $number => $fieldName) {
            // check if the current input has a rule set up
            if (array_key_exists(strtoupper($fieldName), $ruleSet)) {

                foreach ($ruleSet as $field => $rules) {

                    #if(!array_key_exists($field, $input))
                    #{
                    #	continue;
                    #}

                    if ($field == "") {
                        break;
                    } elseif (strtoupper($field) === strtoupper($fieldName)) {
                        $inputFound = 0;
                        $input      = null;
                        $attributesHTML = "";
                        $rules      = explode('|', $rules);


                        foreach ($rules as $rule) {
                            $method = "element_{$rule}";

                            if ($rule == "nodisplay") {
                                break;

                            } elseif (is_callable(array(
                                    $this,
                                    $method
                                )) && $inputFound == 0
                            ) { // see if $this->$method exists
                                $elementString = $method;
                                $inputFound = 1;

                            } else {
                                if (strstr($rule, ',') !== false) { // has attributes
                                    $rule      = explode(',', $rule); // split "max_len,15" into 2 pieces


                                    if (is_callable(array($this, "input_" . $rule[0]))) { // check for input_max_len()
                                        $method = "input_{$rule[0]}";
                                        $input = $this->$method($rule[1]);

                                    } elseif (is_callable(array(
                                        $this,
                                        "attribute_" . $rule[0]
                                    ))) { // check for attribute_max_len()
                                        $method = "attribute_{$rule[0]}";
                                        $attributesHTML .= $this->$method($rule[1]); // effectively: $this->attribute_options("array-transactionTypeOptions")

                                    } else { // it's a simple: id=jqueryIdentifiableName
                                        $attributesHTML .= "{$rule[0]}='{$rule[1]}' ";
                                    }

                                } else { // it's a simple: 'disabled' or |selected=selected| pre-written rule
                                    $attributesHTML .= "{$rule} ";
                                }
                            }
                        }
                        $param = $attributesHTML; // set our $var to equal old-naming convention

                        if ($inputFound == 1) {
                            $result = $this->$elementString($fieldName, $input, $param); // build the HTML input
                            if (is_array($result)) { // Validation Failed
                                $this->errors[] = $result;
                            }

                        } elseif ($rule !== "nodisplay") {
                            $result = $this->element_textbox($field);
                            if (is_array($result)) { // Validation Failed
                                $this->errors[] = $result;
                            }

                        }

                        unset($method); // cleanup.
                    }
                } // end foreach
            } else { // end if.

                $result = $this->element_textbox($fieldName); // run default textbox option.
                if (is_array($result)) { // Validation Failed
                    $this->errors[] = $result;
                }
            }
        }

        return (count($this->errors) > 0) ? $this->errors : true;
    }

    /**
     * Set a readable name for a specified field names
     *
     * @param string $field
     * @param string $readable_name
     * @return void
     */
    public static function set_field_name($field, $readable_name)
    {
        self::$fields[$field] = $readable_name;
    }

    /**
     * Process the validation errors and return human readable error messages
     *
     * @param bool   $convert_to_string = false
     * @param string $field_class
     * @param string $error_class
     * @return array
     * @return string
     */
    public function get_readable_errors(
        $convert_to_string = false,
        $field_class = "field",
        $error_class = "error-message"
    ) {
        if (empty($this->errors)) {
            return ($convert_to_string) ? null : array();
        }

        $resp = array();

        foreach ($this->errors as $e) {

            $field = ucwords(str_replace(array('_', '-'), chr(32), $e['field']));
            $param = $e['param'];

            // Let's fetch explicit field names if they exist
            if (array_key_exists($e['field'], self::$fields)) {
                $field = self::$fields[$e['field']];
            }

            switch ($e['rule']) {
                case 'mismatch' :
                    $resp[] = "There is no validation rule for <span class=\"$field_class\">$field</span>";
                    break;
                case 'validate_required':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field is required";
                    break;
                case 'validate_valid_email':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field is required to be a valid email address";
                    break;
                case 'validate_max_len':
                    if ($param == 1) {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be shorter than $param character";
                    } else {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be shorter than $param characters";
                    }
                    break;
                case 'validate_min_len':
                    if ($param == 1) {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be longer than $param character";
                    } else {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be longer than $param characters";
                    }
                    break;
                case 'validate_exact_len':
                    if ($param == 1) {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be exactly $param character in length";
                    } else {
                        $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be exactly $param characters in length";
                    }
                    break;
                case 'validate_alpha':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain alpha characters(a-z)";
                    break;
                case 'validate_alpha_numeric':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain alpha-numeric characters";
                    break;
                case 'validate_alpha_dash':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain alpha characters &amp; dashes";
                    break;
                case 'validate_numeric':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain numeric characters";
                    break;
                case 'validate_integer':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain a numeric value";
                    break;
                case 'validate_boolean':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain a true or false value";
                    break;
                case 'validate_float':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field may only contain a float value";
                    break;
                case 'validate_valid_url':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field is required to be a valid URL";
                    break;
                case 'validate_url_exists':
                    $resp[] = "The <span class=\"$field_class\">$field</span> URL does not exist";
                    break;
                case 'validate_valid_ip':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to contain a valid IP address";
                    break;
                case 'validate_valid_cc':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to contain a valid credit card number";
                    break;
                case 'validate_valid_name':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to contain a valid human name";
                    break;
                case 'validate_contains':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to contain one of these values: " . implode(', ',
                            $param);
                    break;
                case 'validate_street_address':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be a valid street address";
                    break;
                case 'validate_date':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be a valid date";
                    break;
                case 'validate_min_numeric':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be a numeric value, equal to, or higher than $param";
                    break;
                case 'validate_max_numeric':
                    $resp[] = "The <span class=\"$field_class\">$field</span> field needs to be a numeric value, equal to, or lower than $param";
                    break;
                case 'validate_starts' :
                    $resp[] = "The <span class=\"$field_class\">$field</span> field is required to start with " . $param;
                    break;
                default:
                    $resp[] = "The <span class=\"$field_class\">$field</span> field is invalid";
            }
        }

        if (!$convert_to_string) {
            return $resp;
        } else {
            $buffer = '';
            foreach ($resp as $s) {
                $buffer .= "<span class=\"$error_class\">$s</span>";
            }
            return $buffer;
        }
    }

    /**
     * Process the validation errors and return an array of errors with field names as keys
     *
     * @param null $convert_to_string
     * @return array
     */
    public function get_errors_array($convert_to_string = null)
    {
        if (empty($this->errors)) {
            return ($convert_to_string) ? null : array();
        }

        $resp = array();

        foreach ($this->errors as $e) {

            $field = ucwords(str_replace(array('_', '-'), chr(32), $e['field']));
            $param = $e['param'];

            // Let's fetch explicit field names if they exist
            if (array_key_exists($e['field'], self::$fields)) {
                $field = self::$fields[$e['field']];
            }

            switch ($e['rule']) {
                case 'mismatch' :
                    $resp[$field] = "There is no validation rule for $field";
                    break;
                case 'validate_required':
                    $resp[$field] = "The $field field is required";
                    break;
                case 'validate_valid_email':
                    $resp[$field] = "The $field field is required to be a valid email address";
                    break;
                case 'validate_max_len':
                    if ($param == 1) {
                        $resp[$field] = "The $field field needs to be shorter than $param character";
                    } else {
                        $resp[$field] = "The $field field needs to be shorter than $param characters";
                    }
                    break;
                case 'validate_min_len':
                    if ($param == 1) {
                        $resp[$field] = "The $field field needs to be longer than $param character";
                    } else {
                        $resp[$field] = "The $field field needs to be longer than $param characters";
                    }
                    break;
                case 'validate_exact_len':
                    if ($param == 1) {
                        $resp[$field] = "The $field field needs to be exactly $param character in length";
                    } else {
                        $resp[$field] = "The $field field needs to be exactly $param characters in length";
                    }
                    break;
                case 'validate_alpha':
                    $resp[$field] = "The $field field may only contain alpha characters(a-z)";
                    break;
                case 'validate_alpha_numeric':
                    $resp[$field] = "The $field field may only contain alpha-numeric characters";
                    break;
                case 'validate_alpha_dash':
                    $resp[$field] = "The $field field may only contain alpha characters &amp; dashes";
                    break;
                case 'validate_numeric':
                    $resp[$field] = "The $field field may only contain numeric characters";
                    break;
                case 'validate_integer':
                    $resp[$field] = "The $field field may only contain a numeric value";
                    break;
                case 'validate_boolean':
                    $resp[$field] = "The $field field may only contain a true or false value";
                    break;
                case 'validate_float':
                    $resp[$field] = "The $field field may only contain a float value";
                    break;
                case 'validate_valid_url':
                    $resp[$field] = "The $field field is required to be a valid URL";
                    break;
                case 'validate_url_exists':
                    $resp[$field] = "The $field URL does not exist";
                    break;
                case 'validate_valid_ip':
                    $resp[$field] = "The $field field needs to contain a valid IP address";
                    break;
                case 'validate_valid_cc':
                    $resp[$field] = "The $field field needs to contain a valid credit card number";
                    break;
                case 'validate_valid_name':
                    $resp[$field] = "The $field field needs to contain a valid human name";
                    break;
                case 'validate_contains':
                    $resp[$field] = "The $field field needs to contain one of these values: " . implode(', ', $param);
                    break;
                case 'validate_street_address':
                    $resp[$field] = "The $field field needs to be a valid street address";
                    break;
                case 'validate_date':
                    $resp[$field] = "The $field field needs to be a valid date";
                    break;
                case 'validate_min_numeric':
                    $resp[$field] = "The $field field needs to be a numeric value, equal to, or higher than $param";
                    break;
                case 'validate_max_numeric':
                    $resp[$field] = "The $field field needs to be a numeric value, equal to, or lower than $param";
                    break;
                default:
                    $resp[$field] = "The $field field is invalid";
            }
        }

        return $resp;
    }

    /**
     * Filter the input data according to the specified filter set
     *
     * @access public
     * @param  mixed $input
     * @param  array $filterSet
     * @return mixed
     * @throws Exception
     */
    public function filter(array $input, array $filterSet) // not used yet.
    {
        foreach ($filterSet as $field => $filters) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $filters = explode('|', $filters);

            foreach ($filters as $filter) {
                $params = null;

                if (strstr($filter, ',') !== false) {
                    $filter = explode(',', $filter);

                    $params = array_slice($filter, 1, count($filter) - 1);

                    $filter = $filter[0];
                }

                if (is_callable(array($this, 'filter_' . $filter))) {
                    $method = 'filter_' . $filter;
                    $input[$field] = $this->$method($input[$field], $params);
                } else {
                    if (function_exists($filter)) {
                        $input[$field] = $filter($input[$field]);
                    } else {
                        if (isset(self::$filter_methods[$filter])) {
                            $input[$field] = call_user_func(self::$filter_methods[$filter], $input[$field], $params);
                        } else {
                            throw new Exception("Filter method '$filter' does not exist.");
                        }
                    }
                }
            }
        }

        return $input;
    }








    // ################################################################################################################
    // ################################################################################################################
    // ################################################################################################################


    protected $cssBaseClass = "pure-control-form";
    protected $cssBaseWidth = "width:18em;";
    protected $cssBaseInput = "pure-input-1-3";
    protected $html = array();
    protected $viewOnly = array();
    protected $post = array();

    // fills the data input values if you submit a form and there is an error (the re-display of the form)
    public function getColumn($column)
    {
        return isset($this->listOfDBColumns[$column]) ? $this->listOfDBColumns[$column] : null;
    }

    protected function setPostData()
    {

        foreach ($this->listOfDBColumns AS $num => $field) {
            if (array_key_exists($field, $_POST)) {
                $this->post[$field] = $_POST[$field];
            } else {
                $this->post[$field] = "";
            }
        }
    }


    public function getOtherInputs()
    {
        if (!empty($this->otherInputs)) {
            return $this->otherInputs;
        } else {
            return null;
        }
    }

    public function outputJavaScript($list)
    {
        if (empty($list)) {
            $list = array();
        }

        $html = "<script>";
        foreach ($list as $number => $js) {
            $html .= $js;
        }
        $html .= "</script>";

        return $html;
    }

    protected function element_button()
    {


        if ($error) {
            return array(
                'field' => $field,
                'value' => $input[$field],
                'rule'  => __FUNCTION__,
                'param' => $param
            );
        }
    }

    protected function element_checkbox()
    {

    }

    protected function element_datetime($field, $input = null, $param = null)
    {

        if (!empty($this->post[$field])) {
            //TODO: add date var_dump to see what comes out
            //var_dump($this->post[$field]);
        }

        $this->html[$field] = <<<HEREDOC
                <div class="pure-control-group">
                    <label for="{$field}" style="{$this->cssBaseWidth}">{$field}</label>
                    <input type="text" name="{$field}" value="{$this->post[$field]}" class="{$this->cssBaseInput}" readonly {$param}>
                </div>
            
HEREDOC;
        return true;
    }

    protected function element_file()
    {

    }

    protected function element_hidden()
    {

    }

    protected function element_password()
    {

    }

    protected function element_radio()
    {

    }

    protected function element_select($field, $input = null, $param = null)
    {
        $afterHook = $this->generateAfterHook($param);
        $selected = $this->post[$field];

        $options = "<option selected='selected'></option>";
        foreach ($input as $option) {
            $options .= "<option" . ($selected === $option ? " selected=selected" : null) . ">$option</option>\n";
        }

        $this->html[$field] = <<<HEREDOC
                <div class="pure-control-group">
                    <label for="{$field}" style="{$this->cssBaseWidth}">{$field}</label>
                    <select name="{$field}" id="{$field}" class="{$this->cssBaseInput}" {$param} >
                            {$options}
                    </select> {$afterHook}
                </div>
HEREDOC;

        return true;
    }

    protected function element_textarea()
    {

    }

    public function element_textbox($field, $input = null, $param = null)
    {
        $afterHook = $this->generateAfterHook($param);

        $this->html[$field] = <<<HEREDOC
                <div class="pure-control-group">
                    <label for="{$field}" style="{$this->cssBaseWidth}">{$field}</label>
                    <input name="{$field}" value="{$this->post[$field]}" type="text" placeholder="{$field}" class="{$this->cssBaseInput}" {$param} > {$afterHook}
                </div>
HEREDOC;

        return true;

        if ($error) {
            return array(
                'field' => $field,
                'value' => $input[$field],
                'rule'  => __FUNCTION__,
                'param' => $param
            );
        }
    }

    protected function element_yesno($field, $input = null, $param = null)
    {
        $afterHook = $this->generateAfterHook($param);
        $selected = $this->post[$field];

        $options = "<option" . ($selected === "" ? " selected=selected" : null) . "></option>";
        $options .= "<option" . ($selected === "yes" ? " selected=selected" : null) . ">yes</option>";
        $options .= "<option" . ($selected === "no" ? " selected=selected" : null) . ">no</option>";

        $this->html[$field] = <<<HEREDOC
                <div class="pure-control-group">
                    <label for="{$field}" style="width:18em;">{$field}</label>
                    <select name="{$field}" id="{$field}" class="{$this->cssBaseInput}">
                    {$options}
                    </select> {$afterHook}
                </div>
HEREDOC;

        return true;
    }

    // #################################################################################################
    // #################################################################################################
    // #################################################################################################
    // #################################################################################################


    protected function input_options($input)
    {
        $list = explode('-', $input);

        if (count($list) > 1) {
            return $this->form_rules_options[$list[1]];
        } else {
            return array(); // return a blank array of options.
        }
    }

    protected function attribute_max_len($input)
    {

        if (is_int((int)$input)) {
            return 'maxlength="' . $input . '" ';
        } else {
            return ""; // return a blank array of options.
        }
    }

    protected function generateAfterHook($param)
    {

        if (!is_null($param) && strlen($param) > 0) {
            // need to pull apple out of "after='apple' readonly=''"
            $total  = strlen($param);
            $pos    = strpos($param, "after='"); // find where after=' starts
            $midpos = strpos($param, "'", $pos);
            $endpos = strpos($param, "'", $midpos + 1); // find where the ' finishes AFTER our word
            if ($pos !== false) {
                $afterHook = substr($param, ($midpos) + 1, ($total - $endpos) * -1);
            }
        }

        return empty($afterHook) ? null : $afterHook;
    }


} // EOC