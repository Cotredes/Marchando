<?php

namespace App;

use App\Models\DiningTable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

class DiningTableQrCode
{
    public function url(DiningTable $table): string
    {
        return route('tables.resolve', ['token' => $table->qr_token]);
    }

    public function svg(DiningTable $table): string
    {
        return (new Builder(
            writer: new SvgWriter,
            data: $this->url($table),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 360,
            margin: 16,
        ))->build()->getString();
    }
}
