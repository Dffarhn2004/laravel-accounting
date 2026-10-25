import { Head } from '@inertiajs/react';

type Asset = {
    id: number;
    name: string;
    cost: string;
    residual_value: string;
    useful_life_months: number;
    status: string;
    schedule?: { monthly_amount: string; remaining_value: string };
};

export default function FixedAssets({ assets }: { assets: { data: Asset[] } }) {
    return (
        <>
            <Head title="Aset Tetap" />
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <div>
                    <h1 className="text-3xl font-semibold">Aset Tetap</h1>
                    <p className="text-sm text-muted-foreground">
                        Register aset dan jadwal depresiasi garis lurus.
                    </p>
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/30 text-left">
                            <tr>
                                <th className="p-3">Aset</th>
                                <th className="p-3">Perolehan</th>
                                <th className="p-3">Umur</th>
                                <th className="p-3">Depresiasi/bulan</th>
                                <th className="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assets.data.map((asset) => (
                                <tr key={asset.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{asset.name}</td>
                                    <td className="p-3 tabular-nums">{asset.cost}</td>
                                    <td className="p-3">{asset.useful_life_months} bulan</td>
                                    <td className="p-3 tabular-nums">
                                        {asset.schedule?.monthly_amount ?? '0.00'}
                                    </td>
                                    <td className="p-3">{asset.status}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
