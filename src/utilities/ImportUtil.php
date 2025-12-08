<?php

namespace samuelreichor\genesis\utilities;

use Craft;
use craft\base\Utility;
use samuelreichor\genesis\Genesis;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;

/**
 * Import Util utility
 */
class ImportUtil extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('genesis', 'Genesis');
    }

    public static function id(): string
    {
        return 'genesis';
    }

    public static function icon(): ?string
    {
        return 'upload';
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    public static function contentHtml(): string
    {
        $csvService = Genesis::getInstance()->csvValidationService;

        $allowedColumns = [];
        $requiredColumns = [];

        foreach ($csvService->getSupportedElementTypes() as $type) {
            $allowedColumns[$type] = $csvService->getAllowedColumns($type);
            $requiredColumns[$type] = $csvService->getRequiredColumns($type);
        }

        return Craft::$app->getView()->renderTemplate('genesis/utilities/import', [
            'allowedColumns' => $allowedColumns,
            'requiredColumns' => $requiredColumns,
        ]);
    }
}
