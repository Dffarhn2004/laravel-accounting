/** Dependency-free SVG charts (no Node or chart library needed on shared hosting). */

type Series = { label: string; values: string[]; color: string };

const W = 640;
const H = 220;
const PAD = { l: 8, r: 8, t: 12, b: 24 };

const compact = (n: number) => {
    const a = Math.abs(n);
    const v = a >= 1e9 ? `${(a / 1e9).toFixed(1)} M` : a >= 1e6 ? `${(a / 1e6).toFixed(1)} jt` : a >= 1e3 ? `${(a / 1e3).toFixed(0)} rb` : String(Math.round(a));
    return n < 0 ? `-${v}` : v;
};

/** Multi-series line chart over labelled points. Includes a visually hidden table for screen readers. */
export function LineChart({ title, labels, series }: { title: string; labels: string[]; series: Series[] }) {
    const nums = series.map((s) => s.values.map(Number));
    const all = nums.flat();
    const max = Math.max(0, ...all);
    const min = Math.min(0, ...all);
    const span = max - min || 1;
    const x = (i: number) => PAD.l + (i * (W - PAD.l - PAD.r)) / Math.max(labels.length - 1, 1);
    const y = (v: number) => PAD.t + (1 - (v - min) / span) * (H - PAD.t - PAD.b);
    const empty = all.every((v) => v === 0);

    return (
        <figure>
            <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={title} className="h-56 w-full">
                <line x1={PAD.l} x2={W - PAD.r} y1={y(0)} y2={y(0)} className="stroke-border" />
                <text x={PAD.l} y={PAD.t - 2} className="fill-muted-foreground text-[10px]">{compact(max)}</text>
                {min < 0 && <text x={PAD.l} y={H - PAD.b - 2} className="fill-muted-foreground text-[10px]">{compact(min)}</text>}
                {labels.map((l, i) => (i % 2 === 0 ? <text key={l} x={x(i)} y={H - 6} textAnchor="middle" className="fill-muted-foreground text-[10px]">{l.slice(0, 3)}</text> : null))}
                {nums.map((vals, si) => (
                    <g key={series[si].label}>
                        <polyline fill="none" stroke={series[si].color} strokeWidth="2" points={vals.map((v, i) => `${x(i)},${y(v)}`).join(' ')} />
                        {vals.map((v, i) => <circle key={i} cx={x(i)} cy={y(v)} r="2.5" fill={series[si].color}><title>{`${series[si].label} ${labels[i]}: ${v.toLocaleString('id-ID')}`}</title></circle>)}
                    </g>
                ))}
            </svg>
            {empty && <p className="-mt-6 text-center text-xs text-muted-foreground">Belum ada transaksi pada periode ini.</p>}
            <figcaption className="mt-2 flex flex-wrap gap-4 text-xs">
                {series.map((s) => <span key={s.label} className="inline-flex items-center gap-1.5"><span className="inline-block size-2.5 rounded-full" style={{ background: s.color }} />{s.label}</span>)}
            </figcaption>
            <table className="sr-only">
                <caption>{title}</caption>
                <thead><tr><th>Bulan</th>{series.map((s) => <th key={s.label}>{s.label}</th>)}</tr></thead>
                <tbody>{labels.map((l, i) => <tr key={l}><td>{l}</td>{series.map((s) => <td key={s.label}>{s.values[i]}</td>)}</tr>)}</tbody>
            </table>
        </figure>
    );
}

/** Horizontal share bars; negative or zero amounts are listed but not drawn. */
export function ShareBars({ items, color }: { items: { label: string; amount: string }[]; color: string }) {
    const positive = items.filter((i) => Number(i.amount) > 0);
    const total = positive.reduce((s, i) => s + Number(i.amount), 0);

    if (total === 0) return <p className="text-sm text-muted-foreground">Belum ada data.</p>;

    return (
        <ul className="grid gap-3">
            {positive.map((i) => {
                const share = (Number(i.amount) / total) * 100;
                return (
                    <li key={i.label}>
                        <div className="flex justify-between gap-3 text-sm"><span>{i.label}</span><span className="tabular-nums text-muted-foreground">{Number(i.amount).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} · {share.toFixed(1)}%</span></div>
                        <div className="mt-1 h-2 rounded-full bg-muted" role="presentation"><div className="h-2 rounded-full" style={{ width: `${share}%`, background: color }} /></div>
                    </li>
                );
            })}
        </ul>
    );
}
