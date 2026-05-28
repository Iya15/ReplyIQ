import type { MetadataRoute } from 'next';

const BASE = 'https://app.replyiq.com';

export default function sitemap(): MetadataRoute.Sitemap {
  const now = new Date();

  return [
    { url: BASE,                         lastModified: now, changeFrequency: 'weekly',  priority: 1.0 },
    { url: `${BASE}/pricing`,            lastModified: now, changeFrequency: 'monthly', priority: 0.9 },
    { url: `${BASE}/docs`,               lastModified: now, changeFrequency: 'weekly',  priority: 0.8 },
    { url: `${BASE}/docs/getting-started`, lastModified: now, changeFrequency: 'weekly', priority: 0.8 },
    { url: `${BASE}/docs/embed`,         lastModified: now, changeFrequency: 'monthly', priority: 0.7 },
    { url: `${BASE}/docs/api-reference`, lastModified: now, changeFrequency: 'monthly', priority: 0.7 },
    { url: `${BASE}/docs/troubleshooting`, lastModified: now, changeFrequency: 'monthly', priority: 0.6 },
    { url: `${BASE}/privacy`,            lastModified: now, changeFrequency: 'yearly',  priority: 0.3 },
    { url: `${BASE}/terms`,              lastModified: now, changeFrequency: 'yearly',  priority: 0.3 },
    // Auth pages excluded — low SEO value
  ];
}
