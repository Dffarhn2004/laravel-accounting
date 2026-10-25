import { Head, Link } from '@inertiajs/react';

type Line = {
    id: number;
    debit: string;
    credit: string;
    account: { code: string; name: string };
    journal_entry: { posted_on: string; description: string; branch: { name: string } };
};

export default function Ledger({ lines }: { lines: { data: Line[] } }) {
    return (
        <>
            <Head title="Buku Besar" />
            <main className="mx-auto max-w-6xl space-y-6 p-6">
                <Link href="/dashboard" className="text-sm text-muted-foreground">Kembali</Link>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Buku Besar</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Sumber angka: jurnal yang sudah diposting.</p>
                    </div>
                    <span className="text-sm text-muted-foreground">{lines.data.length} baris</span>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b text-left"><th className="p-3">Tanggal</th><th className="p-3">Akun</th><th className="p-3">Keterangan</th><th className="p-3 text-right">Debit</th><th className="p-3 text-right">Kredit</th></tr></thead>
                        <tbody>
                            {lines.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="p-10 text-center text-muted-foreground">
                                        Belum ada jurnal diposting.
                                    </td>
                                </tr>
                            ) : lines.data.map((line) => (
                                <tr key={line.id} className="border-b">
                                    <td className="p-3">{line.journal_entry.posted_on}</td>
                                    <td className="p-3">{line.account.code} - {line.account.name}</td>
                                    <td className="p-3">{line.journal_entry.description}</td>
                                    <td className="p-3 text-right tabular-nums">{line.debit}</td>
                                    <td className="p-3 text-right tabular-nums">{line.credit}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
