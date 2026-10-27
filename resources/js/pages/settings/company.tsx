import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Company({ company }: { company?: { name: string; code: string } }) {
    const form = useForm({ name: company?.name ?? '', code: company?.code ?? '' });
    return <><Head title="Profil Perusahaan" /><div className="space-y-6"><Heading variant="small" title="Profil Perusahaan" description="Identitas dasar perusahaan yang digunakan dalam workspace akuntansi." /><form onSubmit={(event) => { event.preventDefault(); form.patch('/settings/company'); }} className="max-w-xl space-y-5"><div className="grid gap-2"><Label htmlFor="company-name">Nama perusahaan</Label><Input id="company-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /><InputError message={form.errors.name} /></div><div className="grid gap-2"><Label htmlFor="company-code">Kode perusahaan</Label><Input id="company-code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} required /><p className="text-xs text-muted-foreground">Kode harus unik dan dipakai untuk identifikasi internal.</p><InputError message={form.errors.code} /></div><Button type="submit" disabled={form.processing}>Simpan perubahan</Button></form></div></>;
}

Company.layout = { breadcrumbs: [{ title: 'Profil Perusahaan', href: '/settings/company' }] };
