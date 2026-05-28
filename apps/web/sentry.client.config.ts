import * as Sentry from '@sentry/nextjs';

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,

  // Reduce noise: only send 5% of performance traces to Sentry.
  // Errors are always captured regardless of this setting.
  tracesSampleRate: parseFloat(process.env.NEXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE ?? '0.05'),

  // Link source maps to the deployed release for readable stack traces.
  release: process.env.NEXT_PUBLIC_SENTRY_RELEASE,

  environment: process.env.NODE_ENV,

  // Replay captures 1% of all sessions and 100% of sessions with errors.
  replaysSessionSampleRate: 0.01,
  replaysOnErrorSampleRate: 1.0,

  integrations: [
    Sentry.replayIntegration({
      maskAllText: true,
      blockAllMedia: true,
    }),
  ],

  // Ignore expected navigation errors and browser extension noise.
  ignoreErrors: [
    'ResizeObserver loop limit exceeded',
    'ResizeObserver loop completed with undelivered notifications',
    /^Loading chunk .+ failed/,
    /^Hydration failed/,
  ],
});
