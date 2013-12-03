<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }
        .error-list {
            margin: 0 0 4px;
        }
        .error {
            background-color: #ecc;
            border: 1px solid #e99;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
            color: #900;
            margin: 0 5px 0 0;
        }

        hr {
            border: none;
            height: 1px;
            width: 240px;
            margin-left: 0;
            background-color: #ddd;
        }
    </style>
    <title>HtmlForm Demo Page</title>
</head>
<body>
<?php
error_reporting(E_ALL | E_NOTICE);

$startTime = microtime(true);

require 'DuckForm.php';

$form =  DuckForm::fromFile("fixtures/test.html");

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form->bind();
    if($form->validate()) {
        echo "<h1 style='color: green'>Valid!</h1>";
    } else {
        echo "<h1 style='color: red'>Invalid!</h1>";
    }
}

echo "<hr>";
echo $form;
echo "<hr>".round((microtime(true) - $startTime) * 1000, 3)." ms";
?>
</body></html>
