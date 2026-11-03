import { Head, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';

type Account = { id: number; code: string; name: string; type: string };
type PreviewOrder = {
    order_number: string;
    date: string;
    customer: string;
    lines: number;
    total: string;
    admin: string;
    status: 'ready' | 'skip' | 'error';
    message: string;
};
type Preview = { filename: string; orders: PreviewOrder[]; ready: number; skipped: number; errors: number; total: string };
type Batch = { id: number; code: string; channel: string; file: string; orders: number; skipped: number; total: string; status: string; created_at: string };
type Props = {
    branches: { id: number; code: string; name: string }[];
    accounts: Account[];
    defaults: { receipt_account_id: number | null; revenue_account_id: number | null; other_income_account_id: number | null; admin_account_id: number | null };
    preview: Preview | null;
    status: string | null;
    history: Batch[];
};

const field = 'rounded-md border bg-background p-2 text-sm';
const money = (value: string) => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Impor({ branches, accounts, defaults, preview, status, history }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `imp-${Date.now()}`);
    const assets = accounts.filter((a) => a.type === 'asset');
    const income = accounts.filter((a) => a.type === 'income');
    const expenses = accounts.filter((a) => a.type === 'expense');
    const form = useForm({
        branch_id: (branches[0]?.id ?? '') as number | '',
        channel: 'Kasir',
        receipt_account_id: (defaults.receipt_account_id ?? '') as number | '',
        revenue_account_id: (defaults.revenue_account_id ?? '') as number | '',
        other_income_account_id: (defaults.other_income_account_id ?? '') as number | '',
        admin_account_id: (defaults.admin_account_id ?? '') as number | '',
        file: null as File | null,
    });
    const cancel = useForm({});
    const ready = form.data.branch_id !== '' && form.data.channel.trim() !== '' && form.data.receipt_account_id !== '' && form.data.revenue_account_id !== '' && form.data.other_income_account_id !== '' && form.data.admin_account_id !== '' && form.data.file !== null && form.data.file.size <= 2 * 1024 * 1024;
    const err = (name: string) => errors[name];
    const options = (rows: Account[]) => rows.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>);

    const send = (url: string, posting: boolean) => {
        form.transform((data) => (posting ? { ...data, idempotency_key: key.current } : data));
        form.post(url, { forceFormData: true, preserveScroll: true, preserveState: !posting });
    };

    return (
        <>
            <Head title="Import Penjualan" />
            <main className="mx-auto w-full max-w-[1100px] space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Import Penjualan</h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Satu No Order menjadi satu invoice lunas. Pajak, ongkir, dan biaya admin mengikuti angka di berkas. HPP memakai harga rata-rata cabang bila SKU cocok. Berkas paling besar 2 MB dan 500 order. Pratinjau tidak memposting.</p>
                </header>

                {status && <p className="rounded-lg border border-emerald-600/30 bg-emerald-600/5 p-3 text-sm">{status}</p>}
                {err('file') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('file')}</p>}
                {err('batch') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('batch')}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div className="flex flex-wrap gap-2 sm:col-span-2">
                        <Button variant="outline" asChild><a href="/inputan/impor/template.csv">Unduh template CSV</a></Button>
                        <Button variant="outline" asChild><a href="/inputan/impor/template.xlsx">Unduh template XLSX</a></Button>
                    </div>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={field} value={form.data.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                        <span className="text-xs text-destructive">{err('branch_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Channel
                        <input className={field} value={form.data.channel} onChange={(e) => form.setData('channel', e.target.value)} placeholder="Kasir atau Marketplace" />
                        <span className="text-xs text-destructive">{err('channel')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Akun penerimaan
                        <select className={field} value={form.data.receipt_account_id} onChange={(e) => form.setData('receipt_account_id', Number(e.target.value))}>{options(assets)}</select>
                    </label>
                    <label className="grid gap-1 text-sm">Akun pendapatan
                        <select className={field} value={form.data.revenue_account_id} onChange={(e) => form.setData('revenue_account_id', Number(e.target.value))}>{options(income)}</select>
                    </label>
                    <label className="grid gap-1 text-sm">Akun pendapatan lain (ongkir)
                        <select className={field} value={form.data.other_income_account_id} onChange={(e) => form.setData('other_income_account_id', Number(e.target.value))}>{options(income)}</select>
                    </label>
                    <label className="grid gap-1 text-sm">Akun biaya admin
                        <select className={field} value={form.data.admin_account_id} onChange={(e) => form.setData('admin_account_id', Number(e.target.value))}>{options(expenses)}</select>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Berkas CSV atau XLSX
                        <input className={field} type="file" accept=".csv,.txt,.xlsx" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                        {form.data.file && form.data.file.size > 2 * 1024 * 1024 && <span className="text-xs text-destructive">Berkas paling besar 2 MB.</span>}
                    </label>
                    <div className="flex flex-wrap gap-2 sm:col-span-2">
                        <Button type="button" variant="outline" disabled={!ready || form.processing} onClick={() => send('/inputan/impor/preview', false)}>Pratinjau</Button>
                        <Button type="button" disabled={!ready || form.processing || (preview?.errors ?? 0) > 0 || (preview?.ready ?? 0) < 1} onClick={() => { if (window.confirm('Posting invoice dari berkas ini? File dibaca ulang di server.')) send('/inputan/impor', true); }}>Posting</Button>
                    </div>
                </section>

                {preview && (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">Pratinjau {preview.filename}</h2>
                        <p className="text-sm text-muted-foreground">{preview.ready} siap · {preview.skipped} dilewati · {preview.errors} gagal · tagihan {money(preview.total)}</p>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left"><tr><th className="p-2">No Order</th><th className="p-2">Tanggal</th><th className="p-2">Customer</th><th className="p-2">Baris</th><th className="p-2 text-right">Tagihan</th><th className="p-2 text-right">Admin</th><th className="p-2">Status</th></tr></thead>
                                <tbody>
                                    {preview.orders.map((order) => (
                                        <tr key={order.order_number} className="border-t">
                                            <td className="p-2">{order.order_number}</td>
                                            <td className="p-2">{order.date}</td>
                                            <td className="p-2">{order.customer}</td>
                                            <td className="p-2">{order.lines}</td>
                                            <td className="p-2 text-right">{money(order.total)}</td>
                                            <td className="p-2 text-right">{money(order.admin)}</td>
                                            <td className={`p-2 ${order.status === 'error' ? 'text-destructive' : ''}`}>{order.message}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Riwayat</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left"><tr><th className="p-2">Waktu</th><th className="p-2">Batch</th><th className="p-2">File</th><th className="p-2">Channel</th><th className="p-2">Transaksi</th><th className="p-2 text-right">Total tagihan</th><th className="p-2"></th></tr></thead>
                            <tbody>
                                {history.length === 0 && <tr><td className="p-3 text-muted-foreground" colSpan={7}>Belum ada impor.</td></tr>}
                                {history.map((batch) => (
                                    <tr key={batch.id} className="border-t">
                                        <td className="p-2">{batch.created_at}</td>
                                        <td className="p-2">{batch.code}</td>
                                        <td className="p-2">{batch.file}</td>
                                        <td className="p-2">{batch.channel}</td>
                                        <td className="p-2">{batch.orders}{batch.skipped > 0 ? ` (+${batch.skipped} dilewati)` : ''}</td>
                                        <td className="p-2 text-right">{money(batch.total)}</td>
                                        <td className="p-2 text-right">
                                            {batch.status === 'active' && batch.orders > 0 && (
                                                <Button type="button" variant="outline" size="sm" disabled={cancel.processing} onClick={() => { if (window.confirm(`Batalkan ${batch.code}? Jurnal dibalik, tidak dihapus.`)) cancel.post(`/inputan/impor/${batch.id}/reverse`); }}>Batal</Button>
                                            )}
                                            {batch.status === 'reversed' && <span className="text-muted-foreground">Dibatalkan</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </>
    );
}
