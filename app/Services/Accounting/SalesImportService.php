<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\ImportOrder;
use App\Models\InventoryCostLayer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Support\Accounting\Periods;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * One marketplace/cashier order becomes one invoice. The file is parsed again on post;
 * a preview is never trusted. A live order number in the same company is skipped.
 */
class SalesImportService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MAX_ORDERS = 500;

    public const MAX_ROWS = 8000;

    /** @var list<string> */
    public const HEADERS = [
        'No Order', 'Tanggal', 'Nama Customer', 'Produk', 'Qty', 'Harga Satuan',
        'Diskon', 'Pajak', 'Ongkir', 'Biaya Admin', 'SKU', 'HPP',
    ];

    /** @var array<string, string> */
    private const MAP = [
        'no order' => 'order',
        'tanggal' => 'date',
        'nama customer' => 'customer',
        'produk' => 'product',
        'qty' => 'qty',
        'harga satuan' => 'price',
        'diskon' => 'discount',
        'pajak' => 'tax',
        'ongkir' => 'shipping',
        'biaya admin' => 'admin',
        'sku' => 'sku',
        'hpp' => 'hpp',
    ];

    public function __construct(
        private TransactionPostingService $invoices,
        private InventoryPostingService $inventory,
        private JournalPostingService $journals,
        private JournalReversalService $reversals,
    ) {}

    /** @return list<string|int|float> */
    public static function sampleRow(): array
    {
        return ['ORD-001', '2026-11-01', 'Pelanggan Contoh', 'Produk Contoh', 1, 10000, 0, 0, 0, 0, '', ''];
    }

    public function writeTemplate(string $path): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues(self::sampleRow()));
        $writer->close();
    }

    /**
     * @param  array{company_id:int, branch_id:int, receipt_account_id:int, revenue_account_id:int, other_income_account_id:int, admin_account_id:int}  $scope
     * @return array{filename:string, orders:list<array<string, mixed>>, ready:int, skipped:int, errors:int, total:string}
     */
    public function preview(string $path, string $extension, string $filename, array $scope): array
    {
        $this->accounts($scope);

        return $this->summarize($this->annotate($this->read($path, $extension), $scope), $filename);
    }

    /**
     * @param  array{company_id:int, branch_id:int, channel:string, original_name:string, idempotency_key:string, created_by?:int, receipt_account_id:int, revenue_account_id:int, other_income_account_id:int, admin_account_id:int}  $data
     */
    public function post(string $path, string $extension, array $data): ImportBatch
    {
        $existing = ImportBatch::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing;
        }
        $accounts = $this->accounts($data);
        $orders = $this->annotate($this->read($path, $extension), $data);
        foreach ($orders as $order) {
            if ($order['error']) {
                throw new InvalidArgumentException($order['order_number'].': '.$order['error']);
            }
        }

        return DB::transaction(function () use ($path, $extension, $data, $accounts) {
            $again = ImportBatch::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($again) {
                return $again;
            }
            $orders = $this->annotate($this->read($path, $extension), $data, true);
            foreach ($orders as $order) {
                if ($order['error']) {
                    throw new InvalidArgumentException($order['order_number'].': '.$order['error']);
                }
            }
            $batch = ImportBatch::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'created_by' => $data['created_by'] ?? null,
                'code' => $this->nextCode($data['company_id']),
                'channel' => $data['channel'],
                'original_name' => $data['original_name'],
                'receipt_account_id' => $accounts['receipt']->id,
                'revenue_account_id' => $accounts['revenue']->id,
                'other_income_account_id' => $accounts['other']->id,
                'admin_account_id' => $accounts['admin']->id,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $posted = 0;
            $skipped = 0;
            $total = '0.00';
            foreach ($orders as $index => $order) {
                if ($order['skip']) {
                    $skipped++;
                    ImportOrder::create([
                        'import_batch_id' => $batch->id,
                        'company_id' => $data['company_id'],
                        'order_number' => $order['order_number'],
                        'status' => 'skipped',
                        'skip_reason' => 'No Order sudah ada.',
                    ]);

                    continue;
                }
                try {
                    $row = $this->postOrder($batch, $order, $data, $accounts, $index);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException($order['order_number'].': '.$e->getMessage(), 0, $e);
                }
                $posted++;
                $total = bcadd($total, $row->invoice_total, 2);
            }
            $batch->update(['order_count' => $posted, 'skipped_count' => $skipped, 'total_amount' => $total]);

            return $batch->fresh();
        });
    }

    /**
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverse(ImportBatch $batch, array $data): ImportBatch
    {
        return DB::transaction(function () use ($batch, $data) {
            $batch = ImportBatch::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'reversed') {
                throw new InvalidArgumentException('Batch impor sudah dibatalkan.');
            }
            $orders = $batch->orders()->where('status', 'posted')->with(['invoice', 'movements.movement'])->get();
            foreach ($orders as $order) {
                $invoice = $order->invoice;
                if (! $invoice) {
                    continue;
                }
                $later = $invoice->receiptAllocations()->whereHas('receipt', fn ($q) => $q
                    ->whereNull('reversed_at')
                    ->where(fn ($q2) => $q2->whereNull('journal_entry_id')->orWhere('journal_entry_id', '!=', $invoice->journal_entry_id)))->exists();
                if ($later) {
                    throw new InvalidArgumentException('Order '.$order->order_number.' sudah memiliki pelunasan susulan. Batch tidak dibatalkan.');
                }
            }
            $movementIds = $orders->flatMap(fn (ImportOrder $order) => $order->movements->pluck('inventory_movement_id'))->all();
            $movements = InventoryMovement::whereIn('id', $movementIds)->whereNull('reversed_at')->get();
            foreach ($movements->groupBy('inventory_item_id') as $group) {
                $newest = $group->sortByDesc(fn ($m) => [$m->moved_on->toDateString(), $m->id])->first();
                $latestId = InventoryMovement::where([
                    'company_id' => $newest->company_id,
                    'branch_id' => $newest->branch_id,
                    'inventory_item_id' => $newest->inventory_item_id,
                ])->whereNull('reversed_at')->orderByDesc('moved_on')->orderByDesc('id')->value('id');
                if ((int) $latestId !== $newest->id) {
                    throw new InvalidArgumentException('Ada gerakan persediaan yang lebih baru. Batch tidak dibatalkan.');
                }
            }
            $postedOn = $data['posted_on'] ?? now()->toDateString();
            $latestInvoice = $orders->map(fn ($order) => $order->invoice?->issued_on?->toDateString())->filter()->sort()->last();
            if ($latestInvoice && $postedOn < $latestInvoice) {
                $postedOn = $latestInvoice;
            }
            foreach ($movements->sortByDesc(fn ($m) => [$m->moved_on->toDateString(), $m->id]) as $movement) {
                $this->inventory->reverse($movement, [
                    'company_id' => $data['company_id'],
                    'reason' => $data['reason'],
                    'posted_on' => $postedOn,
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }
            foreach ($orders as $order) {
                if ($order->admin_journal_entry_id) {
                    $period = Periods::openFor($data['company_id'], $postedOn);
                    if (! $period) {
                        throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
                    }
                    $this->reversals->reverse(
                        $order->adminJournal()->firstOrFail(),
                        [
                            'posted_on' => $postedOn,
                            'fiscal_period_id' => $period->id,
                            'description' => 'Pembalikan biaya admin impor '.$order->order_number.': '.$data['reason'],
                            'idempotency_key' => 'import-admin-reversal-'.$order->id,
                            'created_by' => $data['created_by'] ?? null,
                        ],
                    );
                }
                if ($order->invoice) {
                    $this->invoices->reverseInvoice($order->invoice, [
                        'company_id' => $data['company_id'],
                        'reason' => $data['reason'],
                        'posted_on' => $postedOn,
                        'created_by' => $data['created_by'] ?? null,
                    ]);
                }
                $order->update(['status' => 'reversed']);
            }
            $batch->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_reason' => $data['reason']]);

            return $batch->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{receipt:Account, revenue:Account, other:Account, admin:Account, receivable:Account, discount:?Account, vat:?Account}
     */
    private function accounts(array $scope): array
    {
        $companyId = (int) $scope['company_id'];
        $receipt = $this->typed($companyId, (int) $scope['receipt_account_id'], 'asset');
        $revenue = $this->typed($companyId, (int) $scope['revenue_account_id'], 'income');
        $other = $this->typed($companyId, (int) $scope['other_income_account_id'], 'income');
        $admin = $this->typed($companyId, (int) $scope['admin_account_id'], 'expense');
        $receivable = Account::where(['company_id' => $companyId, 'code' => '1201', 'type' => 'asset'])->first();
        if (! $receivable) {
            throw new InvalidArgumentException('Akun 1201 Piutang Usaha belum ada. Muat template akun.');
        }
        if ($receipt->id === $receivable->id) {
            throw new InvalidArgumentException('Akun penerimaan harus berbeda dari piutang.');
        }
        $discount = Account::where(['company_id' => $companyId, 'code' => '5103', 'type' => 'expense'])->first();
        $vat = Account::where(['company_id' => $companyId, 'code' => '2301', 'type' => 'liability'])->first();

        return compact('receipt', 'revenue', 'other', 'admin', 'receivable', 'discount', 'vat');
    }

    private function typed(int $companyId, int $id, string $type): Account
    {
        $account = Account::where('company_id', $companyId)->find($id);
        if (! $account || $account->type !== $type) {
            throw new InvalidArgumentException('Akun tidak sesuai jenis yang diminta.');
        }

        return $account;
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @param  array<string, mixed>  $scope
     * @return list<array<string, mixed>>
     */
    private function annotate(array $orders, array $scope, bool $lock = false): array
    {
        $companyId = (int) $scope['company_id'];
        $branchId = (int) $scope['branch_id'];
        $liveQuery = ImportOrder::where('company_id', $companyId)->where('status', 'posted');
        if ($lock) {
            $liveQuery->lockForUpdate();
        }
        $live = array_fill_keys($liveQuery->pluck('order_number')->all(), true);
        $needed = [];
        foreach ($orders as &$order) {
            if ($order['error']) {
                continue;
            }
            $period = Periods::openFor($companyId, $order['date']);
            if (! $period) {
                $order['error'] = 'Tidak ada tahun buku terbuka untuk tanggal ini.';

                continue;
            }
            $order['period_id'] = $period->id;
            if (isset($live[$order['order_number']])) {
                $order['skip'] = true;

                continue;
            }
            if (bccomp($order['tax'], '0', 2) > 0 && ! Account::where(['company_id' => $companyId, 'code' => '2301'])->exists()) {
                $order['error'] = 'Akun 2301 PPN Keluaran belum ada.';

                continue;
            }
            if ($order['has_discount'] && ! Account::where(['company_id' => $companyId, 'code' => '5103'])->exists()) {
                $order['error'] = 'Akun 5103 Beban Discount belum ada.';

                continue;
            }
            foreach ($order['lines'] as $line) {
                if ($line['sku'] === '') {
                    continue;
                }
                $item = InventoryItem::where(['company_id' => $companyId, 'sku' => $line['sku'], 'is_active' => true])->first();
                if (! $item) {
                    $order['error'] = 'SKU '.$line['sku'].' tidak dikenal.';
                    break;
                }
                $later = InventoryMovement::where(['company_id' => $companyId, 'branch_id' => $branchId, 'inventory_item_id' => $item->id])
                    ->whereNull('reversed_at')->whereDate('moved_on', '>', $order['date'])->exists();
                if ($later) {
                    $order['error'] = 'Ada gerakan persediaan yang lebih baru pada SKU '.$line['sku'].'.';
                    break;
                }
                $needed[$item->id][$order['order_number']] = bcadd($needed[$item->id][$order['order_number']] ?? '0', $line['qty'], 2);
            }
        }
        unset($order);
        $remaining = [];
        foreach (array_keys($needed) as $itemId) {
            $query = InventoryCostLayer::where(['company_id' => $companyId, 'branch_id' => $branchId, 'inventory_item_id' => $itemId]);
            if ($lock) {
                $query->lockForUpdate();
            }
            $remaining[$itemId] = (string) ($query->value('quantity') ?? '0');
        }
        foreach ($orders as &$order) {
            if ($order['error'] || $order['skip']) {
                continue;
            }
            foreach ($order['lines'] as $line) {
                if ($line['sku'] === '') {
                    continue;
                }
                $itemId = (int) InventoryItem::where(['company_id' => $companyId, 'sku' => $line['sku']])->value('id');
                if (bccomp($remaining[$itemId] ?? '0', $line['qty'], 2) < 0) {
                    $order['error'] = 'Stok SKU '.$line['sku'].' tidak cukup.';
                    break;
                }
                $remaining[$itemId] = bcsub($remaining[$itemId], $line['qty'], 2);
            }
        }
        unset($order);

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $data
     * @param  array{receipt:Account, revenue:Account, other:Account, admin:Account, receivable:Account, discount:?Account, vat:?Account}  $accounts
     */
    private function postOrder(ImportBatch $batch, array $order, array $data, array $accounts, int $index): ImportOrder
    {
        $customer = $this->customer((int) $data['company_id'], $order['customer']);
        $key = 'import-'.$data['company_id'].'-'.$batch->id.'-'.$index;
        $lines = [];
        foreach ($order['lines'] as $line) {
            $lines[] = [
                'description' => $line['product'],
                'quantity' => $line['qty'],
                'unit_price' => $line['price'],
                'discount_amount' => $line['discount'],
                'revenue_account_id' => $accounts['revenue']->id,
            ];
        }
        $others = [];
        if (bccomp($order['shipping'], '0', 2) > 0) {
            $others[] = ['label' => 'Ongkir', 'account_id' => $accounts['other']->id, 'amount' => $order['shipping']];
        }
        $taxed = bccomp($order['tax'], '0', 2) > 0;
        $invoice = $this->invoices->invoice([
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'fiscal_period_id' => $order['period_id'],
            'customer_id' => $customer->id,
            'issued_on' => $order['date'],
            'idempotency_key' => $key,
            'description' => 'Impor '.$order['order_number'].' ('.$data['channel'].')',
            'receivable_account_id' => $accounts['receivable']->id,
            'revenue_account_id' => $accounts['revenue']->id,
            'discount_account_id' => $order['has_discount'] ? $accounts['discount']?->id : null,
            'vat_enabled' => $taxed,
            'vat_output_account_id' => $taxed ? $accounts['vat']?->id : null,
            'vat_amount' => $taxed ? $order['tax'] : null,
            'payment_account_id' => $accounts['receipt']->id,
            'payment_method' => $data['channel'],
            'paid_amount' => $order['total'],
            'lines' => $lines,
            'other_incomes' => $others,
            'created_by' => $data['created_by'] ?? null,
        ]);
        $row = ImportOrder::create([
            'import_batch_id' => $batch->id,
            'company_id' => $data['company_id'],
            'order_number' => $order['order_number'],
            'invoice_id' => $invoice->id,
            'admin_amount' => $order['admin'],
            'invoice_total' => $invoice->total,
            'status' => 'posted',
        ]);
        if (bccomp($order['admin'], '0', 2) > 0) {
            $entry = $this->journals->post([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $order['period_id'],
                'posted_on' => $order['date'],
                'description' => 'Biaya admin impor '.$order['order_number'],
                'idempotency_key' => $key.'-admin',
                'source_type' => ImportOrder::class,
                'source_kind' => 'import',
                'source_id' => (string) $row->id,
                'reference' => $batch->code,
                'created_by' => $data['created_by'] ?? null,
                'lines' => [
                    ['account_id' => $accounts['admin']->id, 'debit' => $order['admin'], 'credit' => '0'],
                    ['account_id' => $accounts['receipt']->id, 'debit' => '0', 'credit' => $order['admin']],
                ],
            ]);
            $row->update(['admin_journal_entry_id' => $entry->id]);
        }
        foreach ($order['lines'] as $lineNo => $line) {
            if ($line['sku'] === '') {
                continue;
            }
            $item = InventoryItem::where(['company_id' => $data['company_id'], 'sku' => $line['sku'], 'is_active' => true])->firstOrFail();
            $movement = $this->inventory->issue([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $order['period_id'],
                'inventory_item_id' => $item->id,
                'quantity' => $line['qty'],
                'moved_on' => $order['date'],
                'idempotency_key' => $key.'-issue-'.$lineNo,
                'description' => 'HPP impor '.$order['order_number'],
                'created_by' => $data['created_by'] ?? null,
            ]);
            $row->movements()->create(['inventory_movement_id' => $movement->id]);
        }

        return $row->fresh();
    }

    private function customer(int $companyId, string $name): Customer
    {
        $found = Customer::where('company_id', $companyId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($found) {
            return $found;
        }
        $start = Customer::where('company_id', $companyId)->count() + 1;
        for ($i = $start; $i < $start + 1000; $i++) {
            $code = 'CUS-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            if (! Customer::where(['company_id' => $companyId, 'code' => $code])->exists()) {
                return Customer::create(['company_id' => $companyId, 'name' => $name, 'code' => $code]);
            }
        }

        throw new InvalidArgumentException('Nomor pelanggan habis.');
    }

    private function nextCode(int $companyId): string
    {
        $prefix = 'IMP-'.now()->year.'-';
        $last = ImportBatch::where('company_id', $companyId)->where('code', 'like', $prefix.'%')->orderByDesc('code')->lockForUpdate()->value('code');
        $n = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array{filename:string, orders:list<array<string, mixed>>, ready:int, skipped:int, errors:int, total:string}
     */
    private function summarize(array $orders, string $filename): array
    {
        $ready = 0;
        $skipped = 0;
        $errors = 0;
        $total = '0.00';
        $rows = [];
        foreach ($orders as $order) {
            $status = $order['error'] ? 'error' : ($order['skip'] ? 'skip' : 'ready');
            if ($status === 'error') {
                $errors++;
            } elseif ($status === 'skip') {
                $skipped++;
            } else {
                $ready++;
                $total = bcadd($total, $order['total'], 2);
            }
            $rows[] = [
                'order_number' => $order['order_number'],
                'date' => $order['date'],
                'customer' => $order['customer'],
                'lines' => count($order['lines']),
                'total' => $order['total'],
                'admin' => $order['admin'],
                'status' => $status,
                'message' => $order['error'] ?: ($order['skip'] ? 'No Order sudah ada dan dilewati.' : 'Siap diposting.'),
            ];
        }

        return [
            'filename' => $filename,
            'orders' => $rows,
            'ready' => $ready,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(string $path, string $extension): array
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Berkas melebihi 2 MB atau tidak dapat dibaca.');
        }
        $raw = strtolower($extension) === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);
        if ($raw === []) {
            throw new InvalidArgumentException('Berkas tidak berisi baris data.');
        }
        $header = array_shift($raw);
        $index = $this->headerIndex($header);
        $grouped = [];
        $order = [];
        foreach ($raw as $cells) {
            if ($this->blank($cells)) {
                continue;
            }
            $parsed = $this->row($cells, $index);
            $key = $parsed['order'];
            if (! isset($grouped[$key])) {
                if (count($grouped) >= self::MAX_ORDERS) {
                    throw new InvalidArgumentException('Berkas melebihi 500 order.');
                }
                $grouped[$key] = [
                    'order_number' => $key,
                    'date' => $parsed['date'],
                    'customer' => $parsed['customer'],
                    'lines' => [],
                    'tax' => '0.00',
                    'shipping' => '0.00',
                    'admin' => '0.00',
                    'has_discount' => false,
                    'error' => $parsed['error'],
                    'skip' => false,
                    'period_id' => null,
                    'total' => '0.00',
                ];
                $order[] = $key;
            }
            $target = &$grouped[$key];
            if ($parsed['error'] && ! $target['error']) {
                $target['error'] = $parsed['error'];
            }
            if (! $parsed['error'] && ($parsed['date'] !== $target['date'] || mb_strtolower($parsed['customer']) !== mb_strtolower($target['customer']))) {
                $target['error'] = 'Tanggal atau nama customer berbeda pada No Order yang sama.';
            }
            if (count($target['lines']) >= 15 && ! $target['error']) {
                $target['error'] = 'Satu order paling banyak 15 baris produk.';
            }
            if (! $parsed['error'] && count($target['lines']) < 15) {
                $target['lines'][] = [
                    'product' => $parsed['product'],
                    'qty' => $parsed['qty'],
                    'price' => $parsed['price'],
                    'discount' => $parsed['discount'],
                    'sku' => $parsed['sku'],
                ];
                $target['tax'] = bcadd($target['tax'], $parsed['tax'], 2);
                $target['shipping'] = bcadd($target['shipping'], $parsed['shipping'], 2);
                $target['admin'] = bcadd($target['admin'], $parsed['admin'], 2);
                if (bccomp($parsed['discount'], '0', 2) > 0) {
                    $target['has_discount'] = true;
                }
            }
            unset($target);
        }
        $out = [];
        foreach ($order as $key) {
            $row = $grouped[$key];
            $net = '0.00';
            foreach ($row['lines'] as $line) {
                $gross = bcmul($line['qty'], $line['price'], 2);
                $net = bcadd($net, bcsub($gross, $line['discount'], 2), 2);
            }
            $row['total'] = bcadd(bcadd($net, $row['shipping'], 2), $row['tax'], 2);
            if (! $row['error'] && bccomp($row['total'], '0', 2) <= 0) {
                $row['error'] = 'Total tagihan harus lebih dari nol.';
            }
            if (! $row['error'] && $row['lines'] === []) {
                $row['error'] = 'Order tidak memiliki baris produk.';
            }
            $out[] = $row;
        }
        if ($out === []) {
            throw new InvalidArgumentException('Berkas tidak berisi baris data.');
        }

        return $out;
    }

    /** @param  list<string>  $header  @return array<string, int> */
    private function headerIndex(array $header): array
    {
        $index = [];
        foreach ($header as $i => $label) {
            $key = self::MAP[mb_strtolower(trim($label))] ?? null;
            if ($key) {
                $index[$key] = $i;
            }
        }
        $missing = array_diff(array_values(self::MAP), array_keys($index));
        if ($missing !== []) {
            throw new InvalidArgumentException('Kolom template tidak lengkap. Unduh template lalu isi tanpa mengubah judul kolom.');
        }

        return $index;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $index
     * @return array<string, mixed>
     */
    private function row(array $cells, array $index): array
    {
        $get = fn (string $key) => $cells[$index[$key]] ?? '';
        $error = null;
        $order = trim($get('order'));
        $customer = trim($get('customer'));
        $product = trim($get('product'));
        $sku = trim($get('sku'));
        $date = '';
        $qty = '0.00';
        $price = '0.00';
        $discount = '0.00';
        $tax = '0.00';
        $shipping = '0.00';
        $admin = '0.00';
        try {
            if ($order === '' || $customer === '' || $product === '') {
                throw new InvalidArgumentException('No Order, nama customer, dan produk wajib diisi.');
            }
            $date = $this->date($get('date'));
            $qty = $this->decimal($get('qty'), 'Qty');
            if (bccomp($qty, '0', 2) <= 0) {
                throw new InvalidArgumentException('Qty harus lebih dari nol.');
            }
            $price = $this->decimal($get('price'), 'Harga satuan');
            if (bccomp($price, '0', 2) <= 0) {
                throw new InvalidArgumentException('Harga satuan harus lebih dari nol.');
            }
            $discount = $this->decimal($get('discount'), 'Diskon');
            $gross = bcmul($qty, $price, 2);
            if (bccomp($discount, $gross, 2) > 0) {
                throw new InvalidArgumentException('Diskon melebihi nilai baris.');
            }
            $tax = $this->decimal($get('tax'), 'Pajak');
            $shipping = $this->decimal($get('shipping'), 'Ongkir');
            $admin = $this->decimal($get('admin'), 'Biaya admin');
            $hpp = trim($get('hpp'));
            if ($hpp !== '') {
                $this->decimal($hpp, 'HPP');
            }
            if ($hpp !== '' && $sku === '') {
                throw new InvalidArgumentException('HPP terisi tanpa SKU.');
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }

        return compact('order', 'date', 'customer', 'product', 'qty', 'price', 'discount', 'tax', 'shipping', 'admin', 'sku', 'error');
    }

    private function date(string $value): string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException('Tanggal tidak dikenali. Gunakan YYYY-MM-DD.');
    }

    private function decimal(string $raw, string $label): string
    {
        $raw = str_replace([' ', "\xc2\xa0"], '', trim($raw));
        if ($raw === '') {
            return '0.00';
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = strrpos($raw, ',') > strrpos($raw, '.')
                ? str_replace(',', '.', str_replace('.', '', $raw))
                : str_replace(',', '', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }
        if (! preg_match('/^\d+(\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException($label.' harus angka nol atau positif.');
        }

        return bcadd($raw, '0', 2);
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException('Berkas tidak dapat dibaca.');
        }
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);

            return [];
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [array_map(fn ($c) => trim((string) $c), str_getcsv($first, $delimiter))];
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($c) => $this->cell($c), $cells);
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw new InvalidArgumentException('Berkas terlalu banyak baris.');
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<string>> */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($c) => $this->cell($c), $row->toArray());
                if (count($rows) > self::MAX_ROWS) {
                    $reader->close();
                    throw new InvalidArgumentException('Berkas terlalu banyak baris.');
                }
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    private function cell(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value)) {
            return floor($value) == $value ? sprintf('%.0f', $value) : rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        }

        return trim((string) $value);
    }

    /** @param  list<string>  $cells */
    private function blank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
