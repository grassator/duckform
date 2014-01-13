<?php

require_once __DIR__.'/../DuckForm.php';

class DuckFormTest extends PHPUnit_Framework_TestCase {
    protected $formPath;

    /**
     * @var DuckForm
     */
    public $form;

    public $data = array(
        "fullName" => "John Smith",
        "password" => "qwerty",
        "choice" => "yes",
        "possibilities" => array("one"),
        "agree" => "on",
        "singleCountry" => "United States",
        "country" => array("ru", "uk"),
        "message" => "Blah-Blah",
    );

    public function setUp() {
        $this->formPath = __DIR__."/../fixtures/test.html";
        $this->form = DuckForm::fromFile($this->formPath);
    }

    public function testValidationForEmptyForm() {
        $this->assertFalse($this->form->validate(), 'Should fail validation when empty');
        // Doing very crude counting of how many errors should be there
        $requiredCount = preg_match_all('/<.*?required.*?>/', file_get_contents($this->formPath));
        $this->assertCount($requiredCount, $this->form->errors, "Should have {$requiredCount} errors when empty");
    }

    public function testCustomFieldValidator()
    {
        $this->form->addFieldValidator('country', function($value, $fields, $form){
            if(count($value) < 3) {
                return "There should be more than 2 countries selected.";
            }
            return false;
        });
        $this->form->bind($this->data);
        $this->assertFalse($this->form->validate());
    }

    public function testCustomFormValidator()
    {
        $this->form->addFormValidator(function($fields, &$errors, $form){
            $value = $fields['country']['value'];
            if(count($value) < 3) {
                if(!isset($errors['country'])) {
                    $errors['country'] = array();
                }
                $errors['country'][] = "There should be more than 2 countries selected.";
            }
            return false;
        });
        $this->form->bind($this->data);
        $this->assertFalse($this->form->validate());
    }

    public function testDataBinding() {
        $this->form->bind($this->data, false);
        foreach($this->data as $name => $value) {
            $this->assertEquals(
                $this->form->fields[$name]['value'],
                $value,
                'Should support binding data'
            );
        }
    }

    public function testDataBindingToDocument() {
        $this->form->bind($this->data);

        $this->assertEquals(
            $this->form->doc->getElementById('fullNameId')->getAttribute('value'),
            $this->data['fullName'],
            'Should support writing data to text <input>'
        );

        $this->assertEquals(
            $this->form->doc->getElementById('messageId')->nodeValue,
            $this->data['message'],
            'Should support writing data to text <textarea>'
        );

        $this->assertTrue(
            $this->form->doc->getElementById('choiceYesId')->hasAttribute('checked'),
            'Should support checking<input type="radio">'
        );

        $this->assertTrue(
            $this->form->doc->getElementById('agreeId')->hasAttribute('checked'),
            'Should support checking single <input type="checkbox">'
        );

        $this->assertFalse(
            $this->form->doc->getElementById('subscribeId')->hasAttribute('checked'),
            'Should support unchecking previous value for single <input type="checkbox">'
        );

        $this->assertTrue(
            $this->form->doc->getElementById('possibilityOneId')->hasAttribute('checked'),
            'Should support multiple <input type="checkbox">'
        );

        $this->assertFalse(
            $this->form->doc->getElementById('possibilityTwoId')->hasAttribute('checked'),
            'Should support unchecking previous value for multiple <input type="checkbox">'
        );

        $this->assertTrue(
            $this->form->doc->getElementById('singleCountryUsOptionId')->hasAttribute('selected'),
            'Should support selecting option in <select>'
        );
    }

    public function testValidationForBoundForm() {
        $this->form->bind($this->data);
        $this->assertTrue($this->form->validate(), 'Should pass validation with data bound');
    }

    public function testEmailValidation() {
        $data = $this->data;
        $data['email'] = 'not-a-proper-email';
        $this->form->bind($data);
        $this->assertFalse($this->form->validate(true, false), 'Should fail with invalid email');
        $this->form->bindField('email', 'valid@example.com');
        $this->assertTrue($this->form->validate(true, false), 'Should pass with regular email');
        $this->form->bindField('email', 'имя@домен.рф');
        $this->assertTrue($this->form->validate(true, false), 'Should pass with international email');
    }

    /**
     * @expectedException Exception
     */
    public function testNoFormInHTML() {
        $form = new DuckForm("");
    }

    /**
     * @expectedException Exception
     */
    public function testNoFormInHTMLWithSpecifiedId() {
        $form = new DuckForm("<form></form>", 'someFormId');
    }

    public function testFormWithSpecifiedId() {
        $form = new DuckForm(
            '<form id="wrongId"></form><form id="rightId"></form><form></form>',
            'rightId'
        );
        $this->assertEquals(
            $form->node->getAttribute('id'), 'rightId',
            "Should be able to get the right form from HTML"
        );
    }

    public function testHTMLOutput() {
        $html = $this->form->toHTML();
        $newForm = new DuckForm($html);
        $this->assertEquals(
            $html, "$newForm",
            'Form transformation to HTML without binding should produce equivalent DOM'
        );

        foreach($this->form->fields as $name => &$fieldData) {
            foreach($fieldData as $key => $value) {
                // We can't and don't need to test equality of actual DOMNodes
                if($key === 'nodes') continue;
                $this->assertEquals(
                    $value, $newForm->fields[$name][$key],
                    'Form transformation to/from HTML should produce equivalent DuckForm metadata'
                );
            }
        }

        $this->form->bind($this->data);
        $html = $this->form->toHTML();
        $newForm = new DuckForm($html);
        $this->assertEquals(
            $html, "$newForm",
            'Form transformation to HTML with bound values should produce equivalent DOM'
        );
    }

    public function testGettingValuesFromHtml() {
        $values = $this->form->getValues();
        $this->assertEquals(array(
            'hiddenData' => '1234',
            'possibilities' => array('two'),
            'subscribe' => '1',
            'country' => array('ru'),
        ), $values, "should collect values from HTML when initializing");
    }
}
