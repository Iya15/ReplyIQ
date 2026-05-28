/**
 * Build-time constants injected by Vite's `define` option.
 *
 * vite.config.loader.ts → __WIDGET_BASE__, __API_BASE__
 * vite.config.app.ts    → __WIDGET_API_URL__, __REVERB_APP_KEY__,
 *                          __REVERB_HOST__, __REVERB_PORT__, __REVERB_SCHEME__
 */
declare const __WIDGET_BASE__:    string;
declare const __WIDGET_API_URL__: string;
declare const __API_BASE__:       string;
declare const __REVERB_APP_KEY__: string;
declare const __REVERB_HOST__:    string;
declare const __REVERB_PORT__:    number;
declare const __REVERB_SCHEME__:  string;
declare const __BUILD_HASH__:     string;
