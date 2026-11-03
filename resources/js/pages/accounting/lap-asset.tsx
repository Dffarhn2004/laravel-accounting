import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    branch: string | null;
    date: string;
    source: string;
    code: string;
    account: string;
    cost: string;
    life_years: string;
    monthly: string;
    start: string;
    end: string;
    months: number;
    accumulated: string;
    book: string;
    status: string;
};
type Control = { account: string; register: string; ledger: string; difference: string };
type Props = {
    filters: { as_of: string; account_id: number | null; source: string | null; status: string | null };
    report: { rows: Row[]; groups: { account: string; cost: string; accumulated: string; book: string; count: number }[]; control: { cost: Control[]; accumulated: Control[] } };
    accounts: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const field = 'rounded-md border bg-background p-2 text-sm';

export default function LapAsset({ filters, report, accounts }: Props) {
    return (
        <>
            <Head title="Laporan Daftar Asset" />
            <main className="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Laporan Daftar Asset</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Harga dan akumulasi aset aktif dibandingkan ke saldo akunnya. Selisih biasanya berasal dari jurnal manual.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/data/simulasi">Simulasi D&A</Link></Button>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    const query = Object.fromEntries(Array.from(data.entries()).filter(([, v]) => v !== ''));
                    router.get('/laporan/lap-asset', query, { preserveState: true, replace: true });
                }}>
                    <label className="grid gap-1 text-sm">Per tanggal<Input name="as_of" type="date" defaultValue={filters.as_of} /></label>
                    <label className="grid gap-1 text-sm">Akun
                        <select name="account_id" defaultValue={filters.account_id ?? ''} className={field}>
                            <option value="">Semua akun</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Sumber
                        <select name="source" defaultValue={filters.source ?? ''} className={field}>
                            <option value="">Semua</option><option value="purchase">Beli</option><option value="opening">Modal</option>
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Status
                        <select name="status" defaultValue={filters.status ?? ''} className={field}>
                            <option value="">Semua</option><option value="active">Aktif</option><option value="reversed">Dibalik</option>
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left">{['Cabang', 'Tanggal', 'Sumber', 'Nomor', 'Akun', 'Harga', 'Umur', 'Susut/bulan', 'Mulai', 'Akhir', 'Bulan tersusut', 'Akumulasi', 'Nilai buku', 'Status'].map((h) => <th key={h} className="whitespace-nowrap px-2 py-2 font-medium">{h}</th>)}</tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row) => (
                                <tr key={row.code} className="border-t">
                                    <td className="px-2 py-1">{row.branch}</td>
                                    <td className="px-2 py-1 tabular-nums">{row.date}</td>
                                    <td className="px-2 py-1">{row.source}</td>
                                    <td className="px-2 py-1">{row.code}</td>
                                    <td className="px-2 py-1">{row.account}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{num(row.cost)}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{row.life_years}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{num(row.monthly)}</td>
                                    <td className="px-2 py-1 tabular-nums">{row.start}</td>
                                    <td className="px-2 py-1 tabular-nums">{row.end}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{row.months}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{num(row.accumulated)}</td>
                                    <td className="px-2 py-1 text-right tabular-nums">{num(row.book)}</td>
                                    <td className="px-2 py-1">{row.status}</td>
                                </tr>
                            ))}
                            {report.rows.length === 0 && <tr><td className="px-2 py-3 text-muted-foreground" colSpan={14}>Tidak ada aset untuk filter ini.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <section className="space-y-2">
                    <h2 className="text-lg font-semibold">Rekap kelompok</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left">{['Akun', 'Jumlah', 'Harga', 'Akumulasi', 'Nilai buku'].map((h) => <th key={h} className="px-2 py-2 font-medium">{h}</th>)}</tr></thead>
                            <tbody>
                                {report.groups.map((group) => (
                                    <tr key={group.account} className="border-t">
                                        <td className="px-2 py-1">{group.account}</td>
                                        <td className="px-2 py-1 tabular-nums">{group.count}</td>
                                        <td className="px-2 py-1 text-right tabular-nums">{num(group.cost)}</td>
                                        <td className="px-2 py-1 text-right tabular-nums">{num(group.accumulated)}</td>
                                        <td className="px-2 py-1 text-right tabular-nums">{num(group.book)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <ControlTable title="Harga perolehan vs akun aset" rows={report.control.cost} />
                    <ControlTable title="Akumulasi vs akun akumulasi" rows={report.control.accumulated} />
                </section>
            </main>
        </>
    );
}

function ControlTable({ title, rows }: { title: string; rows: Control[] }) {
    return (
        <section className="space-y-2">
            <h2 className="text-lg font-semibold">{title}</h2>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead><tr className="text-left">{['Akun', 'Register', 'Buku besar', 'Selisih'].map((h) => <th key={h} className="px-2 py-2 font-medium">{h}</th>)}</tr></thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.account} className="border-t">
                                <td className="px-2 py-1">{row.account}</td>
                                <td className="px-2 py-1 text-right tabular-nums">{num(row.register)}</td>
                                <td className="px-2 py-1 text-right tabular-nums">{num(row.ledger)}</td>
                                <td className="px-2 py-1 text-right tabular-nums">{num(row.difference)}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && <tr><td className="px-2 py-3 text-muted-foreground" colSpan={4}>Tidak ada aset aktif.</td></tr>}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
