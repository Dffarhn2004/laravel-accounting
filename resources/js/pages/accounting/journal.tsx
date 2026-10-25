import { Head } from '@inertiajs/react';

export default function Journal({ entries }: { entries: { data: Array<{ id: number; posted_on: string; description: string; lines: Array<{ debit: string; credit: string; account: { code: string; name: string } }> }> } }) {
    return (
        <>
            <Head title="Jurnal Umum" />
            <main className="mx-auto max-w-6xl space-y-4 p-6">
                <h1 className="text-2xl font-semibold">Jurnal Umum</h1>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b text-left"><th className="p-3">Tanggal</th><th className="p-3">Keterangan</th><th className="p-3">Akun</th><th className="p-3 text-right">Debit</th><th className="p-3 text-right">Kredit</th></tr></thead>
                        <tbody>{entries.data.flatMap((entry) => entry.lines.map((line) => <tr key={`${entry.id}-${line.account.code}`} className="border-b last:border-0"><td className="p-3">{entry.posted_on}</td><td className="p-3">{entry.description}</td><td className="p-3">{line.account.code} — {line.account.name}</td><td className="p-3 text-right">{line.debit}</td><td className="p-3 text-right">{line.credit}</td></tr>))}</tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
