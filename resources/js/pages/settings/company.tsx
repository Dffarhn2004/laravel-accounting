import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Company = {
    name: string;
    code: string;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    invoice_bank_name?: string | null;
    invoice_bank_account_name?: string | null;
    invoice_bank_account_number?: string | null;
    default_vat_rate?: string | number;
    default_pph23_rate?: string | number;
};

export default function Company({ company }: { company?: Company }) {
    const form = useForm({
        name: company?.name ?? '',
        code: company?.code ?? '',
        address: company?.address ?? '',
        phone: company?.phone ?? '',
        email: company?.email ?? '',
        invoice_bank_name: company?.invoice_bank_name ?? '',
        invoice_bank_account_name: company?.invoice_bank_account_name ?? '',
        invoice_bank_account_number: company?.invoice_bank_account_number ?? '',
        default_vat_rate: String(company?.default_vat_rate ?? '0'),
        default_pph23_rate: String(company?.default_pph23_rate ?? '0'),
    });

    return <>
        <Head title="Pengaturan & Backup" />
        <div className="space-y-6">
            <Heading variant="small" title="Pengaturan & Backup" description="Kelola identitas usaha, rekening pada invoice, dan nilai pajak bawaan." />
            <form onSubmit={(event) => { event.preventDefault(); form.patch('/settings/company'); }} className="max-w-2xl space-y-8">
                <section className="space-y-5 rounded-lg border p-5">
                    <div><h2 className="font-medium">Identitas Usaha</h2><p className="text-sm text-muted-foreground">Informasi ini digunakan sebagai identitas perusahaan.</p></div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Nama usaha" id="company-name" error={form.errors.name}><Input id="company-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></Field>
                        <Field label="Kode perusahaan" id="company-code" error={form.errors.code}><Input id="company-code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} required /></Field>
                        <Field label="Telepon" id="company-phone" error={form.errors.phone}><Input id="company-phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></Field>
                        <Field label="Email" id="company-email" error={form.errors.email}><Input id="company-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></Field>
                    </div>
                    <Field label="Alamat" id="company-address" error={form.errors.address}><textarea id="company-address" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" /></Field>
                </section>

                <section className="space-y-5 rounded-lg border p-5">
                    <div><h2 className="font-medium">Rekening Invoice</h2><p className="text-sm text-muted-foreground">Ditampilkan saat informasi pembayaran diperlukan pada invoice.</p></div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Nama bank" id="invoice-bank-name" error={form.errors.invoice_bank_name}><Input id="invoice-bank-name" value={form.data.invoice_bank_name} onChange={(event) => form.setData('invoice_bank_name', event.target.value)} /></Field>
                        <Field label="Nomor rekening" id="invoice-bank-number" error={form.errors.invoice_bank_account_number}><Input id="invoice-bank-number" value={form.data.invoice_bank_account_number} onChange={(event) => form.setData('invoice_bank_account_number', event.target.value)} /></Field>
                    </div>
                    <Field label="Atas nama rekening" id="invoice-bank-account-name" error={form.errors.invoice_bank_account_name}><Input id="invoice-bank-account-name" value={form.data.invoice_bank_account_name} onChange={(event) => form.setData('invoice_bank_account_name', event.target.value)} /></Field>
                </section>

                <section className="space-y-5 rounded-lg border p-5">
                    <div><h2 className="font-medium">Default Pajak</h2><p className="text-sm text-muted-foreground">Persentase default dapat diatur usaha. Ketentuan pajak perlu ditinjau profesional sebelum dipakai untuk pelaporan.</p></div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Default PPN (%)" id="default-vat-rate" error={form.errors.default_vat_rate}><Input id="default-vat-rate" type="number" min="0" max="100" step="0.01" value={form.data.default_vat_rate} onChange={(event) => form.setData('default_vat_rate', event.target.value)} required /></Field>
                        <Field label="Default PPh23 (%)" id="default-pph23-rate" error={form.errors.default_pph23_rate}><Input id="default-pph23-rate" type="number" min="0" max="100" step="0.01" value={form.data.default_pph23_rate} onChange={(event) => form.setData('default_pph23_rate', event.target.value)} required /></Field>
                    </div>
                </section>

                <section className="rounded-lg border border-dashed p-5"><h2 className="font-medium">Backup & Restore</h2><p className="mt-1 text-sm text-muted-foreground">Belum tersedia. Desain backup, restore, data contoh, dan reset menunggu keputusan arsitektur ADR-006.</p></section>
                <Button type="submit" disabled={form.processing}>Simpan pengaturan</Button>
            </form>
        </div>
    </>;
}

function Field({ label, id, error, children }: { label: string; id: string; error?: string; children: ReactNode }) {
    return <div className="grid gap-2"><Label htmlFor={id}>{label}</Label>{children}<InputError message={error} /></div>;
}

Company.layout = { breadcrumbs: [{ title: 'Pengaturan & Backup', href: '/settings/company' }] };
