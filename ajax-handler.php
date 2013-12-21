<?php

require 'DuckForm.php';
$form =  DuckForm::fromFile("fixtures/test.html");

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form->bind();
    if($form->validate()) {
        echo "<h1 style='color: green'>Valid!</h1>";
    } else {
        echo "<h1 style='color: red'>Invalid!</h1>";
        echo $form;
    }
}

return $form;
