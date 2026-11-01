import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Construction } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    menu: { id: string; label: string };
    group: string | null;
    spec: string | null;
};

export default function Pending({ menu, group, spec }: Props) {
    return (
        <>
            <Head title={menu.label} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-8">
                <div>
                    <Link
                        href="/dashboard"
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" /> Kembali ke dashboard
                    </Link>
                    {group && <p className="mt-5 text-sm text-muted-foreground">{group}</p>}
                    <div className="mt-1 flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight">{menu.label}</h1>
                        <Badge variant="outline">Belum tersedia</Badge>
                    </div>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Construction className="size-5" /> Halaman ini belum dibangun
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-muted-foreground">
                        <p>
                            Menu ini sudah ada di navigasi sesuai kontrak parity, tetapi belum ada
                            form, tabel, atau posting jurnal di baliknya. Tidak ada data yang
                            ditampilkan agar tidak menyesatkan.
                        </p>
                        {spec && (
                            <p>
                                Spesifikasi: <span className="font-mono text-foreground">{spec}</span>
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
