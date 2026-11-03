<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\InventoryMovementRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\InventoryItem;
use App\Models\TemplateOption;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\InventoryPostingService;
use App\Support\Accounting\Periods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class InventoryMovementController extends Controller
{
    public function create(Request $request, InventoryPostingService $inventory): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return Inertia::render('accounting/persediaan-form', [
            'items' => InventoryItem::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'minimum_stock']),
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get(['id', 'code', 'name']),
            'fiscalPeriods' => FiscalPeriod::where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'paymentMethods' => TemplateOption::query()->where(['company_id' => $companyId, 'group' => 'outflow_payment', 'is_active' => true])->with('account:id,type')->orderBy('sort_order')->get(['id', 'code', 'label', 'account_id'])
                ->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'label' => $o->label, 'settles' => $o->account?->type === 'asset' ? 'now' : ($o->account?->type === 'liability' ? 'debt' : null)])->values(),
            'nextNumber' => $inventory->nextReference($companyId, now()->toDateString()),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function store(InventoryMovementRequest $request, InventoryPostingService $inventory, AttachmentService $attachments): RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);

        if ($user->branch_id && (int) $data['branch_id'] !== $user->branch_id) {
            return back()->withErrors(['branch_id' => 'Anda hanya dapat mencatat untuk cabang Anda sendiri.'])->withInput();
        }
        $period = Periods::openFor($companyId, $data['moved_on']);
        if (! $period) {
            return back()->withErrors(['moved_on' => 'Tidak ada tahun buku terbuka untuk tanggal ini.'])->withInput();
        }

        $payload = $data + ['company_id' => $companyId, 'fiscal_period_id' => $period->id, 'created_by' => $user->id];
        if ($data['kind'] === 'purchase') {
            $option = TemplateOption::where(['company_id' => $companyId, 'group' => 'outflow_payment', 'code' => $data['payment_method']])->first();
            $account = $option?->account_id ? Account::where('company_id', $companyId)->find($option->account_id) : null;
            if (! $account || ! in_array($account->type, ['asset', 'liability'], true)) {
                return back()->withErrors(['payment_method' => 'Jenis pembayaran belum dipetakan ke akun kas/bank/hutang (Template Jurnal bagian 7).'])->withInput();
            }
            $payload['settlement_account_id'] = $account->id;
        }

        try {
            $movement = DB::transaction(function () use ($inventory, $attachments, $payload, $files, $companyId, $user) {
                $movement = $payload['kind'] === 'issue'
                    ? $inventory->issue($payload)
                    : $inventory->purchase($payload);
                if ($files) {
                    $attachments->attach($movement, $companyId, $files, $user->id);
                }

                return $movement;
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['inventory' => $e->getMessage()])->withInput();
        }

        return redirect()->route('data.persediaan.index', ['search' => $movement->reference])->with('success', 'Persediaan '.$movement->reference.' diposting.');
    }
}
