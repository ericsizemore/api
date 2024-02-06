<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests/src',
    ]);

    //$rectorConfig->importNames();
    $rectorConfig->rule(PreferPHPUnitSelfCallRector::class);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::NAMING,
        SetList::STRICT_BOOLEANS,
        //SetList::PRIVATIZATION,
        //SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
    $rectorConfig->skip([
        LocallyCalledStaticMethodToNonStaticRector::class,
        PreferPHPUnitThisCallRector::class,
    ]);
};
