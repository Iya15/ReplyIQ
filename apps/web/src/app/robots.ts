import type { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow:    '/',
        disallow: [
          '/dashboard',
          '/chatbots',
          '/conversations',
          '/analytics',
          '/team',
          '/settings',
          '/billing',
          '/api/',
        ],
      },
    ],
    sitemap: 'https://app.replyiq.com/sitemap.xml',
  };
}
