<?php

require_once 'Minifier.php';

$minifier = new Minifier();

// minify css
$minifier->setType('css')->minify();

// minify js
$minifier->setType('js')->minify();