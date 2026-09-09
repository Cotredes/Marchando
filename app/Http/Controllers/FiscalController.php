<?php

namespace App\Http\Controllers;

use App\FiscalService;
use App\Models\FiscalRecord;
use App\Models\Restaurant;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class FiscalController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewFiscal', $restaurant);
        $records = $restaurant->fiscalRecords()->with(['identity', 'document'])->latest('id')->paginate(30);
        $counts = $restaurant->fiscalRecords()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('fiscal.index', ['restaurant' => $restaurant, 'records' => $records, 'counts' => $counts, 'integration' => app(FiscalService::class)->integration($restaurant)]);
    }

    public function show(Restaurant $restaurant, FiscalRecord $fiscalRecord): View
    {
        $this->authorize('viewFiscal', $restaurant);
        abort_unless($fiscalRecord->restaurant_id === $restaurant->id, 404);

        return view('fiscal.show', ['restaurant' => $restaurant, 'record' => $fiscalRecord->load(['identity', 'document', 'tries'])]);
    }

    public function qr(Restaurant $restaurant, FiscalRecord $fiscalRecord)
    {
        $this->authorize('viewFiscal', $restaurant);
        abort_unless($fiscalRecord->restaurant_id === $restaurant->id, 404);
        $svg = (new Builder(writer: new SvgWriter, data: $fiscalRecord->qr_content, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 240, margin: 8))->build()->getString();

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function retry(Restaurant $restaurant, FiscalRecord $fiscalRecord): RedirectResponse
    {
        $this->authorize('viewFiscal', $restaurant);
        abort_unless($fiscalRecord->restaurant_id === $restaurant->id, 404);
        try {
            app(FiscalService::class)->retry($fiscalRecord);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['fiscal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reintento de envío lanzado.');
    }

    public function anular(Restaurant $restaurant, FiscalRecord $fiscalRecord): RedirectResponse
    {
        $this->authorize('viewFiscal', $restaurant);
        abort_unless($fiscalRecord->restaurant_id === $restaurant->id, 404);
        try {
            $anulation = app(FiscalService::class)->anular($fiscalRecord, request()->user(), (string) request('reason'));
            $fiscalRecord->document?->order?->events()->create(['restaurant_id' => $restaurant->id, 'user_id' => request()->user()->id, 'type' => 'fiscal_anulled', 'data' => ['record_id' => $anulation->id, 'reason' => (string) request('reason')]]);
        } catch (\Throwable $exception) {
            return back()->withErrors(['fiscal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Registro de anulación generado y enviado. El alta original se conserva.');
    }
}
