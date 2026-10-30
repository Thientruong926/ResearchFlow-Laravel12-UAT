<?php

namespace App\Contracts;

use App\Data\SourceDefinition;
use App\Services\ConnectionReadResult;

interface SurveyResponseReader
{
    /** Read only. Deadline uses microtime(true). Never persist rows or fall back to another transport. */
    public function read(SourceDefinition $source, ?float $deadline = null): ConnectionReadResult;
}
