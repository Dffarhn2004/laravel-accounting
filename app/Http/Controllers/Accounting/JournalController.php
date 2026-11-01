<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Accounting\LedgerService;
use App\Support\Accounting\JournalSource;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Jurnal Umum (PRD 05-LAPORAN.md #1): every journal line with its business source, filtered by
 * branch, source, month and free text. Read only; corrections are reversals elsewhere.
 */
class JournalController extends Controller
{
    private const PER_PAGE = 50;

    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{branch_id:?int, source:?string, year:?int, month:?int, search:?string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'source' => ['nullable', Rule::in([...array_keys(JournalSource::LABELS), 'other'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return $data + ['branch_id' => null, 'source' => null, 'year' => null, 'month' => null, 'search' => null];
    }

    public function index(Request $request, LedgerService $ledger): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $restrict = $request->user()->branch_id;
        $query = $ledger->lines($companyId, $filters, $restrict);

        $page = (clone $query)->paginate(self::PER_PAGE)->withQueryString();
        $items = $page->getCollection();
        $running = $ledger->netBefore($companyId, $items, $restrict ?? $filters['branch_id']);
        $urls = $ledger->sourceUrls($items);
        $branches = Branch::where('company_id', $companyId)->pluck('name', 'id');

        $rows = $items->map(function ($r) use (&$running, $urls, $branches) {
            $running[$r->account_id] = bcadd($running[$r->account_id] ?? '0.00', bcsub(LedgerService::money($r->debit), LedgerService::money($r->credit), 2), 2);
            $date = \Illuminate\Support\Carbon::parse($r->posted_on);

            return [
                'line_id' => $r->line_id,
                'entry_id' => $r->entry_id,
                'source' => JournalSource::label($r->source_kind),
                'source_kind' => $r->source_kind,
                'reference' => $r->reference ?: 'JE-'.$r->entry_id,
                'branch' => $branches[$r->branch_id] ?? null,
                'year' => $date->year,
                'month' => LedgerService::MONTHS[$date->month],
                'date' => $date->toDateString(),
                'account_code' => $r->account_code,
                'account_name' => $r->account_name,
                'description' => $r->line_description ?: $r->entry_description,
                'debit' => LedgerService::money($r->debit),
                'credit' => LedgerService::money($r->credit),
                'balance' => LedgerService::normalSigned($running[$r->account_id], $r->account_type, (bool) $r->is_contra),
                'source_url' => $urls[$r->line_id] ?? null,
            ];
        })->values();

        return Inertia::render('accounting/journal', [
            'rows' => $rows,
            'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'totals' => $ledger->totals($query),
            'filters' => $filters,
            'branches' => Branch::where('company_id', $companyId)->when($restrict, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'sources' => collect(JournalSource::LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->push(['key' => 'other', 'label' => 'Lainnya'])->all(),
            'years' => DB::table('journal_entries')->where('company_id', $companyId)->selectRaw('DISTINCT '.(DB::getDriverName() === 'sqlite' ? "CAST(strftime('%Y', posted_on) AS INTEGER)" : 'YEAR(posted_on)').' as y')->orderByDesc('y')->pluck('y'),
            'months' => array_slice(LedgerService::MONTHS, 1),
        ]);
    }

    public function export(Request $request, LedgerService $ledger): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $restrict = $request->user()->branch_id;
        $query = $ledger->lines($companyId, $filters, $restrict);
        $branches = Branch::where('company_id', $companyId)->pluck('name', 'id');

        return response()->streamDownload(function () use ($query, $ledger, $companyId, $restrict, $filters, $branches) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, ['Sumber', 'No Trx', 'Cabang', 'Tahun', 'Bulan', 'Tanggal', 'No Akun', 'Nama Akun', 'Keterangan', 'Debit', 'Kredit', 'Saldo']);
            $running = [];
            foreach ($query->cursor() as $r) {
                if (! isset($running[$r->account_id])) {
                    $running += $ledger->netBefore($companyId, collect([$r]), $restrict ?? $filters['branch_id']);
                }
                $running[$r->account_id] = bcadd($running[$r->account_id], bcsub(LedgerService::money($r->debit), LedgerService::money($r->credit), 2), 2);
                $date = \Illuminate\Support\Carbon::parse($r->posted_on);
                fputcsv($out, [
                    JournalSource::label($r->source_kind), Csv::cell($r->reference ?: 'JE-'.$r->entry_id), Csv::cell($branches[$r->branch_id] ?? ''),
                    $date->year, LedgerService::MONTHS[$date->month], $date->toDateString(), Csv::cell($r->account_code), Csv::cell($r->account_name),
                    Csv::cell($r->line_description ?: $r->entry_description), LedgerService::money($r->debit), LedgerService::money($r->credit),
                    LedgerService::normalSigned($running[$r->account_id], $r->account_type, (bool) $r->is_contra),
                ]);
            }
            fclose($out);
        }, 'jurnal-umum-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
