<?php
defined( 'ABSPATH' ) || exit;
?>

<!DOCTYPE html>
<html>
<body>
<div class="container">
    <div class="label-block">
        <label for="export-url">
            Use this link to import products to PP.lv
        </label>
    </div>
    <div class="input-block">
        <input id="export-url" type="text" readonly="readonly" value="<?=$exportUrl;?>">
        <input class="button" onclick="copyUrl()" type="button" value="Copy">
    </div>
</div>
<div class="container">
    <div class="label-block">Settings</div>
    <div class="input-block">
        <form method="post" action="options.php">
            <?php
            settings_fields('ppfeed_options');
            do_settings_sections('ppfeed_settings');
            do_settings_fields( 'ppfeed', 'ppfeed_settings' );
            submit_button();
            ?>
        </form>
    </div>
</div>
<div class="container">
    <a class="button" target="_blank" href="https://pp.lv/my/feed">XML / Json import</a>
    <a class="button" target="_blank" href="https://pp.lv/lv/about/info/page">FAQ</a>
</div>
</body>
<style>
    body {
        background-color: #f6f6f7;
    }
    .container {
        box-shadow: rgba(23, 24, 24, 0.05) 0px 0px 5px 0px, rgba(0, 0, 0, 0.15) 0px 1px 2px 0px;
        line-height: 20px;
        font-weight: 400;
        font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
        font-size: 14px;
        border-radius: 8px;
        background-color: #fff;
        padding: 16px;
        margin-bottom: 10px;
    }
    .label-block {
        margin-bottom: 8px;
        font-size: 18px;
        font-weight: 600;
    }
    #export-url {
        width: 80%;
    }
    .input-block input {
        font-weight: 500;
        padding: 6px 15px;
    }
    .button {
        background-color: #5985b1;
        border-bottom-color: rgb(255, 255, 255);
        border-bottom-style: none;
        border-bottom-width: 0px;
        border-left-color: rgb(255, 255, 255);
        border-left-style: none;
        border-left-width: 0px;
        border-right-color: rgb(255, 255, 255);
        border-right-style: none;
        border-right-width: 0px;
        border-top-color: rgb(255, 255, 255);
        border-radius: 5px;
        border-top-style: none;
        border-top-width: 0px;
        box-sizing: border-box;
        color: rgb(255, 255, 255);
        cursor: pointer;
        display: inline-block;
        margin-right: 10px;
        outline: rgb(255, 255, 255) none 0px;
        overflow-x: hidden;
        overflow-y: hidden;
        padding: 6px 15px;
        text-align: center;
        text-decoration-color: rgb(255, 255, 255);
        text-decoration-line: none;
        text-decoration-style: solid;
        text-decoration-thickness: auto;
        user-select: none;
        vertical-align: middle;
        white-space: normal;
    }
</style>
<script>
    function copyUrl() {
        var copyText = document.getElementById("export-url");

        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert("Copied the text: " + copyText.value);
    }
</script>
</html>
