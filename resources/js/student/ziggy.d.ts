import type { Config, RouteParamsWithQueryOverload } from 'ziggy-js';

declare global {
    function route(
        name?: string,
        params?: RouteParamsWithQueryOverload,
        absolute?: boolean,
        config?: Config,
    ): string;

    const Ziggy: Config;

    interface Window {
        route: typeof route;
        Ziggy: Config;
    }
}

export {};
