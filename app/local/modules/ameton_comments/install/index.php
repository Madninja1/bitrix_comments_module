<?php

defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Ameton\Comments\Config\Settings;

class ameton_comments extends CModule
{
    public $MODULE_ID = 'ameton.comments';
    public $MODULE_GROUP_RIGHTS = 'Y';

    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;

    public $MODULE_NAME = 'Ameton Comments';
    public $MODULE_DESCRIPTION = 'Комментарии для новостей';
    public $PARTNER_NAME = 'Daniel';
    public $PARTNER_URI = '';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '0.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d');
    }

    public function DoInstall(): void
    {
        global $APPLICATION;

        if (!Loader::includeModule('main')) {
            $APPLICATION->ThrowException('Bitrix main module is required');
            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        Loader::includeModule($this->MODULE_ID);

        $this->installDB();
        Settings::installDefaults();
    }

    public function DoUninstall(): void
    {
        global $APPLICATION;

        if (!Loader::includeModule('main')) {
            $APPLICATION->ThrowException('Bitrix main module is required');
            return;
        }

        Loader::includeModule($this->MODULE_ID);

        // Сначала удаляем сущности/опции, затем модуль
        $this->uninstallDB();
        Settings::uninstallDefaults();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installDB(): void
    {
        $connection = Application::getConnection();
        $sqlPath = __DIR__ . '/db/mysql/install.sql';

        if (is_file($sqlPath)) {
            $connection->queryExecute(file_get_contents($sqlPath));
        }
    }

    public function uninstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlPath = __DIR__ . '/db/mysql/uninstall.sql';

        if (is_file($sqlPath)) {
            $connection->queryExecute(file_get_contents($sqlPath));
        }
    }
}