import type { NextConfig } from 'next';
import { withSentryConfig } from '@sentry/nextjs';

const nextConfig: NextConfig = {
  /* config options here */
};

export default withSentryConfig(nextConfig, {
  // Sentry org + project (set SENTRY_ORG, SENTRY_PROJECT in CI env).
  org:     process.env.SENTRY_ORG,
  project: process.env.SENTRY_PROJECT,

  // Auth token for source-map upload (SENTRY_AUTH_TOKEN in CI secrets).
  // When unset (local dev), source-map upload is skipped automatically.
  authToken: process.env.SENTRY_AUTH_TOKEN,

  // Keep Sentry CLI output quiet in local builds.
  silent: !process.env.CI,

  // Automatically wire up Sentry in server-side rendering.
  autoInstrumentServerFunctions: true,
});
