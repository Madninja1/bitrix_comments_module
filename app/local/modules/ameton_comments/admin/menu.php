<?php

use Bitrix\Main\Localization\Loc;

return [
    'parent_menu' => 'global_menu_services',
    'sort' => 2000,
    'text' => 'Ameton Comments',
    'title' => 'Ameton Comments',
    'icon' => 'sys_menu_icon',
    'page_icon' => 'sys_page_icon',
    'items_id' => 'menu_ameton_comments',
    'items' => [
        [
            'text' => 'Панель',
            'url' => 'ameton_comments_panel.php?lang=' . LANGUAGE_ID,
            'more_url' => ['ameton_comments_panel.php'],
        ],
        [
            'text' => 'Настройки',
            'url' => 'ameton_comments_options.php?lang=' . LANGUAGE_ID,
            'more_url' => ['ameton_comments_options.php'],
        ],
        [
            'text' => 'Сидирование',
            'url' => 'ameton_comments_seed.php?lang=' . LANGUAGE_ID,
            'more_url' => ['ameton_comments_seed.php'],
        ],
        [
            'text' => 'Комментарии',
            'url' => 'ameton_comments_list.php?lang=' . LANGUAGE_ID,
            'more_url' => ['ameton_comments_list.php', 'ameton_comments_edit.php'],
        ],
    ],
];