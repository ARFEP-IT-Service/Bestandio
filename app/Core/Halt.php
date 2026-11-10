<?php

declare(strict_types=1);

namespace Bestandio\Core;

use RuntimeException;

/** Stops request handling after a response (e.g. redirect) has been prepared. */
final class Halt extends RuntimeException
{
}
