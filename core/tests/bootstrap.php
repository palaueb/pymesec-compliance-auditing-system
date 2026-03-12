<?php

$statePath = __DIR__.'/../storage/app/private/plugin-state.testing.json';

if (is_file($statePath)) {
    unlink($statePath);
}

require __DIR__.'/../vendor/autoload.php';
