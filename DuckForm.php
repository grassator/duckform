<?php
class DuckForm {

    /**
     * Holds information about the fields in this form
     * @var array
     */
    public $fields = array();

    /**
     * Holds errors from the last validation of the form
     * @var array
     */
    public $errors = array();

    /**
     * HTML class for generated errors
     * @var string
     */
    public $errorClass = 'error';

    /**
     * HTML class for generated error lists
     * @var string
     */
    public $errorListClass = 'error-list';

    public $defaultErrorMessages = array(
        'required' => 'Please fill in this field',
        'required-checkbox' => 'Please check this box if you want to proceed',
        'required-radio' => 'Please select one of the options',
        'required-select' => 'Please select one of the options',
        'required-select-multiple' => 'Please select at least one of the options',
        'email' => 'Please enter a valid email address',
        'url' => 'Please enter a valid url',
    );

    /**
     * List of attributes on form elements that have a meaning for us
     * be it identification of an elements or validation
     * @var array
     */
    protected $interestingAttributes = array(
        'type', 'name', 'id', 'multiple',
        'required', 'maxlength', 'min', 'max'
    );

    /**
     * DOMDocument parsed from provided HTML
     * @var DOMDocument
     */
    public $doc;

    /**
     * DOMElement root element for the form
     * @var DOMElement
     */
    public $node;

    /**
     * Creates form from a HTML string
     * @constructor
     * @param string  $html
     * @param boolean $formId
     * @throws Exception
     */
    public function __construct($html, $formId = false) {
        $this->doc = new DOMDocument();
        if(!$this->doc->loadHTML($html)) throw new Exception("Couldn't parse HTML");

        $forms = $this->doc->getElementsByTagName('form');

        // If we haven't found any forms there's nothing else to do
        if(!$forms->length) throw new Exception("No forms found");

        // If we are looking for a specific form
        if($formId) {
            /** @var DOMElement $formToCheck */
            foreach ($forms as $formToCheck) {
                if($formToCheck->getAttribute('id') !== $formId) continue;
                $this->node = $formToCheck;
                break;
            }
        } else {
            $this->node = $forms->item(0);
        }

        // If we found no form with specified id exiting
        if(!$this->node) throw new Exception("No form with id $formId");

        /** @var $form DOMElement */
        foreach(array('input', 'select', 'textarea') as $tagName) {
            foreach ($this->node->getElementsByTagName($tagName) as $input) {
                $this->processField($input, $tagName);
            }
        }
    }

    /**
     * Constructs form from provided $file.
     * Optionally treats $files as a PHP allowing for logic execution.
     * @param  string  $filename
     * @param  boolean $formId
     * @return DuckForm
     */
    public static function fromFile($filename, $formId = false) {
        $contents = file_get_contents($filename);
        return new DuckForm($contents, $formId);
    }

    /**
     * Serializes form to html string
     * @return string
     */
    public function __toString() {
        return $this->toHTML();
    }

    /**
     * Serializes form to html string
     * @return string
     */
    public function toHTML() {
        $html = $this->doc->saveHTML();

        // PHP automatically adds DOCTYPE and html/body tags so we are getting rid of those.
        // if there was no proper doctype added by the user
        $defaultDOCTYPE = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">';
        if(strpos($html, $defaultDOCTYPE) === 0) {
            $html = str_replace(
                array($defaultDOCTYPE, '<html><body>', '</body></html>',),
                array('', '', ''),
                $html
            );
        }

        return $html;
    }

    /**
     * Return normalized value of DOM attribute where
     * 'somevalue="somevalue"' is the same as 'somevalue'
     * and means 'true'
     * @param  DOMAttr $domAttr
     * @return mixed
     */
    protected function getNormalizedAttributeValue($domAttr) {
        if($domAttr->value === "") {
            return true;
        } else {
            return $domAttr->value;
        }
    }

    /**
     * Returns currently bound values for the form
     * @return array
     */
    public function getValues() {
        $result = array();
        foreach($this->fields as $name => $fieldData) {
            if(isset($fieldData['value'])) {
                $result[$name] = $fieldData['value'];
            }
        }
        return $result;
    }

    /**
     * Gets value of a field from DOM. It is rather tricky because of
     * how radio, checkboxes and selects work
     * @param DOMElement $fieldNode
     * @param array $data
     * @return array
     */
    protected function extractValueFromDOM($fieldNode, $data) {
        $value = null;
        if($data['type'] === 'select') {
            if($fieldNode->hasAttribute('multiple')) {
                $value = array();
            }
            /** @var DOMElement $option */
            foreach ($fieldNode->getElementsByTagName('option') as $option) {
                // option has behaviour that if no value is specified then
                // value is considered to be the same as option's text
                $optionValue = $option->hasAttribute('value') ?
                    $option->getAttribute('value') :
                    $option->nodeValue;
                if($option->hasAttribute('selected')) {
                    if(is_array($value)) {
                        $value[] = $optionValue;
                    } else {
                        $value = $optionValue;
                        break;
                    }
                }
            }
        } elseif($data['type'] === 'radio' || $data['type'] === 'checkbox') {
            if($fieldNode->hasAttribute('checked')) {
                if($fieldNode->hasAttribute('value')) {
                    $value = $fieldNode->getAttribute('value');
                } else {
                    $value = 'on';
                }
            }
        } else {
            if($fieldNode->hasAttribute('value')) {
                $value = $fieldNode->getAttribute('value');
            }
        }
        $data['value'] = $value;
        return $data;
    }

    /**
     * Extracts field information from DOMElement
     * @param DOMElement $fieldNode
     * @param string $tagName
     */
    protected function processField($fieldNode, $tagName) {
        $data = array(
            "tag" => strtolower($fieldNode->nodeName),
            "type" => $tagName === "input" ? "text" : $tagName,
            // array is needed for fields with names like name="test[]"
            "nodes" => array($fieldNode),
        );

        // Processing present field attributes
        foreach ($fieldNode->attributes as $attribute) {
            // Normalizing the name
            $attrName = strtolower($attribute->nodeName);

            // Checking that it is an interesting node for us
            if(!in_array(
                $attrName,
                $this->interestingAttributes
            )) continue;

            $value = $this->getNormalizedAttributeValue($attribute);
            if(isset($value)) {
                $data[$attrName] = $value;
            }
        }

        // Name is kind of special case because it can be specified via
        // both "name" and "id" attributes while "name" takes precedence
        $name = isset($data['name']) ? $data['name'] :
            (isset($data['id']) ? $data['id'] : false);
        if(isset($data['name'])) unset($data['name']);
        if(isset($data['id'])) unset($data['id']);

        // if field doesn't have a "name" or an "id" then it won't be
        // in $_REQUEST and thus it is useless to us
        if(!$name) return;

        $data = $this->extractValueFromDOM($fieldNode, $data);

        // Support for name="someName[]" notation that allows
        // multiple fields to have the same name to be processed
        // in a $_REQUEST as an array
        $isMultiple = 0;
        $name = preg_replace("/\\[\\s*\\]$/", '', $name, -1, $isMultiple);
        if(isset($this->fields[$name])) {
            if($isMultiple) {
                $this->fields[$name]['multiple'] = true;
            }
            $this->fields[$name]['nodes'][] = $fieldNode;

            // Merging values
            if(!is_array($this->fields[$name]['value'])) {
                if(isset($this->fields[$name]['value'])) {
                    $this->fields[$name]['value'] = array($this->fields[$name]['value']);
                }
            }
            if(isset($data['value'])) {
                if(!is_array($this->fields[$name]['value'])){
                    $this->fields[$name]['value'] = array();
                }
                $this->fields[$name]['value'][] = $data['value'];
            }

            // Appending new attributes if any but not rewriting
            // this is helpful if for example "required" attribute
            // is defined not on the first of radio buttons
            foreach($data as $key => $value) {
                if(!isset($this->fields[$name][$key])) {
                    $this->fields[$name][$key] = $value;
                }
            }
        } else {
            $this->fields[$name] = $data;
        }
    }

    public function bindField($name, $value, $writeToDocument = true) {
        $fieldData = &$this->fields[$name];

        // Saving the value internally
        $fieldData['value'] = $value;

        // just some shortcuts for easier access
        $tag = $fieldData['tag'];
        $type = $fieldData['type'];
        /** @var DOMElement */
        $node = $fieldData['nodes'][0];

        // Since radio buttons and checkboxes aren't sent
        // to the server by browser when they are not checked
        // we need to first toggle of all fields of that type
        if($type === "radio" || $type === "checkbox") {
            $this->setCheckboxOrRadioChecked($name, false);
        }

        // if we need to render bound values into DOMDocument
        if($writeToDocument) {
            if($tag === 'input') {
                $this->writeInputValueToDocument($fieldData, $value);
            } elseif($tag === 'textarea') {
                $node->nodeValue = $value;
            } elseif($tag === 'select') {
                $this->writeSelectValueToDocument($node, $value);
            }
        }
    }

    /**
     * Binds data to form fields
     * @param  array $data
     * @param bool $writeToDocument
     */
    public function bind($data = null, $writeToDocument = true) {
        if(is_null($data)) $data = $_REQUEST;
        foreach (array_keys($this->fields) as $name) {
            // If there is no new value to bind we just exit
            if(!isset($data[$name])) {
                $this->setCheckboxOrRadioChecked($name, false);
            } else {
                $this->bindField($name, $data[$name], $writeToDocument);
            }
        }
    }

    /**
     * Properly unchecks radio or checkbox button groups
     * or single checkboxes
     * @param string $name
     * @param bool $value
     */
    protected function setCheckboxOrRadioChecked($name, $value = true) {
        foreach($this->fields[$name]['nodes'] as $node) {
            $this->toggleAttribute($node, 'checked', $value);
        }
    }

    /**
     * Writes select value to DOM
     * @param DOMElement $node
     * @param string|array $value
     */
    protected function writeSelectValueToDocument($node, $value) {
        if (!is_array($value)) $value = array($value);
        /** @var $option DOMElement */
        // For select we have to iterate over it's options
        // and we can't just use faster childNodes because
        // possibility that there will be groups as well
        foreach ($node->getElementsByTagName('option') as $option) {
            // option has behaviour that if no value is specified then
            // value is considered to be the same as option's text
            $optionValue = $option->hasAttribute('value') ?
                $option->getAttribute('value') :
                $option->nodeValue;

            // Toggling necessary options on and off
            if (in_array($optionValue, $value)) {
                $option->setAttribute('selected', 'selected');
            } else {
                $option->removeAttribute('selected');
            }
        }
    }


    /**
     * Toggles checked attribute
     * @param DOMElement $node
     * @param string $attrName
     * @param bool|null $condition
     */
    protected function toggleAttribute($node, $attrName, $condition) {
        if ($condition) {
            $node->setAttribute($attrName, $attrName);
        } else {
            $node->removeAttribute($attrName);
        }
    }

    /**
     * Writes input value to DOM
     * @param array $fieldData
     * @param string|array $value
     */
    protected function writeInputValueToDocument($fieldData, $value) {
        /** @var $node DOMElement */
        $node = $fieldData['nodes'][0];
        $type = $fieldData['type'];

        // For checkboxes and radio buttons we need to find
        // the ones with the same value and check them
        if($type === 'checkbox' || $type === 'radio') {
            // We always need to check multiple nodes here
            foreach ($fieldData['nodes'] as $node) {
                // if we are dealing with multiple checkboxes with the same name
                if (is_array($value)) {
                    // then we toggle all checkboxes and radio buttons to proper state
                    $this->toggleAttribute(
                        $node, 'checked',
                        in_array($node->getAttribute("value"), $value)
                    );
                } else { // we are dealing with radio buttons or single checkbox
                    $this->toggleAttribute(
                        $node, 'checked',
                        $node->getAttribute("value") === $value ||
                        // If value isn't specified for a radio button or checkboxes,
                        // browsers send a default value ("on").
                        // http://www.w3.org/TR/html5/forms.html#checkbox-state-(type=checkbox)
                        // http://www.w3.org/TR/html5/forms.html#dom-input-value-default-on
                        (!$node->hasAttribute("value") && $value === "on")
                    );
                }
            }
        } elseif ($type === 'password') { // we are not saving password fields.
            return;
        } else { // text, email, url, tel ...
            $node->setAttribute('value', $value);
        }
    }

    /**
     * Validates the form, the errors if any will be located in
     * 'errors' attribute of the form
     * @param  bool $enforceFieldTypes e.g. validate that "email" field has a valid email
     * @param bool $writeToDocument
     * @return bool
     */
    public function validate($enforceFieldTypes = true, $writeToDocument = true) {
        $this->errors = array();

        foreach ($this->fields as $name => &$fieldData) {
            $fieldErrors = array();

            // Checking for present value if the field is required
            if(!empty($fieldData['required'])) {
                if(empty($fieldData['value'])) {
                    if($fieldData['type'] === 'radio') {
                        $fieldErrors[] = $this->defaultErrorMessages['required-radio'];
                    } elseif ($fieldData['type'] === 'checkbox') {
                        $fieldErrors[] = $this->defaultErrorMessages['required-checkbox'];
                    } elseif ($fieldData['type'] === 'select') {
                        if(empty($fieldData['multiple'])) {
                            $fieldErrors[] = $this->defaultErrorMessages['required-select'];
                        } else {
                            $fieldErrors[] = $this->defaultErrorMessages['required-select-multiple'];
                        }
                    } else {
                        $fieldErrors[] = $this->defaultErrorMessages['required'];
                    }
                }
            }

            // Default custom validators
            if($enforceFieldTypes && !empty($fieldData['value'])) {
                if (
                    $fieldData['type'] === 'email' &&
                    // Using such a generic expression to support
                    // international emails like "имя@домен.рф"
                    !preg_match("/[^\\s@]+@[^\\s@]+\\.[^\\s@]+/", $fieldData['value'])
                ) {
                    $fieldErrors[] = $this->defaultErrorMessages['email'];
                }
            }

            if($fieldErrors) {
                $this->errors[$name] = $fieldErrors;
            }
        }

        // Writing errors to document if necessary
        if($writeToDocument && $this->errors) {

            // Using html5 autofocus attribute to focus first field with error
            $isAutoFocusSet = false;

            foreach($this->errors as $name => $errorList) {
                $errorListElement = $this->doc->createElement('div');
                $errorListElement->setAttribute('class', $this->errorListClass);

                foreach($errorList as $error) {
                    $errorElement = $this->doc->createElement('div');
                    $errorElement->setAttribute('class', $this->errorClass);
                    $errorElement->nodeValue = $error;
                    $errorListElement->appendChild($errorElement);
                }

                /** @var DOMElement $targetElement */
                $targetElement = $this->fields[$name]['nodes'][0];

                if(!$isAutoFocusSet && $this->fields[$name]['type'] === 'text') {
                    $targetElement->setAttribute('autofocus', 'autofocus');
                    $isAutoFocusSet = true;
                }

                // Special case usually for checkboxes and radio buttons
                // when label is wrapped around an element.
                if (strtolower($targetElement->parentNode->nodeName) === 'label') {
                    $targetElement = $targetElement->parentNode;
                }

                // Adding error list to the document
                $targetElement->parentNode->insertBefore($errorListElement, $targetElement);
            }
        }

        return !$this->errors;
    }
}
