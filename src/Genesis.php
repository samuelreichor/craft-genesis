<?php

namespace samuelreichor\genesis;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use samuelreichor\genesis\services\CsvImporterService;
use samuelreichor\genesis\services\CsvTransformerService;
use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\utilities\ImportUtil;
use yii\base\Event;
use yii\log\FileTarget;

/**
 * Genesis plugin
 *
 * @method static Genesis getInstance()
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @copyright Samuel Reichör
 * @license MIT
 * @property-read CsvValidationService $csvValidationService
 * @property-read CsvTransformerService $csvTransformerService
 * @property-read CsvImporterService $csvImporterService
 */
class Genesis extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => ['csvValidationService' => CsvValidationService::class, 'csvTransformerService' => CsvTransformerService::class, 'csvImporterService' => CsvImporterService::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_initLogger();
        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    private function _initLogger(): void
    {
        $logFileTarget = new FileTarget([
            'logFile' => '@storage/logs/genesis.log',
            'maxLogFiles' => 10,
            'categories' => ['genesis'],
            'logVars' => [],
        ]);
        Craft::getLogger()->dispatcher->targets[] = $logFileTarget;
    }

    private function attachEventHandlers(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ImportUtil::class;
        });
    }
}
