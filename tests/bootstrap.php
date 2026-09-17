<?php

/**
 * Bootstrap for the unit tests.
 *
 * The generator resolves a complex type by reflecting on the class name written
 * in a doc comment. A name without a namespace resolves in the global one, so
 * the types and providers under test live there, where psr-4 cannot reach them,
 * and are loaded here instead. The namespaced fixtures are loaded the same way,
 * because a file holding several classes is not one psr-4 can find.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/unit/Fixtures/types.php';
require __DIR__ . '/unit/Fixtures/providers.php';
require __DIR__ . '/unit/Fixtures/namespaced.php';
