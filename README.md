# Duck Form

Provides a way to use regular HTML5 form markup to populate forms with values and validate them on server side with PHP. It's also quite fast.

## Why "Duck"?

Ducks are cool!

## Usage

Simple usage looks like this:

```php
require 'DuckForm.php';

$form =  DuckForm::fromFile("file_with_form_markup.html");

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form->bind($_REQUEST);
    if($form->validate()) {
        // Form processing should go here
        
        // redirecting user to a thank you page
        header('Location: /thankyou.html');
        exit;
    }
}
echo $form;
```

It will render errors if necessary and will re-fill submitted form data. 

## Requirements

PHP 5 with [DOM extension](http://www.php.net/manual/en/book.dom.php), which is bundled by default.

## Configuration

You can customize html classes for generated errors.

```php
$form->errorClass = 'my-error-class';
$form->errorListClass = 'my-error-list-class';
```

## Known Issues / Missing Features

* No file upload support
* Lack of validators except for required and email
* Lack of custom validator support

## Licensing

Licensed under permissive [MIT-style license](https://github.com/grassator/duckform/blob/master/LICENSE-MIT).
