<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    "PARAMETERS" => [
        "NEWS_ID" => [
            "PARENT" => "BASE",
            "NAME" => "News ID",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "ROOT_LIMIT" => [
            "PARENT" => "BASE",
            "NAME" => "Root comments per page",
            "TYPE" => "STRING",
            "DEFAULT" => "10",
        ],
    ],
];