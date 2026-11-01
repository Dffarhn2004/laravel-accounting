<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Services\Accounting\LedgerService;
use App\Support\Accounting\JournalSource;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan Buku Besar (PRD 05-LAPORAN.md #6): the detail of one account and, always on the same
 * page, the summary of every account (opening, debit, credit, closing). Balances are on the
 * account's normal side.
 */
class LedgerController extends Controller
{
    private const PER_PAGE = 100;

    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{account_id:?int, branch_id:?int, from:?string, to:?string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return $data + ['account_id' => null, 'branch_id' => null, 'from' => null, 'to' => null];
    }

    public function index(Request $request, LedgerService $ledger): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $restrict = $request->user()->branch_id;
        $branch = $restrict ?? $filters['branch_id'];

        $summary = $ledger->summary($companyId, $filters['from'], $filters['to'], $branch);
        $detail = null;

        if ($filters['account_id']) {
            $account = Account::where('company_id', $companyId)->findOrFail($filters['account_id']);
            $opening = collect($summary)->firstWhere('account_id', $account->id)['opening'];
            $sign = LedgerService::debitNormal($account->type, (bool) $account->is_contra) ? '1' : '-1';
            $query = $ledger->lines($companyId, ['account_id' => $account->id, 'from' => $filters['from'], 'to' => $filters['to']], $branch);

            $page = (clone $query)->paginate(self::PER_PAGE)->withQueryString();
            $items = $page->getCollection();

            // Balance carried into this page = opening + the signed movement of every earlier line.
            $offset = ($page->currentPage() - 1) * self::PER_PAGE;
            $balance = $opening;
            if ($offset > 0) {
                foreach ((clone $query)->limit($offset)->get(['l.debit', 'l.credit']) as $earlier) {
                    $balance = bcadd($balance, bcmul(bcsub(LedgerService::money($earlier->debit), LedgerService::money($earlier->credit), 2), $sign, 2), 2);
                }
            }

            $counters = $ledger->counterAccounts($items);
            $urls = $ledger->sourceUrls($items);
            $rows = $items->map(function ($r) use (&$balance, $sign, $counters, $urls) {
                $balance = bcadd($balance, bcmul(bcsub(LedgerService::money($r->debit), LedgerService::money($r->credit), 2), $sign, 2), 2);

                return [
                    'line_id' => $r->line_id,
                    'entry_id' => $r->entry_id,
                    'date' => Carbon::parse($r->posted_on)->toDateString(),
                    'reference' => $r->reference ?: 'JE-'.$r->entry_id,
                    'source' => JournalSource::label($r->source_kind),
                    'description' => $r->line_description ?: $r->entry_description,
                    'counter' => $counters[$r->line_id] ?? '',
                    'debit' => LedgerService::money($r->debit),
                    'credit' => LedgerService::money($r->credit),
                    'balance' => $balance,
                    'source_url' => $urls[$r->line_id] ?? null,
                ];
            })->values();

            $detail = [
                'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'normal' => $sign === '1' ? 'Debit' : 'Kredit'],
                'opening' => $opening,
                'rows' => $rows,
                'totals' => $ledger->totals($query),
                'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            ];
        }

        return Inertia::render('accounting/ledger', [
            'summary' => $summary,
            'summaryTotals' => [
                'debit' => array_reduce($summary, fn ($c, $r) => bcadd($c, $r['debit'], 2), '0.00'),
                'credit' => array_reduce($summary, fn ($c, $r) => bcadd($c, $r['credit'], 2), '0.00'),
            ],
            'detail' => $detail,
            'filters' => $filters,
            'branches' => Branch::where('company_id', $companyId)->when($restrict, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /** type=summary exports every account; type=detail exports the selected account's lines. */
    public function export(Request $request, LedgerService $ledger): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $branch = $request->user()->branch_id ?? $filters['branch_id'];
        $type = $request->query('type') === 'detail' ? 'detail' : 'summary';
        abort_if($type === 'detail' && ! $filters['account_id'], 422, 'Pilih akun untuk mengekspor detail.');

        return response()->streamDownload(function () use ($ledger, $companyId, $filters, $branch, $type) {
            $out = fopen('php://output', 'w');

            if ($type === 'summary') {
                Csv::begin($out, ['Kode', 'Nama', 'Saldo Awal', 'Debit', 'Kredit', 'Saldo Akhir']);
                foreach ($ledger->summary($companyId, $filters['from'], $filters['to'], $branch) as $r) {
                    fputcsv($out, [Csv::cell($r['code']), Csv::cell($r['name']), $r['opening'], $r['debit'], $r['credit'], $r['closing']]);
                }
            } else {
                $account = Account::where('company_id', $companyId)->findOrFail($filters['account_id']);
                $sign = LedgerService::debitNormal($account->type, (bool) $account->is_contra) ? '1' : '-1';
                $balance = collect($ledger->summary($companyId, $filters['from'], $filters['to'], $branch))->firstWhere('account_id', $account->id)['opening'];
                Csv::begin($out, ['Tanggal', 'No Trx', 'Sumber', 'Keterangan', 'Akun Lawan', 'Debit', 'Kredit', 'Saldo']);
                $lines = $ledger->lines($companyId, ['account_id' => $account->id, 'from' => $filters['from'], 'to' => $filters['to']], $branch);
                foreach ($lines->clone()->get()->chunk(500) as $chunk) {
                    $counters = $ledger->counterAccounts($chunk);
                    foreach ($chunk as $r) {
                        $balance = bcadd($balance, bcmul(bcsub(LedgerService::money($r->debit), LedgerService::money($r->credit), 2), $sign, 2), 2);
                        fputcsv($out, [
                            Carbon::parse($r->posted_on)->toDateString(), Csv::cell($r->reference ?: 'JE-'.$r->entry_id), JournalSource::label($r->source_kind),
                            Csv::cell($r->line_description ?: $r->entry_description), Csv::cell($counters[$r->line_id] ?? ''),
                            LedgerService::money($r->debit), LedgerService::money($r->credit), $balance,
                        ]);
                    }
                }
            }
            fclose($out);
        }, "buku-besar-{$type}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
