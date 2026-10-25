import { Head } from '@inertiajs/react';

export default function Inventory({ items, movements }: { items: any; movements: any }) {
    return (
        <>
            <Head title="Persediaan" />
            <main className="mx-auto max-w-6xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Persediaan</h1>
                <section className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border p-4">
                        <h2 className="font-medium">Item</h2>
                        <p className="mt-2 text-2xl">{items.total ?? 0}</p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <h2 className="font-medium">Movement</h2>
                        <p className="mt-2 text-2xl">{movements.total ?? 0}</p>
                    </div>
                </section>
            </main>
        </>
    );
}
