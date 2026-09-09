<?php

namespace App\Fiscal;

use App\Models\FiscalRecord;

interface AeatTransport
{
    /**
     * @return array{status:string,code:?string,body:?string}
     *                                                        Status: accepted|accepted_with_issues|rejected|error
     */
    public function send(FiscalRecord $record): array;
}
