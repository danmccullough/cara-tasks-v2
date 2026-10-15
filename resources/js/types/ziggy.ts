import { Config, RouteParams } from 'ziggy-js';

declare global {
    function route(): Config;
    // Also accept a bare id, e.g. route('chores.update', chore.id).
    function route(name: string, params?: RouteParams<typeof name> | string | number, absolute?: boolean): string;
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
