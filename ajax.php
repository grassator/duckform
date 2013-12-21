<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta charset="utf-8">
    <script src="http://code.jquery.com/jquery-1.10.2.min.js" type="text/javascript"></script>
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
<?php echo include 'ajax-handler.php'; ?>
<script>
    $('body').on('submit', 'form', function(e) {
        e.preventDefault();
        var $form = $(this);
        $.ajax({
            type: 'POST',
            url: 'ajax-handler.php',
            data: $form.serialize(),
            success: function(data) {
                $form.after(data);
                $form.remove();
            }
        });
        $form.attr('disabled', true);
        $form.css('opacity', 0.5);
    });
</script>
</body></html>
