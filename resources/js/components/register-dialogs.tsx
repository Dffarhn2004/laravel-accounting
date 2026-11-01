import { router, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

export type EvidenceFile = { id: number; name: string; mime: string };

/** Reversal with a mandatory reason: the register's replacement for Edit/Hapus on posted entries. */
export function ReverseDialog({ title, postUrl, onClose }: { title: string | null; postUrl: string | null; onClose: () => void }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [reason, setReason] = useState('');
    const [postedOn, setPostedOn] = useState(new Date().toISOString().slice(0, 10));

    return (
        <Dialog open={title !== null} onOpenChange={(open) => { if (!open) { setReason(''); onClose(); } }}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Balik {title}</DialogTitle>
                    <DialogDescription>Jurnal asli tetap tersimpan. Dibuat jurnal pembalikan pada tanggal di bawah dan transaksi ditandai Dibalik. Untuk koreksi, input transaksi baru.</DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    <label className="grid gap-1 text-sm">Alasan (wajib)
                        <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Mis. salah akun / nominal keliru" />
                        <span className="text-xs text-destructive">{errors.reason}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal pembalikan<Input type="date" value={postedOn} onChange={(e) => setPostedOn(e.target.value)} /></label>
                </div>
                <DialogFooter>
                    <Button variant="destructive" disabled={reason.trim().length < 3 || !postUrl} onClick={() => postUrl && router.post(postUrl, { reason, posted_on: postedOn }, { preserveScroll: true, onSuccess: () => { setReason(''); onClose(); } })}>Balik</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** View, add and delete the evidence files (max 5) of one transaction. */
export function EvidenceDialog({ title, files, max, uploadUrl, onClose }: { title: string | null; files: EvidenceFile[]; max: number; uploadUrl: string | null; onClose: () => void }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const remaining = max - files.length;

    return (
        <Dialog open={title !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bukti {title}</DialogTitle>
                    <DialogDescription>Maksimal {max} file JPG, PNG, WebP, atau PDF (5 MB per file).</DialogDescription>
                </DialogHeader>
                {files.length ? (
                    <ul className="divide-y rounded-md border text-sm">
                        {files.map((f) => (
                            <li key={f.id} className="flex items-center justify-between gap-3 p-3">
                                <span className="flex items-center gap-2"><FileText className="size-4" />{f.name}</span>
                                <span className="flex gap-3">
                                    <a className="underline" href={`/attachments/${f.id}`} target="_blank" rel="noreferrer">Buka</a>
                                    <button type="button" className="text-destructive underline" onClick={() => { if (window.confirm(`Hapus bukti ${f.name}?`)) router.delete(`/attachments/${f.id}`, { preserveScroll: true, onSuccess: onClose }); }}>Hapus</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : <p className="text-sm text-muted-foreground">Belum ada bukti.</p>}
                {remaining > 0 && uploadUrl ? (
                    <label className="grid gap-1 text-sm">Tambah bukti ({remaining} slot)
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => { const list = e.target.files; if (list?.length) router.post(uploadUrl, { attachments: Array.from(list) }, { forceFormData: true, preserveScroll: true, onSuccess: onClose }); }} />
                        <span className="text-xs text-destructive">{errors.attachments}</span>
                    </label>
                ) : <p className="text-xs text-muted-foreground">Slot bukti penuh.</p>}
            </DialogContent>
        </Dialog>
    );
}
