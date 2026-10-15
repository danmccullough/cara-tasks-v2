// Full class names so Tailwind keeps them.
const colors: Record<string, string> = {
    sky: 'bg-sky-100 border-sky-300 dark:bg-sky-950 dark:border-sky-700',
    emerald: 'bg-emerald-100 border-emerald-300 dark:bg-emerald-950 dark:border-emerald-700',
    violet: 'bg-violet-100 border-violet-300 dark:bg-violet-950 dark:border-violet-700',
    amber: 'bg-amber-100 border-amber-300 dark:bg-amber-950 dark:border-amber-700',
    rose: 'bg-rose-100 border-rose-300 dark:bg-rose-950 dark:border-rose-700',
};

export const kidColorNames = Object.keys(colors);

export function kidColor(name: string | null | undefined): string {
    return colors[name ?? ''] ?? colors.sky;
}
