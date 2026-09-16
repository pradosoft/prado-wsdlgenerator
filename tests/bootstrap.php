<?php

/**
 * Bootstrap for the unit tests.
 *
 * The generator resolves a complex type by reflecting on the class name written
 * in a doc comment, and those names carry no namespace. The types under test
 * therefore live in the global namespace, where psr-4 cannot reach them, and
 * are loaded here instead.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/unit/Fixtures/types.php';
require __DIR__ . '/unit/Fixtures/providers.php';
